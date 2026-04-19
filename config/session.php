<?php
/**
 * CMS BDU - Session Configuration
 * Cấu hình phiên làm việc
 */

session_start();

// Kiểm tra thời gian timeout (30 phút)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: ../views/login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Hàm kiểm tra đăng nhập
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Hàm kiểm tra quyền
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($_SESSION['role'], $roles);
}

// Hàm chuyển hướng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../views/login.php');
        exit;
    }
}

// Hàm chuyển hướng nếu không có quyền
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ../views/unauthorized.php');
        exit;
    }
}

// Lấy thông tin user hiện tại
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

// Đăng xuất
function logout() {
    session_unset();
    session_destroy();
    header('Location: ../views/login.php');
    exit;
}
