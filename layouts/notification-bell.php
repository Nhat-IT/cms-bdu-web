<?php
/**
 * Notification bell dropdown — dùng chung cho tất cả trang student
 * Yêu cầu: $userId và $unreadNotifications đã được set trước khi include
 */
$bellNotifs = db_fetch_all(
    "SELECT id, title, created_at FROM notification_logs
     WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC LIMIT 5",
    [$userId]
);
$_bellUnreadCount = (int)db_count(
    "SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);
?>
<div class="dropdown">
    <a href="#" class="text-white text-decoration-none position-relative me-1 d-flex align-items-center"
       id="notifBellDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Thông báo">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($_bellUnreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  style="font-size:.65rem;">
                <?= $_bellUnreadCount > 9 ? '9+' : $_bellUnreadCount ?>
            </span>
        <?php endif; ?>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 mt-2"
         style="min-width:320px;max-width:360px;border-radius:12px;overflow:hidden;"
         aria-labelledby="notifBellDropdown">

        <!-- Header -->
        <div class="px-3 py-2 d-flex align-items-center gap-2"
             style="background:#1565c0;color:#fff;">
            <i class="bi bi-bell-fill"></i>
            <span class="fw-bold">Thông báo mới</span>
            <?php if ($_bellUnreadCount > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $_bellUnreadCount > 9 ? '9+' : $_bellUnreadCount ?></span>
            <?php endif; ?>
        </div>

        <!-- Danh sách thông báo -->
        <?php if (empty($bellNotifs)): ?>
            <div class="px-3 py-4 text-center text-muted small">
                <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                Không có thông báo mới
            </div>
        <?php else: ?>
            <?php foreach ($bellNotifs as $n): ?>
                <a href="notifications-all.php" class="dropdown-item px-3 py-2 border-bottom"
                   style="white-space:normal;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"
                              style="font-size:.7rem;">THÔNG BÁO</span>
                        <small class="text-muted" style="font-size:.72rem;">
                            <?= formatDateTime($n['created_at'], 'd/m/Y H:i') ?>
                        </small>
                    </div>
                    <div class="fw-bold text-dark" style="font-size:.88rem;
                         overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                        <?= e($n['title']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Footer -->
        <div class="px-3 py-2 text-center border-top bg-light">
            <a href="notifications-all.php" class="text-primary fw-bold text-decoration-none"
               style="font-size:.88rem;">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
