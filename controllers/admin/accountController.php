<?php
/**
 * CMS BDU - Account Controller
 * Xử lý thêm/sửa tài khoản từ admin accounts
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

define('PROTECTED_ADMIN_EMAIL', 'admin@bdu.edu.vn');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/accounts.php');
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');
$secondaryRole = trim($_POST['secondary_role'] ?? '');
$academicTitle = trim($_POST['academic_title'] ?? '');
$position = trim($_POST['position'] ?? '');
$birthDateRaw = trim($_POST['birth_date'] ?? '');
$birthDate = $birthDateRaw !== '' ? $birthDateRaw : null;
$classIdRaw = $_POST['class_id'] ?? '';
$classId = ($classIdRaw === '' || $classIdRaw === null) ? null : (int) $classIdRaw;

function usersHasSecondaryRoleColumnAdmin(): bool {
    $row = db_fetch_one("SELECT COUNT(*) as total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'secondary_role'");
    return ((int) ($row['total'] ?? 0)) > 0;
}


function normalizeEmail($email): string {
    return strtolower(trim((string) $email));
}

function isProtectedAdminUserById(int $userId): bool {
    if ($userId <= 0) {
        return false;
    }

    $row = db_fetch_one('SELECT email FROM users WHERE id = ? LIMIT 1', [$userId]);
    return normalizeEmail($row['email'] ?? '') === PROTECTED_ADMIN_EMAIL;
}

function isProtectedAdminEmail(string $email): bool {
    return normalizeEmail($email) === PROTECTED_ADMIN_EMAIL;
}

if (!in_array($action, ['save', 'reset_password', 'toggle_lock', 'delete_user'], true)) {
    redirect('../../views/admin/accounts.php');
}

if ($action === 'reset_password') {
    if ($id <= 0) {
        redirect('../../views/admin/accounts.php?account_error=missing_id');
    }

    try {
        $newHash = password_hash('123456@', PASSWORD_DEFAULT);
        db_query('UPDATE users SET password = ? WHERE id = ?', [$newHash, $id]);
        logSystem("Đặt lại mật khẩu tài khoản ID #$id", 'users', $id);
        redirect('../../views/admin/accounts.php?account_reset=1');
    } catch (Exception $e) {
        redirect('../../views/admin/accounts.php?account_error=reset_failed');
    }
}

if ($action === 'toggle_lock') {
    if ($id <= 0) {
        redirect('../../views/admin/accounts.php?account_error=missing_id');
    }

    if (isProtectedAdminUserById($id)) {
        redirect('../../views/admin/accounts.php?account_error=protected_account');
    }

    try {
        $user = db_fetch_one('SELECT is_active FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$user) {
            redirect('../../views/admin/accounts.php?account_error=user_not_found');
        }

        // is_active: 1 = hoạt động, 0 = bị khóa (ngược với is_locked)
        $next = !empty($user['is_active']) ? 0 : 1;
        db_query('UPDATE users SET is_active = ? WHERE id = ?', [$next, $id]);
        $lockAction = ($next === 0) ? "Khóa tài khoản ID #$id" : "Mở khóa tài khoản ID #$id";
        logSystem($lockAction, 'users', $id);
        redirect('../../views/admin/accounts.php?account_lock_changed=1');
    } catch (Exception $e) {
        redirect('../../views/admin/accounts.php?account_error=toggle_lock_failed');
    }
}

if ($action === 'delete_user') {
    if ($id <= 0) {
        redirect('../../views/admin/accounts.php?account_error=missing_id');
    }

    if (isProtectedAdminUserById($id)) {
        redirect('../../views/admin/accounts.php?account_error=protected_account');
    }

    if ((int) ($_SESSION['user_id'] ?? 0) === $id) {
        redirect('../../views/admin/accounts.php?account_error=cannot_delete_self');
    }

    $confirmPassword = (string) ($_POST['delete_confirm_password'] ?? '');
    if ($confirmPassword === '') {
        redirect('../../views/admin/accounts.php?account_error=invalid_password');
    }

    try {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        $currentUser = db_fetch_one('SELECT password FROM users WHERE id = ? LIMIT 1', [$currentUserId]);
        $storedPassword = (string) ($currentUser['password'] ?? '');

        $passwordInfo = password_get_info($storedPassword);
        $isHashedPassword = isset($passwordInfo['algo']) && $passwordInfo['algo'] !== 0;
        $isValidPassword = $isHashedPassword
            ? password_verify($confirmPassword, $storedPassword)
            : hash_equals($storedPassword, $confirmPassword);

        if (!$isValidPassword) {
            redirect('../../views/admin/accounts.php?account_error=invalid_password');
        }

        db_query('DELETE FROM users WHERE id = ?', [$id]);
        logSystem("Xóa tài khoản ID #$id", 'users', $id);
        redirect('../../views/admin/accounts.php?account_success=1');
    } catch (Exception $e) {
        redirect('../../views/admin/accounts.php?account_error=delete_failed');
    }
}

$validRoles = ['admin', 'staff', 'teacher', 'bcs', 'student'];
if ($username === '' || $fullName === '' || $email === '' || !in_array($role, $validRoles, true)) {
    redirect('../../views/admin/accounts.php?account_error=missing_data');
}

if ($secondaryRole !== '' && (!in_array($secondaryRole, $validRoles, true) || $secondaryRole === $role)) {
    redirect('../../views/admin/accounts.php?account_error=invalid_role');
}

if ($secondaryRole !== '') {
    // Admin không được kết hợp với các vai trò khác
    if ($role === 'admin' || $secondaryRole === 'admin') {
        redirect('../../views/admin/accounts.php?account_error=invalid_role');
    }
    $staffRoles = ['staff', 'teacher'];
    $studentRoles = ['bcs', 'student'];
    $isPrimaryStaff = in_array($role, $staffRoles, true);
    $isSecondaryStaff = in_array($secondaryRole, $staffRoles, true);
    $isPrimaryStudent = in_array($role, $studentRoles, true);
    $isSecondaryStudent = in_array($secondaryRole, $studentRoles, true);

    // Không cho trộn vai trò giữa 2 khối.
    if (!(($isPrimaryStaff && $isSecondaryStaff) || ($isPrimaryStudent && $isSecondaryStudent))) {
        redirect('../../views/admin/accounts.php?account_error=invalid_role');
    }
}

if (($role === 'student' || $role === 'bcs' || $secondaryRole === 'student' || $secondaryRole === 'bcs') && !$classId) {
    redirect('../../views/admin/accounts.php?account_error=missing_class');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('../../views/admin/accounts.php?account_error=invalid_email');
}

if ($birthDate !== null) {
    $dt = DateTime::createFromFormat('Y-m-d', $birthDate);
    $isValidBirthDate = $dt && $dt->format('Y-m-d') === $birthDate;
    if (!$isValidBirthDate) {
        redirect('../../views/admin/accounts.php?account_error=missing_data');
    }
}

$isProtectedById = $id > 0 && isProtectedAdminUserById($id);
$isProtectedByEmail = isProtectedAdminEmail($email);
$existingUser = $id > 0 ? db_fetch_one('SELECT role FROM users WHERE id = ? LIMIT 1', [$id]) : null;
$isExistingAdmin = strtolower((string) ($existingUser['role'] ?? '')) === 'admin';

// Tài khoản đã là admin thì không cho đổi sang vai trò khác qua cập nhật.
if ($isExistingAdmin) {
    $role = 'admin';
    $secondaryRole = '';
}

if ($isProtectedById || $isProtectedByEmail) {
    $email = PROTECTED_ADMIN_EMAIL;
    $role = 'admin';
    $secondaryRole = '';
}

    try {
    if ($id > 0) {
        $hasSecondaryRoleColumn = usersHasSecondaryRoleColumnAdmin();
        if ($hasSecondaryRoleColumn) {
            db_query(
                'UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, secondary_role = ?, academic_title = ?, position = ?, birth_date = ? WHERE id = ?',
                [$username, $fullName, $email, $role, $secondaryRole !== '' ? $secondaryRole : null, $academicTitle !== '' ? $academicTitle : null, $position !== '' ? $position : null, $birthDate, $id]
            );
        } else {
            db_query(
                'UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, academic_title = ?, position = ?, birth_date = ? WHERE id = ?',
                [$username, $fullName, $email, $role, $academicTitle !== '' ? $academicTitle : null, $position !== '' ? $position : null, $birthDate, $id]
            );
        }
        $userId = $id;
        logSystem("Cập nhật tài khoản ID #$id - $fullName (vai trò: $role)", 'users', $userId);
    } else {
        $defaultPasswordHash = password_hash('123456@', PASSWORD_DEFAULT);
        $hasSecondaryRoleColumn = usersHasSecondaryRoleColumnAdmin();
        if ($hasSecondaryRoleColumn) {
            db_query(
                'INSERT INTO users (username, password, full_name, email, role, secondary_role, academic_title, position, birth_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$username, $defaultPasswordHash, $fullName, $email, $role, $secondaryRole !== '' ? $secondaryRole : null, $academicTitle !== '' ? $academicTitle : null, $position !== '' ? $position : null, $birthDate]
            );
        } else {
            db_query(
                'INSERT INTO users (username, password, full_name, email, role, academic_title, position, birth_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$username, $defaultPasswordHash, $fullName, $email, $role, $academicTitle !== '' ? $academicTitle : null, $position !== '' ? $position : null, $birthDate]
            );
        }

        $created = db_fetch_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        $userId = (int) ($created['id'] ?? 0);
        logSystem("Tạo tài khoản mới - $fullName (vai trò: $role)", 'users', $userId);
    }

    if ($userId > 0) {
        // class_students chỉ áp dụng cho nhóm người học.
        db_query('DELETE FROM class_students WHERE student_id = ?', [$userId]);
        if (($role === 'student' || $role === 'bcs') && $classId) {
            db_query('INSERT INTO class_students (class_id, student_id) VALUES (?, ?)', [$classId, $userId]);
        }
    }

    redirect('../../views/admin/accounts.php?account_success=1');
} catch (Exception $e) {
    redirect('../../views/admin/accounts.php?account_error=1');
}
