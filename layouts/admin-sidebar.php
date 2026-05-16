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
        <a href="#" class="nav-link logout-btn" title="Đăng xuất"
           data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="bi bi-box-arrow-left"></i>
            <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<!-- Modal xác nhận đăng xuất -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4 px-3">
                <div class="mb-3">
                    <i class="bi bi-box-arrow-left text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="fw-bold mb-1">Xác nhận đăng xuất</h6>
                <p class="text-muted small mb-4">Bạn có chắc chắn muốn đăng xuất không?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Hủy</button>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-sm px-4">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
</div>
