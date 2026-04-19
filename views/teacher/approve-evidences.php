<?php
/**
 * CMS BDU - Teacher Approve Evidences
 * Trang duyệt minh chứng nghỉ học
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

// Lấy danh sách lớp học phần
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

// Lấy danh sách minh chứng
$stmtEvidences = $pdo->prepare("
    SELECT ae.*, 
           ar.attendance_date, ar.status as attendance_status,
           u.username as student_code, u.full_name as student_name,
           c.class_name, s.subject_name,
           ae.status as evidence_status,
           ae.id as evidence_id
    FROM attendance_evidences ae
    INNER JOIN attendance_records ar ON ae.attendance_record_id = ar.id
    INNER JOIN attendance_sessions ass ON ar.session_id = ass.id
    INNER JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
    INNER JOIN class_subjects cs ON csg.class_subject_id = cs.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    INNER JOIN users u ON ar.student_id = u.id
    WHERE cs.teacher_id = ?
    ORDER BY ae.status ASC, ae.uploaded_at DESC
");
$stmtEvidences->execute([$teacherId]);
$evidences = $stmtEvidences->fetchAll();

// Đếm minh chứng chờ duyệt
$pendingCount = count(array_filter($evidences, function($e) {
    return $e['evidence_status'] === 'Pending';
}));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Duyệt Minh Chứng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/approve-evidences.css">
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
                <a href="class-grades.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i> Bảng điểm</a>
                <a href="approve-evidences.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical me-2"></i> Duyệt minh chứng</span>
                    <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo e($pendingCount); ?></span>
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
                DUYỆT MINH CHỨNG NGHỈ HỌC
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
                    <div class="d-flex align-items-end gap-3 flex-wrap">
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Trạng thái minh chứng</label>
                            <select class="form-select border-secondary fw-bold text-dark" style="width: 200px;" id="statusFilter" onchange="filterEvidences()">
                                <option value="all">Tất cả trạng thái</option>
                                <option value="pending" <?php echo ($pendingCount > 0) ? 'selected' : ''; ?>>Đang chờ duyệt (<?php echo e($pendingCount); ?>)</option>
                                <option value="approved">Đã duyệt (Phép)</option>
                                <option value="rejected">Bị từ chối (K.Phép)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Lớp học phần</label>
                            <select class="form-select border-secondary fw-bold text-dark" style="width: 220px;" id="classFilter" onchange="filterEvidences()">
                                <option value="">-- Tất cả lớp --</option>
                                <?php foreach ($classSubjects as $cs): ?>
                                <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" placeholder="Tìm MSSV, Tên sinh viên..." id="searchEvidence" onkeyup="filterEvidences()">
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="evidencesTable">
                            <thead class="table-light small text-muted fw-bold">
                                <tr>
                                    <th class="ps-4">NGÀY VẮNG</th>
                                    <th>MSSV</th>
                                    <th>HỌ VÀ TÊN SINH VIÊN</th>
                                    <th>LỚP HỌC PHẦN</th>
                                    <th>THỜI GIAN NỘP</th>
                                    <th class="text-center">TRẠNG THÁI</th>
                                    <th class="text-end pe-4">XEM & DUYỆT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($evidences)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Chưa có minh chứng nào cần duyệt.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($evidences as $ev): ?>
                                <?php
                                    $statusClass = 'bg-warning text-dark border-warning';
                                    $statusText = 'Chờ duyệt';
                                    $rowClass = '';
                                    if ($ev['evidence_status'] === 'Approved') {
                                        $statusClass = 'bg-success bg-opacity-10 text-success border-success border-opacity-25';
                                        $statusText = 'Đã duyệt';
                                        $rowClass = 'bg-light';
                                    } elseif ($ev['evidence_status'] === 'Rejected') {
                                        $statusClass = 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25';
                                        $statusText = 'Từ chối';
                                        $rowClass = 'bg-light';
                                    }
                                ?>
                                <tr data-status="<?php echo e($ev['evidence_status']); ?>" data-student="<?php echo e(strtolower($ev['student_name'] . ' ' . $ev['student_code'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo formatDate($ev['attendance_date'], 'd/m/Y'); ?></td>
                                    <td class="fw-bold text-primary"><?php echo e($ev['student_code']); ?></td>
                                    <td class="fw-bold"><?php echo e($ev['student_name']); ?></td>
                                    <td class="text-muted small"><?php echo e($ev['class_name']); ?> - <?php echo e($ev['subject_name']); ?></td>
                                    <td><?php echo formatDateTime($ev['uploaded_at']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo e($statusClass); ?> border"><?php echo e($statusText); ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm <?php echo $ev['evidence_status'] === 'Pending' ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold" onclick='openEvidenceModal("<?php echo e($ev['student_name']); ?>", "<?php echo e($ev['student_code']); ?>", "<?php echo e($ev['class_name']); ?> - <?php echo e($ev['subject_name']); ?>", "<?php echo formatDate($ev['attendance_date'], 'd/m/Y'); ?>", "<?php echo e($ev['drive_link'] ?? ''); ?>", "<?php echo e($ev['evidence_status']); ?>", "<?php echo e($ev['evidence_id']); ?>")'>
                                            <i class="bi bi-eye-fill me-1"></i> <?php echo $ev['evidence_status'] === 'Pending' ? 'Xem File' : 'Đã xem'; ?>
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

    <div class="modal fade" id="evidenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-image me-2"></i>Chi tiết Minh chứng</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-light">
                    <div class="row g-0">
                        <div class="col-md-7 p-3 text-center border-end bg-white">
                            <p class="small text-muted mb-2"><i class="bi bi-zoom-in me-1"></i> Ảnh đính kèm (Có thể cuộn để xem)</p>
                            <img src="" id="modalEvidenceImage" class="evidence-preview-img shadow-sm" alt="Hình ảnh minh chứng" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div id="noImagePlaceholder" class="text-muted py-5" style="display:none;">
                                <i class="bi bi-image fs-1"></i>
                                <p class="mt-2">Không có hình ảnh</p>
                            </div>
                        </div>
                        
                        <div class="col-md-5 p-4 d-flex flex-column">
                            <h5 class="fw-bold text-primary mb-1" id="modalStudentName">--</h5>
                            <p class="text-muted small mb-3 border-bottom pb-2" id="modalStudentId">MSSV: --</p>
                            
                            <div class="mb-2">
                                <span class="text-muted small d-block">Lớp học phần:</span>
                                <span class="fw-bold text-dark" id="modalClass">--</span>
                            </div>
                            <div class="mb-4">
                                <span class="text-muted small d-block">Xin nghỉ ngày:</span>
                                <span class="fw-bold text-danger fs-5" id="modalDate">--</span>
                            </div>
                            
                            <div class="mb-4" id="rejectReasonDiv">
                                <label class="form-label small fw-bold text-danger">Lý do từ chối (Nếu có):</label>
                                <textarea class="form-control form-control-sm" rows="2" id="rejectReason" placeholder="VD: Giấy phép quá mờ, không hợp lệ..."></textarea>
                            </div>

                            <div class="mt-auto d-flex flex-column gap-2" id="actionButtonsDiv">
                                <button class="btn btn-success py-2 fw-bold shadow-sm" onclick="processEvidence('Approved')">
                                    <i class="bi bi-check-circle-fill me-2"></i> Chấp nhận (Nghỉ Có Phép)
                                </button>
                                <button class="btn btn-outline-danger py-2 fw-bold" onclick="processEvidence('Rejected')">
                                    <i class="bi bi-x-circle-fill me-2"></i> Từ chối (Nghỉ Không Phép)
                                </button>
                            </div>
                            
                            <div class="mt-auto alert alert-secondary text-center fw-bold d-none" id="statusMessageDiv">
                                <i class="bi bi-info-circle-fill me-1"></i> Minh chứng này đã được xử lý.
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/script.js"></script>
    <script src="../../public/js/teacher/teacher-layout.js"></script>
    
    <script src="../../public/js/teacher/approve-evidences.js"></script>
    <script>
        let currentEvidenceId = null;
        let currentStatus = null;
        
        function filterEvidences() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchEvidence').value.toLowerCase();
            const rows = document.querySelectorAll('#evidencesTable tbody tr');
            
            rows.forEach(row => {
                const rowStatus = row.dataset.status || '';
                const rowStudent = row.dataset.student || '';
                
                let show = true;
                if (status !== 'all' && rowStatus.toLowerCase() !== status.toLowerCase()) {
                    show = false;
                }
                if (search && !rowStudent.includes(search)) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        function openEvidenceModal(studentName, studentCode, className, date, imageUrl, status, evidenceId) {
            currentEvidenceId = evidenceId;
            currentStatus = status;
            
            document.getElementById('modalStudentName').textContent = studentName || '--';
            document.getElementById('modalStudentId').textContent = 'MSSV: ' + (studentCode || '--');
            document.getElementById('modalClass').textContent = className || '--';
            document.getElementById('modalDate').textContent = date || '--';
            document.getElementById('modalEvidenceImage').src = imageUrl || '';
            
            const actionDiv = document.getElementById('actionButtonsDiv');
            const statusDiv = document.getElementById('statusMessageDiv');
            
            if (status === 'Pending') {
                actionDiv.classList.remove('d-none');
                statusDiv.classList.add('d-none');
            } else {
                actionDiv.classList.add('d-none');
                statusDiv.classList.remove('d-none');
            }
            
            new bootstrap.Modal(document.getElementById('evidenceModal')).show();
        }
        
        function processEvidence(action) {
            const reason = document.getElementById('rejectReason').value;
            
            fetch('api/evidence.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=' + action + '&evidence_id=' + currentEvidenceId + '&reason=' + encodeURIComponent(reason)
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
    </script>
</body>
</html>
