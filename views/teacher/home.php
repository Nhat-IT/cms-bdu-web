<?php
/**
 * CMS BDU - Teacher Dashboard Home
 * Trang tổng quan giảng viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('teacher');

// Lấy thông tin giảng viên
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Lấy teacher_id từ bảng users (dựa trên user_id)
$stmtTeacher = $pdo->prepare("
    SELECT t.id as teacher_id, t.* 
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.id = ?
");
$stmtTeacher->execute([$userId]);
$teacherInfo = $stmtTeacher->fetch();
$teacherId = $teacherInfo['teacher_id'] ?? $userId;

// Đếm lớp phụ trách
$stmtCountClasses = $pdo->prepare("
    SELECT COUNT(DISTINCT cs.id) as total 
    FROM class_subjects cs
    INNER JOIN semesters s ON cs.semester_id = s.id
    WHERE cs.teacher_id = ? AND s.end_date >= CURDATE()
");
$stmtCountClasses->execute([$teacherId]);
$totalClasses = $stmtCountClasses->fetch()['total'];

// Đếm lịch dạy tuần này
$currentDayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$stmtCountSchedule = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM class_subject_groups csg
    INNER JOIN class_subjects cs ON csg.class_subject_id = cs.id
    INNER JOIN semesters s ON cs.semester_id = s.id
    WHERE cs.teacher_id = ? 
    AND csg.day_of_week BETWEEN ? AND ?
    AND s.start_date <= ? AND s.end_date >= ?
");
$stmtCountSchedule->execute([$teacherId, 1, $currentDayOfWeek, $weekEnd, $weekStart]);
$totalSchedule = $stmtCountSchedule->fetch()['total'];

// Đếm bài tập chờ chấm
$stmtCountPending = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM assignment_submissions asa
    INNER JOIN assignments a ON asa.assignment_id = a.id
    INNER JOIN class_subjects cs ON a.class_subject_id = cs.id
    WHERE cs.teacher_id = ? AND asa.score IS NULL
");
$stmtCountPending->execute([$teacherId]);
$pendingAssignments = $stmtCountPending->fetch()['total'];

// Đếm minh chứng chờ duyệt
$stmtCountEvidence = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM attendance_evidences ae
    INNER JOIN attendance_records ar ON ae.attendance_record_id = ar.id
    INNER JOIN attendance_sessions ass ON ar.session_id = ass.id
    INNER JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
    INNER JOIN class_subjects cs ON csg.class_subject_id = cs.id
    WHERE cs.teacher_id = ? AND ae.status = 'Pending'
");
$stmtCountEvidence->execute([$teacherId]);
$pendingEvidences = $stmtCountEvidence->fetch()['total'];

// Lấy danh sách lớp học phần
$stmtClasses = $pdo->prepare("
    SELECT cs.id, cs.semester,
           c.class_name, c.academic_year,
           s.subject_code, s.subject_name,
           csg.group_code, csg.day_of_week, csg.start_period, csg.end_period, csg.room,
           (SELECT COUNT(*) FROM student_subject_registration ssr WHERE ssr.class_subject_group_id = csg.id) as student_count
    FROM class_subjects cs
    INNER JOIN semesters sem ON cs.semester_id = sem.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    INNER JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
    WHERE cs.teacher_id = ? AND sem.end_date >= CURDATE()
    ORDER BY csg.day_of_week, csg.start_period
");
$stmtClasses->execute([$teacherId]);
$classSubjects = $stmtClasses->fetchAll();

// Ngày trong tuần
$dayNames = ['', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ Nhật'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Tổng quan Giảng viên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-home.css">
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
            <div class="sidebar-scrollable w-100">
            <nav class="nav flex-column ps-2 pe-2">
                <a href="home.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1"><i class="bi bi-speedometer2 me-2"></i> Tổng quan</a>
                <a href="attendance.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-calendar-check me-2"></i> Lịch & Điểm danh</a>
                <a href="class-assignments.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-journal-text me-2"></i> Quản lý Bài tập</a>
                <a href="class-grades.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i> Bảng điểm</a>
                <a href="approve-evidences.php" class="nav-link text-white-50 hover-white py-2 mb-1 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical me-2"></i> Duyệt minh chứng</span>
                    <?php if ($pendingEvidences > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo e($pendingEvidences); ?></span>
                    <?php endif; ?>
                </a>
                <a href="documents.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-folder2-open me-2"></i> Kho tài liệu</a>
                <a href="announcements.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-megaphone-fill me-2"></i> Đăng bảng tin</a>
            </nav>
            </div>
        </div>
        
        <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
            <a href="../logout.php" class="nav-link logout-btn" title="Đăng xuất">
                <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3 shadow-sm mb-4">
            <h4 class="m-0 fw-bold d-flex align-items-center text-white">
                TỔNG QUAN GIẢNG DẠY
            </h4>
            <div class="d-flex align-items-center text-white">
                <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
                    <div class="fs-6">Giảng viên: <b class="text-info"><?php echo e($user['full_name'] ?? 'GV'); ?></b></div>
                </div>
                
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown">
                        <img src="<?php echo e(getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? 'GV', 40)); ?>" id="headerAvatar" alt="Avatar" class="rounded-circle border border-white" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                        <li><a class="dropdown-item fw-bold" href="teacher-profile.php"><i class="bi bi-person-vcard text-info me-2"></i>Hồ sơ cá nhân</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="../logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="p-4 pt-0">
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card-custom border-left-primary h-100 p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-custom bg-light-primary me-3 text-primary"><i class="bi bi-book-half"></i></div>
                            <div>
                                <p class="text-primary fw-bold mb-1 small">LỚP PHỤ TRÁCH</p>
                                <h3 class="mb-0 fw-bold text-dark"><?php echo e($totalClasses); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card-custom border-left-success h-100 p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-custom bg-light-success me-3 text-success"><i class="bi bi-calendar-event-fill"></i></div>
                            <div>
                                <p class="text-success fw-bold mb-1 small">LỊCH DẠY TUẦN NÀY</p>
                                <h3 class="mb-0 fw-bold text-dark"><?php echo e($totalSchedule); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card-custom border-left-warning h-100 p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-custom bg-light-warning me-3 text-warning"><i class="bi bi-pencil-square"></i></div>
                            <div>
                                <p class="text-warning fw-bold mb-1 small">BÀI TẬP CHỜ CHẤM</p>
                                <h3 class="mb-0 fw-bold text-dark"><?php echo e($pendingAssignments); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card-custom border-left-danger h-100 p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-custom bg-light-danger me-3 text-danger"><i class="bi bi-file-medical-fill"></i></div>
                            <div>
                                <p class="text-danger fw-bold mb-1 small">MINH CHỨNG CHỜ DUYỆT</p>
                                <h3 class="mb-0 fw-bold text-dark"><?php echo e($pendingEvidences); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="fw-bold text-dark m-0"><i class="bi bi-card-checklist text-primary me-2"></i>Lớp Học Phần (Học kỳ hiện tại)</h5>
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Tìm môn học..." id="searchSubject">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="classTable">
                                    <thead class="table-light small text-muted fw-bold">
                                        <tr>
                                            <th class="ps-4">MÃ LỚP</th>
                                            <th>MÔN HỌC</th>
                                            <th>SĨ SỐ</th>
                                            <th class="text-end pe-4">THAO TÁC</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($classSubjects)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Chưa có lớp học phần nào được phân công.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($classSubjects as $cs): ?>
                                        <tr data-class-id="<?php echo e($cs['id']); ?>">
                                            <td class="fw-bold ps-4 text-primary"><?php echo e($cs['class_name']); ?></td>
                                            <td class="fw-bold text-dark">
                                                <?php echo e($cs['subject_name']); ?><br>
                                                <small class="text-muted fw-normal">
                                                    <i class="bi bi-clock me-1"></i><?php echo e($dayNames[$cs['day_of_week']] ?? ''); ?> (Tiết <?php echo e($cs['start_period']); ?>-<?php echo e($cs['end_period']); ?>)
                                                </small>
                                            </td>
                                            <td><?php echo e($cs['student_count']); ?> SV</td>
                                            <td class="text-end pe-4">
                                                <a href="attendance.php?class_subject_id=<?php echo e($cs['id']); ?>" class="btn btn-light action-btn text-success border me-1" title="Điểm danh"><i class="bi bi-person-check"></i></a>
                                                <a href="class-assignments.php?class_subject_id=<?php echo e($cs['id']); ?>" class="btn btn-light action-btn text-warning border me-1" title="Chấm bài"><i class="bi bi-journal-check"></i></a>
                                                <a href="class-grades.php?class_subject_id=<?php echo e($cs['id']); ?>" class="btn btn-light action-btn text-info border" title="Bảng điểm"><i class="bi bi-bar-chart-fill"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-2 border-0">
                            <h5 class="fw-bold text-dark m-0 d-flex align-items-center">
                                <i class="bi bi-megaphone text-danger me-2 fs-4"></i> Lời nhắc giảng viên
                            </h5>
                        </div>
                        <div class="card-body pt-2">
                            <?php if ($pendingEvidences > 0): ?>
                            <div class="reminder-card reminder-warning">
                                <h6 class="fw-bold mb-1 text-warning" style="color: #e6a800 !important;">Minh chứng chờ duyệt</h6>
                                <p class="mb-0 small text-dark">Bạn có <?php echo e($pendingEvidences); ?> minh chứng mới cần xác nhận.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($pendingAssignments > 0): ?>
                            <div class="reminder-card reminder-primary">
                                <h6 class="fw-bold mb-1 text-primary">Bài tập chờ chấm</h6>
                                <p class="mb-0 small text-dark">Bạn có <?php echo e($pendingAssignments); ?> bài tập chưa được chấm điểm.</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($totalClasses == 0): ?>
                            <div class="reminder-card reminder-danger">
                                <h6 class="fw-bold mb-1 text-danger">Chưa có lớp phụ trách</h6>
                                <p class="mb-0 small text-dark">Hiện tại bạn chưa được phân công giảng dạy lớp học phần nào.</p>
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
    <script src="../../public/js/teacher/teacher-layout.js"></script>
    <script>
        // Simple search filter for class table
        document.getElementById('searchSubject')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#classTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
