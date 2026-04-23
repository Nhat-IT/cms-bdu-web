<?php
/**
 * CMS BDU - Quên mật khẩu
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isLoggedIn()) {
    header('Location: ' . getHomeUrl($_SESSION['role']));
    exit;
}

$error = '';
$success = '';
$step = 1; // 1: nhập email, 2: nhập OTP

// Xử lý gửi OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($email)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!preg_match('/@bdu\.edu\.vn$|@student\.bdu\.edu\.vn$/', $email)) {
        $error = 'Vui lòng sử dụng Email do nhà trường cấp.';
    } else {
        try {
            // Kiểm tra tài khoản tồn tại
            $user = db_fetch_one("SELECT id, full_name FROM users WHERE username = ? AND email = ?", [$username, $email]);
            
            if ($user) {
                // Tạo OTP
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Lưu OTP vào database (sử dụng bảng password_resets)
                db_query("DELETE FROM password_resets WHERE email = ?", [$email]);
                db_query("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)", [$email, password_hash($otp, PASSWORD_DEFAULT), $expires]);
                
                // TODO: Gửi email thực tế với OTP
                // Hiện tại hiển thị OTP để test
                $success = 'Mã OTP đã được gửi đến email của bạn!';
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp; // Chỉ dùng để test, xóa khi deploy
                $step = 2;
            } else {
                $error = 'Không tìm thấy tài khoản với thông tin này.';
            }
        } catch (Exception $e) {
            $error = 'Đã xảy ra lỗi. Vui lòng thử lại.';
        }
    }
}

// Xử lý đặt lại mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $otp = trim($_POST['otp'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($otp) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
        $step = 2;
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
        $step = 2;
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
        $step = 2;
    } else {
        try {
            // Kiểm tra OTP (sử dụng bcrypt để verify)
            $reset = db_fetch_one("SELECT * FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1", [$email]);
            
            if ($reset && password_verify($otp, $reset['token'])) {
                // Cập nhật mật khẩu
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                db_query("UPDATE users SET password = ? WHERE email = ?", [$hashedPassword, $email]);
                
                // Xóa token đã sử dụng
                db_query("DELETE FROM password_resets WHERE email = ?", [$email]);
                
                // Xóa session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                
                $success = 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.';
                $step = 1;
            } else {
                $error = 'Mã OTP không hợp lệ hoặc đã hết hạn.';
                $step = 2;
            }
        } catch (Exception $e) {
            $error = 'Đã xảy ra lỗi. Vui lòng thử lại.';
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi Phục Mật Khẩu - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
    <style>
        body.login-body { 
            background-color: #f1f5f9; 
            display: flex; 
            align-items: center; 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        .login-card { max-width: 900px; border-radius: 1rem; overflow: hidden; border: none; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .login-image { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; padding: 3rem; }
        .login-form { padding: 3rem; }
        .btn-login { padding: 0.8rem; font-weight: 600; letter-spacing: 0.5px; }
        .form-floating > label { color: #6c757d; }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
        .letter-spacing-2 { letter-spacing: 0.2em; }
    </style>
</head>
<body class="login-body">

<div class="container px-4">
    <div class="card login-card mx-auto">
        <div class="row g-0">
            
            <div class="col-md-5 login-image d-none d-md-flex flex-column justify-content-center align-items-center text-center">
                <i class="bi bi-shield-lock-fill" style="font-size: 5rem; margin-bottom: 20px;"></i>
                <h2 class="fw-bold">CMS BDU</h2>
                <p class="mt-3 px-3 text-center">Bảo mật tài khoản là ưu tiên hàng đầu. Vui lòng sử dụng email do nhà trường cấp để khôi phục.</p>
            </div>

            <div class="col-md-7">
                <div class="login-form position-relative">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <!-- Bước 1: Nhập thông tin -->
                        <div id="step1-request-otp">
                            <h3 class="mb-2 fw-bold text-dark">Khôi phục mật khẩu</h3>
                            <p class="text-muted mb-4 small">Nhập Mã định danh và Email do nhà trường cấp để nhận mã OTP khôi phục mật khẩu.</p>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="send_otp">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control fw-bold" id="userId" name="username" placeholder="Mã GV / MSSV" required value="<?= e($_POST['username'] ?? '') ?>">
                                    <label for="userId"><i class="bi bi-person-badge me-2 text-muted"></i>Tên đăng nhập</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="email" class="form-control" id="userEmail" name="email" placeholder="Email trường cấp" required value="<?= e($_POST['email'] ?? '') ?>">
                                    <label for="userEmail"><i class="bi bi-envelope-fill me-2 text-muted"></i>Email (@bdu.edu.vn)</label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-login w-100 text-uppercase rounded-pill" id="btnSendOtp">
                                    Nhận Mã Xác Nhận
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Bước 2: Nhập OTP và mật khẩu mới -->
                        <div id="step2-reset-password">
                            <div class="d-flex align-items-center mb-2">
                                <a href="<?php echo BASE_URL; ?>/forgot-password.php" class="btn btn-link text-decoration-none p-0 me-2 text-muted" title="Quay lại">
                                    <i class="bi bi-arrow-left fs-4"></i>
                                </a>
                                <h3 class="fw-bold text-dark mb-0">Đặt lại mật khẩu</h3>
                            </div>
                            <p class="text-muted mb-4 small">Một mã OTP gồm 6 chữ số đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư.</p>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reset_password">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control text-center fs-4 fw-bold letter-spacing-2" id="otpCode" name="otp" placeholder="Nhập mã OTP" maxlength="6" pattern="[0-9]{6}" required>
                                    <label for="otpCode" class="text-center w-100"><i class="bi bi-key-fill me-2 text-muted"></i>Nhập mã OTP 6 số</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Mật khẩu mới" required>
                                    <label for="newPassword"><i class="bi bi-lock-fill me-2 text-muted"></i>Mật khẩu mới</label>
                                </div>

                                <div class="form-floating mb-4">
                                    <input type="password" class="form-control" id="confirmNewPassword" name="confirm_password" placeholder="Xác nhận mật khẩu" required>
                                    <label for="confirmNewPassword"><i class="bi bi-check-circle-fill me-2 text-muted"></i>Xác nhận mật khẩu mới</label>
                                    <div class="invalid-feedback">Mật khẩu xác nhận không khớp!</div>
                                </div>

                                <button type="submit" class="btn btn-success btn-login w-100 text-uppercase rounded-pill">
                                    Thay Đổi Mật Khẩu
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-10">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="text-decoration-none text-muted fw-bold">
                            <i class="bi bi-box-arrow-in-left me-1"></i> Quay lại trang Đăng nhập
                        </a>
                    </div>

                </div>
            </div>            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('confirmNewPassword')?.addEventListener('input', function() {
    this.classList.remove('is-invalid');
});
</script>
</body>
</html>
