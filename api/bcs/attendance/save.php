<?php
/**
 * API: Save attendance records
 * POST /api/bcs/attendance/save
 * Body: { groupId, attendanceDate, session, records: [{studentId, status}] }
 */
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON.']);
    exit;
}

$groupId       = isset($input['groupId'])       ? (int)$input['groupId']       : 0;
$attendanceDate = isset($input['attendanceDate']) ? trim($input['attendanceDate']) : '';
$sessionLabel  = isset($input['session'])        ? trim($input['session'])        : '';
$records       = isset($input['records'])        ? $input['records']              : [];

if (!$groupId || !$attendanceDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Thieu tham so groupId hoac attendanceDate.']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get or create session in attendance_sessions
$existingSession = db_fetch_one(
    "SELECT id FROM attendance_sessions
     WHERE class_subject_group_id = ? AND attendance_date = ?",
    [$groupId, $attendanceDate]
);

if ($existingSession) {
    $sessionId = $existingSession['id'];
    // Delete old records for this session
    db_query("DELETE FROM attendance_records WHERE session_id = ?", [$sessionId]);
} else {
    db_query(
        "INSERT INTO attendance_sessions (class_subject_group_id, attendance_date, created_by) VALUES (?, ?, ?)",
        [$groupId, $attendanceDate, $userId]
    );
    $sessionId = mysqli_insert_id(getDBConnection());
}

if (!$sessionId) {
    http_response_code(500);
    echo json_encode(['error' => 'Khong the tao session diem danh.']);
    exit;
}

// Insert records
$conn = getDBConnection();
$stmt = mysqli_prepare($conn,
    "INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'iii', $sessionId, $studentId, $status);

foreach ($records as $rec) {
    $studentId = (int)($rec['studentId'] ?? 0);
    $status    = (int)($rec['status']    ?? 1);
    if ($studentId) {
        mysqli_stmt_execute($stmt);
    }
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'sessionId' => $sessionId], JSON_UNESCAPED_UNICODE);
