<?php
/**
 * CMS BDU - Lịch Học Sinh Viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Lịch Học';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/schedule.css'];
$extraJs = ['student/student-layout.js', 'student/schedule.js'];

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT id, semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy tuần hiện tại (tính từ ngày bắt đầu học kỳ)
$currentWeek = 1;
$weekStart = date('d/m/Y');
$weekEnd = date('d/m/Y');

if ($currentSemester && $currentSemester['start_date']) {
    $semesterStart = strtotime($currentSemester['start_date']);
    $today = time();
    $daysDiff = floor(($today - $semesterStart) / (60 * 60 * 24));
    $currentWeek = floor($daysDiff / 7) + 1;
    
    // Tính ngày bắt đầu và kết thúc của tuần hiện tại
    $daysSinceWeekStart = ($currentWeek - 1) * 7;
    $weekStartTimestamp = $semesterStart + ($daysSinceWeekStart * 86400);
    $weekEndTimestamp = $weekStartTimestamp + (6 * 86400);
    $weekStart = date('d/m/Y', $weekStartTimestamp);
    $weekEnd = date('d/m/Y', $weekEndTimestamp);
}

// Lấy lịch học của sinh viên trong tuần
$scheduleData = [];
$dayNames = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật'];
$dayDates = [];

for ($i = 0; $i < 7; $i++) {
    $dayTimestamp = $weekStartTimestamp + ($i * 86400);
    $dayDates[$i] = date('d/m', $dayTimestamp);
}

$stmt = $pdo->prepare("
    SELECT csg.day_of_week, csg.start_period, csg.end_period, csg.room,
           s.subject_name, s.subject_code, t.full_name as teacher_name, csg.group_code,
           cs.start_date, cs.end_date
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users t ON cs.teacher_id = t.id
    WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
    ORDER BY csg.day_of_week, csg.start_period
");
$stmt->execute([$userId]);
$scheduleData = $stmt->fetchAll();

// Lấy số thông báo chưa đọc
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetch()['total'];
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
                LỊCH HỌC SINH VIÊN
            </h5>
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
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4 col-lg-3">
                    <select class="form-select fw-bold text-dark shadow-sm border-secondary" disabled>
                        <option selected><?= e($currentSemester['semester_name'] ?? 'HK2') ?> (<?= e($currentSemester['academic_year'] ?? '2025 - 2026') ?>)</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-5">
                    <select class="form-select fw-bold text-primary shadow-sm border-primary" id="weekSelect">
                        <option value="<?= $currentWeek ?>" selected>Tuần <?= $currentWeek ?> [từ <?= $weekStart ?> đến <?= $weekEnd ?>]</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 col-lg-4 text-md-end">
                    <button class="btn btn-outline-danger fw-bold shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> In Lịch
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="schedule-table-wrapper">
                    <table class="table schedule-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 85px; vertical-align: middle;"><i class="bi bi-arrow-left fs-5 week-nav-btn" title="Tuần trước" onclick="navigateWeek(-1)"></i></th>
                                <th>Thứ 2 <br><span class="fw-normal text-muted">(<?= $dayDates[0] ?>)</span></th>
                                <th>Thứ 3 <br><span class="fw-normal text-muted">(<?= $dayDates[1] ?>)</span></th>
                                <th>Thứ 4 <br><span class="fw-normal text-muted">(<?= $dayDates[2] ?>)</span></th>
                                <th>Thứ 5 <br><span class="fw-normal text-muted">(<?= $dayDates[3] ?>)</span></th>
                                <th>Thứ 6 <br><span class="fw-normal text-muted">(<?= $dayDates[4] ?>)</span></th>
                                <th>Thứ 7 <br><span class="fw-normal text-muted">(<?= $dayDates[5] ?>)</span></th>
                                <th>Chủ Nhật <br><span class="fw-normal text-muted">(<?= $dayDates[6] ?>)</span></th>
                                <th style="width: 85px; vertical-align: middle;"><i class="bi bi-arrow-right fs-5 week-nav-btn" title="Tuần tiếp" onclick="navigateWeek(1)"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Tạo cấu trúc lịch học
                            $scheduleGrid = [];
                            foreach ($scheduleData as $schedule) {
                                $day = $schedule['day_of_week'];
                                if (!isset($scheduleGrid[$day])) $scheduleGrid[$day] = [];
                                $scheduleGrid[$day][] = $schedule;
                            }
                            
                            // Tiết 1-5 (Sáng)
                            for ($period = 1; $period <= 5; $period++): ?>
                                <tr>
                                    <td class="edge-col">Tiết <?= $period ?></td>
                                    <?php for ($day = 2; $day <= 8; $day++): 
                                        $dayIndex = $day - 2;
                                        $cellContent = '';
                                        $rowspan = '';
                                        
                                        // Tìm môn học cho tiết này
                                        foreach ($scheduleGrid[$day] ?? [] as $schedule) {
                                            if ($schedule['start_period'] == $period) {
                                                $rowspan = $schedule['end_period'] - $schedule['start_period'] + 1;
                                                $timeStart = sprintf('%02d:00', 7 + ($period - 1) * 0.75);
                                                $timeEnd = sprintf('%02d:%02d', 7 + ($schedule['end_period'] - 1) * 0.75 + 0.75, (($schedule['end_period'] - 1) * 45) % 60);
                                                $cellContent = '<div class="subject-block" title="' . e($schedule['subject_name']) . '">
                                                    <div class="subject-title">' . e($schedule['subject_name']) . ' (' . e($schedule['subject_code']) . ')</div>
                                                    <div>Nhóm: ' . e($schedule['group_code']) . '</div>
                                                    <div>Phòng: ' . e($schedule['room'] ?? 'Chưa phòng') . '</div>
                                                    <div>GV: ' . e($schedule['teacher_name'] ?? 'Chưa phân công') . '</div>
                                                    <div class="mt-1 fw-bold text-danger"><i class="bi bi-clock"></i> ' . $timeStart . ' - ' . $timeEnd . '</div>
                                                </div>';
                                            }
                                        }
                                    ?>
                                        <td <?= $rowspan > 1 ? 'rowspan="' . $rowspan . '"' : '' ?>>
                                            <?= $cellContent ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                            
                            <?php
                            // Tiết 6-10 (Chiều)
                            $morningPeriods = 5;
                            for ($period = 6; $period <= 10; $period++): 
                                $adjustedPeriod = $period - $morningPeriods;
                            ?>
                                <tr>
                                    <td class="edge-col">Tiết <?= $period ?></td>
                                    <?php for ($day = 2; $day <= 8; $day++): 
                                        $cellContent = '';
                                        $rowspan = '';
                                        
                                        foreach ($scheduleGrid[$day] ?? [] as $schedule) {
                                            if ($schedule['start_period'] == $period) {
                                                $rowspan = $schedule['end_period'] - $schedule['start_period'] + 1;
                                                $timeStart = sprintf('%02d:%02d', 13 + ($adjustedPeriod - 1) * 0.75, (($adjustedPeriod - 1) * 45) % 60);
                                                $timeEnd = sprintf('%02d:%02d', 13 + ($schedule['end_period'] - 6) * 0.75 + 0.75 - 1, (($schedule['end_period'] - 5) * 45) % 60);
                                                $cellContent = '<div class="subject-block" title="' . e($schedule['subject_name']) . '">
                                                    <div class="subject-title">' . e($schedule['subject_name']) . ' (' . e($schedule['subject_code']) . ')</div>
                                                    <div>Nhóm: ' . e($schedule['group_code']) . '</div>
                                                    <div>Phòng: ' . e($schedule['room'] ?? 'Chưa phòng') . '</div>
                                                    <div>GV: ' . e($schedule['teacher_name'] ?? 'Chưa phân công') . '</div>
                                                    <div class="mt-1 fw-bold text-danger"><i class="bi bi-clock"></i> ' . $timeStart . ' - ' . $timeEnd . '</div>
                                                </div>';
                                            }
                                        }
                                    ?>
                                        <td <?= $rowspan > 1 ? 'rowspan="' . $rowspan . '"' : '' ?>>
                                            <?= $cellContent ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>

                            <?php
                            // Tiết 11-15 (Tối)
                            $afternoonPeriods = 10;
                            for ($period = 11; $period <= 15; $period++): 
                                $adjustedPeriod = $period - $afternoonPeriods;
                            ?>
                                <tr>
                                    <td class="edge-col">Tiết <?= $period ?></td>
                                    <?php for ($day = 2; $day <= 8; $day++): 
                                        $cellContent = '';
                                        $rowspan = '';
                                        
                                        foreach ($scheduleGrid[$day] ?? [] as $schedule) {
                                            if ($schedule['start_period'] == $period) {
                                                $rowspan = $schedule['end_period'] - $schedule['start_period'] + 1;
                                                $timeStart = sprintf('%02d:%02d', 16 + ($adjustedPeriod - 1) * 0.75 + ($adjustedPeriod > 1 ? 0 : 0), (($adjustedPeriod - 1) * 45) % 60);
                                                $timeEnd = sprintf('%02d:%02d', 17 + ($schedule['end_period'] - 11) * 0.75, (($schedule['end_period'] - 10) * 45) % 60);
                                                $cellContent = '<div class="subject-block" title="' . e($schedule['subject_name']) . '">
                                                    <div class="subject-title">' . e($schedule['subject_name']) . ' (' . e($schedule['subject_code']) . ')</div>
                                                    <div>Nhóm: ' . e($schedule['group_code']) . '</div>
                                                    <div>Phòng: ' . e($schedule['room'] ?? 'Chưa phòng') . '</div>
                                                    <div>GV: ' . e($schedule['teacher_name'] ?? 'Chưa phân công') . '</div>
                                                </div>';
                                            }
                                        }
                                    ?>
                                        <td <?= $rowspan > 1 ? 'rowspan="' . $rowspan . '"' : '' ?>>
                                            <?= $cellContent ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
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
