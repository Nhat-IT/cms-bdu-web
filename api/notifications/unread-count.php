<?php
/**
 * API: Get unified unread notification count
 * GET /api/notifications/unread-count
 * Returns count from: notification_logs + documents + attendance (14 days)
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$studentMssv = trim((string)($_SESSION['username'] ?? ''));

try {
    // 1. Thông báo từ notification_logs
    $notifCount = (int)db_count(
        "SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0",
        [$userId]
    );

    // 2. Tài liệu mới trong 14 ngày gần nhất
    $docCount = 0;
    if ($studentMssv !== '') {
        $docCount = (int)db_count(
            "SELECT COUNT(DISTINCT d.id)
             FROM documents d
             JOIN class_subjects cs ON d.class_subject_id = cs.id
             LEFT JOIN users uploader ON d.uploader_id = uploader.id
             WHERE cs.class_id IN (
                 SELECT DISTINCT cs2.class_id
                 FROM student_subject_registration ssr
                 JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
                 JOIN class_subjects cs2 ON csg.class_subject_id = cs2.id
                 WHERE (ssr.student_id = ? OR ssr.mssv = ?)
                   AND ssr.status = 'Đang học'
                   AND cs2.class_id IS NOT NULL
             )
             AND LOWER(COALESCE(uploader.role, '')) IN ('bcs', 'admin', 'support_admin')
             AND d.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)",
            [$userId, $studentMssv]
        );
    }

    // 3. Điểm danh mới trong 14 ngày gần nhất
    $attCount = 0;
    if ($studentMssv !== '') {
        $attCount = (int)db_count(
            "SELECT COUNT(DISTINCT ar.id)
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             LEFT JOIN student_subject_registration ssr_m
                 ON ssr_m.class_subject_group_id = csg.id AND ssr_m.mssv = ?
             WHERE (ar.student_id = ?
                    OR (ar.registration_id IS NOT NULL AND ar.registration_id = ssr_m.id))
               AND a_s.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)",
            [$studentMssv, $userId]
        );
    }

    $unifiedCount = $notifCount + $docCount + $attCount;

    echo json_encode([
        'unreadCount' => $unifiedCount,
        'notifCount' => $notifCount,
        'docCount' => $docCount,
        'attCount' => $attCount,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[unread-count.php] ' . $e->getMessage());
    echo json_encode(['unreadCount' => 0, 'notifCount' => 0, 'docCount' => 0, 'attCount' => 0]);
}
