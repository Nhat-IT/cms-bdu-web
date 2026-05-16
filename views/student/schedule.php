<?php
/**
 * CMS BDU - Lịch học sinh viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId       = (int)($_SESSION['user_id'] ?? 0);
$studentMssv  = trim((string)($_SESSION['username'] ?? ''));  // MSSV = username
$pageTitle = 'Lịch học';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/schedule.css'];
$extraJs = ['student/student-layout.js'];

function studentSemesterLabel($semesterName, $academicYear): string {
    $name = strtoupper(trim((string)$semesterName));
    $year = trim((string)$academicYear);
    if (preg_match('/^(HK)?\s*([123])$/i', $name, $m)) {
        return 'Học kỳ ' . $m[2] . ($year !== '' ? (' - ' . $year) : '');
    }
    return trim((string)$semesterName . ($year !== '' ? (' - ' . $year) : ''));
}

$semesters = db_fetch_all(
    "SELECT id, semester_name, academic_year, start_date, end_date
     FROM semesters
     ORDER BY academic_year DESC,
              FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
              start_date DESC"
);

$today = date('Y-m-d');

// Tìm học kỳ hiện tại
$currentSemesterId = 0;
foreach ($semesters as $sem) {
    if (($sem['start_date'] ?? '') <= $today && $today <= ($sem['end_date'] ?? '')) {
        $currentSemesterId = (int)$sem['id'];
        break;
    }
}
// Nếu chưa vào học kỳ nào: chọn học kỳ gần nhất sắp bắt đầu, nếu không có thì học kỳ gần nhất đã qua
if ($currentSemesterId === 0 && !empty($semesters)) {
    $nearest = null;
    $nearestDiff = PHP_INT_MAX;
    foreach ($semesters as $sem) {
        $diff = abs(strtotime($sem['start_date'] ?? $today) - strtotime($today));
        if ($diff < $nearestDiff) {
            $nearestDiff = $diff;
            $nearest = $sem;
        }
    }
    $currentSemesterId = $nearest ? (int)$nearest['id'] : (int)$semesters[0]['id'];
}

// Đưa học kỳ hiện tại lên đầu danh sách dropdown
usort($semesters, function ($a, $b) use ($currentSemesterId) {
    $aIsCurrent = ((int)$a['id'] === $currentSemesterId) ? 0 : 1;
    $bIsCurrent = ((int)$b['id'] === $currentSemesterId) ? 0 : 1;
    return $aIsCurrent - $bIsCurrent;
});

$selectedSemesterId = (int)($_GET['semester_id'] ?? 0);
if ($selectedSemesterId <= 0) {
    $selectedSemesterId = $currentSemesterId;
}

$selectedSemester = null;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $selectedSemesterId) {
        $selectedSemester = $sem;
        break;
    }
}
if ($selectedSemester === null && !empty($semesters)) {
    $selectedSemester = $semesters[0];
    $selectedSemesterId = (int)$selectedSemester['id'];
}

$semesterStartTs = strtotime((string)($selectedSemester['start_date'] ?? '')) ?: strtotime($today);
// +86399 để tính hết ngày cuối (23:59:59), tránh mất tuần cuối cùng
$semesterEndTs = (strtotime((string)($selectedSemester['end_date'] ?? '')) ?: $semesterStartTs) + 86399;
$todayTs = strtotime($today);

// Tuần luôn bắt đầu từ Thứ 2 (Mon-Sun)
$semesterStartDow = (int)date('N', $semesterStartTs); // 1=Mon, 7=Sun
$firstMonday = $semesterStartTs - (($semesterStartDow - 1) * 86400);
$semesterWeeks = max(1, (int)floor(($semesterEndTs - $firstMonday) / (7 * 86400)) + 1);

$currentWeek = max(1, min($semesterWeeks, (int)floor(($todayTs - $firstMonday) / (7 * 86400)) + 1));

$selectedWeek = (int)($_GET['week'] ?? $currentWeek);
if ($selectedWeek < 1) $selectedWeek = 1;
if ($selectedWeek > $semesterWeeks) $selectedWeek = $semesterWeeks;

$weekStartTs = $firstMonday + (($selectedWeek - 1) * 7 * 86400);
$weekEndTs = $weekStartTs + (6 * 86400);
$weekStartYmd = date('Y-m-d', $weekStartTs);
$weekEndYmd = date('Y-m-d', $weekEndTs);
$weekStartText = date('d/m/Y', $weekStartTs);
$weekEndText = date('d/m/Y', $weekEndTs);

$scheduleData = [];
if ($selectedSemesterId > 0) {
    // Khớp theo student_id (có tài khoản) HOẶC mssv (chỉ có trong danh sách)
    // DISTINCT tránh trùng khi cùng SV có cả 2 dòng trong bảng đăng ký
    $scheduleData = db_fetch_all(
        "SELECT DISTINCT csg.day_of_week, csg.start_period, csg.end_period, csg.room,
                csg.group_code, s.subject_name, s.subject_code,
                COALESCE(maint.full_name, t.full_name) AS teacher_name,
                cs.start_date, cs.end_date, 0 AS is_extra, NULL AS extra_date,
                ssr.class_name
         FROM student_subject_registration ssr
         JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
         JOIN class_subjects cs ON csg.class_subject_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN users t ON cs.teacher_id = t.id
         LEFT JOIN users maint ON csg.main_teacher_id = maint.id
         WHERE (ssr.student_id = ? OR ssr.mssv = ?)
           AND ssr.status = 'Đang học'
           AND cs.semester_id = ?
           AND csg.is_extra = 0
           AND cs.start_date <= ?
           AND cs.end_date >= ?
         UNION ALL
         SELECT DISTINCT csg.day_of_week, csg.start_period, csg.end_period, csg.room,
                csg.group_code, s.subject_name, s.subject_code,
                COALESCE(maint.full_name, t.full_name) AS teacher_name,
                cs.start_date, cs.end_date, 1 AS is_extra, csg.extra_date,
                ssr.class_name
         FROM student_subject_registration ssr
         JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
         JOIN class_subjects cs ON csg.class_subject_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN users t ON cs.teacher_id = t.id
         LEFT JOIN users maint ON csg.main_teacher_id = maint.id
         WHERE (ssr.student_id = ? OR ssr.mssv = ?)
           AND ssr.status = 'Đang học'
           AND cs.semester_id = ?
           AND csg.is_extra = 1
           AND csg.extra_date BETWEEN ? AND ?
         ORDER BY day_of_week, start_period",
        [$userId, $studentMssv, $selectedSemesterId, $weekEndYmd, $weekStartYmd,
         $userId, $studentMssv, $selectedSemesterId, $weekStartYmd, $weekEndYmd]
    );
}

$unreadNotifications = (int)db_count(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);

$startTimes = [
    1 => '07:00',
    2 => '07:45',
    3 => '08:30',
    4 => '09:15',
    5 => '10:00',
    6 => '13:00',
    7 => '13:45',
    8 => '14:30',
    9 => '15:15',
    10 => '16:00',
    11 => '17:45',
    12 => '18:30',
    13 => '19:15',
    14 => '20:00'
];

$scheduleByStart = [];
$scheduledCount = 0;
foreach ($scheduleData as $item) {
    $day = (int)($item['day_of_week'] ?? 0);
    $start = (int)($item['start_period'] ?? 0);
    $end = (int)($item['end_period'] ?? 0);
    if ($day < 2 || $day > 8 || $start < 1 || $start > 14 || $end < $start || $end > 14) {
        continue;
    }
    $scheduledCount++;
    if (!isset($scheduleByStart[$day])) {
        $scheduleByStart[$day] = [];
    }
    if (!isset($scheduleByStart[$day][$start])) {
        $scheduleByStart[$day][$start] = [];
    }
    $scheduleByStart[$day][$start][] = $item;
}

$dayDates = [];
for ($i = 0; $i < 7; $i++) {
    $dayDates[$i] = date('d/m', $weekStartTs + ($i * 86400));
}

// Cột ngày hôm nay (2=T2...8=CN), 0 nếu hôm nay không thuộc tuần đang xem
$todayDayOfWeek = 0;
if ($todayTs >= $weekStartTs && $todayTs <= $weekEndTs) {
    $todayDayOfWeek = (int)date('N', $todayTs) + 1; // Mon(1)→2 ... Sun(7)→8
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">LỊCH HỌC SINH VIÊN</h5>
        </div>

        <div class="d-flex align-items-center text-white">
            <?php include_once __DIR__ . '/../../layouts/notification-bell.php'; ?>
        </div>
    </div>

    <div class="p-4">
        <div class="filter-section mb-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-12 col-md-4 col-lg-3">
                    <select class="form-select form-select-sm fw-bold text-dark shadow-sm border-secondary" name="semester_id" id="semesterSelect" onchange="this.form.submit()">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= (int)$sem['id'] ?>" <?= (int)$sem['id'] === (int)$selectedSemesterId ? 'selected' : '' ?>>
                                <?= e(studentSemesterLabel($sem['semester_name'] ?? '', $sem['academic_year'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5 col-lg-5">
                    <select class="form-select form-select-sm fw-bold text-primary shadow-sm border-primary" name="week" id="weekSelect" onchange="this.form.submit()">
                        <?php for ($week = 1; $week <= $semesterWeeks; $week++):
                            $wStart = $firstMonday + (($week - 1) * 7 * 86400);
                            $wEnd   = $wStart + (6 * 86400);
                            $isCurrent = ($week === $currentWeek && $selectedSemesterId === $currentSemesterId);
                        ?>
                            <option value="<?= $week ?>" <?= $week === $selectedWeek ? 'selected' : '' ?>>
                                Tuần <?= $week ?> [<?= date('d/m/Y', $wStart) ?> - <?= date('d/m/Y', $wEnd) ?>]<?= $isCurrent ? ' ◀ Tuần hiện tại' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 col-lg-4 text-md-end">
                    <?php $prevWeek = max(1, $selectedWeek - 1); $nextWeek = min($semesterWeeks, $selectedWeek + 1); ?>
                    <a class="btn btn-outline-secondary btn-sm" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $prevWeek ?>"><i class="bi bi-chevron-left"></i></a>
                    <a class="btn btn-outline-secondary btn-sm" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $nextWeek ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
            </form>
            <div class="small text-muted mt-2">Đang xem tuần <?= $selectedWeek ?>: <?= e($weekStartText) ?> - <?= e($weekEndText) ?></div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="schedule-wrapper">
                    <table class="table master-schedule-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px;">
                                    <a class="week-nav-btn" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $prevWeek ?>" title="Tuần trước">
                                        <i class="bi bi-arrow-left fs-5"></i>
                                    </a>
                                </th>
                                <?php
                                $dayNames = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
                                for ($col = 0; $col < 7; $col++):
                                    $colDay = $col + 2;
                                    $isToday = ($todayDayOfWeek === $colDay);
                                ?>
                                <th class="<?= $isToday ? 'today-header' : '' ?>">
                                    <?= $dayNames[$col] ?><br>
                                    <span class="fw-normal <?= $isToday ? 'today-date' : 'text-muted' ?>"><?= e($dayDates[$col]) ?></span>
                                    <?php if ($isToday): ?><br><span class="today-badge">Hôm nay</span><?php endif; ?>
                                </th>
                                <?php endfor; ?>
                                <th style="width:80px;">
                                    <a class="week-nav-btn" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $nextWeek ?>" title="Tuần sau">
                                        <i class="bi bi-arrow-right fs-5"></i>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rowspanTracker = [2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0];
                            for ($period = 1; $period <= 14; $period++):
                            ?>
                            <tr>
                                <td class="edge-col">Tiết <?= $period ?></td>
                                <?php for ($day = 2; $day <= 8; $day++): ?>
                                    <?php
                                    if ($rowspanTracker[$day] > 0) {
                                        $rowspanTracker[$day]--;
                                        continue;
                                    }

                                    $todayClass = ($todayDayOfWeek === $day) ? ' today-col' : '';
                                    $slotItems = $scheduleByStart[$day][$period] ?? [];
                                    if (empty($slotItems)) {
                                        echo '<td class="master-cell align-middle p-1' . $todayClass . '"></td>';
                                        continue;
                                    }

                                    $item = $slotItems[0];
                                    $start = (int)($item['start_period'] ?? $period);
                                    $end = (int)($item['end_period'] ?? $period);
                                    $rowspan = max(1, min(14, $end) - $start + 1);
                                    $rowspanTracker[$day] = $rowspan - 1;
                                    $groupCode = (string)($item['group_code'] ?? 'N1');
                                    // Chuẩn hóa: N1, N2... (bỏ "Nhóm X")
                                    $groupLabel = preg_replace('/^Nh[oó]m\s*/iu', 'N', $groupCode);
                                    if ($groupLabel === $groupCode && !preg_match('/^N\d+$/i', $groupCode)) {
                                        $groupLabel = 'N' . ltrim($groupCode, 'N');
                                    }
                                    ?>
                                    <td class="master-cell align-middle p-1<?= $todayClass ?>" rowspan="<?= $rowspan ?>">
                                        <div class="subject-block">
                                            <div class="subject-title"><?= e($item['subject_name'] ?? 'Môn học') ?></div>
                                            <?php if (!empty($item['subject_code'])): ?>
                                                <div class="subject-code"><?= e($item['subject_code']) ?></div>
                                            <?php endif; ?>
                                            <div class="subject-meta">Lớp: <b class="text-primary"><?= e($item['class_name'] ?? '--') ?></b> · <?= e($groupLabel) ?></div>
                                            <div class="subject-meta">Phòng: <b><?= e($item['room'] ?: '--') ?></b></div>
                                            <div class="subject-meta">GV: <?= e($item['teacher_name'] ?: 'Chưa phân công') ?></div>
                                            <?php if (count($slotItems) > 1): ?>
                                                <span class="conflict-badge">+<?= count($slotItems) - 1 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endfor; ?>
                                <td class="edge-col"><?= e($startTimes[$period] ?? '') ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($scheduledCount === 0): ?>
            <div class="alert alert-light border mt-3 mb-0 text-muted text-center">
                <i class="bi bi-calendar-x me-2"></i>Bạn chưa có lịch học trong tuần đã chọn.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<?php foreach ($extraJs as $js): ?>
    <script src="../../public/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
