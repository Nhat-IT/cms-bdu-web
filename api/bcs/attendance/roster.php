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

$userId = $_SESSION['user_id'];

// Lấy thông tin lớp của BCS
$classInfo = getUserClassInfo($userId);
$classId = $classInfo['class_id'];
$className = $classInfo['class_name'];
$sourceType = $classInfo['source'];

// Lấy danh sách sinh viên cho nhóm này
if ($sourceType === 'class_students' && $classId) {
    $students = db_fetch_all(
        "SELECT
            u.id         AS student_id,
            u.username,
            u.full_name,
            u.birth_date,
            c.class_name
         FROM student_subject_registration ssr
         JOIN users u ON ssr.student_id = u.id
         JOIN class_students cs2 ON cs2.student_id = u.id
         JOIN classes c ON cs2.class_id = c.id
         WHERE ssr.class_subject_group_id = ?
           AND ssr.status = 'Đang học'",
        [$groupId]
    );
} else {
    // Từ class_students + student_subject_registration
    $students = db_fetch_all(
        "SELECT
            u.id         AS student_id,
            u.username,
            u.full_name,
            u.birth_date,
            c.class_name
         FROM student_subject_registration ssr
         JOIN users u ON ssr.student_id = u.id
         JOIN class_students cs2 ON cs2.student_id = u.id
         JOIN classes c ON cs2.class_id = c.id
         WHERE ssr.class_subject_group_id = ?
           AND ssr.status = 'Đang học'
         ORDER BY u.full_name",
        [$groupId]
    );
}

// Get attendance session
$sessionRow = db_fetch_one(
    "SELECT id FROM attendance_sessions WHERE class_subject_group_id = ? AND attendance_date = ?",
    [$groupId, $date]
);
$sessionId = $sessionRow ? $sessionRow['id'] : null;

// Get existing attendance for this group + date
$attMap = [];
if ($sessionId) {
    $attendance = db_fetch_all(
        "SELECT student_id, status FROM attendance_records WHERE session_id = ?",
        [$sessionId]
    );
    foreach ($attendance as $a) {
        $attMap[$a['student_id']] = $a['status'];
    }
}

// Merge attendance data into students
foreach ($students as &$sv) {
    $sid = $sv['student_id'];
    if (isset($attMap[$sid])) {
        $sv['status'] = $attMap[$sid];
    } else {
        $sv['status'] = 1;
    }
}
unset($sv);

echo json_encode(['students' => $students], JSON_UNESCAPED_UNICODE);
