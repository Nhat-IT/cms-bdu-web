<?php
/**
 * API: Get unread notification count
 * GET /api/notifications/unread-count
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $row = db_fetch_one(
        "SELECT COUNT(*) as cnt FROM notification_logs WHERE user_id = ? AND is_read = 0",
        [$userId]
    );

    echo json_encode(['unreadCount' => (int)($row['cnt'] ?? 0)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[unread-count.php] ' . $e->getMessage());
    echo json_encode(['unreadCount' => 0]);
}
