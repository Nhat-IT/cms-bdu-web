<?php
/**
 * API: Update a student's basic info
 * POST /api/bcs/attendance/update-student
 * Body: { registrationId, studentId, mssv, fullName, birthDate, className }
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

$registrationId = isset($input['registrationId']) ? (int)$input['registrationId'] : 0;
$studentId      = isset($input['studentId'])      ? (int)$input['studentId']      : 0;
$mssv           = isset($input['mssv'])           ? trim($input['mssv'])           : '';
$fullName       = isset($input['fullName'])       ? trim($input['fullName'])       : '';
$birthDate      = isset($input['birthDate'])      ? trim($input['birthDate'])      : '';
$className      = isset($input['className'])      ? trim($input['className'])      : '';

if (!$registrationId || !$mssv || !$fullName || !$className) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu thông tin bắt buộc.']);
    exit;
}

$birthDateVal = ($birthDate !== '') ? $birthDate : null;

try {
    if ($studentId > 0) {
        // Sinh viên có tài khoản: cập nhật bảng users (không đổi username/MSSV vì là thông tin đăng nhập)
        db_query(
            "UPDATE users SET full_name = ?, birth_date = ? WHERE id = ?",
            [$fullName, $birthDateVal, $studentId]
        );
        // Cập nhật class_name trong registration (nếu được lưu riêng)
        db_query(
            "UPDATE student_subject_registration SET class_name = ? WHERE id = ?",
            [$className, $registrationId]
        );
    } else {
        // Sinh viên không có tài khoản: cập nhật trực tiếp bảng registration
        db_query(
            "UPDATE student_subject_registration SET mssv = ?, full_name = ?, birth_date = ?, class_name = ? WHERE id = ?",
            [$mssv, $fullName, $birthDateVal, $className, $registrationId]
        );
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[update-student.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể cập nhật thông tin sinh viên.']);
}
