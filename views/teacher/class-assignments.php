<?php
/**
 * CMS BDU - Teacher Class Assignments
 * Trang quản lý bài tập lớp học phần
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('teacher');

// Lấy thông tin giảng viên
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Lấy teacher_id
$stmtTeacher = $pdo->prepare("
    SELECT t.id as teacher_id 
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.id = ?
");
$stmtTeacher->execute([$userId]);
$teacherInfo = $stmtTeacher->fetch();
$teacherId = $teacherInfo['teacher_id'] ?? $userId;

// Lấy danh sách lớp học phần của giảng viên
$stmtClassSubjects = $pdo->prepare("
    SELECT cs.id,
           c.class_name,
           s.subject_code, s.subject_name
    FROM class_subjects cs
    INNER JOIN semesters sem ON cs.semester_id = sem.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ? AND sem.end_date >= CURDATE()
    ORDER BY c.class_name
");
$stmtClassSubjects->execute([$teacherId]);
$classSubjects = $stmtClassSubjects->fetchAll();

// Lấy danh sách bài tập
$stmtAssignments = $pdo->prepare("
    SELECT a.*, 
           cs.class_name, s.subject_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submitted_count,
           (SELECT COUNT(*) FROM student_subject_registration ssr WHERE ssr.class_subject_group_id IN (SELECT id FROM class_subject_groups WHERE class_subject_id = a.class_subject_id)) as total_students
    FROM assignments a
    INNER JOIN class_subjects cs ON a.class_subject_id = cs.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY a.deadline DESC
");
$stmtAssignments->execute([$teacherId]);
$assignments = $stmtAssignments->fetchAll();

// Lấy submissions nếu có assignment được chọn
$selectedAssignmentId = $_GET['assignment_id'] ?? null;
$submissions = [];
if ($selectedAssignmentId) {
    $stmtSubmissions = $pdo->prepare("
        SELECT asa.*, u.username, u.full_name
        FROM assignment_submissions asa
        INNER JOIN users u ON asa.student_id = u.id
        WHERE asa.assignment_id = ?
        ORDER BY asa.submitted_at DESC
    ");
    $stmtSubmissions->execute([$selectedAssignmentId]);
    $submissions = $stmtSubmissions->fetchAll();
}

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Quản lý Bài tập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/class-assignments.css">
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
                <a href="home.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-speedometer2 me-2"></i> Tổng quan</a>
                <a href="attendance.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-calendar-check me-2"></i> Lịch & Điểm danh</a>
                <a href="class-assignments.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1"><i class="bi bi-journal-text me-2"></i> Quản lý Bài tập</a>
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
                QUẢN LÝ BÀI TẬP
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
            
            <ul class="nav nav-tabs mb-4 border-bottom border-secondary border-opacity-25" id="assignmentTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-panel" type="button" role="tab">
                        <i class="bi bi-list-task me-2"></i> Danh sách Bài tập
                    </button>
                </li>
                <li class="nav-item ms-2" role="presentation">
                    <button class="nav-link px-4 py-3" id="grade-tab" data-bs-toggle="tab" data-bs-target="#grade-panel" type="button" role="tab">
                        <i class="bi bi-check2-square me-2"></i> Chấm bài (Submissions)
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="list-panel" role="tabpanel">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <h5 class="fw-bold text-dark m-0">Bài tập đã giao</h5>
                                <select class="form-select form-select-sm" style="width: 200px;" id="filterClass" onchange="filterAssignments()">
                                    <option value="">-- Tất cả lớp --</option>
                                    <?php foreach ($classSubjects as $cs): ?>
                                    <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addAssignmentModal" onclick="openAssignmentModal('add')">
                                <i class="bi bi-plus-lg me-1"></i> Tạo Bài tập mới
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="assignmentsTable">
                                    <thead class="table-light small text-muted fw-bold">
                                        <tr>
                                            <th class="ps-4">TIÊU ĐỀ BÀI TẬP</th>
                                            <th>LỚP HỌC PHẦN</th>
                                            <th>DEADLINE</th>
                                            <th>TRẠNG THÁI</th>
                                            <th>ĐÃ NỘP</th>
                                            <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($assignments)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Chưa có bài tập nào được tạo.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($assignments as $a): ?>
                                        <?php 
                                            $isOpen = ($a['deadline'] === null) || (strtotime($a['deadline']) > time());
                                            $statusClass = $isOpen ? 'success' : 'secondary';
                                            $statusText = $isOpen ? 'Đang mở' : 'Đã đóng';
                                        ?>
                                        <tr data-class-id="<?php echo e($a['class_subject_id']); ?>">
                                            <td class="ps-4 fw-bold text-primary"><?php echo e($a['title']); ?></td>
                                            <td class="fw-bold text-dark"><?php echo e($a['class_name']); ?> <span class="fw-normal text-muted">- <?php echo e($a['subject_name']); ?></span></td>
                                            <td>
                                                <?php if ($a['deadline']): ?>
                                                <span class="<?php echo $isOpen ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                    <?php echo formatDate($a['deadline'], 'd/m/Y H:i'); ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="text-muted">Không có</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?php echo e($statusClass); ?> bg-opacity-10 text-<?php echo e($statusClass); ?> border border-<?php echo e($statusClass); ?> border-opacity-25"><?php echo e($statusText); ?></span></td>
                                            <td><span class="fw-bold text-primary"><?php echo e($a['submitted_count']); ?></span>/<?php echo e($a['total_students']); ?></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-light action-btn text-primary border me-1" title="Sửa bài tập" data-bs-toggle="modal" data-bs-target="#addAssignmentModal" onclick='openAssignmentModal("edit", "<?php echo e($a['title']); ?>", "<?php echo e($a['class_subject_id']); ?>", "<?php echo e($a['deadline']); ?>", "<?php echo e($a['description'] ?? ''); ?>")'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-light action-btn text-danger border" title="Xóa" onclick="confirmDeleteAssignment('<?php echo e($a['id']); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

                <div class="tab-pane fade" id="grade-panel" role="tabpanel">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <h5 class="fw-bold text-dark m-0"><i class="bi bi-check2-circle text-success me-2"></i>Danh sách Nộp bài</h5>
                                <select class="form-select form-select-sm border-success" style="width: 280px;" id="assignmentSelect" onchange="loadSubmissions()">
                                    <option value="">-- Chọn bài tập để chấm --</option>
                                    <?php foreach ($assignments as $a): ?>
                                    <option value="<?php echo e($a['id']); ?>" <?php echo ($a['id'] == $selectedAssignmentId) ? 'selected' : ''; ?>>
                                        [<?php echo e($a['class_name']); ?>] <?php echo e($a['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Tìm MSSV, Họ tên..." id="searchSubmission" onkeyup="filterSubmissions()">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="submissionsTable">
                                    <thead class="table-light small text-muted fw-bold">
                                        <tr>
                                            <th class="ps-4">MSSV</th>
                                            <th>HỌ TÊN SINH VIÊN</th>
                                            <th>THỜI GIAN NỘP</th>
                                            <th>FILE ĐÍNH KÈM (BÀI LÀM)</th>
                                            <th class="text-center">ĐIỂM SỐ</th>
                                            <th class="text-end pe-4">THAO TÁC</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($submissions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <?php if ($selectedAssignmentId): ?>
                                                Chưa có sinh viên nào nộp bài.
                                                <?php else: ?>
                                                Vui lòng chọn bài tập để xem danh sách nộp bài.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($submissions as $sub): ?>
                                        <tr data-student-id="<?php echo e($sub['student_id']); ?>">
                                            <td class="ps-4 fw-bold text-dark"><?php echo e($sub['username']); ?></td>
                                            <td class="fw-bold"><?php echo e($sub['full_name']); ?></td>
                                            <td>
                                                <?php if ($sub['submitted_at']): ?>
                                                <span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i><?php echo formatDateTime($sub['submitted_at']); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($sub['drive_link']): ?>
                                                <a class="text-decoration-none file-link text-primary" href="<?php echo e($sub['drive_link']); ?>" target="_blank">
                                                    <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>Xem file
                                                </a>
                                                <?php else: ?>
                                                <span class="text-muted">Không có file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($sub['score'] !== null): ?>
                                                <span class="fw-bold text-success fs-5"><?php echo e($sub['score']); ?></span>
                                                <?php else: ?>
                                                <span class="badge bg-warning text-dark">Chưa chấm</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm <?php echo $sub['score'] !== null ? 'btn-light border text-secondary' : 'btn-outline-primary fw-bold'; ?>" data-bs-toggle="modal" data-bs-target="#gradeModal" onclick='openGradeModal("<?php echo e($sub['username']); ?>", "<?php echo e($sub['full_name']); ?>", "<?php echo e($sub['score'] ?? ''); ?>", "<?php echo e($sub['feedback'] ?? ''); ?>", "<?php echo e($sub['id']); ?>")'>
                                                    <i class="bi bi-<?php echo $sub['score'] !== null ? 'pencil me-1' : 'pencil-square me-1'; ?>"></i> <?php echo $sub['score'] !== null ? 'Sửa điểm' : 'Chấm điểm'; ?>
                                                </button>
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
            </div>
        </div>
    </div>

    <div class="modal fade" id="addAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="assignmentModalTitle"><i class="bi bi-journal-plus me-2"></i>Tạo Bài tập mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="assignmentForm" action="api/assignments.php" method="POST">
                        <input type="hidden" name="action" id="assignmentAction" value="create">
                        <input type="hidden" name="assignment_id" id="assignmentId" value="">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Chọn Lớp học phần <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalAssignClass" name="class_subject_id" required>
                                    <option value="">-- Chọn lớp --</option>
                                    <?php foreach ($classSubjects as $cs): ?>
                                    <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Hạn chót nộp bài (Deadline) <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="modalAssignDeadline" name="deadline">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tiêu đề bài tập <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modalAssignTitle" name="title" placeholder="VD: Báo cáo giữa kỳ chương 1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mô tả / Yêu cầu chi tiết</label>
                            <textarea class="form-control" rows="3" id="modalAssignDesc" name="description" placeholder="Nhập hướng dẫn làm bài cho sinh viên..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-primary px-4" onclick="submitAssignment()">Giao bài tập</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gradeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Chấm điểm Sinh viên</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body py-2 px-3 d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" id="modalStudentName">--</h6>
                                <small class="text-muted" id="modalStudentId">MSSV: --</small>
                            </div>
                        </div>
                    </div>

                    <form id="gradeForm">
                        <input type="hidden" name="submission_id" id="gradeSubmissionId" value="">
                        
                        <div class="row mb-3">
                            <div class="col-5">
                                <label class="form-label fw-bold text-success">Điểm số (Hệ 10)</label>
                                <input type="number" step="0.5" min="0" max="10" class="form-control form-control-lg fw-bold text-center text-success" id="modalScoreInput" name="score" placeholder="--">
                            </div>
                        </div>
                        <div>
                            <label class="form-label fw-bold">Nhận xét (Feedback)</label>
                            <textarea class="form-control" rows="3" id="modalFeedbackInput" name="feedback" placeholder="Nhập phản hồi cho sinh viên này (Không bắt buộc)..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pb-4 px-4 bg-light">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button class="btn btn-success px-4" onclick="saveGrade()">Lưu Điểm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/script.js"></script>
    <script src="../../public/js/teacher/teacher-layout.js"></script>
    
    <script src="../../public/js/teacher/class-assignments.js"></script>
    <script>
        function filterAssignments() {
            const classId = document.getElementById('filterClass').value;
            const rows = document.querySelectorAll('#assignmentsTable tbody tr');
            rows.forEach(row => {
                if (!classId || row.dataset.classId === classId) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function loadSubmissions() {
            const assignmentId = document.getElementById('assignmentSelect').value;
            if (assignmentId) {
                window.location.href = 'class-assignments.php?assignment_id=' + assignmentId + '#grade-panel';
            }
        }
        
        function filterSubmissions() {
            const filter = document.getElementById('searchSubmission').value.toLowerCase();
            const rows = document.querySelectorAll('#submissionsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }
        
        function openAssignmentModal(mode, title, classId, deadline, description) {
            document.getElementById('assignmentAction').value = mode === 'add' ? 'create' : 'update';
            document.getElementById('assignmentId').value = '';
            document.getElementById('modalAssignTitle').value = title || '';
            document.getElementById('modalAssignClass').value = classId || '';
            document.getElementById('modalAssignDeadline').value = deadline ? deadline.replace(' ', 'T') : '';
            document.getElementById('modalAssignDesc').value = description || '';
            document.getElementById('assignmentModalTitle').innerHTML = mode === 'add' ? '<i class="bi bi-journal-plus me-2"></i>Tạo Bài tập mới' : '<i class="bi bi-pencil me-2"></i>Sửa Bài tập';
        }
        
        function submitAssignment() {
            // AJAX submit form
            const form = document.getElementById('assignmentForm');
            const formData = new FormData(form);
            
            fetch('api/assignments.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .catch(() => {
                alert('Có lỗi xảy ra');
            });
        }
        
        function openGradeModal(mssv, fullName, score, feedback, submissionId) {
            document.getElementById('modalStudentName').textContent = fullName || '--';
            document.getElementById('modalStudentId').textContent = 'MSSV: ' + (mssv || '--');
            document.getElementById('modalScoreInput').value = score || '';
            document.getElementById('modalFeedbackInput').value = feedback || '';
            document.getElementById('gradeSubmissionId').value = submissionId || '';
        }
        
        function saveGrade() {
            const form = document.getElementById('gradeForm');
            const formData = new FormData(form);
            formData.append('action', 'grade');
            
            fetch('api/assignments.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .catch(() => {
                alert('Có lỗi xảy ra');
            });
        }
        
        function confirmDeleteAssignment(id) {
            if (confirm('Bạn có chắc muốn xóa bài tập này?')) {
                fetch('api/assignments.php?action=delete&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
    </script>
</body>
</html>
