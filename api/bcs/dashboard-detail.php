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
    $classInfo  = getUserClassInfo($userId);
    $classId    = $classInfo['class_id'];
    $className  = $classInfo['class_name'];
    $sourceType = $classInfo['source'];

    // ── Stats ────────────────────────────────────────────────────────────────

    // Count via SSR to include students without accounts (same as home.php)
    $totalStudents = (int) (db_fetch_one(
        "SELECT COUNT(DISTINCT mssv) AS total FROM student_subject_registration WHERE class_name = ?",
        [$className]
    )['total'] ?? 0);

    if ($sourceType === 'class_students' && $classId) {

        $warningPairs = db_fetch_all("
            SELECT ar.student_id, csg.class_subject_id
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs ON cs.student_id = ssr.student_id AND cs.class_id = ?
            WHERE ar.status = 3
            GROUP BY ar.student_id, csg.class_subject_id
            HAVING COUNT(*) >= 3
        ", [$classId]);

        $warningSubjectRows = db_fetch_all("
            SELECT DISTINCT csg.class_subject_id
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs ON cs.student_id = ssr.student_id AND cs.class_id = ?
            WHERE ar.status = 3
            GROUP BY ar.student_id, csg.class_subject_id
            HAVING COUNT(*) >= 3
        ", [$classId]);
    } else {
        $totalStudents = (int) (db_fetch_one(
            "SELECT COUNT(*) AS total FROM class_students
             WHERE class_id = (SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1)",
            [$userId]
        )['total'] ?? 0);

        $warningPairs = db_fetch_all("
            SELECT ar.student_id, csg.class_subject_id
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs ON cs.student_id = ssr.student_id
                AND cs.class_id = (SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1)
            WHERE ar.status = 3
            GROUP BY ar.student_id, csg.class_subject_id
            HAVING COUNT(*) >= 3
        ", [$userId]);

        $warningSubjectRows = db_fetch_all("
            SELECT DISTINCT csg.class_subject_id
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs ON cs.student_id = ssr.student_id
                AND cs.class_id = (SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1)
            WHERE ar.status = 3
            GROUP BY ar.student_id, csg.class_subject_id
            HAVING COUNT(*) >= 3
        ", [$userId]);
    }

    // ── Detail rows ──────────────────────────────────────────────────────────

    $kf     = $keyword ? " AND (u.full_name LIKE ? OR u.username LIKE ?)" : '';
    $kArgs  = $keyword ? ["%$keyword%", "%$keyword%"] : [];

    $subquery = "
        SELECT COUNT(*)
        FROM attendance_records ar2
        JOIN attendance_sessions a_s2 ON ar2.session_id = a_s2.id
        JOIN class_subject_groups csg2 ON a_s2.class_subject_group_id = csg2.id
        WHERE ar2.student_id = ar.student_id
          AND csg2.class_subject_id = csg.class_subject_id
          AND ar2.status = 3
    ";

    if ($sourceType === 'class_students' && $classId) {
        $rows = db_fetch_all("
            SELECT u.full_name, u.username,
                   s.subject_name, a_s.attendance_date,
                   COALESCE(a_s.study_session, '') AS study_session,
                   ar.status, ar.evidence_link AS drive_link,
                   ($subquery) AS total_absent_in_subject
            FROM attendance_records ar
            JOIN attendance_sessions a_s   ON ar.session_id = a_s.id
            JOIN class_subject_groups csg  ON a_s.class_subject_group_id = csg.id
            JOIN class_subjects cs         ON csg.class_subject_id = cs.id
            JOIN subjects s                ON cs.subject_id = s.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs2        ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
            JOIN users u                   ON ar.student_id = u.id
            WHERE ar.status = 3 $kf
            ORDER BY u.full_name, a_s.attendance_date DESC
            LIMIT 200
        ", array_merge([$classId], $kArgs));
    } else {
        $rows = db_fetch_all("
            SELECT u.full_name, u.username,
                   s.subject_name, a_s.attendance_date,
                   COALESCE(a_s.study_session, '') AS study_session,
                   ar.status, ar.evidence_link AS drive_link,
                   ($subquery) AS total_absent_in_subject
            FROM attendance_records ar
            JOIN attendance_sessions a_s   ON ar.session_id = a_s.id
            JOIN class_subject_groups csg  ON a_s.class_subject_group_id = csg.id
            JOIN class_subjects cs         ON csg.class_subject_id = cs.id
            JOIN subjects s                ON cs.subject_id = s.id
            JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
            JOIN class_students cs2        ON cs2.student_id = ssr.student_id
                AND cs2.class_id = (SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1)
            JOIN users u                   ON ar.student_id = u.id
            WHERE ar.status = 3 $kf
            ORDER BY u.full_name, a_s.attendance_date DESC
            LIMIT 200
        ", array_merge([$userId], $kArgs));
    }

    echo json_encode([
        'stats' => [
            'totalStudents'   => $totalStudents,
            'warningStudents' => count($warningPairs),
            'warningSubjects' => count($warningSubjectRows),
        ],
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[api/bcs/dashboard-detail] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể tải dữ liệu. Vui lòng thử lại.']);
}
