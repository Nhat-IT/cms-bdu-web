<?php
/**
 * CMS BDU - Teacher Profile
 * Trang hồ sơ giảng viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('teacher');

// Lấy thông tin giảng viên
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Lấy thông tin chi tiết từ bảng teachers nếu có
$stmtTeacher = $pdo->prepare("
    SELECT t.*, u.*
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.id = ?
");
$stmtTeacher->execute([$userId]);
$teacherInfo = $stmtTeacher->fetch();

// Đếm minh chứng chờ duyệt
$stmtCountEvidence = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM attendance_evidences ae
    INNER JOIN attendance_records ar ON ae.attendance_record_id = ar.id
    INNER JOIN attendance_sessions ass ON ar.session_id = ass.id
    INNER JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
    INNER JOIN class_subjects cs ON csg.class_subject_id = cs.id
    WHERE cs.teacher_id = ? AND ae.status = 'Pending'
");
$stmtCountEvidence->execute([$teacherInfo['teacher_id'] ?? $userId]);
$pendingEvidences = $stmtCountEvidence->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Hồ Sơ Giảng Viên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-profile.css">
</head>
<body class="dashboard-body">

    <div class="sidebar" id="sidebar">
        <div>
            <div class="brand-container flex-shrink-0">
                <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                    <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                    <span class="fs-4 fw-bold hide-on-collapse">CMS BDU</span>
                </a>
            </div>
            <div class="sidebar-scrollable w-100">
            <nav class="nav flex-column ps-2 pe-2">
                <a href="home.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-speedometer2 me-2"></i> Tổng quan</a>
                <a href="attendance.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-calendar-check me-2"></i> Lịch & Điểm danh</a>
                <a href="class-assignments.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-journal-text me-2"></i> Quản lý Bài tập</a>
                <a href="class-grades.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i> Bảng điểm</a>
                <a href="approve-evidences.php" class="nav-link text-white-50 hover-white py-2 mb-1 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical me-2"></i> Duyệt minh chứng</span>
                    <?php if ($pendingEvidences > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo e($pendingEvidences); ?></span>
                    <?php endif; ?>
                </a>
                <a href="documents.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-folder2-open me-2"></i> Kho tài liệu</a>
                <a href="announcements.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-megaphone-fill me-2"></i> Đăng bảng tin</a>
            </nav>
            </div>
        </div>
        
        <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
            <a href="../logout.php" class="nav-link logout-btn" title="Đăng xuất">
                <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3 shadow-sm mb-4">
            <h4 class="m-0 fw-bold d-flex align-items-center text-white">
                HỒ SƠ GIẢNG VIÊN
            </h4>
            <div class="d-flex align-items-center text-white">
                <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
                    <div class="fs-6">Giảng viên: <b class="text-info"><?php echo e($user['full_name'] ?? 'GV'); ?></b></div>
                </div>
                
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown">
                        <img src="<?php echo e(getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? 'GV', 40)); ?>" id="headerAvatar" alt="Avatar" class="rounded-circle border border-white" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                        <li><a class="dropdown-item fw-bold" href="teacher-profile.php"><i class="bi bi-person-vcard text-info me-2"></i>Hồ sơ cá nhân</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="../logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="p-4 pt-0">
            
            <div class="row g-4">
                
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="profile-header-bg"></div>
                        <div class="card-body text-center pt-0">
                            
                            <div class="avatar-wrapper mb-3">
                                <img src="<?php echo e(getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? 'GV', 200)); ?>" id="mainProfileAvatar" class="profile-avatar" alt="Teacher Avatar">
                                <label for="avatarUploadInput" class="avatar-edit-btn" title="Thay đổi ảnh đại diện">
                                    <i class="bi bi-camera-fill"></i>
                                </label>
                                <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/jpg">
                            </div>
                            
                            <h5 class="fw-bold text-dark mb-1"><?php echo e($user['full_name'] ?? 'Chưa cập nhật'); ?></h5>
                            <p class="text-muted small mb-3"><i class="bi bi-briefcase-fill text-info me-1"></i>Giảng viên Cơ hữu</p>
                            
                            <div class="d-flex justify-content-center gap-2 mb-4">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Khoa CNTT</span>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success">Đang giảng dạy</span>
                            </div>

                            <hr class="text-muted border-opacity-25">

                            <div class="text-start mt-3">
                                <p class="mb-2 text-muted small fw-bold">THÔNG TIN TÀI KHOẢN</p>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Mã Giảng viên (Username):</small>
                                    <span class="fw-bold text-dark"><?php echo e($user['username'] ?? 'GV'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Email:</small>
                                    <span class="fw-bold text-dark"><?php echo e($user['email'] ?? 'Chưa cập nhật'); ?></span>
                                </div>
                                <div class="mb-0">
                                    <small class="text-muted d-block">Lần đăng nhập cuối:</small>
                                    <span class="fw-bold text-success"><?php echo date('H:i:s d/m/Y'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white pt-4 pb-2 border-0">
                            <h5 class="fw-bold text-dark m-0"><i class="bi bi-person-lines-fill text-info me-2"></i>Thông tin Liên hệ & Chuyên môn</h5>
                            <p class="text-muted small mt-1 mb-0">Hệ thống sẽ dùng thông tin này để hiển thị trên danh sách Lớp học phần cho sinh viên.</p>
                        </div>
                        <div class="card-body">
                            <form id="profileForm" onsubmit="return handleUpdateProfile(event)">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control border-secondary" value="<?php echo e($user['full_name'] ?? ''); ?>" required id="fullNameInput" name="full_name">
                                    </div>
                                    <div class="col-md-6 mt-3 mt-md-0">
                                        <label class="form-label fw-bold">Email trường cấp <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control border-secondary" value="<?php echo e($user['email'] ?? ''); ?>" required id="emailInput" name="email">
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Số điện thoại liên hệ (Zalo/Call)</label>
                                        <input type="tel" class="form-control border-secondary" value="<?php echo e($teacherInfo['phone_number'] ?? ''); ?>" placeholder="Dùng để BCS liên lạc khi cần..." id="phoneInput" name="phone">
                                    </div>
                                    <div class="col-md-6 mt-3 mt-md-0">
                                        <label class="form-label fw-bold">Địa chỉ</label>
                                        <input type="text" class="form-control border-secondary" value="<?php echo e($teacherInfo['address'] ?? ''); ?>" placeholder="Địa chỉ liên hệ..." id="addressInput" name="address">
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i>LƯU THAY ĐỔI</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 border-start border-4 border-danger">
                        <div class="card-header bg-white pt-4 pb-2 border-0">
                            <h5 class="fw-bold text-dark m-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Đổi Mật Khẩu</h5>
                            <p class="text-muted small mt-1 mb-0">Vui lòng thay đổi mật khẩu định kỳ để bảo vệ dữ liệu điểm số của sinh viên.</p>
                        </div>
                        <div class="card-body">
                            <form id="passwordForm" onsubmit="return handleChangePassword(event)">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-muted">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control border-secondary" placeholder="Nhập mật khẩu cũ..." required id="oldPassword">
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control border-danger" placeholder="Nhập mật khẩu mới..." required id="newPassword">
                                    </div>
                                    <div class="col-md-6 mt-3 mt-md-0">
                                        <label class="form-label fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control border-danger" placeholder="Nhập lại mật khẩu mới..." required id="confirmPassword">
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
    <script src="../../public/js/teacher/teacher-layout.js"></script>

    
    <script src="../../public/js/teacher/teacher-profile.js"></script>
    <script>
        function handleUpdateProfile(event) {
            event.preventDefault();
            
            const form = document.getElementById('profileForm');
            const formData = new FormData(form);
            
            fetch('api/profile.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Cập nhật hồ sơ thành công!');
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .catch(() => {
                alert('Có lỗi xảy ra');
            });
            
            return false;
        }
        
        function handleChangePassword(event) {
            event.preventDefault();
            
            const oldPassword = document.getElementById('oldPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                document.getElementById('confirmPassword').classList.add('is-invalid');
                return false;
            }
            
            document.getElementById('confirmPassword').classList.remove('is-invalid');
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('old_password', oldPassword);
            formData.append('new_password', newPassword);
            
            fetch('api/profile.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Đổi mật khẩu thành công!');
                    document.getElementById('passwordForm').reset();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .catch(() => {
                alert('Có lỗi xảy ra');
            });
            
            return false;
        }
    </script>
</body>
</html>
