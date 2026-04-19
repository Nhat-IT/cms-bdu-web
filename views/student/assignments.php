<?php
/**
 * CMS BDU - Bài Tập Của Tôi
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Bài Tập Của Tôi';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/assignments.css'];
$extraJs = ['student/student-layout.js', 'student/assignments.js'];

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT id, semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy danh sách môn học của sinh viên
$stmt = $pdo->prepare("
    SELECT DISTINCT cs.id as class_subject_id, s.subject_name, s.subject_code, t.full_name as teacher_name
    FROM student_subject_registration ssr
    JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users t ON cs.teacher_id = t.id
    WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
    ORDER BY s.subject_name
");
$stmt->execute([$userId]);
$subjects = $stmt->fetchAll();

// Lấy bài tập của sinh viên với trạng thái nộp
$stmt = $pdo->prepare("
    SELECT a.*, s.subject_name, s.subject_code, t.full_name as teacher_name,
           cs.id as class_subject_id,
           ast.submitted_at, ast.drive_link, ast.score, ast.feedback as teacher_feedback,
           CASE 
               WHEN ast.id IS NOT NULL THEN 'submitted'
               WHEN a.deadline < NOW() THEN 'overdue'
               ELSE 'pending'
           END as submission_status
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users t ON cs.teacher_id = t.id
    LEFT JOIN assignment_submissions ast ON ast.assignment_id = a.id AND ast.student_id = ?
    WHERE cs.id IN (
        SELECT DISTINCT csg.class_subject_id 
        FROM student_subject_registration ssr
        JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
        WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
    )
    ORDER BY a.deadline ASC
");
$stmt->execute([$userId, $userId]);
$assignments = $stmt->fetchAll();

// Phân loại bài tập
$pendingAssignments = [];
$submittedAssignments = [];
$gradedAssignments = [];

foreach ($assignments as $assignment) {
    if ($assignment['score'] !== null) {
        $gradedAssignments[] = $assignment;
    } elseif ($assignment['submitted_at']) {
        $submittedAssignments[] = $assignment;
    } else {
        $pendingAssignments[] = $assignment;
    }
}

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
                BÀI TẬP CỦA TÔI
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

    <div class="p-4 pt-4">

        <div id="assignmentListView">
            
            <div class="card shadow-sm border-0 mb-4 bg-white">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-5 col-lg-12">
                            <ul class="nav nav-pills bg-light p-1 rounded border d-flex w-100" id="assignmentTabs">
                                <li class="nav-item flex-fill text-center">
                                    <button class="nav-link active w-100 fw-bold" data-bs-toggle="pill" type="button" onclick="showAssignmentTab('pending')">Cần làm (<?= count($pendingAssignments) ?>)</button>
                                </li>
                                <li class="nav-item flex-fill text-center">
                                    <button class="nav-link w-100 fw-bold text-danger" data-bs-toggle="pill" type="button" onclick="showAssignmentTab('submitted')">Đã nộp (<?= count($submittedAssignments) ?>)</button>
                                </li>
                                <li class="nav-item flex-fill text-center">
                                    <button class="nav-link w-100 fw-bold text-success" data-bs-toggle="pill" type="button" onclick="showAssignmentTab('graded')">Đã chấm (<?= count($gradedAssignments) ?>)</button>
                                </li>
                            </ul>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="fw-bold small text-muted mb-1 d-block">HỌC KỲ</label>
                            <select class="form-select border-primary bg-light fw-bold text-primary" disabled>
                                <option selected><?= e($currentSemester['semester_name'] ?? 'HK2') ?> (<?= e($currentSemester['academic_year'] ?? '25-26') ?>)</option>
                            </select>
                        </div>
                        <div class="col-xl-4 col-md-6">
                            <label class="fw-bold small text-muted mb-1 d-block">TÌM MÔN HỌC</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-secondary"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-secondary border-start-0 fw-bold text-dark" id="subjectFilter" placeholder="Gõ tên môn học..." onkeyup="filterSubjects()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cần làm -->
            <div id="pendingTab" class="assignment-tab-content">
                <?php if (!empty($pendingAssignments)): ?>
                    <?php 
                    $groupedPending = [];
                    foreach ($pendingAssignments as $a) {
                        $groupedPending[$a['subject_name']][] = $a;
                    }
                    foreach ($groupedPending as $subjectName => $items): 
                    ?>
                        <div class="mb-4 subject-group">
                            <h5 class="fw-bold text-primary mb-3 border-bottom border-primary border-2 d-inline-block pb-1 subject-title"><?= e($subjectName) ?></h5>
                            
                            <?php foreach ($items as $assignment): ?>
                                <?php 
                                $isOverdue = strtotime($assignment['deadline']) < time();
                                $statusClass = $isOverdue ? 'border-danger' : 'border-primary';
                                $statusBadge = $isOverdue ? '<span class="badge bg-danger text-white">Quá hạn</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Đã giao</span>';
                                ?>
                                <div class="card assignment-card mb-2 border-0 shadow-sm <?= $isOverdue ? 'opacity-75' : '' ?>" onclick="openAssignmentDetail(<?= $assignment['id'] ?>)">
                                    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="assignment-icon-bg <?= $isOverdue ? 'bg-danger bg-opacity-10 text-danger' : 'bg-primary bg-opacity-10 text-primary' ?> me-3">
                                                <i class="bi bi-journal-text"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark"><?= e($assignment['title']) ?></h6>
                                                <div class="small text-muted">Đăng bởi: <?= e($assignment['teacher_name'] ?? 'GV') ?> • <?= formatDate($assignment['created_at'], 'd/m/Y') ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?= $isOverdue ? 'text-danger' : 'text-danger' ?> mb-1">
                                                <?= $isOverdue ? 'Đã hết hạn: ' : 'Đến hạn: ' ?><?= formatDate($assignment['deadline'], 'd/m/Y H:i') ?>
                                            </div>
                                            <?= $statusBadge ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
                            <h5 class="text-muted">Tất cả bài tập đã được nộp!</h5>
                            <p class="text-muted">Không có bài tập nào cần làm.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Đã nộp -->
            <div id="submittedTab" class="assignment-tab-content d-none">
                <?php if (!empty($submittedAssignments)): ?>
                    <?php 
                    $groupedSubmitted = [];
                    foreach ($submittedAssignments as $a) {
                        $groupedSubmitted[$a['subject_name']][] = $a;
                    }
                    foreach ($groupedSubmitted as $subjectName => $items): 
                    ?>
                        <div class="mb-4 subject-group">
                            <h5 class="fw-bold text-success mb-3 border-bottom border-success border-2 d-inline-block pb-1 subject-title"><?= e($subjectName) ?></h5>
                            
                            <?php foreach ($items as $assignment): ?>
                                <div class="card assignment-card mb-2 border-0 shadow-sm opacity-75" onclick="openAssignmentDetail(<?= $assignment['id'] ?>)">
                                    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="assignment-icon-bg bg-secondary bg-opacity-10 text-secondary me-3">
                                                <i class="bi bi-hourglass-split"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark"><?= e($assignment['title']) ?></h6>
                                                <div class="small text-muted">Đăng bởi: <?= e($assignment['teacher_name'] ?? 'GV') ?> • <?= formatDate($assignment['created_at'], 'd/m/Y') ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted mb-1">Đã nộp: <?= formatDate($assignment['submitted_at'], 'd/m/Y H:i') ?></div>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Chờ chấm</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox text-muted fs-1 mb-3"></i>
                            <h5 class="text-muted">Chưa có bài nộp nào</h5>
                            <p class="text-muted">Danh sách bài đã nộp sẽ hiển thị ở đây.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Đã chấm -->
            <div id="gradedTab" class="assignment-tab-content d-none">
                <?php if (!empty($gradedAssignments)): ?>
                    <?php 
                    $groupedGraded = [];
                    foreach ($gradedAssignments as $a) {
                        $groupedGraded[$a['subject_name']][] = $a;
                    }
                    foreach ($groupedGraded as $subjectName => $items): 
                    ?>
                        <div class="mb-4 subject-group">
                            <h5 class="fw-bold text-success mb-3 border-bottom border-success border-2 d-inline-block pb-1 subject-title"><?= e($subjectName) ?></h5>
                            
                            <?php foreach ($items as $assignment): ?>
                                <div class="card assignment-card mb-2 border-0 shadow-sm" onclick="openAssignmentDetail(<?= $assignment['id'] ?>)">
                                    <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="assignment-icon-bg bg-success bg-opacity-10 text-success me-3">
                                                <i class="bi bi-journal-check"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark"><?= e($assignment['title']) ?></h6>
                                                <div class="small text-muted">Đăng bởi: <?= e($assignment['teacher_name'] ?? 'GV') ?> • <?= formatDate($assignment['created_at'], 'd/m/Y') ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted mb-1">Đã nộp: <?= formatDate($assignment['submitted_at'], 'd/m/Y') ?></div>
                                            <span class="badge bg-success text-white"><i class="bi bi-check-lg me-1"></i>Đã chấm</span>
                                            <span class="fw-bold <?= $assignment['score'] >= 8 ? 'text-success' : ($assignment['score'] >= 5 ? 'text-warning' : 'text-danger') ?> ms-2"><?= number_format($assignment['score'], 1) ?>/10</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox text-muted fs-1 mb-3"></i>
                            <h5 class="text-muted">Chưa có bài được chấm điểm</h5>
                            <p class="text-muted">Điểm sẽ hiển thị sau khi giảng viên chấm bài.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Chi tiết bài tập -->
        <div id="assignmentDetailView" class="d-none">
            
            <button class="btn btn-link text-decoration-none text-muted fw-bold ps-0 mb-3" onclick="closeAssignmentDetail()">
                <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách
            </button>

            <div class="row g-4" id="assignmentDetailContent">
                <!-- Nội dung chi tiết sẽ được load bằng AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Thêm liên kết -->
<div class="modal fade" id="addLinkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Thêm liên kết</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="url" class="form-control form-control-lg bg-light" id="linkInput" placeholder="Nhập đường liên kết (VD: Link Github, Drive...)">
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="addLinkItem()">Thêm liên kết</button>
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
