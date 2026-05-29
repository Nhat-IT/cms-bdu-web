<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$payload = json_decode(file_get_contents('php://input'), true) ?? [];

$oldPassword = trim((string)($payload['oldPassword'] ?? ''));
$newPassword = trim((string)($payload['newPassword'] ?? ''));

if ($oldPassword === '' || $newPassword === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Vui lòng nhập đầy đủ mật khẩu cũ và mới.']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(['error' => 'Mật khẩu mới phải có ít nhất 6 ký tự.']);
    exit;
}

$row = db_fetch_one('SELECT password FROM users WHERE id = ?', [$userId]);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy tài khoản.']);
    exit;
}

$stored = (string)($row['password'] ?? '');
$info = password_get_info($stored);
$isHashed = isset($info['algo']) && $info['algo'] !== 0;
$valid = $isHashed ? password_verify($oldPassword, $stored) : hash_equals($stored, $oldPassword);

if (!$valid) {
    http_response_code(422);
    echo json_encode(['error' => 'Mật khẩu hiện tại không đúng.']);
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
db_query('UPDATE users SET password = ? WHERE id = ?', [$newHash, $userId]);

logSystem('Đổi mật khẩu', 'users', $userId);

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
