<?php
/**
 * CMS BDU - Trang chủ Ban Cán Sự (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Bảng Điều Khiển BCS';

// Lấy class_id của BCS từ class_students
$stmt = $pdo->prepare("
    SELECT cs.class_id, c.class_name 
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.student_id = ?
");
$stmt->execute([$userId]);
$classInfo = $stmt->fetch();
$classId = $classInfo['class_id'] ?? null;
$className = $classInfo['class_name'] ?? '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$fullName = $currentUser['full_name'] ?? '';
$position = $currentUser['position'] ?? 'Ban Cán Sự';
$avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 55);

// Lấy sĩ số lớp
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM class_students WHERE class_id = ?");
$stmt->execute([$classId]);
$classSize = $stmt->fetch()['total'] ?? 0;

// Lấy số vắng hôm nay
$today = date('Y-m-d');
$dayOfWeek = date('N');
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
    WHERE ar.status = 3 AND a_s.attendance_date = ?
");
$stmt->execute([$classId, $today]);
$absentToday = $stmt->fetch()['total'] ?? 0;

// Lấy số minh chứng chờ duyệt
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ae.id) as total
    FROM attendance_evidences ae
    JOIN attendance_records ar ON ae.attendance_record_id = ar.id
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
    WHERE ae.status = 'Pending'
");
$stmt->execute([$classId]);
$pendingEvidence = $stmt->fetch()['total'] ?? 0;

// Lấy số phản hồi mới
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT f.id) as total
    FROM feedbacks f
    JOIN class_students cs ON f.student_id = cs.student_id
    WHERE cs.class_id = ? AND f.status = 'Pending'
");
$stmt->execute([$classId]);
$newFeedback = $stmt->fetch()['total'] ?? 0;

// Lấy lịch học hôm nay
$stmt = $pdo->prepare("
    SELECT s.subject_name, csg.start_period, csg.end_period, csg.room,
           t.full_name as teacher_name, csg.study_session
    FROM class_subject_groups csg
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
    LEFT JOIN users t ON cs.teacher_id = t.id
    WHERE csg.day_of_week = ? AND ssr.status = 'Đang học'
    GROUP BY csg.id
    ORDER BY csg.start_period
");
$stmt->execute([$classId, $dayOfWeek]);
$todaySchedule = $stmt->fetchAll();

// Lấy thông báo gần đây
$stmt = $pdo->prepare("
    SELECT n.*, u.full_name as creator_name
    FROM notification_logs n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.message IS NOT NULL AND n.message != ''
    ORDER BY n.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentAnnouncements = $stmt->fetchAll();

// Đếm notification
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM notification_logs 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['total'] ?? 0;
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
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/bcs/bcs-layout.css">
</head>
<body class="dashboard-body">

<div class="sidebar" id="sidebar">
    <div>
        <div class="brand-container flex-shrink-0">
            <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                <span class="fs-4 fw-bold hide-on-collapse">CMS BDU</span>
            </a>
        </div>
        <div class="bcs-profile-container text-center flex-shrink-0">
            <a href="profile.php" class="bcs-profile-trigger" title="Xem hồ sơ ban cán sự">
                <img src="<?= e($avatar) ?>" class="rounded-circle shadow-sm mb-2 border border-2 border-primary" width="55" alt="Avatar BCS">
                <div class="hide-on-collapse">
                    <div class="text-white fw-bold fs-6"><?= e($fullName) ?></div>
                    <div class="text-white-50 small mb-1" style="font-size: 0.8rem;">Vai trò: <?= e($position) ?></div>
                </div>
            </a>
            <span class="badge bcs-class-badge mt-1 hide-on-collapse">LỚP: <?= e($className) ?></span>
        </div>
        <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <a href="home.php" class="active"><i class="bi bi-grid-1x2-fill"></i> Bảng điều khiển</a>
            <a href="attendance.php"><i class="bi bi-calendar-check"></i> Quản lý Chuyên cần</a>
            <a href="documents.php"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>
            
            <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CÁ NHÂN</div>
            <a href="../student/home.php" class="text-warning"><i class="bi bi-arrow-repeat"></i> Về Cổng Sinh Viên</a>
        </nav>
        </div>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../../views/logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<div class="main-content" id="mainContent" style="padding: 0; background-color: #f4f6f9; min-height: 100vh;">
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">TổNG QUAN LỚP <span class="text-warning ms-2" id="userClassName"><?= e($className) ?></span></h4>
            
            <a href="dashboard-detail.php" class="btn btn-outline-light btn-sm ms-4 fw-bold shadow-sm">
                <i class="bi bi-bar-chart-fill me-1"></i> Dashboard Chi Tiết
            </a>
        </div>
        
        <div class="bcs-header-meta d-flex align-items-center text-white">
            <span class="bcs-header-label fw-bold">BAN CÁN SỰ</span>
            <a href="feedback.php" class="bcs-notification-link" title="Có <?= $unreadCount ?> thông báo hệ thống">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="bcs-notification-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 fs-6">Sĩ số lớp</p>
                            <h3 class="mb-0 fw-bold"><?= $classSize ?></h3>
                        </div>
                        <div class="icon-box bg-light-primary"><i class="bi bi-people-fill text-primary"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 fs-6">Vắng hôm nay</p>
                            <h3 class="mb-0 fw-bold text-danger"><?= $absentToday ?></h3>
                        </div>
                        <div class="icon-box bg-light-danger"><i class="bi bi-person-x-fill text-danger"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 fs-6">Minh chứng chờ</p>
                            <h3 class="mb-0 fw-bold text-warning"><?= $pendingEvidence ?></h3>
                        </div>
                        <div class="icon-box bg-light-warning"><i class="bi bi-file-earmark-medical-fill text-warning"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 fs-6">Phản hồi mới</p>
                            <h3 class="mb-0 fw-bold text-success"><?= $newFeedback ?></h3>
                        </div>
                        <div class="icon-box bg-light-success"><i class="bi bi-bell-fill text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold"><i class="bi bi-calendar-day text-primary me-2"></i>Lịch học hôm nay</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($todaySchedule)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Môn học</th>
                                        <th>Phòng</th>
                                        <th>Giảng viên</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todaySchedule as $schedule): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">Tiết <?= $schedule['start_period'] ?> - <?= $schedule['end_period'] ?></span></td>
                                        <td class="fw-bold text-dark"><?= e($schedule['subject_name']) ?></td>
                                        <td class="text-muted"><?= e($schedule['room'] ?? 'Chưa phòng') ?></td>
                                        <td><?= e($schedule['teacher_name'] ?? 'Chưa phân công') ?></td>
                                        <td><a href="attendance.php" class="btn btn-sm btn-primary fw-bold shadow-sm">Điểm danh ngay</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1"></i>
                            <p class="text-muted mt-2">Hôm nay không có lịch học</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold"><i class="bi bi-megaphone-fill text-warning me-2"></i>Thông báo</h5>
                        <a href="announcements.php" class="text-decoration-none text-muted fs-6">Xem tất cả</a>
                    </div>
                    <div class="card-body px-0">
                        <?php if (!empty($recentAnnouncements)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentAnnouncements as $ann): ?>
                            <a href="#" class="list-group-item list-group-item-action py-3 border-bottom">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 fw-bold text-dark"><?= e($ann['title'] ?? 'Thông báo mới') ?></h6>
                                    <?php if (!$ann['is_read']): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Mới</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 text-muted small"><?= e(mb_substr($ann['message'], 0, 80)) ?>...</p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-1"></i>
                            <p class="text-muted mt-2">Chưa có thông báo</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/bcs/bcs-layout.js"></script>
</body>
</html>
