<?php
/**
 * CMS BDU - Hồ Sơ Sinh Viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Hồ Sơ Cá Nhân';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/profile.css'];
$extraJs = ['student/student-layout.js', 'student/profile.js'];

$user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$userId]);
$classInfo = db_fetch_one("
    SELECT c.class_name, d.department_name, c.academic_year
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE cs.student_id = ?
    LIMIT 1", [$userId]);

$unreadNotifications = (int)(db_fetch_one(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
)['total'] ?? 0);

$avatarUrl = getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? '', 200);

$mssv = $user['username'] ?? '';
if (strpos($mssv, '@') !== false) {
    $mssv = explode('@', $mssv)[0];
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
            <h5 class="m-0 text-white fw-bold">HỒ SƠ CÁ NHÂN</h5>
        </div>
        <div class="d-flex align-items-center text-white">
            <?php include_once __DIR__ . '/../../layouts/notification-bell.php'; ?>
        </div>
    </div>

    <div class="p-4">
        <div class="row g-4">

            <!-- Cột trái: avatar + thông tin học vụ -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="profile-header-bg"></div>
                    <div class="card-body text-center pt-0">

                        <div class="avatar-wrapper mb-3">
                            <img src="<?= e($avatarUrl) ?>" id="mainProfileAvatar" class="profile-avatar" alt="Student Avatar">
                            <label for="avatarUploadInput" class="avatar-edit-btn" title="Thay đổi ảnh đại diện">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/jpg, image/webp">
                        </div>
                        <div id="avatarUploadMsg" class="small mb-2"></div>

                        <h5 class="fw-bold text-dark mb-1" id="profileDisplayName"><?= e($user['full_name'] ?? '') ?></h5>
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
                                <span class="fw-bold text-dark" id="profileMssv"><?= e($mssv) ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Chuyên ngành:</small>
                                <span class="fw-bold text-dark" id="profileMajor"><?= e($classInfo['department_name'] ?? '--') ?></span>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted d-block">Niên khóa:</small>
                                <span class="fw-bold text-dark" id="profileCohort"><?= e($classInfo['academic_year'] ?? '--') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột phải: form -->
            <div class="col-lg-8">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-person-lines-fill text-info me-2"></i>Thông tin Liên hệ</h5>
                        <p class="text-muted small mt-1 mb-0">Họ tên và Email do Giáo vụ Khoa cấp, không tự thay đổi được.</p>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" onsubmit="return handleUpdateProfile(event)">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Họ và tên đầy đủ</label>
                                    <input type="text" id="profileFullName" class="form-control border-secondary bg-light" value="<?= e($user['full_name'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Ngày sinh</label>
                                    <input type="date" id="profileBirthDate" class="form-control border-secondary" value="<?= e($user['birth_date'] ? substr($user['birth_date'], 0, 10) : '') ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email trường cấp</label>
                                    <input type="email" id="profileEmail" class="form-control border-secondary bg-light" value="<?= e($user['email'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Số điện thoại cá nhân</label>
                                    <input type="tel" id="profilePhoneNumber" class="form-control border-secondary" value="<?= e($user['phone_number'] ?? '') ?>" placeholder="Dùng để GV/BCS liên lạc...">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Địa chỉ hiện tại</label>
                                <input type="text" id="profileAddress" class="form-control border-secondary" value="<?= e($user['address'] ?? '') ?>" placeholder="Nhập địa chỉ tạm trú/thường trú...">
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
                        <p class="text-muted small mt-1 mb-0">Vui lòng thay đổi mật khẩu định kỳ để bảo vệ tài khoản học tập.</p>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm" onsubmit="return handleChangePassword(event)">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control border-secondary" id="oldPassword" placeholder="Nhập mật khẩu cũ..." required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="oldPassword"><i class="bi bi-eye-fill text-muted"></i></button>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control border-danger" id="newPassword" placeholder="Nhập mật khẩu mới..." required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="newPassword"><i class="bi bi-eye-fill text-muted"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control border-danger" id="confirmPassword" placeholder="Nhập lại mật khẩu mới..." required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmPassword"><i class="bi bi-eye-fill text-muted"></i></button>
                                    </div>
                                    <div class="invalid-feedback d-block" id="confirmPasswordError" style="display:none!important"></div>
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
</body>
</html>
