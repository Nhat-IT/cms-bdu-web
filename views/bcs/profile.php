<?php
/**
 * CMS BDU - Hồ sơ Ban Cán Sự (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Hồ Sơ Ban Cán Sự';

// Lấy class_id của BCS từ class_students
$stmt = $pdo->prepare("
    SELECT cs.class_id, c.class_name 
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.student_id = ?
");
$stmt->execute([$userId]);
$classInfo = $stmt->fetch();
$classId = $classInfo['class_id'] ?? null;
$className = $classInfo['class_name'] ?? '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$fullName = $currentUser['full_name'] ?? '';
$email = $currentUser['email'] ?? '';
$position = $currentUser['position'] ?? 'Ban Cán Sự';
$avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 200);
$birthDate = $currentUser['birth_date'] ?? '';
$phone = $currentUser['phone_number'] ?? '';
$address = $currentUser['address'] ?? '';

// Lấy username từ email
$username = explode('@', $email)[0];

// Đếm notification
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['total'] ?? 0;
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
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/bcs/bcs-layout.css">
    <link rel="stylesheet" href="../../public/css/bcs/profile.css">
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

        <div class="bcs-profile-container text-center flex-shrink-0">
            <a href="profile.php" class="bcs-profile-trigger" title="Xem hồ sơ ban cán sự">
                <img src="<?= e($avatar) ?>" id="sidebarAvatar" class="rounded-circle shadow-sm mb-2 border border-2 border-primary" width="55" alt="Avatar BCS">
                <div class="hide-on-collapse">
                    <div class="text-white fw-bold fs-6"><?= e($fullName) ?></div>
                    <div class="text-white-50 small mb-1" style="font-size: 0.8rem;">Vai trò: <span id="sidebarRoleText"><?= e($position) ?></span></div>
                </div>
            </a>
            <span class="badge bcs-class-badge mt-1 hide-on-collapse">LỚP: <?= e($className) ?></span>
        </div>

        <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <a href="home.php"><i class="bi bi-grid-1x2-fill"></i> Bảng điều khiển</a>
            <a href="attendance.php"><i class="bi bi-calendar-check"></i> Quản lý Chuyên cần</a>
            <a href="documents.php"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>
            <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CÁ NHÂN</div>
            <a href="../student/home.php" class="text-warning"><i class="bi bi-arrow-repeat"></i> Về Cổng Sinh Viên</a>
        </nav>
        </div>
    </div>

    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../../views/logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<div class="main-content" id="mainContent" style="padding: 0; background-color: #f4f6f9; min-height: 100vh;">
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">HỒ SƠ BAN CÁN SỰ</h5>
        </div>
        <div class="bcs-header-meta d-flex align-items-center text-white">
            <span class="bcs-header-label fw-bold">BAN CÁN SỰ</span>
            <a href="feedback.php" class="bcs-notification-link" title="Có <?= $unreadCount ?> thông báo hệ thống">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="bcs-notification-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="profile-header-bg"></div>
                    <div class="card-body text-center pt-0">
                        <div class="avatar-wrapper mb-3">
                            <img src="<?= e($avatar) ?>" id="mainProfileAvatar" class="profile-avatar" alt="Avatar BCS">
                            <label for="avatarUploadInput" class="avatar-edit-btn" title="Thay đổi ảnh đại diện">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/jpg">
                        </div>

                        <h5 class="fw-bold text-dark mb-1"><?= e($fullName) ?></h5>
                        <p class="text-muted small mb-3"><i class="bi bi-mortarboard-fill text-info me-1"></i><span id="profileRoleText"><?= e($position) ?></span> - BCS</p>

                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><?= e($className) ?></span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Đang hoạt động</span>
                        </div>

                        <hr class="text-muted border-opacity-25">

                        <div class="text-start mt-3">
                            <p class="mb-2 text-muted small fw-bold">THÔNG TIN HỌC VỤ</p>
                            <div class="mb-3">
                                <small class="text-muted d-block">Mã số sinh viên:</small>
                                <span class="fw-bold text-dark"><?= e($username) ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Lớp:</small>
                                <span class="fw-bold text-dark"><?= e($className) ?></span>
                            </div>
                            <div class="mb-0">
                                <small class="text-muted d-block">Niên khóa:</small>
                                <span class="fw-bold text-dark">2022 - 2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-person-lines-fill text-info me-2"></i>Thông tin liên hệ</h5>
                        <p class="text-muted small mt-1 mb-0">Cập nhật thông tin liên hệ và chức vụ BCS tại đây.</p>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" onsubmit="return handleUpdateProfile(event)">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Họ và tên đầy đủ</label>
                                    <input type="text" class="form-control border-secondary bg-light" value="<?= e($fullName) ?>" readonly title="Không thể tự thay đổi">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Ngày sinh <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control border-secondary" name="birth_date" value="<?= e($birthDate) ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email trường cấp</label>
                                    <input type="email" class="form-control border-secondary bg-light" value="<?= e($email) ?>" readonly title="Không thể tự thay đổi">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Số điện thoại cá nhân <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control border-secondary" name="phone_number" value="<?= e($phone) ?>" required placeholder="Dùng để liên lạc">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">MSSV</label>
                                    <input type="text" class="form-control border-secondary bg-light" value="<?= e($username) ?>" readonly>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold">Chức vụ BCS <span class="text-danger">*</span></label>
                                    <input type="text" id="roleInput" name="position" class="form-control border-secondary" list="roleOptions" value="<?= e($position) ?>" required placeholder="Nhập chức vụ">
                                    <datalist id="roleOptions">
                                        <option value="Lớp trưởng"></option>
                                        <option value="Lớp phó"></option>
                                        <option value="Thư ký"></option>
                                        <option value="BTVH"></option>
                                    </datalist>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Địa chỉ hiện tại</label>
                                <input type="text" class="form-control border-secondary" name="address" value="<?= e($address) ?>" placeholder="Nhập địa chỉ tạm trú/thường trú">
                            </div>

                            <div class="text-end border-top pt-3">
                                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i>LƯU THAY ĐỔI</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-header bg-white pt-4 pb-2 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Đổi mật khẩu</h5>
                        <p class="text-muted small mt-1 mb-0">Đổi mật khẩu định kỳ để bảo vệ tài khoản BCS.</p>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm" onsubmit="return handleChangePassword(event)">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <input type="password" class="form-control border-secondary" placeholder="Nhập mật khẩu cũ" required id="oldPassword">
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control border-danger" placeholder="Nhập mật khẩu mới" required id="newPassword">
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label class="form-label fw-bold text-dark">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control border-danger" placeholder="Nhập lại mật khẩu mới" required id="confirmPassword">
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
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/profile.js"></script>
</body>
</html>
