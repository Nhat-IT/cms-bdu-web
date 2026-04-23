<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';
requireRole('admin');
$currentUser = getCurrentUser();
$teachers = db_fetch_all("SELECT id, full_name as name, academic_title FROM users WHERE role = 'teacher' ORDER BY full_name");
$dbYears   = db_fetch_all("SELECT DISTINCT academic_year FROM semesters ORDER BY academic_year DESC");
$dbSemesters = ['HK1' => 'Học kỳ 1', 'HK2' => 'Học kỳ 2', 'HK3' => 'Học kỳ 3 (Hè)'];
$dbSemestersWithYear = db_fetch_all(
    "SELECT semester_name, academic_year FROM semesters ORDER BY academic_year DESC, semester_name ASC"
);

// Xác định học kỳ đang diễn ra theo ngày hiện tại
$currentSemester = null;
$now = new DateTime();
$nowMonth = (int)$now->format('n');
$nowYear  = (int)$now->format('Y');

if ($nowMonth >= 9 || $nowMonth <= 1) {
    $curYear = $nowMonth >= 9 ? "{$nowYear}-" . ($nowYear + 1) : ($nowYear - 1) . '-' . $nowYear;
    $curSem  = 'HK1';
} elseif ($nowMonth >= 2 && $nowMonth <= 6) {
    $curYear = ($nowYear - 1) . '-' . $nowYear;
    $curSem  = 'HK2';
} else {
    $curYear = ($nowYear - 1) . '-' . $nowYear;
    $curSem  = 'HK3';
}
$currentSemesterValue = $curSem . '|||' . $curYear;
$startTimes = [1=>'07:00',2=>'07:45',3=>'08:30',4=>'09:15',5=>'10:00',6=>'13:00',7=>'13:45',8=>'14:30',9=>'15:15',10=>'16:00',11=>'17:45',12=>'18:30',13=>'19:15',14=>'20:00'];
$classes = db_fetch_all("SELECT id, class_name FROM classes ORDER BY class_name");
$rooms   = db_fetch_all("SELECT room_code, room_name, room_type FROM rooms WHERE is_active = 1 ORDER BY room_code");

// Query tất cả môn học từ danh mục
$rawSubjects = db_fetch_all("
    SELECT id AS subject_id, subject_code, subject_name, credits, is_active AS subject_open
    FROM subjects
    ORDER BY subject_code ASC
");

// Query tất cả phân công (class_subjects)
$rawAssignments = db_fetch_all("
    SELECT
        cs.id                  AS cs_id,
        cs.subject_id,
        cs.start_date,
        cs.end_date,
        c.class_name,
        sm.semester_name       AS hk,
        sm.academic_year,
        cs.teacher_id          AS main_teacher_id
    FROM class_subjects cs
    JOIN classes  c  ON cs.class_id    = c.id
    LEFT JOIN semesters sm ON cs.semester_id = sm.id
    ORDER BY cs.subject_id, sm.academic_year DESC, sm.semester_name ASC
");

// Group phân công theo subject_id
$asgBySubj = [];
foreach ($rawAssignments as $a) {
    $asgBySubj[$a['subject_id']][] = $a;
}

$rawGroups = db_fetch_all("
    SELECT
        csg.id,
        csg.class_subject_id,
        csg.group_code,
        csg.room,
        csg.day_of_week,
        csg.start_period,
        csg.end_period,
        csg.sub_teacher_id,
        u.full_name  AS sub_name,
        u.academic_title AS sub_title
    FROM class_subject_groups csg
    LEFT JOIN users u ON csg.sub_teacher_id = u.id
    ORDER BY csg.class_subject_id, csg.group_code ASC
");

// Group theo class_subject_id
$groupsByCsId = [];
foreach ($rawGroups as $g) {
    $groupsByCsId[$g['class_subject_id']][] = $g;
}

// Build courses array
$dbCourses = [];
// Map teacher_id -> name for quick lookup
$teacherMap = [];
foreach ($teachers as $t) {
    $teacherMap[(string)$t['id']] = ($t['academic_title'] ? $t['academic_title'] . '. ' : '') . $t['name'];
}

foreach ($rawSubjects as $s) {
    $subjId   = $s['subject_id'];
    $assignments = [];
    foreach ($asgBySubj[$subjId] ?? [] as $a) {
        $csId   = $a['cs_id'];
        $groups = $groupsByCsId[$csId] ?? [['group_code'=>'N1','room'=>null,'day_of_week'=>null,'start_period'=>null,'end_period'=>null,'sub_teacher_id'=>null]];
        $openFmt = (!empty($a['start_date']) || !empty($a['end_date']))
            ? date('d/m/Y', strtotime($a['start_date']??'now')) . ' - ' . date('d/m/Y', strtotime($a['end_date']??'now'))
            : 'Chưa xác định';
        $mainTeacherName = $a['main_teacher_id'] ? ($teacherMap[(string)$a['main_teacher_id']] ?? null) : null;
        $csStart = !empty($a['start_date']) ? strtotime($a['start_date']) : null;
        $csEnd   = !empty($a['end_date'])   ? strtotime($a['end_date'])   : null;
        $nowTs   = time();
        if ($csEnd && $nowTs > $csEnd) {
            $computedStatus = '0'; // đã hết hạn → đóng
        } else {
            $computedStatus = '1'; // trong thời gian hoặc chưa xác định → mở
        }
        $assignments[] = [
            'id'         => $s['subject_code'] . '-' . $csId,
            'csId'       => $csId,
            'classCode'  => $a['class_name'],
            'year'       => $a['academic_year'] ?? '',
            'semester'   => $a['hk'] ?? '',
            'openWindow' => $openFmt,
            'computedStatus' => $computedStatus,
            'teacherMain'=> $a['main_teacher_id'] ? (string)$a['main_teacher_id'] : null,
            'teacherMainName' => $mainTeacherName,
            'groups'     => array_map(fn($g) => [
                'code'        => $g['group_code'],
                'teacherMain' => $a['main_teacher_id'] ? (string)$a['main_teacher_id'] : null,
                'teacherMainName' => $mainTeacherName,
                'teacherSub'  => $g['sub_teacher_id']  ? (string)$g['sub_teacher_id']  : null,
                'teacherSubName' => ($g['sub_teacher_id'] && isset($teacherMap[(string)$g['sub_teacher_id']])) ? $teacherMap[(string)$g['sub_teacher_id']] : null,
                'day'         => $g['day_of_week']  ? (string)$g['day_of_week']  : null,
                'start'       => $g['start_period'] ? (string)$g['start_period'] : null,
                'end'         => $g['end_period']   ? (string)$g['end_period']   : null,
                'room'        => $g['room'] ?? null,
            ], $groups),
        ];
    }
    $dbCourses[] = [
        'id'          => 'subj-' . $subjId,
        'subjectId'   => $subjId,
        'subjectCode' => $s['subject_code'],
        'name'        => $s['subject_name'],
        'credits'     => (int)$s['credits'],
        'isOpen'      => (bool)$s['subject_open'],
        'assignments' => $assignments,
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Điều Phối Lịch Dạy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/assignments.css?v=<?= filemtime(__DIR__ . '/../../public/css/admin/assignments.css') ?>">
</head>
<body class="dashboard-body">

<?php $activePage = 'assignments'; require_once __DIR__ . '/../../layouts/admin-sidebar.php'; ?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle = 'HỆ THỐNG ĐIỀU PHỐI LỊCH DẠY';
$pageIcon  = 'bi-calendar-range';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        <ul class="nav nav-tabs mb-4 border-bottom" id="scheduleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#listView" type="button" role="tab">
                    <i class="bi bi-list-ul me-2"></i>Xem Theo Lớp Học Phần
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grid-tab" data-bs-toggle="tab" data-bs-target="#gridView" type="button" role="tab">
                    <i class="bi bi-grid-3x3-gap-fill me-2"></i>Thời Khóa Biểu Tổng (Master)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="scheduleTabsContent">

            <!-- Tab 1: Danh sách lớp học phần -->
            <div class="tab-pane fade show active" id="listView" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-0">
                    <!-- Header -->
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                        <h5 class="m-0 fw-bold text-dark"><i class="bi bi-calendar-week me-2 text-primary"></i>DANH SÁCH CÁC NHÓM ĐỂ XẾP LỊCH</h5>
                        <div class="input-group admin-assignments-search" style="max-width: 320px;">
                            <input type="text" class="form-control" placeholder="Tìm kiếm..." id="searchAssignment">
                            <button class="btn btn-primary" type="button"><i class="bi bi-search"></i></button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card-body bg-light py-3 px-4 border-bottom">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">NĂM HỌC</label>
                                <select class="form-select form-select-sm" id="assignFilterYear">
                                    <option value="all">Chọn tất cả</option>
                                    <?php foreach ($dbYears as $y): ?>
                                        <option value="<?= e($y['academic_year']) ?>"><?= e($y['academic_year']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">HỌC KỲ</label>
                                <select class="form-select form-select-sm" id="assignFilterSemester">
                                    <option value="all">Chọn tất cả</option>
                                    <?php foreach ($dbSemesters as $v => $l): ?>
                                        <option value="<?= $v ?>"><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">TRẠNG THÁI MÔN</label>
                                <select class="form-select form-select-sm" id="assignFilterOpenStatus">
                                    <option value="all">Tất cả</option>
                                    <option value="open">Đang mở</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3 d-flex justify-content-end">
                                <div class="small text-muted mt-2">
                                    <i class="bi bi-info-circle me-1"></i>Thời gian mở môn được lấy từ cấu hình tại trang Quản lý Lớp &amp; Môn
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="card-body p-0">
                        <div id="assignmentOfferingContainer"></div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: TKB Tổng (Master) -->
            <div class="tab-pane fade" id="gridView" role="tabpanel">
                <div class="row g-2 g-md-3 mb-3 align-items-end">
                    <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                        <label class="form-label small fw-bold text-muted mb-1">HỌC KỲ</label>
                        <select class="form-select border-secondary shadow-sm" id="masterFilterSemester" onchange="renderMasterSchedule()">
                            <?php foreach ($dbSemestersWithYear as $s):
                                $val = e($s['semester_name']) . '|||' . e($s['academic_year']);
                                $label = ($dbSemesters[$s['semester_name']] ?? $s['semester_name']) . ' - ' . e($s['academic_year']);
                                $selected = ($val === $currentSemesterValue) ? 'selected' : '';
                            ?>
                                <option value="<?= $val ?>" <?= $selected ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small fw-bold text-muted mb-1">TUẦN</label>
                        <select class="form-select border-primary fw-bold text-primary shadow-sm" id="masterWeekSelect" style="width: auto; min-width: 160px;"></select>
                    </div>
                    <div class="col-6 col-sm-4 col-md-5 col-lg-3">
                        <label class="form-label small fw-bold text-muted mb-1">GIẢNG VIÊN</label>
                        <select class="form-select border-secondary shadow-sm" id="masterFilterTeacher" onchange="renderMasterSchedule()">
                            <option value="all">-- Tất cả Giảng viên --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= e($t['id']) ?>"><?= e(($t['academic_title'] ? $t['academic_title'] . '. ' : '') . $t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-4 col-md-4 col-lg-2">
                        <label class="form-label small fw-bold text-muted mb-1">PHÒNG HỌC</label>
                        <select class="form-select border-secondary shadow-sm" id="masterFilterRoom" onchange="renderMasterSchedule()">
                            <option value="all">-- Tất cả Phòng --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= e($r['room_code']) ?>"><?= e($r['room_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto ms-md-auto">
                        <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>In Lịch</button>
                    </div>
                </div>

                <div class="schedule-wrapper shadow-sm">
                    <table class="table master-schedule-table mb-0" id="masterScheduleTable">
                        <thead>
                            <tr>
                                <th style="width:80px;cursor:pointer" id="btnPrevWeek"><i class="bi bi-arrow-left fs-4 week-nav-btn"></i></th>
                                <th id="headerT2">Thứ 2</th>
                                <th id="headerT3">Thứ 3</th>
                                <th id="headerT4">Thứ 4</th>
                                <th id="headerT5">Thứ 5</th>
                                <th id="headerT6">Thứ 6</th>
                                <th id="headerT7">Thứ 7</th>
                                <th id="headerCN">Chủ Nhật</th>
                                <th style="width:80px;cursor:pointer" id="btnNextWeek"><i class="bi bi-arrow-right fs-4 week-nav-btn"></i></th>
                            </tr>
                        </thead>
                        <tbody id="masterScheduleBody">
                            <?php for ($p = 1; $p <= 14; $p++): ?>
                             <tr>
                                <td class="edge-col">Tiết <?= $p ?></td>
                                <?php foreach ([2,3,4,5,6,7,8] as $d): ?>
                                <td class="master-cell align-middle p-0" data-day="<?= $d ?>" data-period="<?= $p ?>" style="height:35px;"></td>
                                <?php endforeach; ?>
                                <td class="edge-col"><?= $startTimes[$p] ?></td>
                             </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Phân công / Xếp lịch ban đầu -->
<div class="modal fade" id="initialScheduleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold" id="initModalTitle"><i class="bi bi-calendar-plus me-2"></i>Thiết Lập Lịch Giảng Dạy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="return handleInitialScheduleSubmit(event)">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-warning fw-bold small d-none" id="editBulkWarning">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Cảnh báo: Thao tác này áp dụng cho TẤT CẢ các buổi học trong khoảng thời gian đã chọn.
                    </div>
                    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 mb-4" id="initClassInfoAlert">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="text-muted fw-bold d-block mb-1" style="font-size:0.75rem">LỚP <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm fw-bold text-primary border-primary" id="initClassCode" required>
                                    <option value="">-- Chọn lớp --</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= e($c['class_name']) ?>"><?= e($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="text-muted fw-bold d-block mb-1" style="font-size:0.75rem">TÊN MÔN HỌC</label>
                                <span class="fw-bold text-dark" id="initSubjectName" style="font-size:0.95rem">--</span>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-collection me-1"></i>Nhóm đang chỉnh: <span class="fw-bold text-dark" id="initGroupLabel">Toàn bộ nhóm</span>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Phân công Giảng viên</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Giảng viên chính <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary fw-bold" id="initTeacher" required>
                                <option value="">-- Chọn Giảng viên phụ trách --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= e($t['id']) ?>"><?= e(($t['academic_title'] ? $t['academic_title'].'. ' : '') . $t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="initAssistantTeacherContainer">
                            <label class="form-label fw-bold small text-muted">Trợ giảng</label>
                            <select class="form-select border-secondary" id="initAssistantTeacher">
                                <option value="">-- Không có --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= e($t['id']) ?>"><?= e(($t['academic_title'] ? $t['academic_title'].'. ' : '') . $t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="initScheduleSection">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Thời gian &amp; Lịch học cố định</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Thứ (Hàng tuần) <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary" id="initDayOfWeek" required>
                                <option value="">-- Chọn --</option>
                                <option value="2">Thứ 2</option>
                                <option value="3">Thứ 3</option>
                                <option value="4">Thứ 4</option>
                                <option value="5">Thứ 5</option>
                                <option value="6">Thứ 6</option>
                                <option value="7">Thứ 7</option>
                                <option value="8">Chủ Nhật</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Từ Tiết <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary" id="initStartPeriod" required>
                                <option value="">-- Bắt đầu --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Đến Tiết <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary" id="initEndPeriod" required>
                                <option value="">-- Kết thúc --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Phòng học <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary" id="initRoom" required>
                                <option value="">-- Chọn phòng trống --</option>
                                <?php foreach ($rooms as $r):
                                    $typeLabel = match($r['room_type']) {
                                        'lab'      => 'Lab',
                                        'computer' => 'Phòng máy',
                                        default    => 'Thường'
                                    };
                                ?>
                                    <option value="<?= e($r['room_code']) ?>"><?= e($r['room_name']) ?> (<?= $typeLabel ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="initSubmitBtn"><i class="bi bi-shield-check me-2"></i>Lưu Lịch &amp; Phát Sinh</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Quản lý lịch chi tiết -->
<div class="modal fade" id="sessionManagerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3">
                <div>
                    <h5 class="modal-title fw-bold mb-1"><i class="bi bi-calendar3 me-2"></i>Lịch Trình Chi Tiết</h5>
                    <div class="small text-white-50" id="lblClassInfo">Mã Lớp | Tên Môn | Giảng viên</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-0">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between">
                    <button class="btn btn-warning fw-bold shadow-sm" id="btnEditWholeSchedule">
                        <i class="bi bi-pencil-square me-1"></i>Sửa toàn bộ lịch
                    </button>
                    <button class="btn btn-outline-primary fw-bold shadow-sm" id="btnAddSingleSession">
                        <i class="bi bi-plus-circle me-1"></i>Thêm buổi học bù
                    </button>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-bordered table-hover table-sessions bg-white shadow-sm mb-0">
                        <thead>
                            <tr class="text-center">
                                <th>#</th><th>Nhóm</th><th>Ngày học</th><th>Thứ</th><th>Tiết</th><th>Phòng</th><th>Trạng thái</th><th>Sửa</th>
                            </tr>
                        </thead>
                        <tbody class="text-center" id="sessionManagerTbody">
                            <tr><td colspan="8" class="text-muted py-4">Chọn lớp để xem và sửa từng ngày.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sửa buổi đơn -->
<div class="modal fade" id="singleSessionModal" tabindex="-1" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold" id="singleSessionTitle"><i class="bi bi-pencil-square me-2"></i>Chỉnh sửa buổi học</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-primary bg-opacity-10 p-3 border-bottom">
                    <h6 class="fw-bold text-primary mb-1" id="qsSubjectInfo">Tên môn học</h6>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-dark fw-bold small" id="qsClassCode"><i class="bi bi-tags-fill me-1 text-muted"></i>Mã lớp</span>
                        <span class="badge bg-secondary bg-opacity-25 text-dark border border-secondary" id="qsGroup">Nhóm: --</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="fw-bold small text-muted"><i class="bi bi-person-badge-fill me-1"></i>Giảng viên phụ trách <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold text-primary border-secondary shadow-sm" id="singleTeacher">
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= e($t['id']) ?>"><?= e(($t['academic_title'] ? $t['academic_title'].'. ' : '') . $t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="fw-bold small text-muted">Ngày học <span class="text-danger">*</span></label>
                            <input type="date" class="form-control fw-bold text-dark border-secondary" id="singleDate" required>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold small text-muted">Từ tiết</label>
                            <select class="form-select border-secondary" id="singleStart">
                                <option value="">-- Chọn --</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold small text-muted">Đến tiết</label>
                            <select class="form-select border-secondary" id="singleEnd">
                                <option value="">-- Chọn --</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="fw-bold small text-muted">Phòng học</label>
                            <select class="form-select border-secondary" id="singleRoom">
                                <option value="">-- Chọn phòng --</option>
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?= e($r['room_code']) ?>"><?= e($r['room_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="fw-bold small text-muted border-bottom pb-1 mb-2 d-block">Trạng thái buổi học</label>
                            <select class="form-select fw-bold border-secondary shadow-sm" id="singleStatus">
                                <option value="normal" class="text-success">Diễn ra Bình thường</option>
                                <option value="makeup" class="text-warning">Học Bù / Dạy thay</option>
                                <option value="canceled" class="text-danger">Báo Hủy / Cho nghỉ</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="cancelReasonDiv">
                            <label class="fw-bold small text-danger">Lý do báo hủy</label>
                            <textarea class="form-control border-danger" id="cancelReason" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-between">
                <button class="btn btn-outline-danger fw-bold btn-sm" id="btnDeleteSingle" onclick="deleteSingleSession()"><i class="bi bi-trash me-1"></i>Xóa khỏi hệ thống</button>
                <div>
                    <button class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button class="btn btn-primary fw-bold shadow-sm" onclick="saveSingleSession()"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="file" id="assignmentStudentUploadInput" class="d-none" accept=".csv,.xlsx,.xls">

<!-- Modal hướng dẫn tải lên danh sách sinh viên -->
<div class="modal fade" id="studentUploadGuideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Hướng dẫn tải lên danh sách sinh viên</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">File hỗ trợ: <strong>CSV</strong>, <strong>XLSX</strong>, <strong>XLS</strong></p>
                <p class="fw-bold mb-2">Cột bắt buộc (theo thứ tự):</p>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item px-0 py-1"><strong>Mã số sinh viên (MSSV)</strong></li>
                    <li class="list-group-item px-0 py-1"><strong>Họ và tên</strong></li>
                    <li class="list-group-item px-0 py-1"><strong>Ngày sinh</strong> <span class="text-muted">— dd/mm/yyyy</span></li>
                    <li class="list-group-item px-0 py-1"><strong>Lớp</strong></li>
                </ul>
                <div class="alert alert-info py-2 mb-0 small">
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Quy trình:</strong> Sinh viên chưa có tài khoản vẫn được ghi nhận vào nhóm. Khi tài khoản SV (theo MSSV) được tạo sau đó, sinh viên sẽ tự động xem được thời khóa biểu của nhóm.
                </div>
            </div>
            <div class="modal-footer bg-light border-0 pt-0">
                <button class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Đóng</button>
                <button class="btn btn-primary fw-bold shadow-sm" id="confirmUploadBtn"><i class="bi bi-upload me-1"></i> Chọn file</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>
<script>
// Inject DB data vào JS (dùng window.* để assignments.js dùng chung)
window.teachers = <?= json_encode(array_map(fn($t) => [
    'id'    => (string)$t['id'],
    'name'  => $t['name'],
    'title' => $t['academic_title'] ?? ''
], $teachers)) ?>;
window.rooms = <?= json_encode(array_map(fn($r) => [
    'code' => $r['room_code'],
    'name' => $r['room_name'],
    'type' => $r['room_type']
], $rooms)) ?>;
window.days = [
    {value:'2',label:'Thứ 2'},{value:'3',label:'Thứ 3'},{value:'4',label:'Thứ 4'},
    {value:'5',label:'Thứ 5'},{value:'6',label:'Thứ 6'},{value:'7',label:'Thứ 7'},{value:'8',label:'Chủ Nhật'}
];

// Dữ liệu từ DB — subjects catalogue với assignments lồng trong
window.allSubjects = <?= json_encode($dbCourses, JSON_UNESCAPED_UNICODE) ?>;
// Flat version cho backward compat (openSessionManager, addGroupToClass, v.v.)
window.allAssignmentCourses = [];
window.allSubjects.forEach(function(subj) {
    (subj.assignments || []).forEach(function(asg) {
        window.allAssignmentCourses.push({
            id:          asg.id,
            csId:        asg.csId,
            name:        subj.name,
            credits:     subj.credits,
            classCode:   asg.classCode,
            year:        asg.year,
            semester:    asg.semester,
            isOpen:      subj.isOpen,
            openWindow:  asg.openWindow,
            groups:      asg.groups,
            subjectCode: subj.subjectCode,
            subjectId:   subj.subjectId
        });
    });
});

// Map tiết → giờ (phải khai báo trước khi renderMasterSchedule được gọi)
window.startTimeLabels = {1:'07:00',2:'07:45',3:'08:30',4:'09:15',5:'10:00',6:'13:00',7:'13:45',8:'14:30',9:'15:15',10:'16:00',11:'17:45',12:'18:30',13:'19:15',14:'20:00'};

// Helper: lấy room_name từ room_code
function getRoomName(roomCode) {
    if (!roomCode) return '—';
    var r = (window.rooms || []).find(function(r) { return r.code === roomCode; });
    return r ? r.name : roomCode;
}

// ─── MASTER SCHEDULE ────────────────────────────────────────────────────────
(function initMasterSchedule() {
    const weekSelect  = document.getElementById('masterWeekSelect');
    const prevBtn     = document.getElementById('btnPrevWeek');
    const nextBtn     = document.getElementById('btnNextWeek');

    function getMondays(courses) {
        const mondays = new Set();
        courses.forEach(function(c) {
            c.groups.forEach(function(g) {
                if (!g.day || !g.start || !g.end) return;
            });
        });
        const today = new Date();
        const dayOfWeek = today.getDay();
        const monday = new Date(today);
        monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
        for (let w = -2; w <= 10; w++) {
            const d = new Date(monday);
            d.setDate(monday.getDate() + w * 7);
            mondays.add(d.toISOString().slice(0, 10));
        }
        return Array.from(mondays).sort();
    }

    function fmt(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return (d.getDate()).toString().padStart(2,'0') + '/' + (d.getMonth()+1).toString().padStart(2,'0');
    }

    const mondays = getMondays(window.allAssignmentCourses || []);

    mondays.forEach(function(monday) {
        const sun = new Date(monday + 'T00:00:00');
        sun.setDate(sun.getDate() + 6);
        const opt = document.createElement('option');
        opt.value = monday;
        opt.textContent = 'Tuần ' + fmt(monday) + ' - ' + fmt(sun.toISOString().slice(0,10)) + '/' + (sun.getFullYear());
        weekSelect.appendChild(opt);
    });

    const today = new Date();
    const dow = today.getDay();
    const thisMonday = new Date(today);
    thisMonday.setDate(today.getDate() - (dow === 0 ? 6 : dow - 1));
    const thisMondayStr = thisMonday.toISOString().slice(0, 10);
    const closest = mondays.reduce(function(a, b) {
        return Math.abs(new Date(b) - new Date(thisMondayStr)) < Math.abs(new Date(a) - new Date(thisMondayStr)) ? b : a;
    }, mondays[0]);
    if (closest) weekSelect.value = closest;

    weekSelect.addEventListener('change', renderMasterSchedule);
    if (prevBtn) prevBtn.addEventListener('click', function() {
        const idx = mondays.indexOf(weekSelect.value);
        if (idx > 0) { weekSelect.value = mondays[idx - 1]; renderMasterSchedule(); }
    });
    if (nextBtn) nextBtn.addEventListener('click', function() {
        const idx = mondays.indexOf(weekSelect.value);
        if (idx < mondays.length - 1) { weekSelect.value = mondays[idx + 1]; renderMasterSchedule(); }
    });

    renderMasterSchedule();
})();

function renderMasterSchedule() {
    const weekSelect    = document.getElementById('masterWeekSelect');
    const filterTeacher = document.getElementById('masterFilterTeacher');
    const filterRoom    = document.getElementById('masterFilterRoom');
    const monday        = weekSelect ? weekSelect.value : null;
    if (!monday) return;

    const mondayDate = new Date(monday + 'T00:00:00');

    const dayLabels = ['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ Nhật'];
    ['headerT2','headerT3','headerT4','headerT5','headerT6','headerT7','headerCN']
        .forEach(function(id, i) {
            const d = new Date(mondayDate);
            d.setDate(mondayDate.getDate() + i);
            const el = document.getElementById(id);
            if (el) el.innerHTML = dayLabels[i] +
                '<br><span style="font-size:0.8rem;font-weight:400">' +
                d.getDate().toString().padStart(2,'0') + '/' +
                (d.getMonth()+1).toString().padStart(2,'0') + '</span>';
        });

    const tbody = document.getElementById('masterScheduleBody');
    tbody.innerHTML = '';

    for (let p = 1; p <= 14; p++) {
        const tr = document.createElement('tr');
        const edgeL = document.createElement('td');
        edgeL.className = 'edge-col';
        edgeL.textContent = 'Tiết ' + p;
        tr.appendChild(edgeL);
        [2,3,4,5,6,7,8].forEach(function(d) {
            const td = document.createElement('td');
            td.className = 'master-cell align-middle p-1';
            td.dataset.day    = String(d);
            td.dataset.period = String(p);
            td.style.height   = '35px';
            tr.appendChild(td);
        });
        const edgeR = document.createElement('td');
        edgeR.className = 'edge-col';
        edgeR.textContent = window.startTimeLabels[p] || '';
        tr.appendChild(edgeR);
        tbody.appendChild(tr);
    }

    const teacherFilter = filterTeacher ? filterTeacher.value : 'all';
    const roomFilter    = filterRoom    ? filterRoom.value    : 'all';
    const semVal        = (document.getElementById('masterFilterSemester') || {}).value || 'all';
    const [semFilter, yearFilter] = semVal === 'all' ? ['all', 'all'] : semVal.split('|||');
    const occupied = {};
    [2,3,4,5,6,7,8].forEach(d => occupied[d] = {});
    const dayOffsetMap = {2:0,3:1,4:2,5:3,6:4,7:5,8:6};

    (window.allAssignmentCourses || []).forEach(function(course) {
        if (yearFilter !== 'all' && course.year !== yearFilter) return;
        if (semFilter  !== 'all' && course.semester !== semFilter) return;

        course.groups.forEach(function(g) {
            if (!g.day || !g.start || !g.end) return;
            const day   = parseInt(g.day,   10);
            const start = parseInt(g.start, 10);
            const end   = parseInt(g.end,   10);
            if (teacherFilter !== 'all' && g.teacherMain !== teacherFilter) return;
            if (roomFilter    !== 'all' && g.room        !== roomFilter)    return;
            if (occupied[day] && occupied[day][start]) return;

            const span = end - start + 1;
            const cell = tbody.querySelector(
                '.master-cell[data-day="' + day + '"][data-period="' + start + '"]'
            );
            if (!cell) return;

            cell.rowSpan           = span;
            cell.style.verticalAlign = 'top';
            cell.style.padding       = '2px';
            cell.style.height        = (span * 45) + 'px';
            for (let p = start + 1; p <= end; p++) {
                const covered = tbody.querySelector(
                    '.master-cell[data-day="' + day + '"][data-period="' + p + '"]'
                );
                if (covered) covered.remove();
                if (occupied[day]) occupied[day][p] = true;
            }
            if (occupied[day]) occupied[day][start] = true;

            const tObj    = (window.teachers || []).find(t => t.id === g.teacherMain);
            const tName   = tObj ? ((tObj.title ? tObj.title + '. ' : '') + tObj.name) : '—';
            const tStart  = window.startTimeLabels[start] || ('Tiết ' + start);
            const tEnd    = window.startTimeLabels[end]   || ('' + end);

            const block = document.createElement('div');
            block.className   = 'subject-block';
            block.style.height    = '100%';
            block.style.minHeight = (span * 35 - 6) + 'px';
            block.style.cursor    = 'pointer';
            block.title           = 'Click để chỉnh sửa lịch ngày này';
            block.innerHTML =
                '<div class="subject-title" style="font-size:0.72rem">' + course.name + '</div>' +
                '<div style="font-size:0.65rem">Lớp: <b class="text-primary">' + course.classCode + '</b> (' + (g.code === "N1" ? "Nhóm 1" : g.code) + ')</div>' +
                '<div style="font-size:0.65rem">🏛 <b>' + getRoomName(g.room) + '</b></div>' +
                '<div style="font-size:0.65rem">👤 ' + tName + '</div>' +
                '<div style="font-size:0.62rem;color:#334155;margin-top:2px">⏰ ' + tStart + ' → ' + tEnd +
                    ' <span style="color:#94a3b8">(T.' + start + '–' + end + ')</span></div>';

            (function(cc, cg, offset) {
                block.addEventListener('click', function() {
                    const sd = new Date(mondayDate);
                    sd.setDate(mondayDate.getDate() + offset);
                    const sdStr     = sd.toISOString().slice(0, 10);
                    const sdDisplay = sd.getDate().toString().padStart(2,'0') + '/' +
                                      (sd.getMonth()+1).toString().padStart(2,'0') + '/' +
                                      sd.getFullYear();
                    openEditSingleSession(
                        'edit', sdDisplay, sdStr,
                        String(cg.day), String(cg.start), String(cg.end),
                        cg.room || '', 'normal',
                        cc.classCode, cc.name,
                        cg.teacherMain || '', cg.code
                    );
                });
            })(course, g, dayOffsetMap[day] !== undefined ? dayOffsetMap[day] : 0);

            cell.appendChild(block);
        });
    });
}
</script>
<script src="../../public/js/admin/assignments.js?v=<?= filemtime(__DIR__ . '/../../public/js/admin/assignments.js') ?>"></script>
</body>
</html>
