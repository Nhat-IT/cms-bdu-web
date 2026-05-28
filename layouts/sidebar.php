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

// Đếm thông báo chưa đọc (unified count)
$studentMssv = trim((string)($_SESSION['username'] ?? ''));

// 1. Thông báo từ notification_logs
$unreadNotif = db_count("SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0", [$currentUser['id']]);

// 2. Tài liệu mới trong 14 ngày gần nhất
$unreadDocs = 0;
if ($studentMssv !== '') {
    $unreadDocs = db_count(
        "SELECT COUNT(DISTINCT d.id)
         FROM documents d
         JOIN class_subjects cs ON d.class_subject_id = cs.id
         LEFT JOIN users uploader ON d.uploader_id = uploader.id
         WHERE cs.class_id IN (
             SELECT DISTINCT cs2.class_id
             FROM student_subject_registration ssr
             JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
             JOIN class_subjects cs2 ON csg.class_subject_id = cs2.id
             WHERE (ssr.student_id = ? OR ssr.mssv = ?)
               AND ssr.status = 'Đang học'
               AND cs2.class_id IS NOT NULL
         )
         AND LOWER(COALESCE(uploader.role, '')) IN ('bcs', 'admin', 'support_admin')
         AND d.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)",
        [$currentUser['id'], $studentMssv]
    );
}

// 3. Điểm danh mới trong 14 ngày gần nhất
$unreadAtt = 0;
if ($studentMssv !== '') {
    $unreadAtt = db_count(
        "SELECT COUNT(DISTINCT ar.id)
         FROM attendance_records ar
         JOIN attendance_sessions a_s ON ar.session_id = a_s.id
         JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
         LEFT JOIN student_subject_registration ssr_m
             ON ssr_m.class_subject_group_id = csg.id AND ssr_m.mssv = ?
         WHERE (ar.student_id = ?
                OR (ar.registration_id IS NOT NULL AND ar.registration_id = ssr_m.id))
           AND a_s.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)",
        [$studentMssv, $currentUser['id']]
    );
}

// Unified count
$unreadCount = $unreadNotif + $unreadDocs + $unreadAtt;
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
                    <span id="sidebarNotifBadge" class="badge bg-danger rounded-pill float-end<?= $unreadCount === 0 ? ' d-none' : '' ?>">
                        <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                    </span>
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
                <a href="assignments.php" class="nav-link <?= $currentPage === 'assignments' ? 'active' : '' ?>" title="Phân công giảng dạy">
                    <i class="bi bi-journal-text"></i> <span class="hide-on-collapse">Phân công giảng dạy</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">HỆ THỐNG</div>
                <a href="org-settings.php" class="nav-link <?= $currentPage === 'org-settings' ? 'active' : '' ?>" title="Cài đặt">
                    <i class="bi bi-gear-fill"></i> <span class="hide-on-collapse">Cài đặt tổ chức</span>
                </a>
                <a href="system-logs.php" class="nav-link <?= $currentPage === 'system-logs' ? 'active' : '' ?>" title="Nhật ký hệ thống">
                    <i class="bi bi-clock-history"></i> <span class="hide-on-collapse">Nhật ký hệ thống</span>
                </a>

            <?php elseif ($role === 'support_admin'): ?>
                <!-- Menu Support Admin -->
                <div class="px-4 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">QUẢN TRỊ</div>
                <a href="home.php" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" title="Tổng quan hệ thống">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="hide-on-collapse">Tổng quan hệ thống</span>
                </a>
                <a href="accounts.php" class="nav-link <?= $currentPage === 'accounts' ? 'active' : '' ?>" title="Quản lý tài khoản">
                    <i class="bi bi-people-fill"></i> <span class="hide-on-collapse">Quản lý tài khoản</span>
                </a>
                <a href="classes-subjects.php" class="nav-link <?= $currentPage === 'classes-subjects' ? 'active' : '' ?>" title="Quản lý lớp & Môn">
                    <i class="bi bi-building"></i> <span class="hide-on-collapse">Quản lý lớp & Môn</span>
                </a>
                <a href="assignments.php" class="nav-link <?= $currentPage === 'assignments' ? 'active' : '' ?>" title="Phân công giảng dạy">
                    <i class="bi bi-journal-text"></i> <span class="hide-on-collapse">Phân công giảng dạy</span>
                </a>
                
                <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CẤU HÌNH</div>
                <a href="org-settings.php" class="nav-link <?= $currentPage === 'org-settings' ? 'active' : '' ?>" title="Cấu hình học vụ">
                    <i class="bi bi-gear-fill"></i> <span class="hide-on-collapse">Cấu hình học vụ</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="#" class="nav-link logout-btn" title="Đăng xuất" id="logoutBtn">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<!-- Modal xác nhận đăng xuất -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4 px-3">
                <div class="mb-3">
                    <i class="bi bi-box-arrow-left text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="fw-bold mb-1">Xác nhận đăng xuất</h6>
                <p class="text-muted small mb-4">Bạn có chắc chắn muốn đăng xuất không?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Hủy</button>
                    <a href="../logout.php" class="btn btn-danger btn-sm px-4">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var _dbNotifCount = <?= (int)$unreadCount ?>;

document.getElementById('logoutBtn').addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Modal(document.getElementById('logoutModal')).show();
});

// Sử dụng localStorage làm nguồn chính (từ notifications-all.php)
document.addEventListener('DOMContentLoaded', function() {
    // Đọc từ localStorage trước (ưu tiên)
    var lsCount = parseInt(localStorage.getItem('cms_unread_all') || '-1', 10);
    var lsTs = parseInt(localStorage.getItem('cms_unread_ts') || '0', 10);
    var total = _dbNotifCount;

    // Chỉ dùng localStorage nếu timestamp gần đây (trong vòng 5 phút)
    if (lsCount >= 0) {
        var age = Date.now() - lsTs;
        if (age < 300000) { // 5 phút
            total = lsCount;
        }
    }

    // Chỉ cập nhật badge trong sidebar (notification-bell.php quản lý badge riêng)
    function setBadge(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (total > 0) {
            el.textContent = total > 9 ? '9+' : String(total);
            el.classList.remove('d-none');
        } else {
            el.textContent = '';
            el.classList.add('d-none');
        }
    }
    setBadge('sidebarNotifBadge');
});
</script>
