<?php
/**
 * API: Student evidence upload
 * POST /api/student/evidence-upload
 * Form fields: record_id (int), file (image or PDF)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

function ev_json(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) ev_json(401, ['error' => 'Unauthorized']);
if (!hasRole('student')) ev_json(403, ['error' => 'Forbidden']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ev_json(405, ['error' => 'Method not allowed']);

$userId   = (int)$_SESSION['user_id'];
$recordId = (int)($_POST['record_id'] ?? 0);

if ($recordId <= 0) ev_json(400, ['error' => 'Thiếu record_id.']);

if (!isset($_FILES['file']) || (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    ev_json(400, ['error' => 'Thiếu file hoặc tải lên thất bại.']);
}

// Verify the student owns this record and it is an absence row
$record = db_fetch_one(
    "SELECT id, student_id, status, evidence_status FROM attendance_records WHERE id = ? AND student_id = ?",
    [$recordId, $userId]
);
if (!$record) ev_json(403, ['error' => 'Bạn không có quyền nộp minh chứng cho buổi này.']);
if (!in_array((int)$record['status'], [2, 3])) {
    ev_json(400, ['error' => 'Chỉ nộp minh chứng cho buổi vắng.']);
}
if (($record['evidence_status'] ?? '') === 'Approved') {
    ev_json(400, ['error' => 'Minh chứng đã được duyệt, không thể thay thế.']);
}

$file     = $_FILES['file'];
$tmpPath  = (string)$file['tmp_name'];
$origName = basename((string)$file['name']);
$mimeType = (string)$file['type'];
$size     = (int)$file['size'];

if (!is_uploaded_file($tmpPath) || $size <= 0) ev_json(400, ['error' => 'File không hợp lệ.']);
if ($size > 10 * 1024 * 1024) ev_json(400, ['error' => 'File không được vượt quá 10MB.']);

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
if (!in_array($ext, $allowedExts) || !in_array($mimeType, $allowedMimes)) {
    ev_json(400, ['error' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP) hoặc PDF.']);
}

$uploadDir = BASE_PATH . '/public/uploads/evidence/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
if (!is_writable($uploadDir)) ev_json(500, ['error' => 'Thư mục lưu trữ không có quyền ghi.']);

$safeName = preg_replace('/[^\w\-]+/u', '_', pathinfo($origName, PATHINFO_FILENAME));
$unique   = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '_' . $safeName . '.' . $ext;
$dest     = $uploadDir . $unique;

if (!@move_uploaded_file($tmpPath, $dest)) {
    ev_json(500, ['error' => 'Không thể lưu file. Vui lòng thử lại.']);
}

$link = BASE_URL . '/public/uploads/evidence/' . $unique;

try {
    db_query(
        "UPDATE attendance_records
         SET evidence_link = ?, evidence_status = 'Pending', evidence_uploaded_at = NOW()
         WHERE id = ? AND student_id = ?",
        [$link, $recordId, $userId]
    );
} catch (Throwable $e) {
    error_log('[evidence-upload] ' . $e->getMessage());
    ev_json(500, ['error' => 'Cập nhật thất bại. Vui lòng thử lại.']);
}

ev_json(200, ['success' => true, 'link' => $link]);
