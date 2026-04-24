<?php
/**
 * API: BCS Announcements CRUD
 * Endpoint: /api/bcs/announcements.php
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
requireRole('bcs');

function ann_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ann_get_bcs_class_id(int $userId): int {
    $row = db_fetch_one(
        "SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1",
        [$userId]
    );
    return (int)($row['class_id'] ?? 0);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    getDBConnection();

    $classId = ann_get_bcs_class_id($userId);
    if ($classId <= 0) {
        ann_json(400, ['ok' => false, 'error' => 'Tài khoản BCS chưa được gán lớp.']);
    }

    if ($method === 'GET') {
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $params = [$userId];
        $sql = "SELECT id, title, message, created_at
                FROM notification_logs
                WHERE user_id = ? AND COALESCE(title, '') <> ''";
        if ($keyword !== '') {
            $sql .= " AND (LOWER(title) LIKE LOWER(?) OR LOWER(COALESCE(message, '')) LIKE LOWER(?))";
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 100";
        $rows = db_fetch_all($sql, $params);

        $mapped = array_map(static function ($r) {
            return [
                'id' => (int)($r['id'] ?? 0),
                'title' => (string)($r['title'] ?? ''),
                'content' => (string)($r['message'] ?? ''),
                'created_at' => $r['created_at'] ?? null
            ];
        }, $rows);
        ann_json(200, ['ok' => true, 'items' => $mapped]);
    }

    if ($method !== 'POST') {
        ann_json(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    $action = strtolower(trim((string)($input['action'] ?? 'create')));
    $id = (int)($input['id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));

    if ($action === 'create') {
        if ($title === '') {
            ann_json(422, ['ok' => false, 'error' => 'Vui lòng nhập tiêu đề bản tin.']);
        }

        db_query(
            "INSERT INTO notification_logs (user_id, title, message, is_read)
             VALUES (?, ?, ?, 1)",
            [$userId, $title, $content !== '' ? $content : null]
        );

        // Đẩy thông báo tới sinh viên cùng lớp.
        $students = db_fetch_all(
            "SELECT student_id
             FROM class_students
             WHERE class_id = ? AND student_id <> ?",
            [$classId, $userId]
        );
        foreach ($students as $st) {
            $studentId = (int)($st['student_id'] ?? 0);
            if ($studentId <= 0) continue;
            db_query(
                "INSERT INTO notification_logs (user_id, title, message, is_read)
                 VALUES (?, ?, ?, 0)",
                [$studentId, $title, $content !== '' ? $content : null]
            );
        }

        ann_json(200, ['ok' => true]);
    }

    if ($action === 'update') {
        if ($id <= 0 || $title === '') {
            ann_json(422, ['ok' => false, 'error' => 'Dữ liệu cập nhật không hợp lệ.']);
        }

        $row = db_fetch_one(
            "SELECT id FROM notification_logs WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $userId]
        );
        if (!$row) {
            ann_json(404, ['ok' => false, 'error' => 'Không tìm thấy bản tin cần sửa.']);
        }

        db_query(
            "UPDATE notification_logs SET title = ?, message = ? WHERE id = ?",
            [$title, $content !== '' ? $content : null, $id]
        );
        ann_json(200, ['ok' => true]);
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            ann_json(422, ['ok' => false, 'error' => 'Thiếu id bản tin.']);
        }

        $row = db_fetch_one(
            "SELECT id FROM notification_logs WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $userId]
        );
        if (!$row) {
            ann_json(404, ['ok' => false, 'error' => 'Không tìm thấy bản tin cần xóa.']);
        }

        db_query("DELETE FROM notification_logs WHERE id = ?", [$id]);
        ann_json(200, ['ok' => true]);
    }

    ann_json(400, ['ok' => false, 'error' => 'Action không hợp lệ.']);
} catch (Throwable $e) {
    error_log('api/bcs/announcements error: ' . $e->getMessage());
    ann_json(500, ['ok' => false, 'error' => 'Lỗi hệ thống khi xử lý bản tin.']);
}

