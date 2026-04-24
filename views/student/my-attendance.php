<?php
/**
 * CMS BDU - Điểm Danh Cá Nhân Sinh Viên
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Lịch Sử Điểm Danh';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/my-attendance.css'];
$extraJs = ['student/student-layout.js'];

function studentAttendanceSessionLabel($startPeriod): string {
    $p = (int)$startPeriod;
    if ($p >= 1 && $p <= 5) return 'Sáng';
    if ($p >= 6 && $p <= 10) return 'Chiều';
    if ($p >= 11 && $p <= 14) return 'Tối';
    return 'Sáng';
}

function getAttendanceStatusLabel($status): array {
    $s = (int)$status;
    if ($s === 1) return ['class' => 'success', 'text' => 'Có mặt'];
    if ($s === 2) return ['class' => 'warning text-dark', 'text' => 'Vắng có phép'];
    return ['class' => 'danger', 'text' => 'Vắng không phép'];
}

function studentAttendanceSemesterLabel($semesterName, $academicYear): string {
    $name = strtoupper(trim((string)$semesterName));
    $year = trim((string)$academicYear);
    if (preg_match('/^(HK)?([123])$/', $name, $m)) {
        return 'Học kỳ ' . $m[2] . ($year !== '' ? (' - ' . $year) : '');
    }
    return trim((string)$semesterName . ($year !== '' ? (' - ' . $year) : ''));
}

$currentSemester = db_fetch_one(
    "SELECT id, semester_name, academic_year, start_date, end_date
     FROM semesters
     WHERE CURDATE() BETWEEN start_date AND end_date
     ORDER BY start_date DESC
     LIMIT 1"
);
if (!$currentSemester) {
    $currentSemester = db_fetch_one(
        "SELECT id, semester_name, academic_year, start_date, end_date
         FROM semesters
         ORDER BY academic_year DESC,
                  FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
                  start_date DESC
         LIMIT 1"
    );
}

$subjects = db_fetch_all(
    "SELECT DISTINCT cs.id as class_subject_id,
            s.subject_name,
            s.subject_code,
            sm.id as semester_id,
            sm.semester_name,
            sm.academic_year,
            t.full_name as teacher_name,
            cs.start_date,
            cs.end_date
     FROM student_subject_registration ssr
     JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
     JOIN class_subjects cs ON csg.class_subject_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
     LEFT JOIN semesters sm ON cs.semester_id = sm.id
     LEFT JOIN users t ON cs.teacher_id = t.id
     WHERE ssr.student_id = ?
       AND ssr.status = 'Đang học'
       AND COALESCE(s.subject_name, '') <> ''
     ORDER BY s.subject_name",
    [$userId]
);

$selectedSubjectId = (int)($_GET['subject_id'] ?? ($subjects[0]['class_subject_id'] ?? 0));
$selectedSubject = null;
$attendanceSummary = ['total' => 0, 'present' => 0, 'absent' => 0, 'excused' => 0];
$attendanceRecords = [];

if ($selectedSubjectId > 0) {
    foreach ($subjects as $s) {
        if ((int)($s['class_subject_id'] ?? 0) === $selectedSubjectId) {
            $selectedSubject = $s;
            break;
        }
    }

    if ($selectedSubject) {
        $row = db_fetch_one(
            "SELECT COUNT(*) as total
             FROM attendance_sessions a_s
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ?",
            [$selectedSubjectId]
        );
        $attendanceSummary['total'] = (int)($row['total'] ?? 0);

        $row = db_fetch_one(
            "SELECT COUNT(*) as count
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 1",
            [$selectedSubjectId, $userId]
        );
        $attendanceSummary['present'] = (int)($row['count'] ?? 0);

        $row = db_fetch_one(
            "SELECT COUNT(*) as count
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 2",
            [$selectedSubjectId, $userId]
        );
        $attendanceSummary['excused'] = (int)($row['count'] ?? 0);

        $row = db_fetch_one(
            "SELECT COUNT(*) as count
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ? AND ar.student_id = ? AND ar.status = 3",
            [$selectedSubjectId, $userId]
        );
        $attendanceSummary['absent'] = (int)($row['count'] ?? 0);

        $attendanceRecords = db_fetch_all(
            "SELECT ar.*, a_s.attendance_date, csg.start_period,
                    ae.status as evidence_status, ae.drive_link, ae.uploaded_at,
                    u.full_name as approved_by_name
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             LEFT JOIN attendance_evidences ae ON ae.attendance_record_id = ar.id
             LEFT JOIN users u ON ae.approved_by = u.id
             WHERE csg.class_subject_id = ? AND ar.student_id = ?
             ORDER BY a_s.attendance_date DESC",
            [$selectedSubjectId, $userId]
        );
    }
}

$unreadNotifications = (int)(db_fetch_one(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
)['total'] ?? 0);

$warningCount = $attendanceSummary['absent'];
$isWarning = $warningCount >= 3;

$semesterLabel = studentAttendanceSemesterLabel(
    $selectedSubject['semester_name'] ?? ($currentSemester['semester_name'] ?? ''),
    $selectedSubject['academic_year'] ?? ($currentSemester['academic_year'] ?? '')
);
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">LỊCH SỬ ĐIỂM DANH</h5>
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
                            <option selected><?= e($semesterLabel !== '' ? $semesterLabel : 'Học kỳ - Chưa xác định') ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="attendance-filter-label mb-1">CHỌN MÔN HỌC</label>
                        <select class="form-select attendance-select border-primary text-primary" id="studentSubjectSelect" onchange="window.location.href='my-attendance.php?subject_id=' + this.value">
                            <?php if (empty($subjects)): ?>
                                <option value="">-- Chưa có môn học --</option>
                            <?php else: ?>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= (int)$subject['class_subject_id'] ?>" <?= $selectedSubjectId == (int)$subject['class_subject_id'] ? 'selected' : '' ?>>
                                        <?= e($subject['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-5 text-md-end mt-md-4">
                        <span class="badge bg-light text-dark border attendance-summary-badge me-2">Tổng buổi: <?= (int)$attendanceSummary['total'] ?></span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success attendance-summary-badge me-2">Có mặt: <?= (int)$attendanceSummary['present'] ?></span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger attendance-summary-badge">Đã vắng: <?= (int)$attendanceSummary['absent'] ?></span>
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
                            <div class="attendance-warning-text"><strong>Cảnh báo:</strong> Bạn đã vắng <?= (int)$warningCount ?> buổi. Vượt quá 20% sẽ bị cấm thi!</div>
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
                                        $sessionLabel = studentAttendanceSessionLabel($record['start_period'] ?? 1);
                                        $rowClass = '';
                                        if ((int)$record['status'] === 2) $rowClass = 'bg-warning bg-opacity-10';
                                        if ((int)$record['status'] === 3) $rowClass = 'bg-danger bg-opacity-10';
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="ps-4 py-3 text-center attendance-col-stt <?= (int)$record['status'] === 3 ? 'text-danger' : '' ?>"><?= $index + 1 ?></td>
                                        <td class="attendance-col-date <?= (int)$record['status'] === 3 ? 'text-danger' : 'text-dark' ?>"><?= formatDate($record['attendance_date'], 'd/m/Y') ?></td>
                                        <td class="text-dark"><?= e($sessionLabel) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusInfo['class'] ?> px-3 py-1 rounded-pill"><?= e($statusInfo['text']) ?></span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php if ((int)$record['status'] === 3 && empty($record['evidence_status'])): ?>
                                                <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Chưa có minh chứng!</span>
                                            <?php elseif (!empty($record['drive_link'])): ?>
                                                <a href="<?= e($record['drive_link']) ?>" target="_blank" class="btn btn-sm btn-light text-primary border">
                                                    <i class="bi bi-image me-1"></i>Xem file
                                                </a>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-center">
                                            <?php if ((int)$record['status'] === 3 && empty($record['evidence_status'])): ?>
                                                <span class="text-muted">Chưa nộp</span>
                                            <?php elseif (($record['evidence_status'] ?? '') === 'Pending'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-2 py-1">Đang chờ duyệt</span>
                                            <?php elseif (($record['evidence_status'] ?? '') === 'Approved'): ?>
                                                <span class="badge attendance-approved-pill border px-2 py-1">Đã duyệt hợp lệ</span>
                                            <?php elseif (($record['evidence_status'] ?? '') === 'Rejected'): ?>
                                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Bị từ chối</span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<?php foreach ($extraJs as $js): ?>
    <script src="../../public/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
