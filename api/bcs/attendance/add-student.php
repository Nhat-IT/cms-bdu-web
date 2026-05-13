<?php
/**
 * API: Add a student to a class_subject_group roster
 * POST /api/bcs/attendance/add-student
 * Body: { groupId, mssv, fullName, birthDate, className }
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

$groupId   = isset($input['groupId'])   ? (int)trim($input['groupId'])      : 0;
$mssv      = isset($input['mssv'])      ? trim($input['mssv'])              : '';
$fullName  = isset($input['fullName'])  ? trim($input['fullName'])          : '';
$birthDate = isset($input['birthDate']) ? trim($input['birthDate'])         : '';
$className = isset($input['className']) ? trim($input['className'])         : '';

if (!$groupId || !$mssv || !$fullName) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng điền đầy đủ MSSV và Họ tên.']);
    exit;
}

try {
    // Check if mssv is already in this group (for students without accounts)
    $existingMssv = db_fetch_one(
        "SELECT id FROM student_subject_registration WHERE class_subject_group_id = ? AND mssv = ?",
        [$groupId, $mssv]
    );
    if ($existingMssv) {
        http_response_code(409);
        echo json_encode(['error' => "MSSV {$mssv} đã có trong danh sách nhóm này."]);
        exit;
    }

    // Try to link an existing user account by username = mssv
    $user = db_fetch_one("SELECT id FROM users WHERE username = ?", [$mssv]);
    $studentId = $user ? (int)$user['id'] : null;

    // If linked, check student_id uniqueness in this group
    if ($studentId) {
        $existingById = db_fetch_one(
            "SELECT id FROM student_subject_registration WHERE class_subject_group_id = ? AND student_id = ?",
            [$groupId, $studentId]
        );
        if ($existingById) {
            http_response_code(409);
            echo json_encode(['error' => "Sinh viên này đã có trong danh sách nhóm."]);
            exit;
        }
    }

    // Students linked to an account don't need mssv stored separately
    $mssvVal      = $studentId ? null : $mssv;
    $birthDateVal = ($birthDate !== '') ? $birthDate : null;

    db_query(
        "INSERT INTO student_subject_registration
            (class_subject_group_id, student_id, mssv, full_name, birth_date, class_name, status)
         VALUES (?, ?, ?, ?, ?, ?, 'Đang học')",
        [$groupId, $studentId, $mssvVal, $fullName, $birthDateVal, $className]
    );

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[add-student.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể thêm sinh viên. Vui lòng thử lại.']);
}
