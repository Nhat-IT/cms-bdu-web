<?php
/**
 * CMS BDU - Admin Sidebar Partial
 * Dùng chung cho tất cả trang Admin.
 *
 * Biến cần truyền trước khi include:
 *   $activePage (string) — slug của trang hiện tại, ví dụ: 'home', 'accounts', ...
 */

$activePage = $activePage ?? '';

$adminMenuItems = [
    ['slug' => 'home',             'href' => 'home.php',             'icon' => 'bi-speedometer2',      'label' => 'Tổng quan hệ thống'],
    ['slug' => 'org-settings',     'href' => 'org-settings.php',     'icon' => 'bi-gear-wide-connected','label' => 'Cấu hình Học vụ'],
    ['slug' => 'accounts',         'href' => 'accounts.php',         'icon' => 'bi-people',             'label' => 'Quản lý Tài khoản'],
    ['slug' => 'classes-subjects', 'href' => 'classes-subjects.php', 'icon' => 'bi-building',           'label' => 'Quản lý Lớp & Môn'],
    ['slug' => 'assignments',      'href' => 'assignments.php',      'icon' => 'bi-diagram-3-fill',     'label' => 'Phân công Giảng dạy'],
    ['slug' => 'system-logs',      'href' => 'system-logs.php',      'icon' => 'bi-shield-lock',        'label' => 'Nhật ký hệ thống'],
];
?>
<div class="sidebar sidebar-admin" id="sidebar">
    <div>
        <div class="brand-container flex-shrink-0">
            <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                <span class="fs-4 fw-bold hide-on-collapse">CMS ADMIN</span>
            </a>
        </div>
        <div class="text-center mb-3 text-white-50 small fw-bold hide-on-collapse">QUẢN TRỊ HỆ THỐNG</div>
        <div class="sidebar-scrollable w-100">
            <nav class="d-flex flex-column mt-3">
                <?php foreach ($adminMenuItems as $item): ?>
                    <a href="<?= $item['href'] ?>"<?= $activePage === $item['slug'] ? ' class="active"' : '' ?>>
                        <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="<?= BASE_URL ?>/logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i>
            <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>
