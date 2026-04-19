<?php
/**
 * CMS BDU - Teacher Class Grades
 * Trang quản lý bảng điểm lớp học phần
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

// Lấy danh sách học kỳ
$stmtSemesters = $pdo->query("SELECT * FROM semesters ORDER BY start_date DESC");
$semesters = $stmtSemesters->fetchAll();
$currentSemesterId = null;
foreach ($semesters as $s) {
    if ($s['start_date'] <= date('Y-m-d') && $s['end_date'] >= date('Y-m-d')) {
        $currentSemesterId = $s['id'];
        break;
    }
}

// Lấy class_subject được chọn
$selectedClassSubjectId = $_GET['class_subject_id'] ?? null;

// Lấy danh sách lớp học phần của giảng viên
$stmtClassSubjects = $pdo->prepare("
    SELECT cs.id, cs.semester_id,
           c.class_name,
           s.subject_code, s.subject_name
    FROM class_subjects cs
    INNER JOIN semesters sem ON cs.semester_id = sem.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.class_name
");
$stmtClassSubjects->execute([$teacherId]);
$classSubjects = $stmtClassSubjects->fetchAll();

// Lấy học sinh và điểm nếu có class_subject được chọn
$students = [];
if ($selectedClassSubjectId) {
    $stmtStudents = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.birth_date,
               g.assignment_score, g.midterm_score, g.final_score, g.total_score, g.grade_letter,
               csg.id as group_id
        FROM student_subject_registration ssr
        INNER JOIN users u ON ssr.student_id = u.id
        INNER JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
        LEFT JOIN grades g ON g.student_id = u.id AND g.class_subject_group_id = csg.id
        WHERE csg.class_subject_id = ?
        ORDER BY u.username
    ");
    $stmtStudents->execute([$selectedClassSubjectId]);
    $students = $stmtStudents->fetchAll();
    
    // Đếm sĩ số
    $totalStudents = count($students);
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
    <title>CMS BDU - Quản lý Bảng điểm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/class-grades.css">
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
                <a href="class-assignments.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-journal-text me-2"></i> Quản lý Bài tập</a>
                <a href="class-grades.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i> Bảng điểm</a>
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
                BẢNG ĐIỂM LỚP HỌC PHẦN
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
            
            <div class="card shadow-sm border-0">
                
                <div class="card-header bg-white p-4 border-bottom border-secondary border-opacity-10 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Chọn Học kỳ</label>
                            <select id="semesterSelect" class="form-select border-info fw-bold text-dark" style="width: 200px;" onchange="filterBySemester()">
                                <?php foreach ($semesters as $sem): ?>
                                <option value="<?php echo e($sem['id']); ?>" <?php echo ($sem['id'] == $currentSemesterId) ? 'selected' : ''; ?>>
                                    <?php echo e($sem['semester_name']); ?> - <?php echo e($sem['academic_year']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Chọn Lớp học phần</label>
                            <select id="classSelect" class="form-select border-info fw-bold text-dark" style="width: 300px;" onchange="loadGrades()">
                                <option value="">-- Chọn lớp học phần --</option>
                                <?php foreach ($classSubjects as $cs): ?>
                                <option value="<?php echo e($cs['id']); ?>" <?php echo ($cs['id'] == $selectedClassSubjectId) ? 'selected' : ''; ?>>
                                    <?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ms-2">
                            <span class="badge bg-light text-dark border p-2 mt-4"><i class="bi bi-people-fill me-1"></i>Sĩ số: <?php echo e($totalStudents ?? 0); ?> SV</span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button class="btn btn-outline-success me-2" onclick="exportGrades()"><i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel</button>
                        <button class="btn btn-primary px-4 shadow-sm" onclick="saveGrades()"><i class="bi bi-floppy-fill me-2"></i>Lưu Bảng Điểm</button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-grades mb-0" id="gradesTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">STT</th>
                                    <th style="width: 120px;">MSSV</th>
                                    <th class="text-start">HỌ VÀ TÊN</th>
                                    <th style="width: 120px;" title="Điểm Bài tập (Trọng số 20%)">Đ. BÀI TẬP<br><small class="text-muted fw-normal">(20%)</small></th>
                                    <th style="width: 120px;" title="Điểm Giữa kỳ (Trọng số 30%)">Đ. GIỮA KỲ<br><small class="text-muted fw-normal">(30%)</small></th>
                                    <th style="width: 120px;" title="Điểm Cuối kỳ (Trọng số 50%)">Đ. CUỐI KỲ<br><small class="text-muted fw-normal">(50%)</small></th>
                                    <th style="width: 120px;" class="bg-light">TỔNG KẾT<br><small class="text-muted fw-normal">(Hệ 10)</small></th>
                                    <th style="width: 100px;" class="bg-light">ĐIỂM CHỮ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        Vui lòng chọn lớp học phần để xem bảng điểm.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php $stt = 1; foreach ($students as $student): ?>
                                <?php 
                                    $totalScore = null;
                                    $gradeLetter = null;
                                    if ($student['assignment_score'] !== null && $student['midterm_score'] !== null && $student['final_score'] !== null) {
                                        $totalScore = $student['assignment_score'] * 0.2 + $student['midterm_score'] * 0.3 + $student['final_score'] * 0.5;
                                        if ($totalScore >= 8.5) $gradeLetter = 'A';
                                        elseif ($totalScore >= 7.0) $gradeLetter = 'B';
                                        elseif ($totalScore >= 5.5) $gradeLetter = 'C';
                                        elseif ($totalScore >= 4.0) $gradeLetter = 'D';
                                        else $gradeLetter = 'F';
                                    }
                                ?>
                                <tr data-student-id="<?php echo e($student['id']); ?>" data-group-id="<?php echo e($student['group_id']); ?>">
                                    <td class="text-center text-muted"><?php echo e($stt++); ?></td>
                                    <td class="fw-bold"><?php echo e($student['username']); ?></td>
                                    <td class="text-start fw-bold text-dark"><?php echo e($student['full_name']); ?></td>
                                    <td><input type="number" class="grade-input assign-score" min="0" max="10" step="0.5" value="<?php echo e($student['assignment_score'] ?? ''); ?>" onchange="calculateRow(this)"></td>
                                    <td><input type="number" class="grade-input midterm-score" min="0" max="10" step="0.5" value="<?php echo e($student['midterm_score'] ?? ''); ?>" onchange="calculateRow(this)"></td>
                                    <td><input type="number" class="grade-input final-score" min="0" max="10" step="0.5" value="<?php echo e($student['final_score'] ?? ''); ?>" onchange="calculateRow(this)"></td>
                                    <td class="bg-light fw-bold text-primary fs-5 total-score"><?php echo $totalScore !== null ? number_format($totalScore, 1) : '--'; ?></td>
                                    <td class="bg-light">
                                        <?php if ($gradeLetter): ?>
                                        <span class="badge grade-<?php echo strtolower($gradeLetter); ?> w-100 py-2 grade-letter"><?php echo e($gradeLetter); ?></span>
                                        <?php else: ?>
                                        <span class="badge w-100 py-2 grade-letter">--</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3 alert alert-info border-0 shadow-sm d-flex">
                <i class="bi bi-info-circle-fill fs-4 me-3 mt-1"></i>
                <div>
                    <strong>Quy chế tính điểm:</strong> Điểm Tổng Kết = (Điểm Bài Tập * 20%) + (Điểm Giữa Kỳ * 30%) + (Điểm Cuối Kỳ * 50%).<br>
                    <strong>Quy đổi Điểm chữ:</strong> Từ 8.5 đến 10 (A) | Từ 7.0 đến 8.4 (B) | Từ 5.5 đến 6.9 (C) | Từ 4.0 đến 5.4 (D) | Dưới 4.0 (F).
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/script.js"></script>
    <script src="../../public/js/teacher/teacher-layout.js"></script>
    
    <script src="../../public/js/teacher/class-grades.js"></script>
    <script>
        function loadGrades() {
            const classSubjectId = document.getElementById('classSelect').value;
            if (classSubjectId) {
                window.location.href = 'class-grades.php?class_subject_id=' + classSubjectId;
            }
        }
        
        function filterBySemester() {
            // Filter by semester - reload page with semester filter
            const semesterId = document.getElementById('semesterSelect').value;
            const classSubjectId = document.getElementById('classSelect').value;
            let url = 'class-grades.php?semester_id=' + semesterId;
            if (classSubjectId) url += '&class_subject_id=' + classSubjectId;
            window.location.href = url;
        }
        
        function exportGrades() {
            alert('Xuất file Excel bảng điểm.');
        }
    </script>
</body>
</html>
