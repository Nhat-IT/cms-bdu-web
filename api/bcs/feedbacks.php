<?php
/**
 * API: BCS Feedback list/resolve/delete
 * Endpoint: /api/bcs/feedbacks.php
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

function bcs_feedback_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bcs_feedback_class_id(int $userId): int {
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

    $classId = bcs_feedback_class_id($userId);
    if ($classId <= 0) {
        bcs_feedback_json(400, ['ok' => false, 'error' => 'Tài khoản BCS chưa được gán lớp.']);
    }

    if ($method === 'GET') {
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $status = strtolower(trim((string)($_GET['status'] ?? 'all')));

        $sql = "SELECT f.*, u.full_name, u.username
                FROM feedbacks f
                JOIN users u ON f.student_id = u.id
                JOIN class_students cs ON f.student_id = cs.student_id
                WHERE cs.class_id = ?";
        $params = [$classId];

        if ($status === 'pending') {
            $sql .= " AND f.status = 'Pending'";
        } elseif ($status === 'resolved') {
            $sql .= " AND f.status = 'Resolved'";
        }

        if ($keyword !== '') {
            $sql .= " AND (
                LOWER(COALESCE(u.full_name, '')) LIKE LOWER(?)
                OR LOWER(COALESCE(u.username, '')) LIKE LOWER(?)
                OR LOWER(COALESCE(f.title, '')) LIKE LOWER(?)
                OR LOWER(COALESCE(f.content, '')) LIKE LOWER(?)
            )";
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY CASE WHEN f.status = 'Pending' THEN 0 ELSE 1 END, f.updated_at DESC LIMIT 200";
        $rows = db_fetch_all($sql, $params);
        bcs_feedback_json(200, ['ok' => true, 'items' => $rows]);
    }

    if ($method !== 'POST') {
        bcs_feedback_json(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        bcs_feedback_json(422, ['ok' => false, 'error' => 'Thiếu id phản hồi.']);
    }

    $exists = db_fetch_one(
        "SELECT f.id
         FROM feedbacks f
         JOIN class_students cs ON f.student_id = cs.student_id
         WHERE f.id = ? AND cs.class_id = ?
         LIMIT 1",
        [$id, $classId]
    );
    if (!$exists) {
        bcs_feedback_json(404, ['ok' => false, 'error' => 'Không tìm thấy phản hồi.']);
    }

    if ($action === 'resolve') {
        $reply = trim((string)($input['reply_content'] ?? ''));
        db_query(
            "UPDATE feedbacks
             SET reply_content = ?, status = 'Resolved', updated_at = NOW()
             WHERE id = ?",
            [$reply !== '' ? $reply : null, $id]
        );
        bcs_feedback_json(200, ['ok' => true]);
    }

    if ($action === 'delete') {
        db_query("DELETE FROM feedbacks WHERE id = ?", [$id]);
        bcs_feedback_json(200, ['ok' => true]);
    }

    bcs_feedback_json(400, ['ok' => false, 'error' => 'Action không hợp lệ.']);
} catch (Throwable $e) {
    error_log('api/bcs/feedbacks error: ' . $e->getMessage());
    bcs_feedback_json(500, ['ok' => false, 'error' => 'Lỗi hệ thống khi xử lý phản hồi.']);
}

