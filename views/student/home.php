<?php
/**
 * CMS BDU - Trang chủ Sinh viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Tổng quan cá nhân';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/home.css'];
$extraJs = ['student/student-layout.js'];

// Lấy số môn đang học
$subjectCount = db_count("
    SELECT COUNT(DISTINCT csg.class_subject_id) 
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
", [$userId]);

// Lấy số buổi vắng
$absenceCount = db_count("
    SELECT COUNT(*) 
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id AND ssr.student_id = ?
    WHERE ar.student_id = ? AND ar.status = 3
", [$userId, $userId]);

// Lấy số phản hồi đã xử lý
$resolvedFeedback = db_count("
    SELECT COUNT(*) 
    FROM feedbacks 
    WHERE student_id = ? AND status = 'Resolved'
", [$userId]);

// Lấy môn cảnh báo (vắng >= 3)
$warningSubjects = db_fetch_all("
    SELECT s.subject_name, 
           COUNT(CASE WHEN ar.status = 3 THEN 1 END) as absences
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id AND ssr.student_id = ?
    WHERE ar.student_id = ?
    GROUP BY s.id
    HAVING absences >= 3
", [$userId, $userId]);
$warningCount = count($warningSubjects);

// Lấy lịch học hôm nay
$dayOfWeek = date('N');
$todaySchedule = db_fetch_all("
    SELECT s.subject_name, csg.start_period, csg.end_period, csg.room,
           t.full_name as teacher_name, 'Sáng' as session_name
    FROM class_subject_groups csg
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id AND ssr.student_id = ?
    LEFT JOIN users t ON cs.teacher_id = t.id
    WHERE csg.day_of_week = ? AND ssr.status = 'Đang học'
    ORDER BY csg.start_period
", [$userId, $dayOfWeek]);

// Lấy tình trạng chuyên cần các môn
$attendanceStatus = db_fetch_all("
    SELECT s.subject_name, 
           COUNT(CASE WHEN ar.status = 3 THEN 1 END) as absences,
           3 as limit_absences
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id AND ssr.student_id = ?
    WHERE ar.student_id = ? AND ssr.status = 'Đang học'
    GROUP BY s.id
    ORDER BY absences DESC
", [$userId, $userId]);

// Lấy số thông báo chưa đọc
$unreadNotifications = db_count("SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0", [$userId]);
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
                        <?php if (!empty($todaySchedule)): ?>
                            <?php foreach ($todaySchedule as $schedule): ?>
                                <div class="p-3 mb-3 bg-light rounded border-start border-4 border-primary shadow-sm">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge bg-primary px-2 py-1">
                                            <i class="bi bi-clock me-1"></i><?= e($schedule['session_name']) ?>
                                        </span>
                                        <?php if ($schedule['room']): ?>
                                            <span class="text-muted small fw-bold">
                                                <i class="bi bi-geo-alt-fill me-1 text-danger"></i><?= e($schedule['room']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1"><?= e($schedule['subject_name']) ?></h6>
                                    <p class="text-muted small mb-0">
                                        <i class="bi bi-person-badge text-primary me-1"></i>GV: <?= e($schedule['teacher_name'] ?? 'Chưa phân công') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center border rounded border-dashed bg-white mt-4">
                                <i class="bi bi-cup-hot text-muted fs-1 mb-2"></i>
                                <h6 class="text-muted fw-bold">Hôm nay bạn được nghỉ!</h6>
                                <p class="small text-muted mb-0">Hãy dành thời gian ôn bài nhé.</p>
                            </div>
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
