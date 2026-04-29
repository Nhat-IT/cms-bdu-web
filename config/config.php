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
            if (function_exists('putenv')) {
                @putenv("$name=$value");
            }
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

// Base URL for the application (without trailing slash)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$basePath = '/cms';
define('BASE_URL', $protocol . $host . $basePath);
define('BASE_PATH', __DIR__ . '/..');

function createPDOConnection() {
    if (!class_exists('PDO')) {
        throw new Exception('PHP PDO extension is not available on host.');
    }

    $drivers = PDO::getAvailableDrivers();
    if (!in_array('mysql', $drivers, true)) {
        throw new Exception('PHP PDO MySQL driver is not available on host.');
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ]);
}

class LazyPDOProxy {
    private $connection = null;

    private function getConnection() {
        if ($this->connection === null) {
            $this->connection = createPDOConnection();
        }
        return $this->connection;
    }

    public function __call($name, $arguments) {
        $connection = $this->getConnection();
        return $connection->$name(...$arguments);
    }
}

// Chế độ hiển thị lỗi (Tắt khi deploy)
$appDebug = strtolower((string) envOrDefault('APP_DEBUG', 'false'));
$isDebug = in_array($appDebug, ['1', 'true', 'yes', 'on'], true);
ini_set('display_errors', $isDebug ? '1' : '0');
error_reporting(E_ALL);

// Khởi tạo kết nối MySQLi
function getDBConnection() {
    global $conn;
    
    if (!isset($conn) || $conn === false) {
        try {
            // Avoid uncaught mysqli_sql_exception on shared hosting.
            if (function_exists('mysqli_report')) {
                mysqli_report(MYSQLI_REPORT_OFF);
            }

            if (!function_exists('mysqli_connect')) {
                throw new Exception('PHP extension mysqli is not available on host.');
            }
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        } catch (Throwable $e) {
            $safeHost = DB_HOST ?: '(empty-host)';
            throw new Exception("Lỗi kết nối database tới '{$safeHost}'. Vui lòng kiểm tra DB_HOST/DB_NAME/DB_USER/DB_PASS trong .env.local hoặc biến môi trường host. Chi tiết: " . $e->getMessage());
        }

        if (!$conn) {
            $safeHost = DB_HOST ?: '(empty-host)';
            throw new Exception("Lỗi kết nối database tới '{$safeHost}'. Vui lòng kiểm tra DB_HOST/DB_NAME/DB_USER/DB_PASS trong .env.local hoặc biến môi trường host. Chi tiết: " . mysqli_connect_error());
        }
        
        // Set charset
        mysqli_set_charset($conn, "utf8mb4");
    }
    
    return $conn;
}

// Kết nối được khởi tạo lazy khi thực sự cần query.
$conn = null;

// Backward compatibility cho các trang cũ còn dùng $pdo.
$pdo = new LazyPDOProxy();

// Hàm escape
function e($string) {
    global $conn;
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Hàm chạy query an toàn
function db_query($sql, $params = []) {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Không thể kết nối database.');
    }

    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt === false) {
        $error = mysqli_error($conn);
        error_log("DB_PREPARE_ERROR: " . $error . " | SQL: " . substr($sql, 0, 200));
        throw new Exception("Lỗi prepare: " . $error);
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    $execResult = mysqli_stmt_execute($stmt);
    
    if ($execResult === false) {
        $error = mysqli_stmt_error($stmt);
        error_log("DB_EXECUTE_ERROR: " . $error . " | SQL: " . substr($sql, 0, 200));
        mysqli_stmt_close($stmt);
        throw new Exception("Lỗi execute: " . $error);
    }
    
    // Kiểm tra loại query: SELECT hay INSERT/UPDATE/DELETE
    $trimmedSql = trim($sql);
    $isSelect = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\s/i', $trimmedSql);
    
    if ($isSelect) {
        $result = mysqli_stmt_get_result($stmt);
        return $result;
    } else {
        // INSERT/UPDATE/DELETE - lấy số dòng bị ảnh hưởng
        mysqli_stmt_store_result($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        
        // Kiểm tra xem INSERT/UPDATE có thực sự thành công không
        if ($affected < 0) {
            $error = mysqli_stmt_error($stmt);
            error_log("DB_AFFECTED_ERROR: " . $error . " | SQL: " . substr($sql, 0, 200) . " | affected=" . $affected);
            mysqli_stmt_close($stmt);
            throw new Exception("Lỗi khi thực hiện query: " . $error);
        }
        
        error_log("DB_QUERY_SUCCESS: affected=" . $affected . " | SQL: " . substr($sql, 0, 100));
        mysqli_stmt_close($stmt);
        return $affected;
    }
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
