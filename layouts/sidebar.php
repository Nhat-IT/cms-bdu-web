<?php
/**
 * CMS BDU - Sidebar Layout
 * Sidebar dùng chung cho tất cả các trang sau khi đăng nhập
 */

if (!isLoggedIn()) {
    header('Location: ../views/login.php');
    exit;
}

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$role = $currentUser['role'];

// Lấy thông tin user từ database
$userInfo = db_fetch_one("SELECT * FROM users WHERE id = ?", [$currentUser['id']]);

// Lấy thông tin lớp học nếu là sinh viên hoặc BCS
$className = '';
if (in_array($role, ['student', 'bcs'])) {
    $class = db_fetch_one("
        SELECT c.class_name 
        FROM class_students cs
        JOIN classes c ON cs.class_id = c.id
        WHERE cs.student_id = ?
        LIMIT 1
    ", [$currentUser['id']]);
    $className = $class ? $class['class_name'] : '';
}

// Đếm thông báo chưa đọc
$unreadCount = db_count("SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0", [$currentUser['id']]);
?>
<div class="sidebar" id="sidebar">
    
    <div class="brand-container flex-shrink-0">
        <a href="<?= getHomeUrl($role) ?>" class="text-decoration-none text-primary d-flex align-items-center">
            <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
            <span class="fs-4 fw-bold hide-on-collapse">CMS BDU</span>
        </a>
    </div>
    
    <div class="profile-container text-center flex-shrink-0">
        <a href="<?= getProfileUrl($role) ?>" class="profile-trigger" title="Xem hồ sơ cá nhân">
            <img src="<?= getAvatarUrl($userInfo['avatar'] ?? '', $userInfo['full_name'] ?? '') ?>" 
                 class="rounded-circle shadow-sm mb-2 border border-2 border-primary" 
                 width="55" alt="Avatar">
            <div class="hide-on-collapse">
                <div class="text-white fw-bold fs-6"><?= e($userInfo['full_name'] ?? '') ?></div>
                <div class="text-white-50 small mb-1" style="font-size: 0.8rem;">
                    <?php if ($role === 'student' || $role === 'bcs'): ?>
                        <?= e($userInfo['username'] ?? '') ?>
                    <?php else: ?>
                        <?= e($userInfo['email'] ?? '') ?>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php if ($className): ?>
            <span class="badge <?= $role === 'bcs' ? 'bcs-class-badge' : 'student-class-badge' ?> mt-1 hide-on-collapse">
                LỚP: <?= e($className) ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <?php if ($role === 'student'): ?>
                <!-- Menu Sinh viên -->
                <div class="px-4 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">HỌC VỤ</div>
                <a href="home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" title="Tổng quan cá nhân">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="hide-on-collapse">Tổng quan cá nhân</span>
                </a>
                <a href="my-attendance.php" class="nav-link <?= $currentPage === 'my-attendance' ? 'active' : '' ?>" title="Xem điểm danh">
                    <i class="bi bi-person-lines-fill"></i> <span class="hide-on-collapse">Xem điểm danh</span>
                </a>
                <a href="schedule.php" class="nav-link <?= $currentPage === 'schedule' ? 'active' : '' ?>" title="Xem Lịch học">
                    <i class="bi bi-calendar-week"></i> <span class="hide-on-collapse">Xem Lịch học</span>
                </a>
                <a href="documents.php" class="nav-link <?= $currentPage === 'documents' ? 'active' : '' ?>" title="Kho Tài liệu lớp">
                    <i class="bi bi-folder2-open"></i> <span class="hide-on-collapse">Kho Tài liệu lớp</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">TƯƠNG TÁC</div>
                <a href="notifications-all.php" class="nav-link <?= $currentPage === 'notifications-all' ? 'active' : '' ?>" title="Xem thông báo">
                    <i class="bi bi-bell-fill"></i> <span class="hide-on-collapse">Thông báo</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger rounded-pill float-end"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="my-feedback.php" class="nav-link <?= $currentPage === 'my-feedback' ? 'active' : '' ?>" title="Gửi phản hồi">
                    <i class="bi bi-envelope-paper"></i> <span class="hide-on-collapse">Gửi phản hồi</span>
                </a>

            <?php elseif ($role === 'bcs'): ?>
                <!-- Menu BCS -->
                <div class="px-4 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">QUẢN LÝ</div>
                <a href="home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" title="Tổng quan lớp">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="hide-on-collapse">Tổng quan lớp</span>
                </a>
                <a href="dashboard-detail.php" class="nav-link <?= $currentPage === 'dashboard-detail' ? 'active' : '' ?>" title="Báo cáo chi tiết">
                    <i class="bi bi-clipboard2-data-fill"></i> <span class="hide-on-collapse">Báo cáo chi tiết</span>
                </a>
                <a href="attendance.php" class="nav-link <?= $currentPage === 'attendance' ? 'active' : '' ?>" title="Điểm danh">
                    <i class="bi bi-person-lines-fill"></i> <span class="hide-on-collapse">Điểm danh</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">TÀI LIỆU</div>
                <a href="documents.php" class="nav-link <?= $currentPage === 'documents' ? 'active' : '' ?>" title="Kho Tài liệu">
                    <i class="bi bi-folder2-open"></i> <span class="hide-on-collapse">Kho Tài liệu</span>
                </a>
                <a href="announcements.php" class="nav-link <?= $currentPage === 'announcements' ? 'active' : '' ?>" title="Thông báo">
                    <i class="bi bi-megaphone-fill"></i> <span class="hide-on-collapse">Thông báo</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">TƯƠNG TÁC</div>
                <a href="feedback.php" class="nav-link <?= $currentPage === 'feedback' ? 'active' : '' ?>" title="Phản hồi">
                    <i class="bi bi-chat-left-text-fill"></i> <span class="hide-on-collapse">Phản hồi Sinh viên</span>
                </a>

            <?php elseif ($role === 'teacher'): ?>
                <!-- Menu Giảng viên -->
                <div class="px-4 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">GIẢNG DẠY</div>
                <a href="home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" title="Tổng quan">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="hide-on-collapse">Tổng quan</span>
                </a>
                <a href="attendance.php" class="nav-link <?= $currentPage === 'attendance' ? 'active' : '' ?>" title="Điểm danh">
                    <i class="bi bi-person-lines-fill"></i> <span class="hide-on-collapse">Điểm danh</span>
                </a>
                <a href="class-grades.php" class="nav-link <?= $currentPage === 'class-grades' ? 'active' : '' ?>" title="Bảng điểm">
                    <i class="bi bi-table"></i> <span class="hide-on-collapse">Bảng điểm</span>
                </a>
                <a href="class-assignments.php" class="nav-link <?= $currentPage === 'class-assignments' ? 'active' : '' ?>" title="Bài tập">
                    <i class="bi bi-journal-text"></i> <span class="hide-on-collapse">Bài tập</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">TÀI LIỆU</div>
                <a href="documents.php" class="nav-link <?= $currentPage === 'documents' ? 'active' : '' ?>" title="Tài liệu">
                    <i class="bi bi-folder2-open"></i> <span class="hide-on-collapse">Tài liệu môn học</span>
                </a>
                <a href="announcements.php" class="nav-link <?= $currentPage === 'announcements' ? 'active' : '' ?>" title="Thông báo">
                    <i class="bi bi-megaphone-fill"></i> <span class="hide-on-collapse">Thông báo</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">DUYỆT</div>
                <a href="approve-evidences.php" class="nav-link <?= $currentPage === 'approve-evidences' ? 'active' : '' ?>" title="Duyệt minh chứng">
                    <i class="bi bi-check-circle-fill"></i> <span class="hide-on-collapse">Duyệt minh chứng</span>
                </a>

            <?php elseif ($role === 'admin'): ?>
                <!-- Menu Admin -->
                <div class="px-4 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">QUẢN TRỊ</div>
                <a href="home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" title="Tổng quan">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="hide-on-collapse">Tổng quan</span>
                </a>
                <a href="accounts.php" class="nav-link <?= $currentPage === 'accounts' ? 'active' : '' ?>" title="Quản lý tài khoản">
                    <i class="bi bi-people-fill"></i> <span class="hide-on-collapse">Quản lý tài khoản</span>
                </a>
                <a href="classes-subjects.php" class="nav-link <?= $currentPage === 'classes-subjects' ? 'active' : '' ?>" title="Lớp học & Môn học">
                    <i class="bi bi-building"></i> <span class="hide-on-collapse">Lớp học & Môn học</span>
                </a>
                <a href="assignments.php" class="nav-link <?= $currentPage === 'assignments' ? 'active' : '' ?>" title="Bài tập">
                    <i class="bi bi-journal-text"></i> <span class="hide-on-collapse">Bài tập</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">HỆ THỐNG</div>
                <a href="org-settings.php" class="nav-link <?= $currentPage === 'org-settings' ? 'active' : '' ?>" title="Cài đặt">
                    <i class="bi bi-gear-fill"></i> <span class="hide-on-collapse">Cài đặt tổ chức</span>
                </a>
                <a href="system-logs.php" class="nav-link <?= $currentPage === 'system-logs' ? 'active' : '' ?>" title="Nhật ký hệ thống">
                    <i class="bi bi-clock-history"></i> <span class="hide-on-collapse">Nhật ký hệ thống</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../logout.php" class="nav-link logout-btn" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>
