<?php
/**
 * API: Student evidence delete
 * POST /api/student/evidence-delete
 * Form fields: record_id (int)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

function evdel_json(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) evdel_json(401, ['error' => 'Unauthorized']);
if (!hasRole('student')) evdel_json(403, ['error' => 'Forbidden']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') evdel_json(405, ['error' => 'Method not allowed']);

$userId   = (int)$_SESSION['user_id'];
$recordId = (int)($_POST['record_id'] ?? 0);

if ($recordId <= 0) evdel_json(400, ['error' => 'Thiếu record_id.']);

$record = db_fetch_one(
    "SELECT id, student_id, status, evidence_status, evidence_link FROM attendance_records WHERE id = ? AND student_id = ?",
    [$recordId, $userId]
);
if (!$record) evdel_json(403, ['error' => 'Không tìm thấy bản ghi.']);
if (($record['evidence_status'] ?? '') === 'Approved') {
    evdel_json(400, ['error' => 'Minh chứng đã được duyệt, không thể xóa.']);
}
if (empty($record['evidence_link'])) {
    evdel_json(400, ['error' => 'Không có minh chứng để xóa.']);
}

// Delete local file if stored on this server
$link = (string)$record['evidence_link'];
if (strpos($link, BASE_URL . '/public/uploads/evidence/') === 0) {
    $filename = basename(parse_url($link, PHP_URL_PATH));
    $localPath = BASE_PATH . '/public/uploads/evidence/' . $filename;
    if (is_file($localPath)) @unlink($localPath);
}

try {
    db_query(
        "UPDATE attendance_records
         SET evidence_link = NULL, evidence_status = NULL, evidence_uploaded_at = NULL
         WHERE id = ? AND student_id = ?",
        [$recordId, $userId]
    );
} catch (Throwable $e) {
    error_log('[evidence-delete] ' . $e->getMessage());
    evdel_json(500, ['error' => 'Xóa thất bại. Vui lòng thử lại.']);
}

evdel_json(200, ['success' => true]);
