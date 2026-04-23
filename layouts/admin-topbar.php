<?php
/**
 * CMS BDU - Admin Topbar Partial
 * Dùng chung cho tất cả trang Admin.
 *
 * Biến cần truyền trước khi include:
 *   $pageTitle  (string) — tiêu đề hiển thị trên topbar
 *   $pageIcon   (string) — class Bootstrap Icon, ví dụ: 'bi-speedometer2'
 *   $currentUser (array) — từ getCurrentUser()
 */

$pageTitle  = $pageTitle  ?? 'Quản trị hệ thống';
$pageIcon   = $pageIcon   ?? 'bi-shield-lock-fill';
$currentUser = $currentUser ?? [];
?>
<div class="top-navbar-admin d-flex justify-content-between align-items-center px-4 py-3">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h4 class="m-0 text-white fw-bold d-flex align-items-center">
            <i class="bi <?= $pageIcon ?> me-2 fs-3 text-warning"></i>
            <?= e($pageTitle) ?>
        </h4>
    </div>

    <div class="d-flex align-items-center text-white">
        <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
            <div class="fs-6">Quản trị viên:
                <span class="fw-bold admin-operator-name">
                    <?= e($currentUser['full_name'] ?? 'Admin') ?>
                </span>
            </div>
        </div>

        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none"
               data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-2"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                <li>
                    <a class="dropdown-item fw-bold" href="admin-profile.php">
                        <i class="bi bi-person-vcard text-primary me-2"></i>Hồ sơ cá nhân
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item fw-bold text-danger" href="<?= BASE_URL ?>/logout.php">
                        <i class="bi bi-box-arrow-right text-danger me-2"></i>Đăng xuất
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
