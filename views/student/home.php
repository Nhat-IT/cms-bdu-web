<?php
/**
 * CMS BDU - Trang chủ Sinh viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId      = (int)($_SESSION['user_id'] ?? 0);
$studentMssv = trim((string)($_SESSION['username'] ?? ''));
$pageTitle = 'Tổng quan cá nhân';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/home.css'];
$extraJs = ['student/student-layout.js'];

$today = date('Y-m-d');

// Học kỳ hiện tại (dùng hàm chung từ helpers.php)
$currentSem   = getCurrentSemester();
$currentSemId = (int)($currentSem['id'] ?? 0);

// Số môn đang học trong học kỳ hiện tại
$subjectCount = db_count("
    SELECT COUNT(DISTINCT csg.class_subject_id)
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    WHERE (ssr.student_id = ? OR ssr.mssv = ?)
      AND ssr.status = 'Đang học'
      AND cs.semester_id = ?
", [$userId, $studentMssv, $currentSemId]);

// Số buổi vắng không phép trong học kỳ hiện tại
// Khớp theo student_id (có TK) hoặc registration_id qua mssv (chỉ có trong DS)
$absenceCount = db_count("
    SELECT COUNT(DISTINCT ar.id)
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    LEFT JOIN student_subject_registration ssr_m
        ON ssr_m.class_subject_group_id = csg.id AND ssr_m.mssv = ?
    WHERE cs.semester_id = ?
      AND ar.status = 3
      AND (ar.student_id = ? OR (ar.registration_id IS NOT NULL AND ar.registration_id = ssr_m.id))
", [$studentMssv, $currentSemId, $userId]);

// Số phản hồi đã xử lý
$resolvedFeedback = db_count("
    SELECT COUNT(*)
    FROM feedbacks
    WHERE student_id = ? AND status = 'Resolved'
", [$userId]);

// Môn cảnh báo vắng >= 3 trong học kỳ hiện tại
$warningSubjects = db_fetch_all("
    SELECT s.subject_name,
           COUNT(DISTINCT CASE WHEN ar.status = 3 THEN ar.id END) AS absences
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN attendance_sessions a_s ON a_s.class_subject_group_id = csg.id
    LEFT JOIN attendance_records ar ON ar.session_id = a_s.id
        AND (ar.student_id = ? OR ar.registration_id = ssr.id)
    WHERE (ssr.student_id = ? OR ssr.mssv = ?)
      AND ssr.status = 'Đang học'
      AND cs.semester_id = ?
    GROUP BY s.id, s.subject_name
    HAVING absences >= 3
", [$userId, $userId, $studentMssv, $currentSemId]);
$warningCount = count($warningSubjects);

// Lịch học hôm nay — day('N'): Mon=1…Sun=7 → DB day_of_week: Mon=2…Sun=8
$dayOfWeekDb = (int)date('N') + 1;
$todaySchedule = db_fetch_all("
    SELECT DISTINCT s.subject_name, s.subject_code,
           csg.start_period, csg.end_period, csg.room, csg.group_code,
           COALESCE(maint.full_name, t.full_name) AS teacher_name,
           0 AS is_extra
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
      AND csg.day_of_week = ?
      AND cs.start_date <= ?
      AND cs.end_date >= ?
    UNION ALL
    SELECT DISTINCT s.subject_name, s.subject_code,
           csg.start_period, csg.end_period, csg.room, csg.group_code,
           COALESCE(maint.full_name, t.full_name) AS teacher_name,
           1 AS is_extra
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
      AND csg.extra_date = ?
    ORDER BY start_period
", [$userId, $studentMssv, $currentSemId, $dayOfWeekDb, $today, $today,
   $userId, $studentMssv, $currentSemId, $today]);

// Tình trạng chuyên cần các môn trong học kỳ hiện tại
$attendanceStatus = db_fetch_all("
    SELECT s.subject_name,
           COUNT(DISTINCT CASE WHEN ar.status = 3 THEN ar.id END) AS absences,
           3 AS limit_absences
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN attendance_sessions a_s ON a_s.class_subject_group_id = csg.id
    LEFT JOIN attendance_records ar ON ar.session_id = a_s.id
        AND (ar.student_id = ? OR ar.registration_id = ssr.id)
    WHERE (ssr.student_id = ? OR ssr.mssv = ?)
      AND ssr.status = 'Đang học'
      AND cs.semester_id = ?
    GROUP BY s.id, s.subject_name
    ORDER BY absences DESC
", [$userId, $userId, $studentMssv, $currentSemId]);

// Số thông báo chưa đọc
$unreadNotifications = db_count("SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0", [$userId]);
$notice = trim((string)($_GET['notice'] ?? ''));
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
                TỔNG QUAN HỌC TẬP
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
        <?php if ($notice === 'assignments_disabled'): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Chức năng Bài tập của tôi đang tạm ẩn để nâng cấp.
        </div>
        <?php elseif ($notice === 'grades_disabled'): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Chức năng Kết quả học tập đang tạm ẩn để nâng cấp.
        </div>
        <?php endif; ?>
        
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card-custom h-100 p-3 shadow-sm border-0 border-start border-4 border-primary bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary"><i class="bi bi-journal-bookmark-fill"></i></div>
                        <div>
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">MÔN ĐANG HỌC</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= $subjectCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom h-100 p-3 shadow-sm border-0 border-start border-4 border-danger bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.8rem;">MÔN CẢNH BÁO</p>
                            <h3 class="mb-0 fw-bold text-danger"><?= $warningCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom h-100 p-3 shadow-sm border-0 border-start border-4 border-warning bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-warning me-3 text-warning"><i class="bi bi-calendar-x-fill"></i></div>
                        <div>
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">TỔNG BUỔI VẮNG</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= $absenceCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom h-100 p-3 shadow-sm border-0 border-start border-4 border-success bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success"><i class="bi bi-envelope-check-fill"></i></div>
                        <div>
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.8rem;">PHẢN HỒI ĐÃ XỬ LÝ</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= $resolvedFeedback ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-calendar-day text-primary me-2"></i>Lịch học hôm nay</h5>
                        <a href="schedule.php" class="btn btn-sm btn-outline-secondary">Xem toàn bộ lịch</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todaySchedule)): ?>
                            <div class="p-4 text-center border rounded border-dashed bg-white mt-2">
                                <i class="bi bi-cup-hot text-muted fs-1 mb-2 d-block"></i>
                                <h6 class="text-muted fw-bold">Hôm nay bạn được nghỉ!</h6>
                                <p class="small text-muted mb-0">Hãy dành thời gian ôn bài nhé.</p>
                            </div>
                        <?php else: ?>
                        <?php
                        // Nhóm theo buổi: Sáng (tiết 1-5), Chiều (6-10), Tối (11-14)
                        $bySession = ['sang' => [], 'chieu' => [], 'toi' => []];
                        foreach ($todaySchedule as $s) {
                            $sp = (int)($s['start_period'] ?? 0);
                            if ($sp <= 5)       $bySession['sang'][]   = $s;
                            elseif ($sp <= 10)  $bySession['chieu'][]  = $s;
                            else                $bySession['toi'][]    = $s;
                        }
                        $sessions = [
                            'sang'  => ['label' => 'Buổi sáng',  'icon' => 'bi-brightness-high', 'badge' => 'bg-warning text-dark'],
                            'chieu' => ['label' => 'Buổi chiều', 'icon' => 'bi-cloud-sun',        'badge' => 'bg-info text-dark'],
                            'toi'   => ['label' => 'Buổi tối',   'icon' => 'bi-moon-stars',       'badge' => 'bg-secondary'],
                        ];
                        // Chỉ hiển thị buổi tối nếu có lịch
                        if (empty($bySession['toi'])) unset($sessions['toi']);
                        ?>
                        <?php foreach ($sessions as $key => $meta): ?>
                            <div class="mb-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi <?= $meta['icon'] ?> text-secondary"></i>
                                    <span class="fw-bold text-secondary small text-uppercase"><?= $meta['label'] ?></span>
                                </div>
                                <?php if (!empty($bySession[$key])): ?>
                                    <?php foreach ($bySession[$key] as $schedule):
                                        $sp = (int)$schedule['start_period'];
                                        $ep = (int)$schedule['end_period'];
                                        $isExtra = (int)($schedule['is_extra'] ?? 0);
                                    ?>
                                        <div class="p-3 mb-2 bg-light rounded border-start border-4 <?= $isExtra ? 'border-warning' : 'border-primary' ?> shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="d-flex gap-2 align-items-center">
                                                    <span class="badge <?= $meta['badge'] ?> px-2 py-1">
                                                        Tiết <?= $sp ?><?= $ep > $sp ? '–' . $ep : '' ?>
                                                    </span>
                                                    <?php if ($isExtra): ?>
                                                        <span class="badge bg-warning text-dark">Học bù</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($schedule['room']): ?>
                                                    <span class="text-muted small fw-bold">
                                                        <i class="bi bi-geo-alt-fill me-1 text-danger"></i><?= e($schedule['room']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-0"><?= e($schedule['subject_name']) ?></h6>
                                            <?php if (!empty($schedule['subject_code'])): ?>
                                                <small class="text-muted"><?= e($schedule['subject_code']) ?></small>
                                            <?php endif; ?>
                                            <p class="text-muted small mb-0 mt-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i>GV: <?= e($schedule['teacher_name'] ?: 'Chưa phân công') ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded bg-white border text-muted small">
                                        <i class="bi bi-cup-hot"></i>
                                        <span><?= $meta['label'] ?> bạn được nghỉ, dành thời gian ôn tập nhé!</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-activity text-danger me-2"></i>Tình trạng Chuyên cần</h5>
                        <a href="my-attendance.php" class="btn btn-sm btn-outline-primary">Xem chi tiết</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendanceStatus)): ?>
                            <?php foreach ($attendanceStatus as $status): ?>
                                <?php 
                                $percentage = $status['limit_absences'] > 0 
                                    ? ($status['absences'] / $status['limit_absences']) * 100 
                                    : 0;
                                $barClass = $percentage >= 100 ? 'bg-danger' : ($percentage >= 50 ? 'bg-warning' : 'bg-success');
                                $textClass = $percentage >= 100 ? 'text-danger' : ($percentage >= 50 ? 'text-warning' : 'text-success');
                                ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-end mb-1">
                                        <div>
                                            <h6 class="fw-bold <?= $percentage >= 100 ? 'text-danger' : 'text-dark' ?> mb-0">
                                                <?= e($status['subject_name']) ?>
                                            </h6>
                                            <small class="text-muted">Giới hạn vắng: <?= $status['limit_absences'] ?> buổi</small>
                                        </div>
                                        <span class="fw-bold <?= $textClass ?>">
                                            Đã vắng <?= $status['absences'] ?>/<?= $status['limit_absences'] ?>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar <?= $barClass ?>" 
                                             role="progressbar" 
                                             style="width: <?= min($percentage, 100) ?>%;"></div>
                                    </div>
                                    <?php if ($percentage >= 100): ?>
                                        <small class="text-danger fw-bold mt-1 d-block">
                                            <i class="bi bi-exclamation-circle me-1"></i>Bạn đang ở mức CẤM THI. 
                                            <a href="my-attendance.php" class="text-danger text-decoration-underline">Vui lòng nộp minh chứng!</a>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Chưa có dữ liệu chuyên cần.</p>
                        <?php endif; ?>
                    </div>
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
