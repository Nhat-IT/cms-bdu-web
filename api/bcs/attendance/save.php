<?php
/**
 * API: Save attendance records
 * POST /api/bcs/attendance/save
 * Body: { groupId, attendanceDate, session, records: [{studentId, registrationId, status, note}] }
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

try {
    $conn = getDBConnection();
    $conn->query("ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS study_session VARCHAR(20) DEFAULT NULL AFTER attendance_date");
    $conn->query("ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS note VARCHAR(255) DEFAULT NULL AFTER status");
    $conn->query("ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS registration_id INT(11) DEFAULT NULL AFTER student_id");
    $conn->query("ALTER TABLE attendance_records MODIFY COLUMN student_id INT(11) DEFAULT NULL");

    $existingSession = db_fetch_one(
        "SELECT id FROM attendance_sessions
         WHERE class_subject_group_id = ? AND attendance_date = ?",
        [$groupId, $attendanceDate]
    );

    if ($existingSession) {
        $sessionId = $existingSession['id'];
        db_query("DELETE FROM attendance_records WHERE session_id = ?", [$sessionId]);
        if ($sessionLabel !== '') {
            db_query(
                "UPDATE attendance_sessions SET study_session = ? WHERE id = ?",
                [$sessionLabel, $sessionId]
            );
        }
    } else {
        db_query(
            "INSERT INTO attendance_sessions (class_subject_group_id, attendance_date, study_session, created_by) VALUES (?, ?, ?, ?)",
            [$groupId, $attendanceDate, $sessionLabel, $userId]
        );
        $sessionId = mysqli_insert_id(getDBConnection());
    }

    if (!$sessionId) {
        throw new Exception('Cannot create attendance session');
    }

    foreach ($records as $rec) {
        $studentId      = (int)($rec['studentId']      ?? 0);
        $registrationId = (int)($rec['registrationId'] ?? 0);
        $status         = (int)($rec['status']         ?? 1);
        $note           = trim($rec['note'] ?? '');

        if ($studentId <= 0 && $registrationId <= 0) continue;

        // Use NULL (not 0) so the FK constraint on student_id is not violated
        db_query(
            "INSERT INTO attendance_records (session_id, student_id, registration_id, status, note) VALUES (?, ?, ?, ?, ?)",
            [$sessionId, $studentId > 0 ? $studentId : null, $registrationId > 0 ? $registrationId : null, $status, $note]
        );
    }

    echo json_encode(['success' => true, 'sessionId' => $sessionId], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[save.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể lưu điểm danh. Vui lòng thử lại.']);
}
