<?php
/**
 * CMS BDU - Tất Cả Thông Báo
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Thông Báo';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/notifications-all.css'];
$extraJs = ['student/student-layout.js', 'student/notifications-all.js'];

// Xử lý đánh dấu đã đọc
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notificationId = intval($_POST['id'] ?? 0);
    if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notification_logs SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notification_logs SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    exit;
}

// Lấy danh sách thông báo
$stmt = $pdo->prepare("
    SELECT * FROM notification_logs 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// Đếm thông báo chưa đọc
$unreadNotifications = count(array_filter($notifications, function($n) { return $n['is_read'] == 0; }));
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
                <i class="bi bi-bell-fill me-2"></i> TẤT CẢ THÔNG BÁO
            </h5>
        </div>

        <div class="d-flex align-items-center text-white">
            <a href="notifications-all.php" class="text-white text-decoration-none" title="Thông báo mới chưa đọc">
                <i class="bi bi-bell fs-5 text-white position-relative cursor-pointer">
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </i>
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Danh sách thông báo</h5>
                <p class="text-muted mb-0">Tất cả cập nhật từ Ban cán sự, Giảng viên và Khoa Công nghệ thông tin.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-success fw-bold" id="markAllReadBtn" onclick="markAllAsRead()">
                    <i class="bi bi-check2-all me-1"></i> Đánh dấu tất cả là đã đọc
                </button>
                <a href="notifications-all.php" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-arrow-clockwise me-1"></i> Làm mới
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label small fw-bold text-muted mb-1">Tìm kiếm</label>
                        <input type="text" class="form-control" id="notifySearchInput" placeholder="Nhập tiêu đề hoặc nội dung thông báo..." onkeyup="filterNotifications()">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-bold text-muted mb-1">Nguồn thông báo</label>
                        <select class="form-select" id="notifySourceFilter" onchange="filterNotifications()">
                            <option value="all" selected>Tất cả nguồn</option>
                            <option value="BAN CÁN SỰ">Ban cán sự</option>
                            <option value="GIẢNG VIÊN">Giảng viên</option>
                            <option value="KHOA CNTT">Khoa CNTT</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Trạng thái</label>
                        <select class="form-select" id="notifyReadFilter" onchange="filterNotifications()">
                            <option value="all" selected>Tất cả</option>
                            <option value="unread">Chưa đọc</option>
                            <option value="read">Đã đọc</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 d-grid">
                        <button type="button" class="btn btn-outline-secondary" id="clearNotifyFilterBtn" onclick="clearFilters()">Xóa lọc</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="list-group list-group-flush" id="notifyList">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        // Xác định nguồn dựa trên tiêu đề hoặc message
                        $source = 'KHÁC';
                        $sourceClass = 'source-badge-default';
                        if (stripos($notification['title'] ?? '', 'BCS') !== false || stripos($notification['message'] ?? '', 'ban cán sự') !== false) {
                            $source = 'BAN CÁN SỰ';
                            $sourceClass = 'source-badge-bcs';
                        } elseif (stripos($notification['title'] ?? '', 'GV') !== false || stripos($notification['message'] ?? '', 'giảng viên') !== false) {
                            $source = 'GIẢNG VIÊN';
                            $sourceClass = 'source-badge-lecturer';
                        } elseif (stripos($notification['title'] ?? '', 'Khoa') !== false || stripos($notification['message'] ?? '', 'khoa') !== false) {
                            $source = 'KHOA CNTT';
                            $sourceClass = 'source-badge-dept';
                        }
                        ?>
                        <div class="list-group-item notify-item py-3 <?= $notification['is_read'] == 0 ? '' : 'opacity-75' ?>" 
                             data-source="<?= $source ?>" 
                             data-read="<?= $notification['is_read'] == 0 ? 'false' : 'true' ?>"
                             data-title="<?= e(strtolower($notification['title'] . ' ' . $notification['message'])) ?>"
                             onclick="showNotificationDetail(<?= htmlspecialchars(json_encode($notification)) ?>)"
                             style="cursor: pointer;">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="mb-1">
                                        <?php if ($notification['is_read'] == 0): ?>
                                            <span class="badge bg-primary rounded-pill me-1" style="font-size: 0.5rem; padding: 3px 6px;"></span>
                                        <?php endif; ?>
                                        <span class="badge <?= $sourceClass ?>"><?= $source ?></span>
                                    </div>
                                    <h6 class="fw-bold mb-1 <?= $notification['is_read'] == 0 ? 'text-primary' : 'text-dark' ?>"><?= e($notification['title'] ?? 'Thông báo') ?></h6>
                                    <p class="text-muted mb-0"><?= e($notification['message'] ?? '') ?></p>
                                </div>
                                <small class="text-muted <?= $notification['is_read'] == 0 ? 'text-primary' : '' ?>"><?= formatDateTime($notification['created_at'], 'd/m/Y H:i') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item py-5 text-center text-muted">
                        <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                        <h5>Không có thông báo nào</h5>
                        <p class="mb-0">Các thông báo mới sẽ xuất hiện ở đây.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notifyDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="notifyDetailTitle">Chi tiết thông báo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <span class="badge source-badge" id="notifyDetailSource">Nguồn</span>
                </div>
                <div class="text-muted small mb-3" id="notifyDetailTime">Thời gian</div>
                <p class="mb-0 text-dark" id="notifyDetailContent">Nội dung thông báo</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Đóng</button>
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
