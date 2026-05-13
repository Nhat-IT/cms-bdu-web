<?php
/**
 * CMS BDU - Session Configuration
 * Cấu hình phiên làm việc
 */

require_once __DIR__ . '/config.php';

// Don't call session_start() if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function isApiRequest() {
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
    return strpos($accept, 'application/json') !== false;
}

function respondUnauthorized($message = 'unauthorized') {
    error_log('respondUnauthorized called: message=' . $message . ', session=' . json_encode([
        'user_id' => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'timeout' => isset($_SESSION['last_activity']) ? (time() - $_SESSION['last_activity']) : 'none'
    ]));

    // Nếu là request API → trả JSON; ngược lại (truy cập trực tiếp) → redirect trình duyệt
    if (isApiRequest()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'message' => $message,
            'redirect' => BASE_URL . '/login.php'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        redirect(BASE_URL . '/login.php');
    }
}

// Kiểm tra thời gian timeout (30 phút)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    if (isApiRequest()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'message' => 'session_expired',
            'redirect' => (defined('BASE_URL') ? BASE_URL : '/cms') . '/login.php'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        redirect(BASE_URL . '/login.php');
    }
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

    $userRoles = [];
    if (!empty($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        $userRoles = $_SESSION['roles'];
    } elseif (!empty($_SESSION['role'])) {
        $userRoles = [$_SESSION['role']];
    }

    return count(array_intersect($userRoles, $roles)) > 0;
}

// Hàm chuyển hướng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        respondUnauthorized('login_required');
    }
}

// Hàm chuyển hướng nếu không có quyền
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        error_log('requireRole failed: userRoles=' . json_encode([
            'session_roles' => $_SESSION['roles'] ?? [],
            'session_role' => $_SESSION['role'] ?? null,
            'required_roles' => $roles
        ]));

        if (isApiRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'message' => 'forbidden',
                'redirect' => BASE_URL . '/unauthorized.php'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            redirect(BASE_URL . '/unauthorized.php');
        }
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
        'roles' => $_SESSION['roles'] ?? [$_SESSION['role'] ?? null],
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

// Đăng xuất
function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

