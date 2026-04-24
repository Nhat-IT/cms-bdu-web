<?php
/**
 * API: Upload file and return share link.
 * Priority:
 * 1) Upload Google Drive (if Google SDK + credentials available)
 * 2) Fallback lưu file nội bộ tại /public/uploads/documents
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

function upload_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function upload_sanitize_filename(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[^\w\.\-\(\) ]+/u', '_', $name);
    $name = preg_replace('/\s+/', '_', $name ?? '');
    return $name ?: ('file_' . time());
}

function upload_try_google_drive(string $tmpPath, string $originalName, string $mimeType): ?array {
    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('Google\\Client') || !class_exists('Google\\Service\\Drive')) {
        return null;
    }

    $credPath = envOrDefault('GOOGLE_APPLICATION_CREDENTIALS', '');
    if ($credPath === '') {
        return null;
    }

    if (!preg_match('/^[A-Za-z]:\\\\|^\//', $credPath)) {
        $credPath = BASE_PATH . '/' . ltrim($credPath, '/\\');
    }
    if (!file_exists($credPath)) {
        return null;
    }

    try {
        $client = new Google\Client();
        $client->setAuthConfig($credPath);
        $client->setScopes([Google\Service\Drive::DRIVE_FILE]);
        $service = new Google\Service\Drive($client);

        $metadata = new Google\Service\Drive\DriveFile([
            'name' => $originalName
        ]);

        $folderId = trim((string)envOrDefault('GOOGLE_DRIVE_FOLDER_ID', ''));
        if ($folderId !== '') {
            $metadata->setParents([$folderId]);
        }

        $created = $service->files->create($metadata, [
            'data' => file_get_contents($tmpPath),
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink,webContentLink'
        ]);

        if (!empty($created->id)) {
            $permission = new Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            $service->permissions->create($created->id, $permission);
        }

        $link = $created->webViewLink ?? '';
        if ($link === '' && !empty($created->id)) {
            $link = 'https://drive.google.com/file/d/' . $created->id . '/view';
        }

        if ($link === '') {
            return null;
        }

        return [
            'success' => true,
            'storage' => 'drive',
            'link' => $link,
            'fileId' => $created->id ?? ''
        ];
    } catch (Throwable $e) {
        error_log('upload-to-drive Google error: ' . $e->getMessage());
        return null;
    }
}

if (!isLoggedIn()) {
    upload_json(401, ['success' => false, 'error' => 'Bạn chưa đăng nhập.']);
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    upload_json(405, ['success' => false, 'error' => 'Method không hợp lệ.']);
}

if (!isset($_FILES['file'])) {
    upload_json(400, ['success' => false, 'error' => 'Thiếu tệp tải lên.']);
}

$file = $_FILES['file'];
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    upload_json(400, ['success' => false, 'error' => 'Tải tệp lên thất bại.']);
}

$tmpPath = (string)($file['tmp_name'] ?? '');
$originalName = (string)($file['name'] ?? 'document');
$mimeType = (string)($file['type'] ?? 'application/octet-stream');
$size = (int)($file['size'] ?? 0);

if (!is_uploaded_file($tmpPath) || $size <= 0) {
    upload_json(400, ['success' => false, 'error' => 'Tệp không hợp lệ.']);
}

if ($size > 25 * 1024 * 1024) {
    upload_json(400, ['success' => false, 'error' => 'Kích thước tệp vượt quá 25MB.']);
}

$driveResult = upload_try_google_drive($tmpPath, $originalName, $mimeType);
if ($driveResult) {
    upload_json(200, $driveResult);
}

// Fallback: lưu nội bộ nếu chưa cấu hình Drive SDK/credentials.
$uploadDir = BASE_PATH . '/public/uploads/documents';
if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    upload_json(500, ['success' => false, 'error' => 'Không thể tạo thư mục lưu tệp.']);
}

$safeName = upload_sanitize_filename($originalName);
$uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
$targetPath = $uploadDir . '/' . $uniqueName;

if (!move_uploaded_file($tmpPath, $targetPath)) {
    upload_json(500, ['success' => false, 'error' => 'Không thể lưu tệp lên máy chủ.']);
}

$publicLink = BASE_URL . '/public/uploads/documents/' . rawurlencode($uniqueName);
upload_json(200, [
    'success' => true,
    'storage' => 'local',
    'link' => $publicLink,
    'fileId' => ''
]);

