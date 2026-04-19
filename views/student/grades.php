<?php
/**
 * CMS BDU - Kết Quả Học Tập
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Kết Quả Học Tập';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/grades.css'];
$extraJs = ['student/student-layout.js', 'student/grades.js'];

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT id, semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy điểm của sinh viên
$stmt = $pdo->prepare("
    SELECT g.*, s.subject_name, s.subject_code,
           csg.group_code, t.full_name as teacher_name
    FROM grades g
    JOIN class_subject_groups csg ON g.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users t ON cs.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$userId]);
$grades = $stmt->fetchAll();

// Lấy số thông báo chưa đọc
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetch()['total'];

// Lấy thông tin chi tiết từng môn (attendance + assignment submissions)
$gradeDetails = [];
foreach ($grades as $grade) {
    // Lấy số buổi vắng
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        WHERE a_s.class_subject_group_id = ? AND ar.student_id = ? AND ar.status = 3
    ");
    $stmt->execute([$grade['class_subject_group_id'], $userId]);
    $grade['absences'] = $stmt->fetch()['total'];
    
    // Lấy điểm bài tập chi tiết
    $stmt = $pdo->prepare("
        SELECT ast.*
        FROM assignment_submissions ast
        JOIN assignments a ON ast.assignment_id = a.id
        WHERE a.class_subject_id = ? AND ast.student_id = ?
    ");
    $stmt->execute([$grade['class_subject_group_id'], $userId]);
    $grade['submissions'] = $stmt->fetchAll();
    
    $gradeDetails[] = $grade;
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">
                KẾT QUẢ HỌC TẬP
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

        <div class="col-12">
            <div class="card grade-card shadow-sm border-0 h-100">
                <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Chi Tiết Điểm Các Học Phần</h5>
                        <p class="text-muted small mb-0 mt-1">Bảng tổng hợp điểm thành phần và chuyên cần của sinh viên.</p>
                    </div>
                    <select class="form-select w-auto border-secondary text-primary fw-bold shadow-sm" disabled>
                        <option selected><?= e($currentSemester['semester_name'] ?? 'HK2') ?> (<?= e($currentSemester['academic_year'] ?? '2025 - 2026') ?>)</option>
                    </select>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-grades mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">HỌC PHẦU</th>
                                    <th class="text-center" title="Bài tập (20%)">BT (20%)</th>
                                    <th class="text-center" title="Giữa kỳ (30%)">GK (30%)</th>
                                    <th class="text-center" title="Cuối kỳ (50%)">CK (50%)</th>
                                    <th class="text-center" title="Tổng kết">TỔNG</th>
                                    <th class="text-center">CHỮ</th>
                                    <th class="text-center pe-4">CHI TIẾT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($gradeDetails)): ?>
                                    <?php foreach ($gradeDetails as $grade): ?>
                                        <?php
                                        $gradeClass = '';
                                        $gradeLetterClass = '';
                                        if ($grade['total_score'] >= 8.5) {
                                            $gradeClass = 'text-success';
                                            $gradeLetterClass = 'grade-a';
                                        } elseif ($grade['total_score'] >= 7.0) {
                                            $gradeClass = 'text-primary';
                                            $gradeLetterClass = 'grade-b';
                                        } elseif ($grade['total_score'] >= 5.5) {
                                            $gradeClass = 'text-warning';
                                            $gradeLetterClass = 'grade-c';
                                        } elseif ($grade['total_score'] >= 5.0) {
                                            $gradeClass = 'text-warning';
                                            $gradeLetterClass = 'grade-d';
                                        } else {
                                            $gradeClass = 'text-danger';
                                            $gradeLetterClass = 'grade-f';
                                        }
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= e($grade['subject_name']) ?></td>
                                            <td class="text-center text-muted"><?= $grade['assignment_score'] !== null ? number_format($grade['assignment_score'], 1) : '--' ?></td>
                                            <td class="text-center text-muted"><?= $grade['midterm_score'] !== null ? number_format($grade['midterm_score'], 1) : '--' ?></td>
                                            <td class="text-center text-muted"><?= $grade['final_score'] !== null ? number_format($grade['final_score'], 1) : '--' ?></td>
                                            <td class="text-center fw-bold score-box <?= $gradeClass ?>">
                                                <?= $grade['total_score'] !== null ? number_format($grade['total_score'], 1) : '--' ?>
                                            </td>
                                            <td class="text-center fw-bold <?= $gradeLetterClass ?>"><?= e($grade['grade_letter'] ?? '--') ?></td>
                                            <td class="text-center pe-4">
                                                <button class="btn btn-outline-primary btn-detail px-3" onclick='openDetailModal(
                                                    "<?= e(addslashes($grade['subject_name'])) ?>",
                                                    "<?= $grade['absences'] ?>/3",
                                                    <?= json_encode(array_map(function($s) { return ['name' => 'Bài nộp', 'score' => $s['score']]; }, $grade['submissions'])) ?>
                                                )'>
                                                    <i class="bi bi-eye-fill me-1"></i> Xem
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            Chưa có dữ liệu điểm cho học kỳ này.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="subjectDetailModal" tabindex="-1" aria-labelledby="subjectDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold" id="subjectDetailModalLabel">Chi tiết môn học</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="bi bi-calendar2-x text-danger me-2"></i>Tình trạng Chuyên cần
                        </h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold">Tổng số buổi vắng:</span>
                            <span class="badge bg-danger fs-6" id="modalAbsence">0/3</span>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                            <i class="bi bi-journal-check text-success me-2"></i>Chi tiết Điểm Bài tập (20%)
                        </h6>
                        <ul class="list-group list-group-flush" id="modalBTList">
                            <!-- Dynamic content -->
                        </ul>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0 pt-0 bg-light justify-content-center">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Đóng</button>
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
