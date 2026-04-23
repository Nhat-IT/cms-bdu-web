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

        if ($user) {
            $storedPassword = (string) ($user['password'] ?? '');
            $passwordInfo = password_get_info($storedPassword);
            $isHashedPassword = isset($passwordInfo['algo']) && $passwordInfo['algo'] !== 0;

            $isValidPassword = false;
            if ($isHashedPassword) {
                $isValidPassword = password_verify($password, $storedPassword);
            } else {
                // Backward compatibility: allow old plaintext passwords, then migrate.
                $isValidPassword = hash_equals($storedPassword, (string) $password);
            }

            if (!$isValidPassword) {
                logSystem("Đăng nhập thất bại - sai mật khẩu (username: $username)", null, null);
                return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
            }

            // Kiểm tra tài khoản bị khóa (is_active = 0)
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                logSystem("Đăng nhập thất bại - tài khoản bị khóa (username: $username)", 'users', $user['id']);
                return ['success' => false, 'message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ Admin để được hỗ trợ.'];
            }

            // Auto-upgrade plaintext password to a secure hash after successful login.
            if (!$isHashedPassword) {
                $newHashedPassword = password_hash($password, PASSWORD_DEFAULT);
                db_query("UPDATE users SET password = ? WHERE id = ?", [$newHashedPassword, $user['id']]);
            }

            $roles = [$user['role'] ?? null, $user['secondary_role'] ?? null];
            $roles = array_values(array_unique(array_filter($roles, static function ($value) {
                return $value !== null && $value !== '';
            })));

            // Lưu session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['roles'] = $roles;
            $_SESSION['secondary_role'] = $user['secondary_role'] ?? null;
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['last_activity'] = time();

            logSystem("Đăng nhập thành công", 'users', $user['id']);

            return ['success' => true, 'role' => $user['role'], 'roles' => $roles];
        }

        logSystem("Đăng nhập thất bại - không tìm thấy tài khoản (username: $username)", null, null);
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

        logSystem("Cập nhật hồ sơ", 'users', $userId);

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
        logSystem("Đổi mật khẩu", 'users', $userId);

        return ['success' => true, 'message' => 'Đổi mật khẩu thành công.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'];
    }
}
