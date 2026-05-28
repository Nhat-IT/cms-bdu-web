<?php
/**
 * Notification bell dropdown — dùng chung cho tất cả trang student
 * Hiển thị thông báo từ nhiều nguồn: notification_logs + documents + attendance
 * Badge được cập nhật bằng PHP (server-side) và JavaScript (client-side sync)
 */

// ── 1. Thông báo từ notification_logs ───────────────────────────────────────
$bellNotifs = db_fetch_all(
    "SELECT id, title, created_at, is_read FROM notification_logs
     WHERE user_id = ? AND is_read = 0
     ORDER BY created_at DESC LIMIT 4",
    [$userId]
);
$_bellNotifCount = (int)db_count(
    "SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);

// ── 2. Tài liệu mới trong 14 ngày gần nhất ─────────────────────────────────
$studentMssv = trim((string)($_SESSION['username'] ?? ''));
$recentDocCount = 0;
if ($studentMssv !== '') {
    $recentDocs = db_fetch_all(
        "SELECT d.id, d.title, d.category, d.created_at, d.note,
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
         AND d.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         ORDER BY d.created_at DESC
         LIMIT 20",
        [$userId, $studentMssv]
    );
    $recentDocCount = count($recentDocs);
} else {
    $recentDocs = [];
}

// ── 3. Điểm danh mới trong 14 ngày gần nhất ────────────────────────────────
$recentAttCount = 0;
if ($studentMssv !== '') {
    $recentAtt = db_fetch_all(
        "SELECT DISTINCT ar.id, a_s.attendance_date,
                s.subject_name, ar.status AS att_status
         FROM attendance_records ar
         JOIN attendance_sessions a_s ON ar.session_id = a_s.id
         JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
         JOIN class_subjects cs ON csg.class_subject_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN student_subject_registration ssr_m
             ON ssr_m.class_subject_group_id = csg.id AND ssr_m.mssv = ?
         WHERE (ar.student_id = ?
                OR (ar.registration_id IS NOT NULL AND ar.registration_id = ssr_m.id))
           AND a_s.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
         ORDER BY a_s.attendance_date DESC
         LIMIT 20",
        [$studentMssv, $userId]
    );
    $recentAttCount = count($recentAtt);
} else {
    $recentAtt = [];
}

// ── Tổng hợp feed cho dropdown ─────────────────────────────────────────────
$bellFeed = [];

// Thông báo
foreach ($bellNotifs as $n) {
    $bellFeed[] = [
        'type'      => 'notification',
        'time'      => $n['created_at'] ?? null,
        'title'     => (string)($n['title'] ?? 'Thông báo'),
        'content'   => '',
        'source'    => 'THÔNG BÁO',
        'icon'      => 'bi-bell-fill',
        'color'     => 'primary',
    ];
}

// Tài liệu mới (lấy tối đa 2 item gần nhất)
$docIdx = 0;
foreach ($recentDocs as $d) {
    if ($docIdx >= 2) break;
    $upRole = strtolower((string)($d['uploader_role'] ?? ''));
    $srcLabel = match ($upRole) {
        'bcs'                    => 'BCS',
        'admin', 'support_admin' => 'PHÒNG ĐÀO TẠO',
        default                  => 'HỆ THỐNG',
    };
    $bellFeed[] = [
        'type'      => 'document',
        'time'      => $d['created_at'] ?? null,
        'title'     => 'Tài liệu: ' . mb_strimwidth((string)($d['title'] ?? 'Không tiêu đề'), 0, 50, '…'),
        'content'   => ($d['category'] ?? '') . ($d['subject_name'] ? ' — ' . $d['subject_name'] : ''),
        'source'    => $srcLabel,
        'icon'      => 'bi-file-earmark-arrow-down-fill',
        'color'     => 'info',
    ];
    $docIdx++;
}

// Điểm danh mới (lấy tối đa 2 item gần nhất)
$attMeta = [1 => 'Có mặt', 2 => 'Đi muộn', 3 => 'Vắng'];
$attIdx = 0;
foreach ($recentAtt as $ar) {
    if ($attIdx >= 2) break;
    $attCode = (int)($ar['att_status'] ?? 0);
    $attLabel = $attMeta[$attCode] ?? 'Không rõ';
    $bellFeed[] = [
        'type'      => 'attendance',
        'time'      => $ar['attendance_date'] ?? null,
        'title'     => 'Điểm danh: ' . $attLabel,
        'content'   => ($ar['subject_name'] ?? 'Môn học'),
        'source'    => 'ĐIỂM DANH',
        'icon'      => $attCode === 1 ? 'bi-person-check-fill' : ($attCode === 2 ? 'bi-clock-history' : 'bi-calendar-x-fill'),
        'color'     => $attCode === 1 ? 'success' : ($attCode === 2 ? 'warning' : 'danger'),
    ];
    $attIdx++;
}

// Sắp xếp theo thời gian giảm dần
usort($bellFeed, fn($a, $b) => strcmp((string)($b['time'] ?? ''), (string)($a['time'] ?? '')));

// Giới hạn hiển thị dropdown
$bellFeedDisplay = array_slice($bellFeed, 0, 4);

// ── Tổng số thông báo chưa đọc (unified) ────────────────────────────────────
// Sử dụng: notification_logs + documents + attendance (14 ngày gần nhất)
$_bellUnreadCount = $_bellNotifCount + $recentDocCount + $recentAttCount;
?>
<div class="dropdown">
    <a href="#" class="text-white text-decoration-none position-relative me-1 d-flex align-items-center"
       id="notifBellDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Thông báo">
        <i class="bi bi-bell fs-5"></i>
        <span id="notifBellBadge"
              class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger<?= $_bellUnreadCount === 0 ? ' d-none' : '' ?>"
              style="font-size:.65rem;">
            <?= $_bellUnreadCount > 9 ? '9+' : ($_bellUnreadCount > 0 ? $_bellUnreadCount : '') ?>
        </span>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 mt-2"
         style="min-width:320px;max-width:360px;border-radius:12px;overflow:hidden;"
         aria-labelledby="notifBellDropdown">

        <!-- Header -->
        <div class="px-3 py-2 d-flex align-items-center gap-2"
             style="background:#1565c0;color:#fff;">
            <i class="bi bi-bell-fill"></i>
            <span class="fw-bold">Thông báo mới</span>
            <span id="bellHeaderBadge"
                  class="badge bg-danger ms-auto<?= $_bellUnreadCount === 0 ? ' d-none' : '' ?>">
                <?= $_bellUnreadCount > 9 ? '9+' : ($_bellUnreadCount > 0 ? $_bellUnreadCount : '') ?>
            </span>
        </div>

        <!-- Danh sách thông báo (tất cả nguồn) -->
        <div id="bellNotifList">
        <?php if (empty($bellFeedDisplay)): ?>
            <div class="px-3 py-4 text-center text-muted small">
                <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                Không có thông báo mới
            </div>
        <?php else: ?>
            <?php
            $bellIconMap = [
                'notification' => 'bi-bell-fill',
                'document'     => 'bi-file-earmark-arrow-down-fill',
                'attendance'   => 'bi-calendar-check-fill',
            ];
            $bellColorMap = [
                'primary'   => 'bg-primary bg-opacity-10 text-primary border-primary',
                'info'      => 'bg-info bg-opacity-10 text-info border-info',
                'success'   => 'bg-success bg-opacity-10 text-success border-success',
                'warning'   => 'bg-warning bg-opacity-10 text-dark border-warning',
                'danger'    => 'bg-danger bg-opacity-10 text-danger border-danger',
            ];
            ?>
            <?php foreach ($bellFeedDisplay as $item): ?>
                <?php
                    $icon = $bellIconMap[$item['type']] ?? 'bi-bell-fill';
                    $color = $bellColorMap[$item['color']] ?? 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                ?>
                <a href="notifications-all.php" class="dropdown-item px-3 py-2 border-bottom"
                   style="white-space:normal;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge <?= $color ?> border"
                              style="font-size:.65rem;">
                            <i class="bi <?= $icon ?> me-1"></i><?= e($item['source']) ?>
                        </span>
                        <small class="text-muted" style="font-size:.72rem;">
                            <?= $item['time'] ? formatDateTime($item['time'], 'd/m/Y H:i') : '' ?>
                        </small>
                    </div>
                    <div class="fw-bold text-dark" style="font-size:.88rem;
                         overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                        <?= e($item['title']) ?>
                    </div>
                    <?php if (!empty($item['content'])): ?>
                        <small class="text-muted" style="font-size:.78rem;">
                            <?= e($item['content']) ?>
                        </small>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="px-3 py-2 text-center border-top bg-light">
            <a href="notifications-all.php" class="text-primary fw-bold text-decoration-none"
               style="font-size:.88rem;">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<script>
// Cập nhật badge thông báo - dùng PHP count làm chính, sync với localStorage
(function() {
    var _phpCount = <?= (int)$_bellUnreadCount ?>;
    var _lsCount = parseInt(localStorage.getItem('cms_unread_all') || '-1', 10);
    var _lsTs = parseInt(localStorage.getItem('cms_unread_ts') || '0', 10);

    function setBellBadge(count) {
        var badges = [
            document.getElementById('notifBellBadge'),
            document.getElementById('bellHeaderBadge')
        ];
        badges.forEach(function(el) {
            if (!el) return;
            if (count > 0) {
                el.textContent = count > 9 ? '9+' : String(count);
                el.classList.remove('d-none');
            } else {
                el.textContent = '';
                el.classList.add('d-none');
            }
        });
    }

    localStorage.setItem('cms_unread_php', String(_phpCount));

    // Dùng localStorage nếu còn mới (trong vòng 5 phút) — do notifications-all.php ghi
    var _age = Date.now() - _lsTs;
    var _useLocal = _lsCount >= 0 && _age < 300000;
    if (!_useLocal) {
        localStorage.setItem('cms_unread_all', String(_phpCount));
        localStorage.setItem('cms_unread_ts', String(Date.now()));
    }
    setBellBadge(_useLocal ? _lsCount : _phpCount);
})();
</script>
