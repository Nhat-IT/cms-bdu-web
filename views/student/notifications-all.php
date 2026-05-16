<?php
/**
 * CMS BDU - Tất cả thông báo & cập nhật
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId      = (int)($_SESSION['user_id'] ?? 0);
$studentMssv = trim((string)($_SESSION['username'] ?? ''));
$pageTitle   = 'Thông báo & Cập nhật';
$extraCss    = ['layout.css', 'student/student-layout.css', 'student/notifications-all.css'];
$extraJs     = ['student/student-layout.js'];

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($nid > 0) {
            db_query("UPDATE notification_logs SET is_read = 1 WHERE id = ? AND user_id = ?", [$nid, $userId]);
            logSystem("Đọc thông báo ID #$nid", 'notification_logs', $nid);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mark_all_read') {
        db_query("UPDATE notification_logs SET is_read = 1 WHERE user_id = ?", [$userId]);
        logSystem('Đánh dấu tất cả thông báo đã đọc', 'notification_logs');
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mark_unread') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($nid > 0) {
            db_query("UPDATE notification_logs SET is_read = 0 WHERE id = ? AND user_id = ?", [$nid, $userId]);
            logSystem("Đánh dấu thông báo ID #$nid là chưa đọc", 'notification_logs', $nid);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Yêu cầu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Học kỳ hiện tại ───────────────────────────────────────────────────────────
$currentSem   = getCurrentSemester();
$currentSemId = (int)($currentSem['id'] ?? 0);

// ── 1. THÔNG BÁO (notification_logs) ─────────────────────────────────────────
$rawNotifications = db_fetch_all(
    "SELECT id, title, message, is_read, created_at
     FROM notification_logs
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 60",
    [$userId]
);

// ── 2. TÀI LIỆU MỚI (documents) ──────────────────────────────────────────────
$rawDocuments = db_fetch_all(
    "SELECT DISTINCT d.id, d.title, d.category, d.created_at, d.note,
            s.subject_name, s.subject_code,
            uploader.full_name AS uploader_name, uploader.role AS uploader_role
     FROM documents d
     JOIN class_subjects cs ON d.class_subject_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
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
     ORDER BY d.created_at DESC
     LIMIT 40",
    [$userId, $studentMssv]
);

// ── 3. ĐIỂM DANH (attendance_records) ────────────────────────────────────────
$rawAttendance = db_fetch_all(
    "SELECT DISTINCT ar.id, ar.status AS att_status, ar.evidence_status,
            a_s.attendance_date,
            s.subject_name, s.subject_code, csg.room
     FROM attendance_records ar
     JOIN attendance_sessions a_s ON ar.session_id = a_s.id
     JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
     JOIN class_subjects cs ON csg.class_subject_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
     LEFT JOIN student_subject_registration ssr_m
         ON ssr_m.class_subject_group_id = csg.id AND ssr_m.mssv = ?
     WHERE (ar.student_id = ?
            OR (ar.registration_id IS NOT NULL AND ar.registration_id = ssr_m.id))
     ORDER BY a_s.attendance_date DESC
     LIMIT 50",
    [$studentMssv, $userId]
);

// ── 4. XẾP LỚP (student_subject_registration + class_subjects) ───────────────
$rawSchedule = db_fetch_all(
    "SELECT DISTINCT cs.id AS cs_id, s.subject_name, s.subject_code,
            c.class_name, cs.start_date, cs.end_date,
            csg.day_of_week, csg.start_period, csg.end_period, csg.room, csg.group_code,
            COALESCE(maint.full_name, t.full_name) AS teacher_name,
            sm.semester_name, sm.academic_year
     FROM student_subject_registration ssr
     JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
     JOIN class_subjects cs ON csg.class_subject_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
     LEFT JOIN classes c ON cs.class_id = c.id
     LEFT JOIN semesters sm ON cs.semester_id = sm.id
     LEFT JOIN users t ON cs.teacher_id = t.id
     LEFT JOIN users maint ON csg.main_teacher_id = maint.id
     WHERE (ssr.student_id = ? OR ssr.mssv = ?)
       AND ssr.status = 'Đang học'
       AND cs.semester_id = ?
     ORDER BY cs.start_date DESC, s.subject_name",
    [$userId, $studentMssv, $currentSemId]
);

// ── Build unified feed ────────────────────────────────────────────────────────
$attStatusMeta = [
    1 => ['label' => 'Có mặt',  'icon' => 'bi-person-check-fill',  'color' => 'success'],
    2 => ['label' => 'Đi muộn', 'icon' => 'bi-clock-history',      'color' => 'warning'],
    3 => ['label' => 'Vắng',    'icon' => 'bi-calendar-x-fill',    'color' => 'danger'],
];
$evidenceLabel = [
    'Pending'  => 'Đang chờ duyệt',
    'Approved' => 'Minh chứng được duyệt',
    'Rejected' => 'Minh chứng bị từ chối',
];

function feedSourceClass(string $source): string {
    return match ($source) {
        'BAN CÁN SỰ'    => 'source-badge-bcs',
        'GIẢNG VIÊN'    => 'source-badge-lecturer',
        'PHÒNG ĐÀO TẠO' => 'source-badge-dept',
        'ĐIỂM DANH'     => 'source-badge-attendance',
        'XẾP LỚP'       => 'source-badge-schedule',
        default          => 'source-badge-default',
    };
}

$feed = [];

// Thông báo
foreach ($rawNotifications as $n) {
    $feed[] = [
        'type'      => 'notification',
        'sort_time' => (string)($n['created_at'] ?? '2000-01-01'),
        'notify_id' => (int)$n['id'],
        'is_read'   => (int)($n['is_read'] ?? 1) === 1,
        'title'     => (string)($n['title'] ?? 'Thông báo'),
        'content'   => (string)($n['message'] ?? ''),
        'time'      => $n['created_at'] ?? null,
        'icon'      => 'bi-bell-fill',
        'color'     => 'primary',
        'source'    => 'BAN CÁN SỰ',
        'item_key'  => '',
        'extra'     => ['message' => (string)($n['message'] ?? '')],
    ];
}

// Tài liệu mới
foreach ($rawDocuments as $d) {
    $upRole   = strtolower((string)($d['uploader_role'] ?? ''));
    $srcLabel = match ($upRole) {
        'bcs'                    => 'BAN CÁN SỰ',
        'admin', 'support_admin' => 'PHÒNG ĐÀO TẠO',
        'teacher'                => 'GIẢNG VIÊN',
        default                  => 'HỆ THỐNG',
    };
    $catIcon = match (mb_strtolower((string)($d['category'] ?? ''))) {
        'thông báo'     => 'bi-megaphone-fill',
        'biên bản'      => 'bi-file-earmark-text-fill',
        'học liệu'      => 'bi-book-half',
        'danh sách lớp' => 'bi-person-lines-fill',
        default         => 'bi-file-earmark-arrow-down-fill',
    };
    $subjectPart = $d['subject_name'] ? ' — ' . $d['subject_name'] : '';
    $notePart    = $d['note'] ? ' (' . mb_strimwidth((string)$d['note'], 0, 60, '…') . ')' : '';
    $feed[] = [
        'type'      => 'document',
        'sort_time' => (string)($d['created_at'] ?? '2000-01-01'),
        'notify_id' => 0,
        'is_read'   => false,
        'title'     => 'Tài liệu mới: ' . ($d['title'] ?? 'Không tiêu đề'),
        'content'   => ($d['category'] ?? 'Tài liệu') . $subjectPart . $notePart,
        'time'      => $d['created_at'] ?? null,
        'icon'      => $catIcon,
        'color'     => 'info',
        'source'    => $srcLabel,
        'item_key'  => 'doc_' . (int)$d['id'],
        'extra'     => [
            'doc_title'     => (string)($d['title'] ?? ''),
            'category'      => (string)($d['category'] ?? ''),
            'subject_name'  => (string)($d['subject_name'] ?? ''),
            'uploader_name' => (string)($d['uploader_name'] ?? ''),
            'note'          => (string)($d['note'] ?? ''),
        ],
    ];
}

// Điểm danh
foreach ($rawAttendance as $ar) {
    $attCode  = (int)($ar['att_status'] ?? 0);
    $meta     = $attStatusMeta[$attCode] ?? ['label' => 'Không rõ', 'icon' => 'bi-question-circle-fill', 'color' => 'secondary'];
    $evRaw    = (string)($ar['evidence_status'] ?? '');
    $evPart   = ($evRaw !== '' && $evRaw !== 'None') ? ' | ' . ($evidenceLabel[$evRaw] ?? $evRaw) : '';
    $roomPart = $ar['room'] ? 'Phòng ' . $ar['room'] : '';
    $feed[] = [
        'type'      => 'attendance',
        'sort_time' => (string)($ar['attendance_date'] ?? '2000-01-01'),
        'notify_id' => 0,
        'is_read'   => false,
        'title'     => 'Điểm danh: ' . $meta['label'] . ' — ' . ($ar['subject_name'] ?? 'Môn học'),
        'content'   => trim($roomPart . $evPart),
        'time'      => $ar['attendance_date'] ?? null,
        'icon'      => $meta['icon'],
        'color'     => $meta['color'],
        'source'    => 'ĐIỂM DANH',
        'item_key'  => 'att_' . (int)$ar['id'],
        'extra'     => [
            'subject_name'   => (string)($ar['subject_name'] ?? ''),
            'att_label'      => $meta['label'],
            'att_status'     => $attCode,
            'evidence_status'=> $evRaw,
            'evidence_label' => ($evRaw !== '' && $evRaw !== 'None') ? ($evidenceLabel[$evRaw] ?? $evRaw) : '',
            'room'           => (string)($ar['room'] ?? ''),
        ],
    ];
}

// Xếp lớp (một entry mỗi class_subject)
$seenCs = [];
foreach ($rawSchedule as $sc) {
    $csId = (int)($sc['cs_id'] ?? 0);
    if (isset($seenCs[$csId])) continue;
    $seenCs[$csId] = true;

    $semLabel   = ($sc['semester_name'] && $sc['academic_year'])
                  ? 'HK' . $sc['semester_name'] . ' — ' . $sc['academic_year'] : '';
    $classLabel = $sc['class_name'] ? ' (' . $sc['class_name'] . ')' : '';
    $teachLabel = $sc['teacher_name'] ? 'GV: ' . $sc['teacher_name'] : '';
    $content    = implode(' | ', array_filter([$semLabel, $teachLabel]));
    $feed[] = [
        'type'      => 'schedule',
        'sort_time' => (string)($sc['start_date'] ?? '2000-01-01'),
        'notify_id' => 0,
        'is_read'   => true,
        'title'     => 'Xếp lớp: ' . ($sc['subject_name'] ?? 'Môn học') . $classLabel,
        'content'   => $content,
        'time'      => $sc['start_date'] ?? null,
        'icon'      => 'bi-calendar-week-fill',
        'color'     => 'secondary',
        'source'    => 'XẾP LỚP',
        'item_key'  => '',
        'extra'     => [
            'subject_name'  => (string)($sc['subject_name'] ?? ''),
            'subject_code'  => (string)($sc['subject_code'] ?? ''),
            'class_name'    => (string)($sc['class_name'] ?? ''),
            'teacher_name'  => (string)($sc['teacher_name'] ?? ''),
            'semester_label'=> $semLabel,
            'start_date'    => (string)($sc['start_date'] ?? ''),
            'end_date'      => (string)($sc['end_date'] ?? ''),
        ],
    ];
}

// Sắp xếp mới nhất trước
usort($feed, fn($a, $b) => strcmp($b['sort_time'], $a['sort_time']));

// Đếm số thông báo chưa đọc
$unreadNotifications = 0;
foreach ($feed as $item) {
    if ($item['type'] === 'notification' && !$item['is_read']) {
        $unreadNotifications++;
    }
}

// Đếm theo loại
$countByType = ['notification' => 0, 'document' => 0, 'attendance' => 0, 'schedule' => 0];
foreach ($feed as $item) {
    $countByType[$item['type']] = ($countByType[$item['type']] ?? 0) + 1;
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
            <button class="btn btn-outline-light me-3 border-0" id="sidebarToggle">
                <i class="bi bi-list fs-3"></i>
            </button>
            <h5 class="m-0 text-white fw-bold">
                <i class="bi bi-bell-fill me-2"></i>THÔNG BÁO & CẬP NHẬT
            </h5>
        </div>
        <!-- Badge chuông: tối đa 9+ -->
        <div class="d-flex align-items-center">
            <a href="notifications-all.php" class="text-white text-decoration-none position-relative me-1" title="Thông báo">
                <i class="bi bi-bell fs-5"></i>
                <span id="topUnreadCount"
                      class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger<?= $unreadNotifications === 0 ? ' d-none' : '' ?>"
                      style="font-size:.65rem;">
                    <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                </span>
            </a>
        </div>
    </div>

    <div class="p-4">

        <!-- Tiêu đề + nút -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Tổng hợp hoạt động</h5>
                <p class="text-muted mb-0" id="statusSubtitle">
                    <?= count($feed) ?> mục &bull;
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="text-primary fw-bold"><?= $unreadNotifications ?> thông báo chưa đọc</span>
                    <?php else: ?>
                        <span class="text-success fw-semibold">Đã đọc tất cả thông báo</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <!-- Nút luôn hiển thị, disable khi không có unread -->
                <button type="button" class="btn btn-outline-success fw-bold" id="markAllReadBtn"
                        <?= $unreadNotifications === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-check2-all me-1"></i>Đánh dấu tất cả đã đọc
                </button>
                <a href="notifications-all.php" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
                </a>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-sm type-chip active" data-type="all">
                        <i class="bi bi-grid me-1"></i>Tất cả
                        <span class="badge bg-white text-dark ms-1"><?= count($feed) ?></span>
                    </button>
                    <button class="btn btn-sm type-chip" data-type="notification">
                        <i class="bi bi-bell-fill me-1"></i>Thông báo
                        <span class="badge bg-white text-dark ms-1"><?= $countByType['notification'] ?></span>
                    </button>
                    <button class="btn btn-sm type-chip" data-type="document">
                        <i class="bi bi-file-earmark-fill me-1"></i>Tài liệu
                        <span class="badge bg-white text-dark ms-1"><?= $countByType['document'] ?></span>
                    </button>
                    <button class="btn btn-sm type-chip" data-type="attendance">
                        <i class="bi bi-calendar-check-fill me-1"></i>Điểm danh
                        <span class="badge bg-white text-dark ms-1"><?= $countByType['attendance'] ?></span>
                    </button>
                    <button class="btn btn-sm type-chip" data-type="schedule">
                        <i class="bi bi-journal-bookmark-fill me-1"></i>Xếp lớp
                        <span class="badge bg-white text-dark ms-1"><?= $countByType['schedule'] ?></span>
                    </button>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-lg-6">
                        <input type="text" class="form-control form-control-sm" id="notifySearchInput"
                               placeholder="Tìm kiếm tiêu đề hoặc nội dung…">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select form-select-sm" id="notifyReadFilter">
                            <option value="all" selected>Tất cả trạng thái</option>
                            <option value="unread">Chưa đọc</option>
                            <option value="read">Đã đọc</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-bold" id="clearNotifyFilterBtn">
                            <i class="bi bi-x-circle me-1"></i>Xóa lọc
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danh sách feed -->
        <div class="card shadow-sm border-0">
            <div class="list-group list-group-flush" id="notifyList">
                <?php if (!empty($feed)): ?>
                    <?php foreach ($feed as $item): ?>
                        <?php
                            $type      = $item['type'];
                            $isRead    = $item['is_read'];
                            $srcClass  = feedSourceClass($item['source']);
                            $searchText = mb_strtolower($item['title'] . ' ' . $item['content']);
                            $detailJson = json_encode(array_merge([
                                'type'    => $type,
                                'title'   => $item['title'],
                                'content' => $item['content'],
                                'time'    => $item['time'],
                                'source'  => $item['source'],
                                'color'   => $item['color'],
                            ], $item['extra'] ?? []), JSON_UNESCAPED_UNICODE);
                        ?>
                        <div class="list-group-item notify-item py-3"
                             data-id="<?= $item['notify_id'] ?>"
                             data-type="<?= e($type) ?>"
                             data-read="<?= $isRead ? 'true' : 'false' ?>"
                             data-item-key="<?= e($item['item_key'] ?? '') ?>"
                             data-search="<?= e($searchText) ?>"
                             data-detail="<?= e($detailJson) ?>">
                            <div class="d-flex align-items-start gap-3">
                                <!-- Icon loại -->
                                <div class="feed-icon-wrap flex-shrink-0 icon-feed-<?= e($item['color']) ?>">
                                    <i class="bi <?= e($item['icon']) ?>"></i>
                                </div>
                                <!-- Nội dung -->
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <?php if (!$isRead): ?>
                                                <span class="unread-dot"></span>
                                            <?php endif; ?>
                                            <span class="badge source-badge <?= e($srcClass) ?>"><?= e($item['source']) ?></span>
                                        </div>
                                        <small class="text-muted text-nowrap">
                                            <?= $item['time'] ? formatDateTime($item['time'], 'd/m/Y') : '' ?>
                                        </small>
                                    </div>
                                    <h6 class="mb-1 <?= !$isRead ? 'fw-bold text-primary' : 'fw-semibold text-dark' ?>">
                                        <?= e($item['title']) ?>
                                    </h6>
                                    <?php if (!empty($item['content'])): ?>
                                        <p class="text-muted mb-0 small feed-content-line"><?= e($item['content']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($type === 'notification'): ?>
                                        <small class="text-muted"><?= $item['time'] ? formatDateTime($item['time'], 'H:i d/m/Y') : '' ?></small>
                                    <?php endif; ?>
                                </div>
                                <!-- Nút mark-unread (Gmail style) + chevron -->
                                <div class="flex-shrink-0 align-self-center d-flex align-items-center gap-1">
                                    <button class="btn-mark-unread" title="Đánh dấu là chưa đọc"
                                            data-notify-id="<?= $item['notify_id'] ?>"
                                            data-item-key="<?= e($item['item_key'] ?? '') ?>"
                                            data-item-type="<?= e($type) ?>">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                    <i class="bi bi-chevron-right small text-muted"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item py-5 text-center text-muted">
                        <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                        <h5>Chưa có hoạt động nào</h5>
                        <p class="mb-0">Thông báo, tài liệu và điểm danh mới sẽ xuất hiện ở đây.</p>
                    </div>
                <?php endif; ?>

                <div class="list-group-item py-4 text-center text-muted d-none" id="emptyFilterMsg">
                    <i class="bi bi-funnel fs-3 d-block mb-2"></i>
                    <p class="mb-0">Không có mục nào khớp với bộ lọc hiện tại.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal chi tiết -->
<div class="modal fade" id="notifyDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white bg-primary" id="notifyDetailHeader">
                <h5 class="modal-title fw-bold" id="notifyDetailTitle">Chi tiết</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="notifyDetailBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
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
(function () {
    const searchInput    = document.getElementById('notifySearchInput');
    const readFilter     = document.getElementById('notifyReadFilter');
    const clearBtn       = document.getElementById('clearNotifyFilterBtn');
    const markAllBtn     = document.getElementById('markAllReadBtn');
    const topBadge       = document.getElementById('topUnreadCount');
    const emptyMsg       = document.getElementById('emptyFilterMsg');
    const statusSubtitle = document.getElementById('statusSubtitle');
    const detailModal    = new bootstrap.Modal(document.getElementById('notifyDetailModal'));
    const typeChips      = document.querySelectorAll('.type-chip');
    const totalCount     = <?= count($feed) ?>;

    let activeType = 'all';

    const headerColorMap = {
        notification: 'bg-primary',
        document:     'bg-info',
        attendance:   'bg-danger',
        schedule:     'bg-secondary',
    };

    // ── Chips lọc theo loại ──────────────────────────────────────────────────
    typeChips.forEach(chip => {
        chip.addEventListener('click', function () {
            typeChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            activeType = this.dataset.type;
            applyFilters();
        });
    });

    // ── Áp dụng lọc ─────────────────────────────────────────────────────────
    function applyFilters() {
        const keyword = (searchInput?.value ?? '').trim().toLowerCase();
        const readVal = readFilter?.value ?? 'all';
        let visible = 0;

        document.querySelectorAll('.notify-item').forEach(item => {
            const matchType    = activeType === 'all' || item.dataset.type === activeType;
            const matchKeyword = !keyword || (item.dataset.search ?? '').includes(keyword);
            const isRead       = item.dataset.read === 'true';
            const matchRead    = readVal === 'all'
                || (readVal === 'unread' && !isRead)
                || (readVal === 'read'   &&  isRead);

            const show = matchType && matchKeyword && matchRead;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        emptyMsg?.classList.toggle('d-none', visible > 0);
    }

    // ── Gửi mark_read ────────────────────────────────────────────────────────
    async function markRead(id) {
        if (id <= 0) return;
        await fetch('notifications-all.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mark_read', id: String(id) }).toString(),
        });
    }

    // ── Đánh dấu tất cả đã đọc ──────────────────────────────────────────────
    async function markAllAsRead() {
        if (markAllBtn?.disabled) return;
        await fetch('notifications-all.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mark_all_read' }).toString(),
        });
        document.querySelectorAll('.notify-item').forEach(item => {
            const key = item.dataset.itemKey;
            if (key) localStorage.setItem('feed_' + key, '1');
            setItemRead(item);
        });
        updateUnreadCounter();
        applyFilters();
    }

    function setItemRead(item) {
        if (item.dataset.read === 'true') return;
        item.dataset.read = 'true';
        item.querySelector('.unread-dot')?.remove();
        const h6 = item.querySelector('h6');
        if (h6) {
            h6.classList.remove('text-primary', 'fw-bold');
            h6.classList.add('fw-semibold', 'text-dark');
        }
    }

    function setItemUnread(item) {
        if (item.dataset.read === 'false') return;
        item.dataset.read = 'false';
        const badgeRow = item.querySelector('.d-flex.align-items-center.gap-2');
        if (badgeRow && !badgeRow.querySelector('.unread-dot')) {
            const dot = document.createElement('span');
            dot.className = 'unread-dot';
            badgeRow.insertBefore(dot, badgeRow.firstChild);
        }
        const h6 = item.querySelector('h6');
        if (h6) {
            h6.classList.remove('fw-semibold', 'text-dark');
            h6.classList.add('fw-bold', 'text-primary');
        }
    }

    async function markUnread(id) {
        if (id <= 0) return;
        await fetch('notifications-all.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mark_unread', id: String(id) }).toString(),
        });
    }

    // ── Nút mark-unread (Gmail style) ────────────────────────────────────────
    document.querySelectorAll('.btn-mark-unread').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const item = btn.closest('.notify-item');
            if (!item || item.dataset.read === 'false') return;
            const type = btn.dataset.itemType;
            const key  = btn.dataset.itemKey;
            if (type === 'notification') {
                markUnread(parseInt(btn.dataset.notifyId, 10));
            } else if (key) {
                localStorage.removeItem('feed_' + key);
            }
            setItemUnread(item);
            updateUnreadCounter();
            applyFilters();
        });
    });

    // ── Cập nhật badge chuông & trạng thái (tối đa 9+) ──────────────────────
    function updateUnreadCounter() {
        const unreadNotif = document.querySelectorAll(
            '.notify-item[data-type="notification"][data-read="false"]'
        ).length;
        const unreadAll = document.querySelectorAll(
            '.notify-item[data-read="false"]'
        ).length;

        // Bell badge: chỉ đếm notification_logs
        if (topBadge) {
            if (unreadNotif > 0) {
                topBadge.textContent = unreadNotif > 9 ? '9+' : String(unreadNotif);
                topBadge.classList.remove('d-none');
            } else {
                topBadge.classList.add('d-none');
            }
        }

        // Nút "đánh dấu tất cả": disable khi không còn mục nào chưa đọc
        if (markAllBtn) markAllBtn.disabled = unreadAll === 0;

        // Subtitle: chỉ hiện số thông báo notification_logs chưa đọc
        if (statusSubtitle) {
            const base = `${totalCount} mục &bull; `;
            statusSubtitle.innerHTML = unreadNotif > 0
                ? base + `<span class="text-primary fw-bold">${unreadNotif} thông báo chưa đọc</span>`
                : base + `<span class="text-success fw-semibold">Đã đọc tất cả thông báo</span>`;
        }
    }

    // ── Hàm helper cho modal ─────────────────────────────────────────────────
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function fmtDate(v) {
        if (!v) return '';
        const d = new Date((String(v)).replace(' ', 'T'));
        return isNaN(d) ? v : d.toLocaleDateString('vi-VN');
    }

    function fmtDateTime(v) {
        if (!v) return '';
        const d = new Date((String(v)).replace(' ', 'T'));
        return isNaN(d) ? v : d.toLocaleString('vi-VN', { hour12: false });
    }

    function srcCls(s) {
        const m = {
            'BAN CÁN SỰ':    'source-badge-bcs',
            'GIẢNG VIÊN':    'source-badge-lecturer',
            'PHÒNG ĐÀO TẠO': 'source-badge-dept',
            'ĐIỂM DANH':     'source-badge-attendance',
            'XẾP LỚP':       'source-badge-schedule',
        };
        return m[s] ?? 'source-badge-default';
    }

    function tr(label, valHtml) {
        if (!valHtml) return '';
        return `<tr><td class="text-muted fw-semibold pe-3 align-top text-nowrap" style="width:38%">${esc(label)}</td><td>${valHtml}</td></tr>`;
    }

    // ── Xây nội dung modal theo loại ─────────────────────────────────────────
    function buildBody(d) {
        const srcBadge = `<span class="badge source-badge ${srcCls(d.source || '')}">${esc(d.source)}</span>`;
        const timeStr  = d.type === 'notification' ? fmtDateTime(d.time) : fmtDate(d.time);
        const header   = `<div class="d-flex align-items-center gap-2 mb-3">${srcBadge}<small class="text-muted">${timeStr}</small></div>`;

        if (d.type === 'notification') {
            const msg = d.message || d.content || '';
            return header + `<p class="mb-0" style="white-space:pre-line">${esc(msg)}</p>`;
        }

        if (d.type === 'document') {
            return header + `
                <table class="table table-sm table-borderless mb-3">
                    ${tr('Tên tài liệu', esc(d.doc_title))}
                    ${tr('Loại',         esc(d.category))}
                    ${tr('Môn học',      esc(d.subject_name))}
                    ${tr('Người đăng',   esc(d.uploader_name))}
                    ${tr('Ghi chú',      esc(d.note))}
                    ${tr('Ngày đăng',    fmtDate(d.time))}
                </table>
                <a href="documents.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-folder2-open me-1"></i>Xem kho tài liệu
                </a>`;
        }

        if (d.type === 'attendance') {
            const attColors = { 1: 'success', 2: 'warning', 3: 'danger' };
            const sc = attColors[d.att_status] || 'secondary';
            const evColors = { Pending: 'warning', Approved: 'success', Rejected: 'danger' };
            const ec = evColors[d.evidence_status] || 'secondary';
            const statusBadge = `<span class="badge bg-${sc}">${esc(d.att_label || '')}</span>`;
            const evBadge = d.evidence_label
                ? `<span class="badge bg-${ec} bg-opacity-10 text-${ec} border border-${ec}">${esc(d.evidence_label)}</span>`
                : '';
            return header + `
                <table class="table table-sm table-borderless mb-3">
                    ${tr('Môn học',      esc(d.subject_name))}
                    ${tr('Ngày',         fmtDate(d.time))}
                    ${tr('Trạng thái',   statusBadge)}
                    ${tr('Phòng học',    esc(d.room))}
                    ${d.evidence_label ? tr('Minh chứng', evBadge) : ''}
                </table>
                <a href="my-attendance.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-person-lines-fill me-1"></i>Xem điểm danh của tôi
                </a>`;
        }

        if (d.type === 'schedule') {
            const codeHtml = d.subject_code
                ? ` <small class="text-muted">(${esc(d.subject_code)})</small>` : '';
            return header + `
                <table class="table table-sm table-borderless mb-3">
                    ${tr('Môn học',     esc(d.subject_name) + codeHtml)}
                    ${tr('Lớp',         esc(d.class_name))}
                    ${tr('Giảng viên',  esc(d.teacher_name))}
                    ${tr('Học kỳ',      esc(d.semester_label))}
                    ${tr('Bắt đầu',     fmtDate(d.start_date))}
                    ${tr('Kết thúc',    fmtDate(d.end_date))}
                </table>
                <a href="schedule.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-calendar-week me-1"></i>Xem lịch học
                </a>`;
        }

        return header + `<p class="mb-0">${esc(d.content || '')}</p>`;
    }

    // ── Mở modal khi click bất kỳ item nào ──────────────────────────────────
    function openDetail(item) {
        let d = {};
        try { d = JSON.parse(item.dataset.detail || '{}'); } catch (e) { /* ignore */ }

        const type = d.type || item.dataset.type || 'notification';

        // Cập nhật header màu theo loại
        const headerEl = document.getElementById('notifyDetailHeader');
        headerEl.className = 'modal-header text-white ' + (headerColorMap[type] || 'bg-primary');
        document.getElementById('notifyDetailTitle').textContent = d.title || 'Chi tiết';

        // Điền nội dung
        document.getElementById('notifyDetailBody').innerHTML = buildBody(d);

        // Đánh dấu đã đọc khi click
        if (item.dataset.read === 'false') {
            if (type === 'notification') {
                markRead(parseInt(item.dataset.id, 10));
            } else {
                const key = item.dataset.itemKey;
                if (key) localStorage.setItem('feed_' + key, '1');
            }
            setItemRead(item);
            updateUnreadCounter();
            applyFilters();
        }

        detailModal.show();
    }

    // ── Gắn sự kiện ─────────────────────────────────────────────────────────
    document.querySelectorAll('.notify-item').forEach(item => {
        item.addEventListener('click', () => openDetail(item));
    });

    searchInput?.addEventListener('input', applyFilters);
    readFilter?.addEventListener('change', applyFilters);

    clearBtn?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (readFilter)  readFilter.value  = 'all';
        typeChips.forEach(c => c.classList.remove('active'));
        document.querySelector('.type-chip[data-type="all"]')?.classList.add('active');
        activeType = 'all';
        applyFilters();
    });

    markAllBtn?.addEventListener('click', markAllAsRead);

    // Khôi phục trạng thái đã đọc từ localStorage cho tài liệu & điểm danh
    document.querySelectorAll('.notify-item').forEach(item => {
        const key = item.dataset.itemKey;
        if (key && localStorage.getItem('feed_' + key)) setItemRead(item);
    });

    updateUnreadCounter();
})();
</script>
</body>
</html>
