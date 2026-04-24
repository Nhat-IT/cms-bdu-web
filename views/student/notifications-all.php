<?php
/**
 * CMS BDU - Tất cả thông báo
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Thông báo';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/notifications-all.css'];
$extraJs = ['student/student-layout.js'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['id'] ?? 0);
        if ($notificationId > 0) {
            db_query(
                "UPDATE notification_logs SET is_read = 1 WHERE id = ? AND user_id = ?",
                [$notificationId, $userId]
            );
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mark_all_read') {
        db_query("UPDATE notification_logs SET is_read = 1 WHERE user_id = ?", [$userId]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$notifications = db_fetch_all(
    "SELECT id, title, message, is_read, created_at
     FROM notification_logs
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100",
    [$userId]
);

$unreadNotifications = 0;
foreach ($notifications as $n) {
    if ((int)($n['is_read'] ?? 0) === 0) {
        $unreadNotifications++;
    }
}

function notifySourceInfo($title, $message): array {
    $text = strtolower(trim((string)$title . ' ' . (string)$message));
    if (strpos($text, 'bcs') !== false || strpos($text, 'ban cán sự') !== false) {
        return ['BAN CÁN SỰ', 'source-badge-bcs'];
    }
    if (strpos($text, 'giảng viên') !== false || strpos($text, 'gv') !== false) {
        return ['GIẢNG VIÊN', 'source-badge-lecturer'];
    }
    if (strpos($text, 'khoa') !== false || strpos($text, 'cntt') !== false) {
        return ['KHOA CNTT', 'source-badge-dept'];
    }
    return ['HỆ THỐNG', 'source-badge-default'];
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center"><i class="bi bi-bell-fill me-2"></i>TẤT CẢ THÔNG BÁO</h5>
        </div>

        <div class="d-flex align-items-center text-white">
            <a href="notifications-all.php" class="text-white text-decoration-none" title="Thông báo mới chưa đọc">
                <i class="bi bi-bell fs-5 text-white position-relative cursor-pointer">
                    <?php if ($unreadNotifications > 0): ?>
                        <span id="topUnreadCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </i>
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Danh sách thông báo</h5>
                <p class="text-muted mb-0">Nhấn vào từng thông báo để xem chi tiết.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-success fw-bold" id="markAllReadBtn">
                    <i class="bi bi-check2-all me-1"></i>Đánh dấu tất cả đã đọc
                </button>
                <a href="notifications-all.php" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label small fw-bold text-muted mb-1">Tìm kiếm</label>
                        <input type="text" class="form-control" id="notifySearchInput" placeholder="Nhập tiêu đề hoặc nội dung thông báo...">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-bold text-muted mb-1">Nguồn thông báo</label>
                        <select class="form-select" id="notifySourceFilter">
                            <option value="all" selected>Tất cả nguồn</option>
                            <option value="BAN CÁN SỰ">Ban cán sự</option>
                            <option value="GIẢNG VIÊN">Giảng viên</option>
                            <option value="KHOA CNTT">Khoa CNTT</option>
                            <option value="HỆ THỐNG">Hệ thống</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Trạng thái</label>
                        <select class="form-select" id="notifyReadFilter">
                            <option value="all" selected>Tất cả</option>
                            <option value="unread">Chưa đọc</option>
                            <option value="read">Đã đọc</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 d-grid">
                        <button type="button" class="btn btn-outline-secondary" id="clearNotifyFilterBtn">Xóa lọc</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="list-group list-group-flush" id="notifyList">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php [$sourceText, $sourceClass] = notifySourceInfo($notification['title'] ?? '', $notification['message'] ?? ''); ?>
                        <div
                            class="list-group-item notify-item py-3 <?= (int)$notification['is_read'] === 0 ? '' : 'opacity-75' ?>"
                            data-id="<?= (int)$notification['id'] ?>"
                            data-source="<?= e($sourceText) ?>"
                            data-read="<?= (int)$notification['is_read'] === 0 ? 'false' : 'true' ?>"
                            data-title="<?= e(strtolower((string)($notification['title'] ?? '') . ' ' . (string)($notification['message'] ?? ''))) ?>"
                            data-detail="<?= e(json_encode($notification, JSON_UNESCAPED_UNICODE)) ?>"
                            style="cursor: pointer;"
                        >
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="mb-1">
                                        <?php if ((int)$notification['is_read'] === 0): ?>
                                            <span class="badge bg-primary rounded-pill me-1" style="font-size: 0.5rem; padding: 3px 6px;"></span>
                                        <?php endif; ?>
                                        <span class="badge <?= e($sourceClass) ?>"><?= e($sourceText) ?></span>
                                    </div>
                                    <h6 class="fw-bold mb-1 <?= (int)$notification['is_read'] === 0 ? 'text-primary' : 'text-dark' ?>"><?= e($notification['title'] ?? 'Thông báo') ?></h6>
                                    <p class="text-muted mb-0"><?= e($notification['message'] ?? '') ?></p>
                                </div>
                                <small class="text-muted <?= (int)$notification['is_read'] === 0 ? 'text-primary' : '' ?>"><?= formatDateTime($notification['created_at'] ?? null, 'd/m/Y H:i') ?></small>
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
<script>
const notifyList = document.getElementById('notifyList');
const searchInput = document.getElementById('notifySearchInput');
const sourceFilter = document.getElementById('notifySourceFilter');
const readFilter = document.getElementById('notifyReadFilter');
const clearBtn = document.getElementById('clearNotifyFilterBtn');
const markAllBtn = document.getElementById('markAllReadBtn');
const unreadTopBadge = document.getElementById('topUnreadCount');
const detailModal = new bootstrap.Modal(document.getElementById('notifyDetailModal'));

function formatDateTimeVN(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
}

function updateUnreadCounter() {
    const unread = document.querySelectorAll('.notify-item[data-read="false"]').length;
    if (!unreadTopBadge) return;
    if (unread <= 0) {
        unreadTopBadge.style.display = 'none';
        return;
    }
    unreadTopBadge.style.display = 'inline-block';
    unreadTopBadge.textContent = unread > 99 ? '99+' : String(unread);
}

function filterNotifications() {
    const keyword = (searchInput?.value || '').trim().toLowerCase();
    const source = sourceFilter?.value || 'all';
    const read = readFilter?.value || 'all';

    document.querySelectorAll('.notify-item').forEach((item) => {
        const text = item.getAttribute('data-title') || '';
        const rowSource = item.getAttribute('data-source') || '';
        const rowRead = item.getAttribute('data-read') === 'true' ? 'read' : 'unread';

        const matchKeyword = !keyword || text.includes(keyword);
        const matchSource = source === 'all' || rowSource === source;
        const matchRead = read === 'all' || rowRead === read;

        item.style.display = (matchKeyword && matchSource && matchRead) ? '' : 'none';
    });
}

function clearFilters() {
    if (searchInput) searchInput.value = '';
    if (sourceFilter) sourceFilter.value = 'all';
    if (readFilter) readFilter.value = 'all';
    filterNotifications();
}

async function markRead(id) {
    const body = new URLSearchParams();
    body.set('action', 'mark_read');
    body.set('id', String(id));

    await fetch('notifications-all.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });
}

async function markAllAsRead() {
    const body = new URLSearchParams();
    body.set('action', 'mark_all_read');

    await fetch('notifications-all.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });

    document.querySelectorAll('.notify-item').forEach((item) => {
        item.setAttribute('data-read', 'true');
        item.classList.add('opacity-75');
        const title = item.querySelector('h6');
        if (title) {
            title.classList.remove('text-primary');
            title.classList.add('text-dark');
        }
    });
    updateUnreadCounter();
    filterNotifications();
}

function openNotificationDetail(item) {
    const raw = item.getAttribute('data-detail') || '{}';
    let detail = {};
    try { detail = JSON.parse(raw); } catch (e) { detail = {}; }

    const source = item.getAttribute('data-source') || 'HỆ THỐNG';
    const sourceNode = document.getElementById('notifyDetailSource');
    const titleNode = document.getElementById('notifyDetailTitle');
    const timeNode = document.getElementById('notifyDetailTime');
    const contentNode = document.getElementById('notifyDetailContent');

    if (sourceNode) sourceNode.textContent = source;
    if (titleNode) titleNode.textContent = detail.title || 'Chi tiết thông báo';
    if (timeNode) timeNode.textContent = formatDateTimeVN(detail.created_at || '');
    if (contentNode) contentNode.textContent = detail.message || '';

    if (item.getAttribute('data-read') === 'false') {
        item.setAttribute('data-read', 'true');
        item.classList.add('opacity-75');
        markRead(item.getAttribute('data-id'));
        updateUnreadCounter();
        filterNotifications();
    }

    detailModal.show();
}

document.querySelectorAll('.notify-item').forEach((item) => {
    item.addEventListener('click', function () {
        openNotificationDetail(item);
    });
});

searchInput?.addEventListener('input', filterNotifications);
sourceFilter?.addEventListener('change', filterNotifications);
readFilter?.addEventListener('change', filterNotifications);
clearBtn?.addEventListener('click', clearFilters);
markAllBtn?.addEventListener('click', markAllAsRead);

updateUnreadCounter();
</script>
</body>
</html>
