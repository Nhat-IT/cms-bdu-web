<?php
/**
 * CMS BDU - Hồ Sơ Admin/Support Admin
 * Trang hồ sơ cá nhân của Admin và Support Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin và support_admin được phép truy cập
requireRole(['admin', 'support_admin']);

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();
$role = $currentUser['role'];

// Lấy thông tin đầy đủ từ database
$user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Lấy thông tin đăng nhập cuối từ system_logs
$lastLoginRecord = db_fetch_one(
    "SELECT created_at FROM system_logs 
     WHERE user_id = ? AND action = 'Đăng nhập thành công' 
     ORDER BY created_at DESC LIMIT 1",
    [$_SESSION['user_id']]
);

if ($lastLoginRecord && !empty($lastLoginRecord['created_at'])) {
    $lastLoginTimestamp = strtotime($lastLoginRecord['created_at']);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    
    if (date('Y-m-d', $lastLoginTimestamp) === date('Y-m-d', $today)) {
        $lastLogin = 'Hôm nay, lúc ' . date('H:i:s', $lastLoginTimestamp);
    } elseif (date('Y-m-d', $lastLoginTimestamp) === date('Y-m-d', $yesterday)) {
        $lastLogin = 'Hôm qua, lúc ' . date('H:i:s', $lastLoginTimestamp);
    } else {
        $lastLogin = formatDate($lastLoginRecord['created_at'], 'd/m/Y H:i:s');
    }
} else {
    $lastLogin = 'Chưa có thông tin';
}

// Xử lý cập nhật thông tin
$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($fullName) || empty($email)) {
            $flashMessage = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
            $flashType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flashMessage = 'Email không hợp lệ.';
            $flashType = 'danger';
        } else {
            // Cập nhật vào database
            db_query("UPDATE users SET full_name = ?, email = ? WHERE id = ?", [$fullName, $email, $_SESSION['user_id']]);

            // Cập nhật session
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;

            logSystem("Cập nhật hồ sơ cá nhân", 'users', $_SESSION['user_id']);

            $flashMessage = 'Cập nhật thông tin thành công!';
            $flashType = 'success';
            
            // Refresh user data
            $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        }
    } elseif ($_POST['action'] === 'change_password') {
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Debug: hiển thị user_id đang dùng
        error_log("DEBUG: change_password - session_user_id=" . ($_SESSION['user_id'] ?? 'NULL') . ", username=" . ($_SESSION['username'] ?? 'NULL'));
        
        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            $flashMessage = 'Vui lòng điền đầy đủ thông tin.';
            $flashType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $flashMessage = 'Mật khẩu xác nhận không khớp.';
            $flashType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $flashMessage = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
            $flashType = 'danger';
        } else {
            // Kiểm tra mật khẩu cũ (hỗ trợ cả hash và dữ liệu cũ plaintext)
            $passwordRow = db_fetch_one("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
            $storedPassword = (string) ($passwordRow['password'] ?? '');
            $passwordInfo = password_get_info($storedPassword);
            $isHashedPassword = isset($passwordInfo['algo']) && $passwordInfo['algo'] !== 0;
            $isValidOldPassword = $isHashedPassword ? password_verify($oldPassword, $storedPassword) : hash_equals($storedPassword, (string) $oldPassword);

            if (!$isValidOldPassword) {
                $flashMessage = 'Mật khẩu cũ không đúng.';
                $flashType = 'danger';
            } else {
                // Cập nhật mật khẩu mới
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Debug: log độ dài password hash
                error_log("DEBUG: New password hash length = " . strlen($newHashedPassword));
                
                $conn = getDBConnection();
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "si", $newHashedPassword, $_SESSION['user_id']);
                    $execResult = mysqli_stmt_execute($stmt);
                    $affected = mysqli_stmt_affected_rows($stmt);
                    mysqli_stmt_close($stmt);
                    
                    // Debug: hiển thị thông tin update
                    $debugInfo = "DEBUG: Updating user_id=" . ($_SESSION['user_id'] ?? 'NULL') . " (" . ($_SESSION['username'] ?? 'NULL') . ")";
                    error_log($debugInfo);
                    error_log("DEBUG: Password update result - exec=" . ($execResult ? 'true' : 'false') . ", affected=" . $affected);
                    
                    // Verify update
                    $verifyRow = db_fetch_one("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
                    error_log("DEBUG: Stored password hash length = " . strlen($verifyRow['password'] ?? ''));
                    
                    if ($execResult && $affected >= 0) {
                        logSystem("Đổi mật khẩu", 'users', $_SESSION['user_id']);
                        $flashMessage = 'Đổi mật khẩu thành công! Bạn sẽ được đăng xuất. (User ID: ' . ($_SESSION['user_id'] ?? 'NULL') . ')';
                        $flashType = 'success';
                        echo '<script>setTimeout(function(){ window.location.href = "../logout.php"; }, 2000);</script>';
                    } else {
                        $flashMessage = 'Có lỗi khi cập nhật mật khẩu.';
                        $flashType = 'danger';
                    }
                } else {
                    error_log("DEBUG: Failed to prepare statement - " . mysqli_error($conn));
                    $flashMessage = 'Có lỗi khi cập nhật mật khẩu: ' . mysqli_error($conn);
                    $flashType = 'danger';
                }
            }
        }
    }
}

$avatarUrl = getAvatarUrl($user['avatar'] ?? null, $user['full_name'] ?? 'Admin', 200);
$headerAvatarUrl = getAvatarUrl($user['avatar'] ?? null, $user['full_name'] ?? 'Admin', 32);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - <?php echo $role === 'support_admin' ? 'Hồ Sơ Hỗ Trợ Quản Trị' : 'Hồ Sơ Quản Trị Viên'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-profile.css">
</head>
<body class="dashboard-body">

<?php
$activePage = '';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = $role === 'support_admin' ? 'HỒ SƠ HỖ TRỢ QUẢN TRỊ' : 'HỒ SƠ QUẢN TRỊ VIÊN';
$pageIcon   = 'bi-person-badge-fill';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        
        <?php if ($flashMessage): ?>
        <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show" role="alert">
            <?php echo e($flashMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            
            <!-- Cột trái - Thông tin cá nhân -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="profile-header-bg"></div>
                    <div class="card-body text-center pt-0">
                        
                        <div class="avatar-wrapper mb-3">
                            <img src="<?php echo e($avatarUrl); ?>" id="mainProfileAvatar" class="profile-avatar" alt="Admin Avatar">
                            <label for="avatarUploadInput" class="avatar-edit-btn" title="Thay đổi ảnh đại diện">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/jpg">
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-1"><?php echo e($user['full_name'] ?? 'Admin'); ?></h5>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-shield-fill-check text-danger me-1"></i>
                            <?php echo $role === 'support_admin' ? 'Hỗ trợ Quản trị Hệ thống' : 'Quản trị viên Hệ thống'; ?>
                        </p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <?php if ($role === 'admin'): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Toàn quyền (Super Admin)</span>
                            <?php else: ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Hỗ trợ (Support Admin)</span>
                            <?php endif; ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Đang hoạt động</span>
                        </div>

                        <hr class="text-muted border-opacity-25">

                        <div class="text-start mt-3">
                            <p class="mb-2 text-muted small fw-bold">THÔNG TIN TÀI KHOẢN</p>
                            <div class="mb-3">
                                <small class="text-muted d-block">Mã định danh (Username):</small>
                                <span class="fw-bold text-dark"><?php echo e($user['username']); ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Ngày khởi tạo:</small>
                                <span class="fw-bold text-dark"><?php echo formatDate($user['created_at']); ?></span>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted d-block">Lần đăng nhập cuối:</small>
                                <span class="fw-bold text-success"><?php echo e($lastLogin); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột phải - Form cập nhật -->
            <div class="col-lg-8">
                
                <!-- Form Cập nhật thông tin liên hệ -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-envelope-at-fill text-primary me-2"></i>Thông tin Liên hệ Hệ thống</h5>
                        <p class="text-muted small mt-1 mb-0">Các cảnh báo bảo mật, báo cáo xuất file từ hệ thống sẽ được gửi về Email này.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" onsubmit="return handleUpdateProfile(event)">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Tên hiển thị <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control border-secondary" value="<?php echo e($user['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Email nhận cảnh báo <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control border-primary" value="<?php echo e($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i>LƯU THAY ĐỔI</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Form Đổi mật khẩu -->
                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Đổi Mật Khẩu <?php echo $role === 'support_admin' ? 'Hỗ Trợ' : 'Quản Trị'; ?></h5>
                        <p class="text-muted small mt-1 mb-0">Vui lòng sử dụng mật khẩu mạnh (chứa chữ hoa, chữ thường, số và ký tự đặc biệt).</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" onsubmit="return handleChangePassword(event)">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <input type="password" name="old_password" class="form-control border-secondary" placeholder="Nhập mật khẩu cũ..." required id="oldPassword">
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" name="new_password" class="form-control border-danger" placeholder="Nhập mật khẩu mới..." required id="newPassword" minlength="6">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control border-danger" placeholder="Nhập lại mật khẩu mới..." required id="confirmPassword">
                                    <div class="invalid-feedback">Mật khẩu xác nhận không khớp!</div>
                                </div>
                            </div>
                            <div class="text-end border-top pt-3">
                                <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm"><i class="bi bi-key-fill me-1"></i>CẬP NHẬT MẬT KHẨU</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // Thu gọn sidebar mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Upload Avatar Preview
    document.getElementById('avatarUploadInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            if(file.size > 2 * 1024 * 1024) {
                alert('Dung lượng ảnh vượt quá 2MB. Vui lòng chọn ảnh khác nhỏ hơn!');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const imgUrl = e.target.result;
                document.getElementById('mainProfileAvatar').src = imgUrl;
                document.getElementById('headerAvatar').src = imgUrl;
                
                setTimeout(() => {
                    alert('Đã cập nhật ảnh đại diện thành công! (Trong thực tế sẽ tải lên server)');
                }, 300);
            }
            reader.readAsDataURL(file);
        }
    });

    function handleUpdateProfile(e) {
        return true; // Cho phép submit form
    }

    function handleChangePassword(e) {
        e.preventDefault();
        
        const newPw = document.getElementById('newPassword');
        const confirmPw = document.getElementById('confirmPassword');
        
        if (newPw.value !== confirmPw.value) {
            confirmPw.classList.add('is-invalid');
            return false;
        } else {
            confirmPw.classList.remove('is-invalid');
            if(newPw.value.length < 6) {
                alert('Mật khẩu mới phải có ít nhất 6 ký tự!');
                return false;
            }
            if(confirm('Bạn có chắc chắn muốn đổi Mật khẩu Quản trị hệ thống không? Bạn sẽ phải đăng nhập lại sau khi đổi.')) {
                e.target.submit();
            }
        }
        return false;
    }

    document.getElementById('confirmPassword').addEventListener('input', function() {
        this.classList.remove('is-invalid');
    });
</script>
</body>
</html>
