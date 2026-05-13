<?php
/**
 * CMS BDU - Helper Functions
 * Các hàm tiện ích dùng chung
 */

// Lấy giá trị setting từ DB
function getSetting($key, $default = null) {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = db_fetch_one("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        $cache[$key] = $row ? $row['setting_value'] : null;
    }
    return $cache[$key] ?? $default;
}

// Lưu giá trị setting vào DB
function setSetting($key, $value) {
    $exists = db_fetch_one("SELECT id FROM settings WHERE setting_key = ?", [$key]);
    if ($exists) {
        db_query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
    } else {
        db_query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
    }
}

// Lấy học kỳ hiện tại ưu tiên: session → DB → date → latest
function getCurrentSemester() {
    $forcedId = isset($_SESSION['current_semester_id']) ? (int) $_SESSION['current_semester_id'] : 0;
    if ($forcedId > 0) {
        $sem = db_fetch_one('SELECT * FROM semesters WHERE id = ?', [$forcedId]);
        if ($sem) return $sem;
    }
    $dbId = (int) getSetting('current_semester_id');
    if ($dbId > 0) {
        $sem = db_fetch_one('SELECT * FROM semesters WHERE id = ?', [$dbId]);
        if ($sem) return $sem;
    }
    $sem = db_fetch_one(
        "SELECT * FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1"
    );
    if ($sem) return $sem;
    return db_fetch_one("SELECT * FROM semesters ORDER BY start_date DESC LIMIT 1");
}

// Lấy avatar với fallback
function getAvatarUrl($avatar = null, $name = '', $size = 55) {
    if ($avatar && file_exists($avatar)) {
        return $avatar;
    }
    $encodedName = urlencode((string) ($name ?? ''));
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
        'support_admin' => 'Giáo vụ khoa',
        'admin' => 'Quản trị viên',
    ];
    return $labels[$role] ?? $role;
}

// Lấy URL trang chủ theo role
function getHomeUrl($role) {
    $urls = [
        'student' => BASE_URL . '/views/student/home.php',
        'bcs'     => BASE_URL . '/views/bcs/home.php',
        'teacher' => BASE_URL . '/views/admin/home.php', // TODO: tạo views/teacher khi có
        'support_admin' => BASE_URL . '/views/admin/home.php',
        'admin'   => BASE_URL . '/views/admin/home.php',
    ];
    return $urls[$role] ?? BASE_URL . '/views/student/home.php';
}

// Lấy URL profile theo role
function getProfileUrl($role) {
    $urls = [
        'student' => BASE_URL . '/views/student/student-profile.php',
        'bcs' => BASE_URL . '/views/bcs/profile.php',
        'teacher' => BASE_URL . '/views/teacher/teacher-profile.php',
        'support_admin' => BASE_URL . '/views/admin/admin-profile.php',
        'admin' => BASE_URL . '/views/admin/admin-profile.php',
    ];
    return $urls[$role] ?? BASE_URL . '/views/student/student-profile.php';
}

// Lấy URL login
function getLoginUrl() {
    return BASE_URL . '/login.php';
}

// Lấy URL logout
function getLogoutUrl() {
    return BASE_URL . '/logout.php';
}

// Ghi nhật ký hệ thống
function logSystem(string $action, ?string $targetTable = null, ?int $targetId = null): void {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return;
    db_query(
        'INSERT INTO system_logs (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)',
        [$userId, $action, $targetTable, $targetId]
    );
}

/**
 * Lấy thông tin lớp của user từ class_students
 * Trả về array: ['class_id' => int|null, 'class_name' => string, 'source' => string]
 */
function getUserClassInfo($userId) {
    $result = ['class_id' => null, 'class_name' => '', 'source' => ''];

    // Lấy từ class_students (qua student_id) — JOIN với classes để đảm bảo lớp tồn tại
    $classRow = db_fetch_one(
        "SELECT cs.class_id, c.class_name
         FROM class_students cs
         JOIN classes c ON cs.class_id = c.id
         WHERE cs.student_id = ?",
        [$userId]
    );
    if ($classRow && !empty($classRow['class_id'])) {
        $result['class_id'] = $classRow['class_id'];
        $result['class_name'] = $classRow['class_name'];
        $result['source'] = 'class_students';
        return $result;
    }

    // Fallback: tìm class_name từ student_subject_registration, rồi match ngược với classes
    $regRow = db_fetch_one(
        "SELECT ssr.class_name
         FROM student_subject_registration ssr
         WHERE ssr.student_id = ? AND ssr.class_name IS NOT NULL AND ssr.class_name != ''
         LIMIT 1",
        [$userId]
    );
    if ($regRow && !empty($regRow['class_name'])) {
        $classByName = db_fetch_one(
            "SELECT id, class_name FROM classes WHERE class_name = ? LIMIT 1",
            [$regRow['class_name']]
        );
        if ($classByName) {
            $result['class_id'] = $classByName['id'];
            $result['class_name'] = $classByName['class_name'];
            $result['source'] = 'student_subject_registration';
            return $result;
        }
    }

    return $result;
}
