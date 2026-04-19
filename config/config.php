<?php
/**
 * CMS BDU - Database Configuration
 * Kết nối MySQL Database sử dụng MySQLi
 */

// Thông tin kết nối Database
define('DB_HOST', 'sql300.infinityfree.com');
define('DB_NAME', 'if0_41700850_cms_bdu');
define('DB_USER', 'if0_41700850');
define('DB_PASS', 'Nhat472k');
define('DB_PORT', 3306);

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
