<?php
/**
 * CMS BDU - Điểm Danh Cá Nhân Sinh Viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Lịch Sử Điểm Danh';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/my-attendance.css'];
$extraJs = ['student/student-layout.js', 'student/my-attendance.js'];

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT id, semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy danh sách môn học của sinh viên
$stmt = $pdo->prepare("
    SELECT DISTINCT cs.id as class_subject_id, s.subject_name, s.subject_code,
           cs.semester, t.full_name as teacher_name, cs.start_date, cs.end_date
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

// Lấy thông tin môn được chọn hoặc mặc định
$selectedSubjectId = $_GET['subject_id'] ?? ($subjects[0]['class_subject_id'] ?? null);

$selectedSubject = null;
$attendanceSummary = ['total' => 0, 'present' => 0, 'absent' => 0, 'excused' => 0];
$attendanceRecords = [];

if ($selectedSubjectId) {
    // Tìm môn được chọn
    foreach ($subjects as $s) {
        if ($s['class_subject_id'] == $selectedSubjectId) {
            $selectedSubject = $s;
            break;
        }
    }
    
    if ($selectedSubject) {
        // Lấy tổng số buổi điểm danh
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM attendance_sessions a_s
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            WHERE csg.class_subject_id = ?
        ");
        $stmt->execute([$selectedSubjectId]);
        $attendanceSummary['total'] = $stmt->fetch()['total'];
        
        // Lấy số buổi có mặt
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 1
        ");
        $stmt->execute([$selectedSubjectId, $userId]);
        $attendanceSummary['present'] = $stmt->fetch()['count'];
        
        // Lấy số buổi vắng có phép
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 2
        ");
        $stmt->execute([$selectedSubjectId, $userId]);
        $attendanceSummary['excused'] = $stmt->fetch()['count'];
        
        // Lấy số buổi vắng không phép
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 3
        ");
        $stmt->execute([$selectedSubjectId, $userId]);
        $attendanceSummary['absent'] = $stmt->fetch()['count'];
        
        // Lấy chi tiết các buổi điểm danh
        $stmt = $pdo->prepare("
            SELECT ar.*, a_s.attendance_date, a_s.session_type,
                   ae.status as evidence_status, ae.drive_link, ae.uploaded_at,
                   u.full_name as approved_by_name
            FROM attendance_records ar
            JOIN attendance_sessions a_s ON ar.session_id = a_s.id
            JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
            LEFT JOIN attendance_evidences ae ON ae.attendance_record_id = ar.id
            LEFT JOIN users u ON ae.approved_by = u.id
            WHERE csg.class_subject_id = ? AND ar.student_id = ?
            ORDER BY a_s.attendance_date DESC
        ");
        $stmt->execute([$selectedSubjectId, $userId]);
        $attendanceRecords = $stmt->fetchAll();
    }
}

// Lấy số thông báo chưa đọc
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetch()['total'];

// Cảnh báo vắng
$warningCount = $attendanceSummary['absent'];
$isWarning = $warningCount >= 3;
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
                LỊCH SỬ ĐIỂM DANH
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
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-3">
                        <label class="attendance-filter-label mb-1">HỌC KỲ</label>
                        <select class="form-select attendance-select bg-light" id="studentSemesterSelect" disabled>
                            <option selected><?= e($currentSemester['semester_name'] ?? 'HK2') ?> (<?= e($currentSemester['academic_year'] ?? '25-26') ?>)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="attendance-filter-label mb-1">CHỌN MÔN HỌC</label>
                        <select class="form-select attendance-select border-primary text-primary" id="studentSubjectSelect" onchange="window.location.href='my-attendance.php?subject_id=' + this.value">
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['class_subject_id'] ?>" <?= $selectedSubjectId == $subject['class_subject_id'] ? 'selected' : '' ?>>
                                    <?= e($subject['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 text-md-end mt-md-4">
                        <span class="badge bg-light text-dark border attendance-summary-badge me-2">Tổng buổi: <?= $attendanceSummary['total'] ?></span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success attendance-summary-badge me-2">Có mặt: <?= $attendanceSummary['present'] ?></span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger attendance-summary-badge">Đã vắng: <?= $attendanceSummary['absent'] ?></span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-7">
                        <?php if ($selectedSubject): ?>
                        <div id="studentSubjectInfo" class="attendance-info-box p-2 bg-light rounded border-start border-3 border-primary shadow-sm d-flex align-items-center">
                            <div class="row w-100 text-dark small m-0">
                                <div class="col-md-5 p-0"><i class="bi bi-person-badge text-primary me-2"></i>Giảng viên: <strong><?= e($selectedSubject['teacher_name'] ?? 'Chưa phân công') ?></strong></div>
                                <div class="col-md-7 p-0"><i class="bi bi-calendar2-range text-primary me-2"></i>Thời gian: <strong><?= formatDate($selectedSubject['start_date'], 'd/m/Y') ?> - <?= formatDate($selectedSubject['end_date'], 'd/m/Y') ?></strong></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <?php if ($isWarning): ?>
                        <div class="alert alert-danger mb-0 py-3 d-flex align-items-center attendance-warning-box shadow-sm border-danger border-opacity-50">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                            <div class="attendance-warning-text"><strong>Cảnh báo:</strong> Bạn đã vắng <?= $warningCount ?> buổi. Vượt quá 20% sẽ bị cấm thi!</div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mb-0 py-3 d-flex align-items-center shadow-sm border-success border-opacity-50">
                            <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                            <div>Tình trạng chuyên cần tốt. Tiếp tục duy trì nhé!</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0">
                <h5 class="attendance-detail-title fw-bold text-dark m-0"><i class="bi bi-list-columns-reverse text-primary me-2"></i>Chi tiết các buổi học</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle custom-detail-table attendance-detail-table mb-0">
                        <thead class="text-muted small fw-bold" style="background-color: #f8f9fa;">
                            <tr>
                                <th class="ps-4 py-3 text-center">STT</th>
                                <th class="py-3">NGÀY HỌC</th>
                                <th class="py-3">BUỔI</th>
                                <th class="py-3">TRẠNG THÁI</th>
                                <th class="py-3">FILE MINH CHỨNG</th>
                                <th class="pe-4 py-3 text-center">XÉT DUYỆT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendanceRecords)): ?>
                                <?php foreach ($attendanceRecords as $index => $record): ?>
                                    <?php
                                    $statusInfo = getAttendanceStatusLabel($record['status']);
                                    $rowClass = '';
                                    if ($record['status'] == 2) $rowClass = 'bg-warning bg-opacity-10';
                                    if ($record['status'] == 3) $rowClass = 'bg-danger bg-opacity-10';
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="ps-4 py-3 text-center attendance-col-stt <?= $record['status'] == 3 ? 'text-danger' : '' ?>"><?= $index + 1 ?></td>
                                        <td class="attendance-col-date <?= $record['status'] == 3 ? 'text-danger' : 'text-dark' ?>"><?= formatDate($record['attendance_date'], 'd/m/Y') ?></td>
                                        <td class="text-dark"><?= e($record['session_type'] ?? 'Sáng') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusInfo['class'] ?> px-3 py-1 rounded-pill">
                                                <i class="bi bi-<?= $record['status'] == 1 ? 'check-circle' : ($record['status'] == 2 ? 'exclamation-circle' : 'x-circle') ?> me-1"></i>
                                                <?= $statusInfo['text'] ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php if ($record['status'] == 3 && empty($record['evidence_status'])): ?>
                                                <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Chưa có minh chứng!</span>
                                            <?php elseif ($record['drive_link']): ?>
                                                <a href="<?= e($record['drive_link']) ?>" target="_blank" class="btn btn-sm btn-light text-primary border">
                                                    <i class="bi bi-image me-1"></i>Xem file
                                                </a>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-center">
                                            <?php if ($record['status'] == 3 && empty($record['evidence_status'])): ?>
                                                <button class="btn btn-sm btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadProofModal" onclick="prepareProofUpload('<?= formatDate($record['attendance_date'], 'd/m/Y') ?>', '<?= e($record['session_type'] ?? 'Sáng') ?>')">
                                                    <i class="bi bi-cloud-upload me-1"></i> Nộp bổ sung
                                                </button>
                                            <?php elseif ($record['evidence_status'] == 'Pending'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-2 py-1">
                                                    <i class="bi bi-hourglass-split me-1"></i>Đang chờ duyệt
                                                </span>
                                            <?php elseif ($record['evidence_status'] == 'Approved'): ?>
                                                <span class="badge attendance-approved-pill border px-2 py-1">
                                                    <i class="bi bi-check-all me-1"></i>Đã duyệt hợp lệ
                                                </span>
                                            <?php elseif ($record['evidence_status'] == 'Rejected'): ?>
                                                <div class="d-flex flex-column align-items-center">
                                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1 mb-1">
                                                        <i class="bi bi-x-octagon-fill me-1"></i>Bị từ chối
                                                    </span>
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#uploadProofModal">
                                                        <i class="bi bi-arrow-repeat me-1"></i>Nộp lại
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Chưa có dữ liệu điểm danh cho môn học này.
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

<div class="modal fade" id="uploadProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-medical-fill me-2"></i>Nộp Minh Chứng Vắng Học</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        
        <div class="alert alert-warning small mb-3 border-warning border-opacity-50">
            <strong>Thông tin buổi vắng:</strong> Môn <span class="fw-bold"><?= e($selectedSubject['subject_name'] ?? '') ?></span> - Ngày <span id="proofDateInfo" class="fw-bold text-danger">--</span>
        </div>

        <form id="proofForm" action="submit-proof.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="record_id" id="proofRecordId">
          <div class="mb-3">
            <label class="form-label fw-bold">Loại minh chứng <span class="text-danger">*</span></label>
            <select class="form-select border-danger" id="proofType" name="proof_type" required>
                <option value="kham_benh" selected>Giấy khám bệnh / Viện phí</option>
                <option value="don_xin_phep">Đơn xin phép có chữ ký Phụ huynh</option>
                <option value="khac">Lý do khác (Có ảnh chứng minh)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Link Google Drive <span class="text-danger">*</span></label>
            <input class="form-control border-danger" type="url" id="proofFile" name="drive_link" placeholder="https://drive.google.com/..." required>
            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Chia sẻ file trên Google Drive với quyền "Ai có link đều xem được"</div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold">Ghi chú (Tùy chọn)</label>
            <textarea class="form-control" rows="2" name="note" placeholder="Ví dụ: Mình đính kèm ảnh siêu âm có dấu mộc đỏ..."></textarea>
          </div>
        </form>

      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" form="proofForm" class="btn btn-danger fw-bold px-4"><i class="bi bi-send-fill me-2"></i>Gửi Minh Chứng</button>
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
