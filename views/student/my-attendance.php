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
    if ($status === null) return ['class' => 'secondary', 'text' => '--'];
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

$currentSemester = getCurrentSemester();

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
        $today = new DateTime();
        $today->setTime(23, 59, 59);

        // Groups this student is registered in for this subject
        $groups = db_fetch_all(
            "SELECT csg.id, csg.day_of_week, csg.start_period, csg.is_extra, csg.extra_date
             FROM class_subject_groups csg
             JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ? AND ssr.student_id = ?",
            [$selectedSubjectId, $userId]
        );

        $subStart = !empty($selectedSubject['start_date']) ? new DateTime($selectedSubject['start_date']) : null;
        $subEnd   = !empty($selectedSubject['end_date'])   ? new DateTime($selectedSubject['end_date'])   : null;

        // Generate every scheduled date up to today
        // day_of_week: 2=Mon … 7=Sat, 8=Sun (Vietnamese "Thứ" notation)
        // PHP date('N'): 1=Mon … 6=Sat, 7=Sun → vnDay==8 ? 7 : vnDay-1
        $scheduledMap = []; // "gid_date" => ['date', 'group_id', 'start_period']
        foreach ($groups as $grp) {
            $gid = (int)$grp['id'];
            if ((int)$grp['is_extra'] === 1 && !empty($grp['extra_date'])) {
                $d = new DateTime($grp['extra_date']);
                if ($d <= $today) {
                    $scheduledMap[$gid . '_' . $d->format('Y-m-d')] = [
                        'date' => $d->format('Y-m-d'), 'group_id' => $gid,
                        'start_period' => (int)$grp['start_period'],
                    ];
                }
            } elseif (!empty($grp['day_of_week']) && $subStart) {
                $vnDay   = (int)$grp['day_of_week'];
                $phpN    = ($vnDay === 8) ? 7 : ($vnDay - 1);
                $limit   = $subEnd ? min($subEnd, $today) : $today;
                $cur     = clone $subStart;
                while ($cur <= $limit) {
                    if ((int)$cur->format('N') === $phpN) {
                        $scheduledMap[$gid . '_' . $cur->format('Y-m-d')] = [
                            'date' => $cur->format('Y-m-d'), 'group_id' => $gid,
                            'start_period' => (int)$grp['start_period'],
                        ];
                    }
                    $cur->modify('+1 day');
                }
            }
        }

        // Sort oldest first (Jan → Dec)
        uasort($scheduledMap, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Existing attendance sessions for this subject
        $existingSessions = db_fetch_all(
            "SELECT a_s.id, a_s.class_subject_group_id, a_s.attendance_date, a_s.study_session
             FROM attendance_sessions a_s
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ?",
            [$selectedSubjectId]
        );
        $sessionMap = [];
        foreach ($existingSessions as $sess) {
            $sessionMap[$sess['class_subject_group_id'] . '_' . $sess['attendance_date']] = $sess;
        }

        // Existing records for this student
        $existingRecords = db_fetch_all(
            "SELECT ar.id, ar.session_id, ar.status,
                    ar.evidence_status, ar.evidence_link as drive_link, ar.evidence_uploaded_at
             FROM attendance_records ar
             JOIN attendance_sessions a_s ON ar.session_id = a_s.id
             JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
             WHERE csg.class_subject_id = ? AND ar.student_id = ?",
            [$selectedSubjectId, $userId]
        );
        $recordMap = [];
        foreach ($existingRecords as $rec) {
            $recordMap[(int)$rec['session_id']] = $rec;
        }

        // Merge into final list
        $attendanceRecords = [];
        foreach ($scheduledMap as $sched) {
            $session = $sessionMap[$sched['group_id'] . '_' . $sched['date']] ?? null;
            $record  = $session ? ($recordMap[(int)$session['id']] ?? null) : null;
            $attendanceRecords[] = [
                'attendance_date'      => $sched['date'],
                'start_period'         => $sched['start_period'],
                'study_session'        => $session['study_session'] ?? null,
                'status'               => $record ? (int)$record['status'] : null,
                'id'                   => $record ? (int)$record['id'] : null,
                'evidence_status'      => $record['evidence_status'] ?? null,
                'drive_link'           => $record['drive_link'] ?? null,
                'evidence_uploaded_at' => $record['evidence_uploaded_at'] ?? null,
            ];
        }

        // Summary counts
        $attendanceSummary['total']   = count($scheduledMap);
        $attendanceSummary['present'] = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 1));
        $attendanceSummary['excused'] = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 2));
        $attendanceSummary['absent']  = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 3));
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
                    <div class="col-md-3 col-lg-2">
                        <label class="attendance-filter-label mb-1">HỌC KỲ</label>
                        <select class="form-select form-select-sm attendance-select bg-light" id="studentSemesterSelect" disabled>
                            <option selected><?= e($semesterLabel !== '' ? $semesterLabel : 'Học kỳ - Chưa xác định') ?></option>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-4">
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
                        <colgroup>
                            <col class="col-att-stt">
                            <col class="col-att-date">
                            <col class="col-att-session">
                            <col class="col-att-status">
                            <col class="col-att-evidence">
                            <col class="col-att-review">
                        </colgroup>
                        <thead class="text-muted small fw-bold" style="background-color: #f8f9fa;">
                            <tr>
                                <th>STT</th>
                                <th>NGÀY HỌC</th>
                                <th>BUỔI</th>
                                <th>TRẠNG THÁI</th>
                                <th>FILE MINH CHỨNG</th>
                                <th>XÉT DUYỆT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendanceRecords)): ?>
                                <?php foreach ($attendanceRecords as $index => $record): ?>
                                    <?php
                                        $st = $record['status']; // null | 1 | 2 | 3
                                        $statusInfo   = getAttendanceStatusLabel($st);
                                        $sessionLabel = studentAttendanceSessionLabel($record['start_period'] ?? 1);
                                        $rowClass = '';
                                        if ($st === 2) $rowClass = 'bg-warning bg-opacity-10';
                                        if ($st === 3) $rowClass = 'bg-danger bg-opacity-10';
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="attendance-col-stt <?= $st === 3 ? 'text-danger' : '' ?>"><?= $index + 1 ?></td>
                                        <td class="attendance-col-date <?= $st === 3 ? 'text-danger fw-bold' : 'text-dark' ?>"><?= formatDate($record['attendance_date'], 'd/m/Y') ?></td>
                                        <td class="text-dark"><?= e($sessionLabel) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusInfo['class'] ?> px-3 py-1 rounded-pill"><?= e($statusInfo['text']) ?></span>
                                        </td>
                                        <?php
                                            $evStatus = $record['evidence_status'] ?? '';
                                            $isAbsent = in_array($st, [2, 3]);
                                            $canUpload = $isAbsent && $evStatus !== 'Approved' && $evStatus !== 'Pending';
                                        ?>
                                        <td>
                                            <?php if (!$isAbsent): ?>
                                                <span class="text-muted">--</span>
                                            <?php elseif ($evStatus === 'Approved' && !empty($record['drive_link'])): ?>
                                                <a href="<?= e($record['drive_link']) ?>" target="_blank" class="btn btn-sm btn-success text-white">
                                                    <i class="bi bi-check-circle me-1"></i>Xem file
                                                </a>
                                            <?php elseif ($evStatus === 'Pending'): ?>
                                                <div class="evidence-upload-wrap" data-record-id="<?= (int)$record['id'] ?>">
                                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                                        <?php if (!empty($record['drive_link'])): ?>
                                                        <a href="<?= e($record['drive_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary ev-btn">
                                                            <i class="bi bi-image me-1"></i>Xem
                                                        </a>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-secondary ev-btn evidence-change-btn" type="button" title="Thay đổi file">
                                                            <i class="bi bi-arrow-repeat me-1"></i>Thay
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger ev-btn evidence-delete-btn" type="button" title="Xóa minh chứng">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                    <div class="evidence-change-form mt-2 d-none">
                                                        <div class="input-group input-group-sm">
                                                            <input type="file" class="form-control form-control-sm evidence-file-input" accept="image/*,.pdf">
                                                            <button class="btn btn-primary btn-sm evidence-upload-btn" type="button"><i class="bi bi-upload"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="evidence-upload-msg mt-1 small"></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="evidence-upload-wrap" data-record-id="<?= (int)$record['id'] ?>">
                                                    <?php if (!empty($record['drive_link'])): ?>
                                                    <a href="<?= e($record['drive_link']) ?>" target="_blank" class="btn btn-sm btn-outline-danger ev-btn mb-1">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Xem file cũ
                                                    </a>
                                                    <?php endif; ?>
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control form-control-sm evidence-file-input" accept="image/*,.pdf">
                                                        <button class="btn btn-primary btn-sm evidence-upload-btn" type="button"><i class="bi bi-upload me-1"></i>Nộp</button>
                                                    </div>
                                                    <div class="evidence-upload-msg mt-1 small"></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-center">
                                            <?php if (!$isAbsent): ?>
                                                <span class="text-muted">--</span>
                                            <?php elseif ($evStatus === 'Pending'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-2 py-1">Đang chờ duyệt</span>
                                            <?php elseif ($evStatus === 'Approved'): ?>
                                                <span class="badge attendance-approved-pill border px-2 py-1">Đã duyệt hợp lệ</span>
                                            <?php elseif ($evStatus === 'Rejected'): ?>
                                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Bị từ chối</span>
                                            <?php else: ?>
                                                <span class="text-muted small">Chưa nộp</span>
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
<script>
function evMsg(wrap, html) {
    wrap.querySelector('.evidence-upload-msg').innerHTML = html;
}

// Upload (nộp / thay đổi)
document.querySelectorAll('.evidence-upload-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const wrap = btn.closest('.evidence-upload-wrap');
        const recordId = wrap.dataset.recordId;
        const fileInput = wrap.querySelector('.evidence-file-input');

        if (!fileInput.files || !fileInput.files[0]) {
            evMsg(wrap, '<span class="text-danger">Vui lòng chọn file.</span>');
            return;
        }

        const formData = new FormData();
        formData.append('record_id', recordId);
        formData.append('file', fileInput.files[0]);

        btn.disabled = true;
        evMsg(wrap, '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Đang tải...</span>');

        fetch('/cms/api/student/evidence-upload.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(function (data) {
                if (data.success) {
                    evMsg(wrap, '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Nộp thành công!</span>');
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    evMsg(wrap, '<span class="text-danger">' + (data.error || 'Lỗi tải lên.') + '</span>');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                evMsg(wrap, '<span class="text-danger">Lỗi kết nối. Thử lại.</span>');
                btn.disabled = false;
            });
    });
});

// Hiện form thay đổi file (khi đang Pending)
document.querySelectorAll('.evidence-change-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const wrap = btn.closest('.evidence-upload-wrap');
        const form = wrap.querySelector('.evidence-change-form');
        form.classList.toggle('d-none');
    });
});

// Xóa minh chứng
document.querySelectorAll('.evidence-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (!confirm('Bạn có chắc muốn xóa minh chứng đã nộp?')) return;
        const wrap = btn.closest('.evidence-upload-wrap');
        const recordId = wrap.dataset.recordId;

        btn.disabled = true;
        evMsg(wrap, '<span class="text-muted">Đang xóa...</span>');

        const formData = new FormData();
        formData.append('record_id', recordId);

        fetch('/cms/api/student/evidence-delete.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(function (data) {
                if (data.success) {
                    evMsg(wrap, '<span class="text-success">Đã xóa!</span>');
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    evMsg(wrap, '<span class="text-danger">' + (data.error || 'Xóa thất bại.') + '</span>');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                evMsg(wrap, '<span class="text-danger">Lỗi kết nối. Thử lại.</span>');
                btn.disabled = false;
            });
    });
});
</script>
</body>
</html>
