<?php
/**
 * CMS BDU - Xử lý callback từ Google OAuth
 * Nhận code → đổi lấy token → lấy thông tin user → đăng nhập
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

function googleOAuthError($msgVi, $code = '') {
    $msg = urlencode($msgVi);
    header('Location: ' . BASE_URL . '/login.php?google_error=' . $msg . ($code ? '&code=' . urlencode($code) : ''));
    exit;
}

// Nếu đã đăng nhập
if (isLoggedIn()) {
    header('Location: ' . getHomeUrl($_SESSION['role']));
    exit;
}

// Lỗi từ Google (user từ chối, v.v.)
if (!empty($_GET['error'])) {
    googleOAuthError('Đăng nhập Google bị huỷ.', $_GET['error']);
}

$code        = $_GET['code']  ?? '';
$returnState = $_GET['state'] ?? '';
$savedState  = $_SESSION['google_oauth_state'] ?? '';

// Kiểm tra state CSRF
if (empty($code) || empty($returnState) || !hash_equals($savedState, $returnState)) {
    googleOAuthError('Yêu cầu không hợp lệ. Vui lòng thử lại.', 'state_mismatch');
}
unset($_SESSION['google_oauth_state']);

$clientId     = envOrDefault('GOOGLE_CLIENT_ID', '');
$clientSecret = envOrDefault('GOOGLE_CLIENT_SECRET', '');
$redirectUri  = BASE_URL . '/api/auth/google-callback.php';

if (empty($clientId) || empty($clientSecret)) {
    googleOAuthError('Google OAuth chưa được cấu hình.', 'missing_credentials');
}

// --- Bước 1: Đổi code lấy access token ---
$tokenPayload = http_build_query([
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

$tokenResponse = googleHttpPost('https://oauth2.googleapis.com/token', $tokenPayload);

if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
    $errDetail = isset($tokenResponse['error']) ? $tokenResponse['error'] : 'unknown';
    googleOAuthError('Không thể xác thực với Google. Vui lòng thử lại.', $errDetail);
}

$accessToken = $tokenResponse['access_token'];

// --- Bước 2: Lấy thông tin người dùng từ Google ---
$userInfo = googleHttpGet('https://www.googleapis.com/oauth2/v3/userinfo', $accessToken);

if (!$userInfo || empty($userInfo['email'])) {
    googleOAuthError('Không lấy được thông tin tài khoản Google.', 'no_email');
}

$googleEmail = strtolower(trim($userInfo['email']));
$emailVerified = !empty($userInfo['email_verified']);

if (!$emailVerified) {
    googleOAuthError('Email Google chưa được xác minh.', 'email_not_verified');
}

// --- Bước 3: Tìm user trong database theo email ---
$user = db_fetch_one("SELECT * FROM users WHERE LOWER(email) = ?", [$googleEmail]);

if (!$user) {
    googleOAuthError('Email ' . $googleEmail . ' không phải do BDU cấp. Vui lòng liên hệ Admin.', 'email_not_found');
}

// Kiểm tra tài khoản có bị khóa không
if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
    googleOAuthError('Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Admin.', 'account_locked');
}

// --- Bước 4: Tạo session đăng nhập ---
$roles = [$user['role'] ?? null, $user['secondary_role'] ?? null];
$roles = array_values(array_unique(array_filter($roles, static function ($v) {
    return $v !== null && $v !== '';
})));

$_SESSION['user_id']        = $user['id'];
$_SESSION['username']       = $user['username'];
$_SESSION['full_name']      = $user['full_name'];
$_SESSION['email']          = $user['email'];
$_SESSION['role']           = $user['role'];
$_SESSION['roles']          = $roles;
$_SESSION['secondary_role'] = $user['secondary_role'] ?? null;
$_SESSION['avatar']         = $user['avatar'];
$_SESSION['last_activity']  = time();
$_SESSION['auth_method']    = 'google';

logSystem('Đăng nhập bằng Google', 'users', $user['id']);

header('Location: ' . getHomeUrl($user['role']));
exit;

// --- Helper functions ---

function googleHttpPost($url, $payload) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            error_log('Google OAuth cURL POST error: ' . $curlError);
            return null;
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    // Fallback: file_get_contents
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 15,
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        error_log('Google OAuth file_get_contents POST error for ' . $url);
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function googleHttpGet($url, $accessToken) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            error_log('Google OAuth cURL GET error: ' . $curlError);
            return null;
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    // Fallback: file_get_contents
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $accessToken\r\n",
            'timeout' => 15,
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        error_log('Google OAuth file_get_contents GET error for ' . $url);
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}
