<?php
/**
 * CMS BDU - Authentication Controller
 * Xử lý đăng nhập, đăng xuất, quên mật khẩu
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

// Xử lý đăng nhập
function handleLogin($username, $password) {
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.'];
    }
    
    try {
        $user = db_fetch_one("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Lưu session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['last_activity'] = time();
            
            return ['success' => true, 'role' => $user['role']];
        }
        
        return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'];
    }
}

// Xử lý đăng xuất
function handleLogout() {
    logout();
}

// Cập nhật profile
function handleUpdateProfile($userId, $data) {
    try {
        db_query(
            "UPDATE users SET phone_number = ?, address = ?, birth_date = ?, avatar = ? WHERE id = ?",
            [
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['birth_date'] ?? null,
                $data['avatar'] ?? null,
                $userId
            ]
        );
        
        if (isset($data['avatar'])) {
            $_SESSION['avatar'] = $data['avatar'];
        }
        
        return ['success' => true, 'message' => 'Cập nhật hồ sơ thành công.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'];
    }
}

// Đổi mật khẩu
function handleChangePassword($userId, $oldPassword, $newPassword, $confirmPassword) {
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.'];
    }
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Mật khẩu xác nhận không khớp.'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'];
    }
    
    try {
        $user = db_fetch_one("SELECT password FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không đúng.'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        db_query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
        
        return ['success' => true, 'message' => 'Đổi mật khẩu thành công.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'];
    }
}
