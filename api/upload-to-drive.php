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
    // SDK approach removed: google/apiclient is not installed.
    // upload_try_google_drive() falls back to upload_try_google_drive_rest() below.
    return null;
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

// ------------------------------------------------------------------
// Store file on local filesystem → /public/uploads/documents/
// Returns array on success, null on failure
// ------------------------------------------------------------------
function upload_try_local(string $tmpPath, string $originalName): ?array {
    $uploadDir = BASE_PATH . '/public/uploads/documents/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_writable($uploadDir)) return null;

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = upload_sanitize_filename(pathinfo($originalName, PATHINFO_FILENAME));
    $unique = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '_' . $base . ($ext ? '.' . $ext : '');
    $dest = $uploadDir . $unique;

    if (!@move_uploaded_file($tmpPath, $dest)) return null;

    return [
        'success' => true,
        'storage' => 'local',
        'link'    => BASE_URL . '/public/uploads/documents/' . $unique,
        'fileId'  => '',
    ];
}

// ------------------------------------------------------------------
// Store file in the DB (documents table) as last-resort storage
// Returns docId on success, 0 on failure
// ------------------------------------------------------------------
function upload_store_in_db(string $tmpPath, string $originalName, string $mimeType, int $size, ?int $userId): int {
    try {
        $conn = getDBConnection();

        // Allow class_subject_id to be NULL for the temporary upload record
        $conn->query("ALTER TABLE documents MODIFY COLUMN class_subject_id INT(11) DEFAULT NULL");

        $stmt = $conn->prepare(
            "INSERT INTO documents (title, note, category, drive_link, drive_file_id,
                                   icon_type, file_data, file_size, file_mime, original_filename,
                                   class_subject_id, uploader_id)
             VALUES (?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, ?, NULL, ?)"
        );
        if (!$stmt) return 0;

        $iconType = 'file';
        $lct = strtolower($originalName);
        if (preg_match('/\.pdf$/', $lct)) $iconType = 'pdf';
        elseif (preg_match('/\.(doc|docx)$/', $lct)) $iconType = 'doc';
        elseif (preg_match('/\.(xls|xlsx|csv)$/', $lct)) $iconType = 'xls';
        elseif (preg_match('/\.(zip|rar|7z)$/', $lct)) $iconType = 'zip';

        $fileData = file_get_contents($tmpPath);
        if ($fileData === false || $fileData === '') return 0;

        $fileMime = $mimeType ?: 'application/octet-stream';
        $stmt->bind_param('ssssisss', $originalName, $iconType, $fileData, $size, $fileMime, $originalName, $userId);
        $stmt->send_long_data(2, $fileData);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) return 0;
        return (int)mysqli_insert_id($conn);
    } catch (Throwable $e) {
        error_log('upload-store-in-db error: ' . $e->getMessage());
        return 0;
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

// 1. Google Drive (nếu có credentials)
$driveResult = upload_try_google_drive($tmpPath, $originalName, $mimeType);
if ($driveResult) {
    upload_json(200, $driveResult);
}

// 2. Local filesystem
$localResult = upload_try_local($tmpPath, $originalName);
if ($localResult) {
    upload_json(200, $localResult);
}

// 3. Database BLOB (last resort)
$docId = upload_store_in_db($tmpPath, $originalName, $mimeType, $size, (int)($_SESSION['user_id'] ?? 0));
if ($docId > 0) {
    upload_json(200, [
        'success' => true,
        'storage' => 'database',
        'doc_id'  => $docId,
        'link'    => '',
        'fileId'  => '',
    ]);
}

upload_json(500, ['success' => false, 'error' => 'Không thể lưu tệp. Vui lòng thử lại.']);

