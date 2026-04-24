<?php
/**
 * CMS BDU - Lịch học sinh viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
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

$currentSemester = null;
$today = date('Y-m-d');
foreach ($semesters as $sem) {
    $start = $sem['start_date'] ?? '';
    $end = $sem['end_date'] ?? '';
    if ($start !== '' && $end !== '' && $today >= $start && $today <= $end) {
        $currentSemester = $sem;
        break;
    }
}
if ($currentSemester === null && !empty($semesters)) {
    $currentSemester = $semesters[0];
}

$selectedSemesterId = (int)($_GET['semester_id'] ?? ($currentSemester['id'] ?? 0));
$selectedSemester = $currentSemester;
foreach ($semesters as $sem) {
    if ((int)$sem['id'] === $selectedSemesterId) {
        $selectedSemester = $sem;
        break;
    }
}

$semesterStartTs = strtotime((string)($selectedSemester['start_date'] ?? '')) ?: strtotime(date('Y-m-d'));
$semesterEndTs = strtotime((string)($selectedSemester['end_date'] ?? '')) ?: $semesterStartTs;
$semesterWeeks = max(1, (int)floor((($semesterEndTs - $semesterStartTs) / 86400) / 7) + 1);

$currentWeek = (int)floor((strtotime(date('Y-m-d')) - $semesterStartTs) / (7 * 86400)) + 1;
if ($currentWeek < 1) {
    $currentWeek = 1;
}
if ($currentWeek > $semesterWeeks) {
    $currentWeek = $semesterWeeks;
}

$selectedWeek = (int)($_GET['week'] ?? $currentWeek);
if ($selectedWeek < 1) {
    $selectedWeek = 1;
}
if ($selectedWeek > $semesterWeeks) {
    $selectedWeek = $semesterWeeks;
}

$weekStartTs = $semesterStartTs + (($selectedWeek - 1) * 7 * 86400);
$weekEndTs = $weekStartTs + (6 * 86400);
$weekStartYmd = date('Y-m-d', $weekStartTs);
$weekEndYmd = date('Y-m-d', $weekEndTs);
$weekStartText = date('d/m/Y', $weekStartTs);
$weekEndText = date('d/m/Y', $weekEndTs);

$scheduleData = [];
if ($selectedSemesterId > 0) {
    $scheduleData = db_fetch_all(
        "SELECT csg.day_of_week, csg.start_period, csg.end_period, csg.room,
                csg.group_code, s.subject_name, s.subject_code,
                COALESCE(st.full_name, t.full_name) AS teacher_name,
                cs.start_date, cs.end_date
         FROM student_subject_registration ssr
         JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
         JOIN class_subjects cs ON csg.class_subject_id = cs.id
         JOIN subjects s ON cs.subject_id = s.id
         LEFT JOIN users t ON cs.teacher_id = t.id
         LEFT JOIN users st ON csg.sub_teacher_id = st.id
         WHERE ssr.student_id = ?
           AND ssr.status = 'Đang học'
           AND cs.semester_id = ?
           AND (cs.start_date IS NULL OR cs.start_date <= ?)
           AND (cs.end_date IS NULL OR cs.end_date >= ?)
         ORDER BY csg.day_of_week, csg.start_period",
        [$userId, $selectedSemesterId, $weekEndYmd, $weekStartYmd]
    );
}

$unreadNotifications = (int)db_count(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);

$scheduleGrid = [];
foreach ($scheduleData as $schedule) {
    $day = (int)($schedule['day_of_week'] ?? 0);
    if ($day < 2 || $day > 8) {
        continue;
    }
    if (!isset($scheduleGrid[$day])) {
        $scheduleGrid[$day] = [];
    }
    $scheduleGrid[$day][] = $schedule;
}

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

function periodTimeRange(int $startPeriod, int $endPeriod): string {
    $slots = [
        1 => ['07:00', '07:45'],
        2 => ['07:50', '08:35'],
        3 => ['08:40', '09:25'],
        4 => ['09:35', '10:20'],
        5 => ['10:25', '11:10'],
        6 => ['13:00', '13:45'],
        7 => ['13:50', '14:35'],
        8 => ['14:40', '15:25'],
        9 => ['15:35', '16:20'],
        10 => ['16:25', '17:10'],
        11 => ['17:30', '18:15'],
        12 => ['18:20', '19:05'],
        13 => ['19:10', '19:55'],
        14 => ['20:00', '20:45']
    ];

    if (!isset($slots[$startPeriod]) || !isset($slots[$endPeriod])) {
        return '';
    }
    return $slots[$startPeriod][0] . ' - ' . $slots[$endPeriod][1];
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
            <a href="notifications-all.php" class="text-white text-decoration-none" title="Xem thông báo">
                <i class="bi bi-bell fs-5 text-white position-relative cursor-pointer">
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </i>
            </a>
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
                <div class="col-12 col-md-5 col-lg-4">
                    <select class="form-select form-select-sm fw-bold text-primary shadow-sm border-primary" name="week" id="weekSelect" onchange="this.form.submit()">
                        <?php for ($week = 1; $week <= $semesterWeeks; $week++):
                            $start = $semesterStartTs + (($week - 1) * 7 * 86400);
                            $end = $start + (6 * 86400);
                        ?>
                            <option value="<?= $week ?>" <?= $week === $selectedWeek ? 'selected' : '' ?>>
                                Tuần <?= $week ?> [<?= date('d/m/Y', $start) ?> - <?= date('d/m/Y', $end) ?>]
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 col-lg-3 text-md-end">
                    <?php $prevWeek = max(1, $selectedWeek - 1); $nextWeek = min($semesterWeeks, $selectedWeek + 1); ?>
                    <a class="btn btn-outline-secondary btn-sm" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $prevWeek ?>"><i class="bi bi-chevron-left"></i></a>
                    <a class="btn btn-outline-secondary btn-sm" href="?semester_id=<?= (int)$selectedSemesterId ?>&week=<?= $nextWeek ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="col-12 col-md-12 col-lg-2 text-lg-end">
                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i>In lịch
                    </button>
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
                                <th>Thứ 2<br><span class="fw-normal text-muted"><?= e($dayDates[0]) ?></span></th>
                                <th>Thứ 3<br><span class="fw-normal text-muted"><?= e($dayDates[1]) ?></span></th>
                                <th>Thứ 4<br><span class="fw-normal text-muted"><?= e($dayDates[2]) ?></span></th>
                                <th>Thứ 5<br><span class="fw-normal text-muted"><?= e($dayDates[3]) ?></span></th>
                                <th>Thứ 6<br><span class="fw-normal text-muted"><?= e($dayDates[4]) ?></span></th>
                                <th>Thứ 7<br><span class="fw-normal text-muted"><?= e($dayDates[5]) ?></span></th>
                                <th>Chủ nhật<br><span class="fw-normal text-muted"><?= e($dayDates[6]) ?></span></th>
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

                                    $slotItems = $scheduleByStart[$day][$period] ?? [];
                                    if (empty($slotItems)) {
                                        echo '<td class="master-cell align-middle p-1"></td>';
                                        continue;
                                    }

                                    $item = $slotItems[0];
                                    $start = (int)($item['start_period'] ?? $period);
                                    $end = (int)($item['end_period'] ?? $period);
                                    $rowspan = max(1, min(14, $end) - $start + 1);
                                    $rowspanTracker[$day] = $rowspan - 1;
                                    $timeRange = periodTimeRange($start, $end);
                                    $groupCode = (string)($item['group_code'] ?? 'N1');
                                    $groupLabel = ($groupCode === 'N1') ? 'Nhóm 1' : $groupCode;
                                    ?>
                                    <td class="master-cell align-middle p-1" rowspan="<?= $rowspan ?>">
                                        <div class="subject-block">
                                            <div class="subject-title"><?= e($item['subject_name'] ?? 'Môn học') ?><?= !empty($item['subject_code']) ? (' (' . e($item['subject_code']) . ')') : '' ?></div>
                                            <div class="subject-meta">Lớp: <b class="text-primary"><?= e($item['class_name'] ?? '--') ?></b> (<?= e($groupLabel) ?>)</div>
                                            <div class="subject-meta">Phòng: <?= e($item['room'] ?: '--') ?></div>
                                            <div class="subject-meta">GV: <?= e($item['teacher_name'] ?: 'Chưa phân công') ?></div>
                                            <?php if ($timeRange !== ''): ?>
                                                <div class="subject-room"><i class="bi bi-clock me-1"></i><?= e($timeRange) ?></div>
                                            <?php endif; ?>
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
