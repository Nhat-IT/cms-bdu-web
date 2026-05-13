<?php
/**
 * API: Get attendance roster for a group + date
 * GET /api/bcs/attendance/roster?groupId=X&date=YYYY-MM-DD
 */
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$groupId = isset($_GET['groupId']) ? (int)$_GET['groupId'] : 0;
$date    = isset($_GET['date'])    ? trim($_GET['date'])    : '';

if (!$groupId || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Thieu tham so groupId hoac date.']);
    exit;
}

try {
    $conn = getDBConnection();
    $conn->query("ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS study_session VARCHAR(20) DEFAULT NULL AFTER attendance_date");
    $conn->query("ALTER TABLE attendance_records MODIFY COLUMN student_id INT(11) DEFAULT NULL");

    $students = db_fetch_all(
        "SELECT
            ssr.id                           AS registration_id,
            COALESCE(u.id, 0)                AS student_id,
            COALESCE(u.username, ssr.mssv)   AS username,
            COALESCE(u.full_name, ssr.full_name) AS full_name,
            COALESCE(u.birth_date, ssr.birth_date) AS birth_date,
            COALESCE(
                (SELECT c.class_name FROM class_students cs2 JOIN classes c ON cs2.class_id = c.id WHERE cs2.student_id = u.id LIMIT 1),
                ssr.class_name,
                ''
            ) AS class_name
         FROM student_subject_registration ssr
         LEFT JOIN users u ON ssr.student_id = u.id
         WHERE ssr.class_subject_group_id = ?
           AND ssr.status = 'Đang học'
         ORDER BY COALESCE(u.username, ssr.mssv)",
        [$groupId]
    );

    $sessionRow = db_fetch_one(
        "SELECT id, study_session FROM attendance_sessions WHERE class_subject_group_id = ? AND attendance_date = ?",
        [$groupId, $date]
    );
    $sessionId = $sessionRow ? $sessionRow['id'] : null;
    $studySession = $sessionRow ? ($sessionRow['study_session'] ?? '') : '';

    $attMap = [];
    if ($sessionId) {
        $attendance = db_fetch_all(
            "SELECT ar.student_id, ar.registration_id, ar.status, ar.note
             FROM attendance_records ar
             WHERE ar.session_id = ?",
            [$sessionId]
        );
        foreach ($attendance as $a) {
            $key = (!empty($a['student_id']) && $a['student_id'] > 0)
                ? (int)$a['student_id']
                : (int)$a['registration_id'];
            $attMap[$key] = ['status' => $a['status'], 'note' => $a['note'] ?? ''];
        }
    }

    foreach ($students as &$sv) {
        $regId = (int)($sv['registration_id'] ?? 0);
        $stuId = (int)($sv['student_id'] ?? 0);
        $key = ($stuId > 0) ? $stuId : $regId;
        $sv['registration_id'] = $regId;
        $sv['status'] = isset($attMap[$key]) ? (int)$attMap[$key]['status'] : 1;
        $sv['note']   = isset($attMap[$key]) ? $attMap[$key]['note'] : '';
    }
    unset($sv);

    echo json_encode(['students' => $students, 'studySession' => $studySession], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[roster.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi truy vấn dữ liệu. Vui lòng thử lại.']);
}
