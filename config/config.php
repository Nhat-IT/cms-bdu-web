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

// Load environment variables from .env.local if exists
$envFile = __DIR__ . '/../.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!empty($name) && !isset($_ENV[$name])) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

// Thông tin kết nối Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'cms_bdu');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Chế độ hiển thị lỗi (Tắt khi deploy)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Khởi tạo kết nối MySQLi
function getDBConnection() {
    global $conn;
    
    if (!isset($conn) || $conn === false) {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if (!$conn) {
            die("Lỗi kết nối database: " . mysqli_connect_error());
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
