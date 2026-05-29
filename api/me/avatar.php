<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['avatar']['error'] ?? -1;
    http_response_code(400);
    echo json_encode(['error' => 'Upload thất bại (code ' . $errCode . ').']);
    exit;
}

$file = $_FILES['avatar'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Chỉ chấp nhận ảnh JPG, PNG, GIF, WebP.']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['error' => 'Dung lượng ảnh tối đa 2MB.']);
    exit;
}

$uploadDir = __DIR__ . '/../../public/uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext = $extMap[$mimeType] ?? 'jpg';
$filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể lưu file.']);
    exit;
}

// Xóa avatar cũ nếu là file upload
$oldRow = db_fetch_one('SELECT avatar FROM users WHERE id = ?', [$userId]);
$oldAvatar = $oldRow['avatar'] ?? null;
if ($oldAvatar && strpos($oldAvatar, '/uploads/avatars/') !== false) {
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $oldFsPath = $docRoot . $oldAvatar;
    if (file_exists($oldFsPath)) {
        @unlink($oldFsPath);
    }
}

// Tạo web path từ document root
$destNorm = str_replace('\\', '/', $destPath);
$docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$webPath  = '/' . ltrim(str_replace($docRoot, '', $destNorm), '/');

db_query('UPDATE users SET avatar = ? WHERE id = ?', [$webPath, $userId]);
$_SESSION['avatar'] = $webPath;

echo json_encode(['success' => true, 'avatar' => $webPath], JSON_UNESCAPED_UNICODE);
