<?php
/**
 * API: BCS Dashboard Detail
 * GET /api/bcs/dashboard-detail?keyword=
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!hasRole('bcs')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$keyword = trim($_GET['keyword'] ?? '');

try {
    $classInfo = getUserClassInfo($userId);
    $className = $classInfo['class_name'];

    if (!$className) {
        echo json_encode([
            'stats' => ['totalStudents' => 0, 'warningStudents' => 0, 'warningSubjects' => 0],
            'rows'  => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    $totalStudents = (int)(db_fetch_one(
        "SELECT COUNT(DISTINCT mssv) AS total FROM student_subject_registration WHERE class_name = ?",
        [$className]
    )['total'] ?? 0);

    // Join SSR trực tiếp tới từng bản ghi điểm danh (tránh cartesian product
    // và bao gồm cả SV không có tài khoản qua registration_id)
    $ssrJoin = "
        JOIN student_subject_registration ssr
            ON ssr.class_subject_group_id = csg.id
            AND (
                (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
                OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
            )
    ";

    $warningPairs = db_fetch_all("
        SELECT COALESCE(ar.student_id, ar.registration_id) AS student_key,
               csg.class_subject_id
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
        $ssrJoin
        WHERE ar.status = 3
          AND ssr.class_name = ?
        GROUP BY student_key, csg.class_subject_id
        HAVING COUNT(*) >= 3
    ", [$className]);

    // Lấy số môn cảnh báo từ warningPairs (không cần query thêm)
    $warningSubjectCount = count(array_unique(array_column($warningPairs, 'class_subject_id')));
    $warningStudentCount = count(array_unique(array_column($warningPairs, 'student_key')));

    // ── Detail rows ──────────────────────────────────────────────────────────

    $kf    = $keyword ? " AND (COALESCE(u.full_name, ssr.full_name) LIKE ? OR COALESCE(u.username, ssr.mssv) LIKE ?)" : '';
    $kArgs = $keyword ? ["%$keyword%", "%$keyword%"] : [];

    // Đếm tổng vắng theo từng môn cho từng SV
    $subquery = "
        SELECT COUNT(*)
        FROM attendance_records ar2
        JOIN attendance_sessions a_s2 ON ar2.session_id = a_s2.id
        JOIN class_subject_groups csg2 ON a_s2.class_subject_group_id = csg2.id
        WHERE (
                  (ar.student_id IS NOT NULL AND ar2.student_id = ar.student_id)
                  OR (ar.student_id IS NULL AND ar2.registration_id = ar.registration_id)
              )
          AND csg2.class_subject_id = csg.class_subject_id
          AND ar2.status = 3
    ";

    $rows = db_fetch_all("
        SELECT
            COALESCE(u.full_name, ssr.full_name)  AS full_name,
            COALESCE(u.username,  ssr.mssv)        AS username,
            s.subject_name,
            a_s.attendance_date,
            COALESCE(a_s.study_session, '')        AS study_session,
            ar.status,
            ar.evidence_link                       AS drive_link,
            ($subquery)                            AS total_absent_in_subject
        FROM attendance_records ar
        JOIN attendance_sessions a_s  ON ar.session_id = a_s.id
        JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
        JOIN class_subjects cs        ON csg.class_subject_id = cs.id
        JOIN subjects s               ON cs.subject_id = s.id
        LEFT JOIN users u             ON ar.student_id = u.id
        $ssrJoin
        WHERE ar.status = 3
          AND ssr.class_name = ? $kf
        ORDER BY COALESCE(u.full_name, ssr.full_name), a_s.attendance_date DESC
        LIMIT 200
    ", array_merge([$className], $kArgs));

    echo json_encode([
        'stats' => [
            'totalStudents'   => $totalStudents,
            'warningStudents' => $warningStudentCount,
            'warningSubjects' => $warningSubjectCount,
        ],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[api/bcs/dashboard-detail] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể tải dữ liệu. Vui lòng thử lại.']);
}
