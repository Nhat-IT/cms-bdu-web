<?php
/**
 * CMS BDU - Database Configuration
 * Kết nối MySQL Database sử dụng MySQLi
 * 
 * THÔNG TIN CẤU HÌNH:
 * - Copy file .env.example thành .env.local
 * - Cập nhật thông tin database trong .env.local
 * - KHÔNG commit .env.local lên git
 */

// Load environment variables from common env files.
function loadEnvFile($filePath) {
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove wrapping quotes: KEY="value" or KEY='value'
        if ((strlen($value) >= 2) &&
            (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $currentValue = getenv($name);
        $hasCurrent = !($currentValue === false || trim((string) $currentValue) === '');
        if (!$hasCurrent && isset($_ENV[$name]) && trim((string) $_ENV[$name]) !== '') {
            $hasCurrent = true;
        }
        if (!$hasCurrent && isset($_SERVER[$name]) && trim((string) $_SERVER[$name]) !== '') {
            $hasCurrent = true;
        }

        if ($name !== '' && !$hasCurrent) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("$name=$value");
        }
    }
}

function envOrDefault($key, $default = '') {
    if (isset($_ENV[$key])) {
        $value = trim((string) $_ENV[$key]);
        if ($value !== '') {
            return $value;
        }
    }

    if (isset($_SERVER[$key])) {
        $value = trim((string) $_SERVER[$key]);
        if ($value !== '') {
            return $value;
        }
    }

    $value = getenv($key);
    if ($value !== false) {
        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

$envCandidates = [
    __DIR__ . '/../.env.local',
    __DIR__ . '/../.env',
    __DIR__ . '/../env.local',
    __DIR__ . '/.env.local',
    __DIR__ . '/.env',
    __DIR__ . '/env.local',
    __DIR__ . '/env'
];

foreach ($envCandidates as $envFile) {
    loadEnvFile($envFile);
}

function detectInfinityFreeAccountPrefix() {
    $path = str_replace('\\', '/', __DIR__);
    if (preg_match('/(if0_\d+)/', $path, $matches)) {
        return $matches[1];
    }
    return '';
}

$ifAccountPrefix = detectInfinityFreeAccountPrefix();
$httpHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
$isInfinityFreeHost =
    strpos($httpHost, 'infinityfree') !== false ||
    strpos($httpHost, '.ct.ws') !== false ||
    strpos($httpHost, '.epizy.com') !== false ||
    $ifAccountPrefix !== '';

// Thông tin kết nối Database
define('DB_HOST', envOrDefault('DB_HOST', $isInfinityFreeHost ? 'sql300.infinityfree.com' : 'localhost'));
define('DB_NAME', envOrDefault('DB_NAME', $ifAccountPrefix !== '' ? $ifAccountPrefix . '_cms_bdu' : 'cms_bdu'));
define('DB_USER', envOrDefault('DB_USER', $ifAccountPrefix !== '' ? $ifAccountPrefix : 'root'));
define('DB_PASS', envOrDefault('DB_PASS', envOrDefault('DB_PASSWORD', '')));
define('DB_PORT', (int) envOrDefault('DB_PORT', '3306'));

// Chế độ hiển thị lỗi (Tắt khi deploy)
$appDebug = strtolower((string) (getenv('APP_DEBUG') ?: 'false'));
$isDebug = in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
ini_set('display_errors', $isDebug ? '1' : '0');
error_reporting(E_ALL);

// Khởi tạo kết nối MySQLi
function getDBConnection() {
    global $conn;
    
    if (!isset($conn) || $conn === false) {
        try {
            // Avoid uncaught mysqli_sql_exception on shared hosting.
            mysqli_report(MYSQLI_REPORT_OFF);
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        } catch (Throwable $e) {
            $safeHost = DB_HOST ?: '(empty-host)';
            die("Lỗi kết nối database tới '{$safeHost}'. Vui lòng kiểm tra DB_HOST/DB_NAME/DB_USER/DB_PASS trong .env.local hoặc biến môi trường host. Chi tiết: " . $e->getMessage());
        }

        if (!$conn) {
            $safeHost = DB_HOST ?: '(empty-host)';
            die("Lỗi kết nối database tới '{$safeHost}'. Vui lòng kiểm tra DB_HOST/DB_NAME/DB_USER/DB_PASS trong .env.local hoặc biến môi trường host. Chi tiết: " . mysqli_connect_error());
        }
        
        // Set charset
        mysqli_set_charset($conn, "utf8mb4");
    }
    
    return $conn;
}

// Lấy kết nối
$conn = getDBConnection();

// Hàm escape
function e($string) {
    global $conn;
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Hàm chạy query an toàn
function db_query($sql, $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        throw new Exception("Lỗi prepare: " . mysqli_error($conn));
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return $result;
}

// Hàm lấy một dòng
function db_fetch_one($sql, $params = []) {
    $result = db_query($sql, $params);
    return mysqli_fetch_assoc($result) ?? null;
}

// Hàm lấy tất cả dòng
function db_fetch_all($sql, $params = []) {
    $result = db_query($sql, $params);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Hàm lấy số lượng
function db_count($sql, $params = []) {
    $result = db_query($sql, $params);
    $row = mysqli_fetch_assoc($result);
    return $row ? array_values($row)[0] : 0;
}
