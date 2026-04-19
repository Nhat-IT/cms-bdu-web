<?php
/**
 * CMS BDU - Helper Functions
 * Các hàm tiện ích dùng chung
 */

// Lấy avatar với fallback
function getAvatarUrl($avatar = null, $name = '', $size = 55) {
    if ($avatar && file_exists($avatar)) {
        return $avatar;
    }
    $encodedName = urlencode($name);
    return "https://ui-avatars.com/api/?name={$encodedName}&background=0d6efd&color=fff&size={$size}";
}

// Format ngày giờ
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    $timestamp = is_string($date) ? strtotime($date) : $date;
    return date($format, $timestamp);
}

function formatDateTime($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    $timestamp = is_string($date) ? strtotime($date) : $date;
    return date($format, $timestamp);
}

// Format số điện thoại
function formatPhone($phone) {
    if (!$phone) return '';
    return preg_replace('/(\d{4})(\d{3})(\d{3})/', '$1.$2.$3', $phone);
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Lấy role label tiếng Việt
function getRoleLabel($role) {
    $labels = [
        'student' => 'Sinh viên',
        'bcs' => 'Ban Cán Sự',
        'teacher' => 'Giảng viên',
        'admin' => 'Quản trị',
    ];
    return $labels[$role] ?? $role;
}

// Lấy URL trang chủ theo role
function getHomeUrl($role) {
    $urls = [
        'student' => 'student/home.php',
        'bcs' => 'bcs/home.php',
        'teacher' => 'teacher/home.php',
        'admin' => 'admin/home.php',
    ];
    return $urls[$role] ?? 'student/home.php';
}

// Lấy URL profile theo role
function getProfileUrl($role) {
    $urls = [
        'student' => 'student/student-profile.php',
        'bcs' => 'bcs/profile.php',
        'teacher' => 'teacher/teacher-profile.php',
        'admin' => 'admin/admin-profile.php',
    ];
    return $urls[$role] ?? 'profile.php';
}
