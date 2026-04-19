<?php
/**
 * CMS BDU - Hồ Sơ Sinh Viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Hồ Sơ Cá Nhân';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/profile.css'];
$extraJs = ['student/student-layout.js', 'student/profile.js'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Lấy thông tin lớp học
$stmt = $pdo->prepare("
    SELECT c.class_name, d.department_name, c.academic_year
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE cs.student_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$classInfo = $stmt->fetch();

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy số thông báo chưa đọc
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetch()['total'];

// Xử lý cập nhật thông tin
$updateSuccess = false;
$updateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $birthDate = $_POST['birth_date'] ?? null;
        $phone = $_POST['phone_number'] ?? null;
        $address = $_POST['address'] ?? null;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET birth_date = ?, phone_number = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([$birthDate, $phone, $address, $userId]);
            $updateSuccess = true;
            
            // Cập nhật lại thông tin user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $updateError = 'Đã xảy ra lỗi khi cập nhật thông tin.';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            $updateError = 'Vui lòng điền đầy đủ thông tin.';
        } elseif ($newPassword !== $confirmPassword) {
            $updateError = 'Mật khẩu xác nhận không khớp.';
        } elseif (!password_verify($oldPassword, $user['password'])) {
            $updateError = 'Mật khẩu hiện tại không đúng.';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $updateSuccess = true;
            } catch (PDOException $e) {
                $updateError = 'Đã xảy ra lỗi khi đổi mật khẩu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="../../public/css/<?= e($css) ?>">
    <?php endforeach; ?>
</head>
<body class="dashboard-body">

<?php include_once __DIR__ . '/../../layouts/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 shadow-sm">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light me-3 border-0" id="sidebarToggle"><i class="bi bi-list fs-3"></i></button>
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">
                HỒ SƠ CÁ NHÂN
            </h5>
        </div>
        
        <div class="d-flex align-items-center text-white">
            <a href="notifications-all.php" class="text-white text-decoration-none" title="Xem thông báo">
                <i class="bi bi-bell fs-5 text-white position-relative cursor-pointer">
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </i>
            </a>
        </div>
    </div>

    <div class="p-4">
        
        <?php if ($updateSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Cập nhật thông tin thành công!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($updateError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= e($updateError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="profile-header-bg"></div>
                    <div class="card-body text-center pt-0">
                        
                        <div class="avatar-wrapper mb-3">
                            <img src="<?= getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? '', 200) ?>" id="mainProfileAvatar" class="profile-avatar" alt="Student Avatar">
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-1"><?= e($user['full_name'] ?? '') ?></h5>
                        <p class="text-muted small mb-3"><i class="bi bi-mortarboard-fill text-info me-1"></i>Sinh viên Chính quy</p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><?= e($classInfo['class_name'] ?? 'Chưa có lớp') ?></span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Đang học</span>
                        </div>

                        <hr class="text-muted border-opacity-25">

                        <div class="text-start mt-3">
                            <p class="mb-2 text-muted small fw-bold">THÔNG TIN HỌC VỤ</p>
                            <div class="mb-3">
                                <small class="text-muted d-block">Mã số sinh viên:</small>
                                <span class="fw-bold text-dark"><?= e($user['username'] ?? '') ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Chuyên ngành:</small>
                                <span class="fw-bold text-dark"><?= e($classInfo['department_name'] ?? 'Công nghệ thông tin') ?></span>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted d-block">Niên khóa:</small>
                                <span class="fw-bold text-dark"><?= e($classInfo['academic_year'] ?? '2022 - 2026') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-person-lines-fill text-info me-2"></i>Thông tin Liên hệ</h5>
                        <p class="text-muted small mt-1 mb-0">Hệ thống không cho phép tự ý đổi Họ tên và Email cấp kèm. Liên hệ Giáo vụ Khoa nếu có sai sót.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Họ và tên đầy đủ</label>
                                    <input type="text" class="form-control border-secondary bg-light" value="<?= e($user['full_name'] ?? '') ?>" readonly title="Không thể tự thay đổi">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Ngày sinh</label>
                                    <input type="date" class="form-control border-secondary" name="birth_date" value="<?= e($user['birth_date'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email trường cấp</label>
                                    <input type="email" class="form-control border-secondary bg-light" value="<?= e($user['email'] ?? '') ?>" readonly title="Không thể tự thay đổi">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Số điện thoại cá nhân</label>
                                    <input type="tel" class="form-control border-secondary" name="phone_number" value="<?= e($user['phone_number'] ?? '') ?>" placeholder="Dùng để GV/BCS liên lạc...">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Địa chỉ hiện tại</label>
                                <input type="text" class="form-control border-secondary" name="address" value="<?= e($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ tạm trú/thường trú...">
                            </div>
                            <div class="text-end border-top pt-3">
                                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i>LƯU THAY ĐỔI</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Đổi Mật Khẩu</h5>
                        <p class="text-muted small mt-1 mb-0">Vui lòng thay đổi mật khẩu định kỳ để bảo vệ tài khoản học tập của bạn.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" onsubmit="return validatePasswordChange(event)">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <input type="password" class="form-control border-secondary" placeholder="Nhập mật khẩu cũ..." required id="oldPassword" name="old_password">
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control border-danger" placeholder="Nhập mật khẩu mới..." required id="newPassword" name="new_password">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control border-danger" placeholder="Nhập lại mật khẩu mới..." required id="confirmPassword" name="confirm_password">
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
<?php foreach ($extraJs as $js): ?>
    <script src="../../public/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
<script>
function validatePasswordChange(e) {
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    
    if (newPass !== confirmPass) {
        document.getElementById('confirmPassword').classList.add('is-invalid');
        e.preventDefault();
        return false;
    }
    
    if (newPass.length < 6) {
        alert('Mật khẩu mới phải có ít nhất 6 ký tự.');
        e.preventDefault();
        return false;
    }
    
    return true;
}
</script>
</body>
</html>
