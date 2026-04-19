<?php
/**
 * Script tạo tài khoản admin mặc định
 * Chạy 1 lần duy nhất
 */

require_once __DIR__ . '/config/config.php';

$username = 'admin';
$email = 'admin@bdu.edu.vn';
$password = 'admin123';
$fullName = 'Quản trị viên';

try {
    // Kiểm tra đã có tài khoản chưa
    $existing = db_fetch_one("SELECT id FROM users WHERE username = ?", [$username]);
    
    if ($existing) {
        echo "Tài khoản admin đã tồn tại!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $result = db_query(
            "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)",
            [$username, $hashedPassword, $fullName, $email, 'admin']
        );
        
        echo "Tạo tài khoản admin thành công!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    }
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}
