<?php
/**
 * CMS BDU - Khởi tạo Google OAuth flow
 * Chuyển hướng người dùng đến trang đăng nhập Google
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

// Nếu đã đăng nhập thì về trang chủ
if (isLoggedIn()) {
    header('Location: ' . getHomeUrl($_SESSION['role']));
    exit;
}

$clientId     = envOrDefault('GOOGLE_CLIENT_ID', '');
$redirectUri  = BASE_URL . '/api/auth/google-callback.php';

if (empty($clientId)) {
    header('Location: ' . BASE_URL . '/login.php?error=google_not_configured');
    exit;
}

// Tạo state ngẫu nhiên để chống CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = http_build_query([
    'client_id'             => $clientId,
    'redirect_uri'          => $redirectUri,
    'response_type'         => 'code',
    'scope'                 => 'openid email profile',
    'access_type'           => 'online',
    'state'                 => $state,
    'prompt'                => 'select_account',
    'hd'                    => '', // Không giới hạn domain ở đây; kiểm tra ở callback
]);

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;

header('Location: ' . $authUrl);
exit;
