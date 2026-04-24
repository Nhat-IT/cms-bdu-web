<?php
/**
 * API: Upload file and return share link.
 * Priority:
 * 1) Upload Google Drive (SDK or REST with service account)
 * 2) Fallback local storage (/public/uploads/documents)
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

function upload_resolve_credential_path(): string {
    $credPath = envOrDefault('GOOGLE_APPLICATION_CREDENTIALS', '');
    if ($credPath === '') return '';

    if (!preg_match('/^[A-Za-z]:\\\\|^\//', $credPath)) {
        $credPath = BASE_PATH . '/' . ltrim($credPath, '/\\');
    }
    return $credPath;
}

function upload_base64url_encode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function upload_curl_json(string $url, string $method = 'GET', ?array $headers = null, $body = null): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    if ($ch === false) return null;

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60
    ];
    if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($ch, $opts);

    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        if ($err !== '') error_log('upload-to-drive curl error: ' . $err);
        return null;
    }

    $json = json_decode($resp, true);
    return ['code' => $code, 'json' => is_array($json) ? $json : null, 'raw' => $resp];
}

function upload_get_drive_access_token(string $credPath): string {
    if (!is_file($credPath)) return '';
    $cred = json_decode((string)file_get_contents($credPath), true);
    if (!is_array($cred)) return '';

    $clientEmail = trim((string)($cred['client_email'] ?? ''));
    $privateKey = (string)($cred['private_key'] ?? '');
    $tokenUri = trim((string)($cred['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
    if ($clientEmail === '' || $privateKey === '') return '';

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claim = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'aud' => $tokenUri,
        'exp' => $now + 3600,
        'iat' => $now
    ];
    $jwtHead = upload_base64url_encode(json_encode($header));
    $jwtClaim = upload_base64url_encode(json_encode($claim));
    $unsigned = $jwtHead . '.' . $jwtClaim;

    $signature = '';
    $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) return '';
    $jwt = $unsigned . '.' . upload_base64url_encode($signature);

    $res = upload_curl_json(
        $tokenUri,
        'POST',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ])
    );
    if (!$res || ($res['code'] ?? 0) < 200 || ($res['code'] ?? 0) >= 300) return '';
    return (string)($res['json']['access_token'] ?? '');
}

function upload_try_google_drive_sdk(string $tmpPath, string $originalName, string $mimeType): ?array {
    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null;
    }
    require_once $autoload;

    if (!class_exists('Google\\Client') || !class_exists('Google\\Service\\Drive')) {
        return null;
    }

    $credPath = upload_resolve_credential_path();
    if ($credPath === '' || !file_exists($credPath)) {
        return null;
    }

    try {
        $client = new Google\Client();
        $client->setAuthConfig($credPath);
        $client->setScopes([Google\Service\Drive::DRIVE_FILE]);
        $service = new Google\Service\Drive($client);

        $meta = new Google\Service\Drive\DriveFile(['name' => $originalName]);
        $folderId = trim((string)envOrDefault('GOOGLE_DRIVE_FOLDER_ID', ''));
        if ($folderId !== '') {
            $meta->setParents([$folderId]);
        }

        $created = $service->files->create($meta, [
            'data' => file_get_contents($tmpPath),
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink'
        ]);

        if (!empty($created->id)) {
            $permission = new Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            $service->permissions->create($created->id, $permission);
        }

        $fileId = (string)($created->id ?? '');
        if ($fileId === '') return null;
        $link = (string)($created->webViewLink ?? ('https://drive.google.com/file/d/' . $fileId . '/view'));
        return ['success' => true, 'storage' => 'drive', 'link' => $link, 'fileId' => $fileId];
    } catch (Throwable $e) {
        error_log('upload-to-drive SDK error: ' . $e->getMessage());
        return null;
    }
}

function upload_try_google_drive_rest(string $tmpPath, string $originalName, string $mimeType): ?array {
    $credPath = upload_resolve_credential_path();
    if ($credPath === '' || !file_exists($credPath)) return null;

    $token = upload_get_drive_access_token($credPath);
    if ($token === '') return null;

    $meta = ['name' => $originalName];
    $folderId = trim((string)envOrDefault('GOOGLE_DRIVE_FOLDER_ID', ''));
    if ($folderId !== '') {
        $meta['parents'] = [$folderId];
    }
    $boundary = '-------cmsbdu' . bin2hex(random_bytes(8));
    $multipartBody =
        "--{$boundary}\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n" .
        "--{$boundary}\r\n" .
        "Content-Type: " . ($mimeType ?: 'application/octet-stream') . "\r\n\r\n" .
        file_get_contents($tmpPath) . "\r\n" .
        "--{$boundary}--";

    $uploadRes = upload_curl_json(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink',
        'POST',
        [
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary
        ],
        $multipartBody
    );
    if (!$uploadRes || ($uploadRes['code'] ?? 0) < 200 || ($uploadRes['code'] ?? 0) >= 300) {
        return null;
    }

    $fileId = (string)($uploadRes['json']['id'] ?? '');
    if ($fileId === '') return null;

    // Public read permission
    upload_curl_json(
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '/permissions',
        'POST',
        [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        json_encode(['role' => 'reader', 'type' => 'anyone'])
    );

    $link = (string)($uploadRes['json']['webViewLink'] ?? ('https://drive.google.com/file/d/' . $fileId . '/view'));
    return ['success' => true, 'storage' => 'drive', 'link' => $link, 'fileId' => $fileId];
}

function upload_try_google_drive(string $tmpPath, string $originalName, string $mimeType): ?array {
    $sdk = upload_try_google_drive_sdk($tmpPath, $originalName, $mimeType);
    if ($sdk) return $sdk;
    return upload_try_google_drive_rest($tmpPath, $originalName, $mimeType);
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

// Fallback local
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

