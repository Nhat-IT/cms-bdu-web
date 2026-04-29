<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';
requireRole('admin');
$currentUser = getCurrentUser();
$teachers = db_fetch_all("
    SELECT id, full_name as name, academic_title
    FROM users
    WHERE LOWER(role) IN ('teacher', 'support_admin', 'staff')
    ORDER BY full_name
");
$dbYears   = db_fetch_all("SELECT DISTINCT academic_year FROM semesters ORDER BY academic_year DESC");
$dbSemesters = ['HK1' => 'Học kỳ 1', 'HK2' => 'Học kỳ 2', 'HK3' => 'Học kỳ 3 (Hè)'];

// Tìm học kỳ hiện tại để đặt làm mặc định
$currentSemester = db_fetch_one("
    SELECT semester_name, academic_year 
    FROM semesters 
    WHERE CURDATE() BETWEEN start_date AND end_date 
    ORDER BY start_date DESC 
    LIMIT 1
");
$defaultYear = $currentSemester['academic_year'] ?? ($dbYears[0]['academic_year'] ?? '');
$defaultSemester = $currentSemester['semester_name'] ?? '';

function formatSemesterNameLabel($semesterName, $dbSemesters) {
    $raw = trim((string)($semesterName ?? ''));
    if ($raw === '') return 'Học kỳ';
    if (isset($dbSemesters[$raw])) return $dbSemesters[$raw];

    if (preg_match('/^HK\s*([123])$/i', $raw, $m)) {
        return $m[1] === '3' ? 'Học kỳ 3 (Hè)' : ('Học kỳ ' . $m[1]);
    }
    if (in_array($raw, ['1', '2', '3'], true)) {
        return $raw === '3' ? 'Học kỳ 3 (Hè)' : ('Học kỳ ' . $raw);
    }

    return $raw;
}

function normalizeSemesterCode($semesterName) {
    $raw = strtoupper(trim((string)($semesterName ?? '')));
    if ($raw === '') return '';
    if (preg_match('/^(?:HK)?\s*([123])$/', $raw, $m)) {
        return 'HK' . $m[1];
    }
    return $raw;
}

$dbSemestersWithYear = db_fetch_all(
    "SELECT semester_name, academic_year, start_date, end_date
     FROM semesters
     ORDER BY academic_year DESC,
              FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
              start_date ASC"
);

// Xác định học kỳ đang diễn ra theo ngày hiện tại
$currentSemesterValue = '';
foreach ($dbSemestersWithYear as $s) {
    if (!empty($s['start_date']) && !empty($s['end_date']) && date('Y-m-d') >= $s['start_date'] && date('Y-m-d') <= $s['end_date']) {
        $currentSemesterValue = ($s['semester_name'] ?? '') . '|||' . ($s['academic_year'] ?? '');
        break;
    }
}
if ($currentSemesterValue === '' && !empty($dbSemestersWithYear)) {
    $currentSemesterValue = ($dbSemestersWithYear[0]['semester_name'] ?? '') . '|||' . ($dbSemestersWithYear[0]['academic_year'] ?? '');
}

$semesterRanges = [];
foreach ($dbSemestersWithYear as $s) {
    $key = ($s['semester_name'] ?? '') . '|||' . ($s['academic_year'] ?? '');
    $semesterRanges[$key] = [
        'start' => $s['start_date'] ?? null,
        'end'   => $s['end_date'] ?? null,
        'label' => formatSemesterNameLabel($s['semester_name'] ?? '', $dbSemesters) . ' - ' . ($s['academic_year'] ?? '')
    ];
}
$startTimes = [1=>'07:00',2=>'07:45',3=>'08:30',4=>'09:15',5=>'10:00',6=>'13:00',7=>'13:45',8=>'14:30',9=>'15:15',10=>'16:00',11=>'17:45',12=>'18:30',13=>'19:15',14=>'20:00'];
$classes = db_fetch_all("SELECT id, class_name FROM classes ORDER BY class_name");
$rooms   = db_fetch_all("SELECT room_code, room_name, room_type FROM rooms WHERE is_active = 1 ORDER BY room_code");

// Query tất cả môn học từ danh mục
$rawSubjects = db_fetch_all("
    SELECT id AS subject_id, subject_code, subject_name, credits, is_active AS subject_open,
           semester, academic_year, open_date, close_date
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
        COALESCE(
            (SELECT csg2.main_teacher_id 
             FROM class_subject_groups csg2 
             WHERE csg2.class_subject_id = cs.id AND csg2.main_teacher_id IS NOT NULL
             LIMIT 1),
            cs.teacher_id
        ) AS main_teacher_id
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
        r.room_name AS room_name,
        csg.day_of_week,
        csg.start_period,
        csg.end_period,
        csg.sub_teacher_id,
        csg.main_teacher_id,
        u.full_name  AS sub_name,
        u.academic_title AS sub_title,
        um.full_name AS main_name,
        um.academic_title AS main_title
    FROM class_subject_groups csg
    LEFT JOIN users u ON csg.sub_teacher_id = u.id
    LEFT JOIN users um ON csg.main_teacher_id = um.id
    LEFT JOIN rooms r ON (r.room_code = csg.room OR r.room_name = csg.room)
    ORDER BY csg.class_subject_id, csg.group_code ASC
");

// Số lượng SV theo lớp học phần (class_subject_id)
$studentCountByCsId = [];
$rawStudentCounts = db_fetch_all("
    SELECT cs.id AS class_subject_id, COUNT(cs2.student_id) AS student_count
    FROM class_subjects cs
    LEFT JOIN class_students cs2 ON cs2.class_id = cs.class_id
    GROUP BY cs.id
");
foreach ($rawStudentCounts as $row) {
    $studentCountByCsId[(int)$row['class_subject_id']] = (int)$row['student_count'];
}

// Group theo class_subject_id
$groupsByCsId = [];
foreach ($rawGroups as $g) {
    $groupsByCsId[$g['class_subject_id']][] = $g;
}

// Map môn học
$subjectMap = [];
foreach ($rawSubjects as $s) {
    $subjectMap[$s['subject_id']] = $s;
}

// Group phân công theo class_name
$asgByClass = [];
foreach ($rawAssignments as $a) {
    $asgByClass[$a['class_name']][] = $a;
}

// Build courses array
$dbCourses = [];
// Map teacher_id -> name for quick lookup
$teacherMap = [];
foreach ($teachers as $t) {
    $teacherMap[(string)$t['id']] = ($t['academic_title'] ? $t['academic_title'] . '. ' : '') . $t['name'];
}

foreach ($classes as $c) {
    $className = $c['class_name'];
    $assignments = [];
    $hasOpen = false;
    
    foreach ($asgByClass[$className] ?? [] as $a) {
        $csId   = $a['cs_id'];
        $groups = $groupsByCsId[$csId] ?? [['group_code'=>'N1','room'=>null,'day_of_week'=>null,'start_period'=>null,'end_period'=>null,'sub_teacher_id'=>null,'main_teacher_id'=>null]];
        $mainTeacherName = $a['main_teacher_id'] ? ($teacherMap[(string)$a['main_teacher_id']] ?? null) : null;
        
        $subjInfo = $subjectMap[$a['subject_id']] ?? null;
        if (!$subjInfo) continue;
        if ($subjInfo['subject_open']) $hasOpen = true;
        $openDate = $subjInfo['open_date'] ?? null;
        $closeDate = $subjInfo['close_date'] ?? null;
        $openFmt = (!empty($openDate) || !empty($closeDate))
            ? (!empty($openDate) ? date('d/m/Y', strtotime($openDate)) : '--') . ' - ' . (!empty($closeDate) ? date('d/m/Y', strtotime($closeDate)) : '--')
            : 'Chưa xác định';

        $csStart = !empty($a['start_date']) ? strtotime($a['start_date']) : null;
        $csEnd   = !empty($a['end_date'])   ? strtotime($a['end_date'])   : null;
        $nowTs   = time();
        if ($csEnd && $nowTs > $csEnd) {
            $computedStatus = '0'; // đã hết hạn → đóng
        } else {
            $computedStatus = '1'; // trong thời gian hoặc chưa xác định → mở
        }

        $assignments[] = [
            'id'             => $subjInfo['subject_code'] . '-' . $csId,
            'csId'           => $csId,
            'subjectId'      => $a['subject_id'],
            'subjectCode'    => $subjInfo['subject_code'],
            'subjectName'    => $subjInfo['subject_name'],
            'classCode'      => $className,
            'startDate'      => $a['start_date'] ?? null,
            'endDate'        => $a['end_date'] ?? null,
            'credits'        => (int)$subjInfo['credits'],
            'isOpen'         => (bool)$subjInfo['subject_open'],
            'year'           => $a['academic_year'] ?? '',
            'semester'       => normalizeSemesterCode($a['hk'] ?? ''),
            'openWindow'     => $openFmt,
            'computedStatus' => $computedStatus,
            'hasStudents'    => (($studentCountByCsId[(int)$csId] ?? 0) > 0),
            'studentCount'   => (int)($studentCountByCsId[(int)$csId] ?? 0),
            'teacherMain'    => $a['main_teacher_id'] ? (string)$a['main_teacher_id'] : null,
            'teacherMainName'=> $mainTeacherName,
            'groups'         => array_map(fn($g) => [
                'code'        => $g['group_code'],
                'teacherMain' => $g['main_teacher_id'] ? (string)$g['main_teacher_id'] : ($a['main_teacher_id'] ? (string)$a['main_teacher_id'] : null),
                'teacherMainName' => $g['main_teacher_id']
                    ? (isset($teacherMap[(string)$g['main_teacher_id']]) ? $teacherMap[(string)$g['main_teacher_id']] : (($g['main_title'] ? $g['main_title'] . '. ' : '') . $g['main_name']))
                    : $mainTeacherName,
                'teacherSub'  => $g['sub_teacher_id']  ? (string)$g['sub_teacher_id']  : null,
                'teacherSubName' => ($g['sub_teacher_id'] && isset($teacherMap[(string)$g['sub_teacher_id']])) ? $teacherMap[(string)$g['sub_teacher_id']] : null,
                'day'         => $g['day_of_week']  ? (string)$g['day_of_week']  : null,
                'start'       => $g['start_period'] ? (string)$g['start_period'] : null,
                'end'         => $g['end_period']   ? (string)$g['end_period']   : null,
                'room'        => $g['room'] ?? null,
                'roomName'    => $g['room_name'] ?? null,
            ], $groups)
        ];
    }
    
    if (!empty($assignments)) {
        $dbCourses[] = [
            'id'          => 'class-' . $className,
            'classCode'   => $className,
            'name'        => 'Lớp ' . $className,
            'hasOpen'     => $hasOpen,
            'assignments' => $assignments,
        ];
    }
}

// Bổ sung các môn đã tạo trong danh mục nhưng chưa có lớp học phần nào
$assignedSubjectIds = [];
foreach ($rawAssignments as $a) {
    $assignedSubjectIds[(int)($a['subject_id'] ?? 0)] = true;
}

$unassignedAssignments = [];
foreach ($rawSubjects as $s) {
    $subjectId = (int)($s['subject_id'] ?? 0);
    if ($subjectId <= 0 || isset($assignedSubjectIds[$subjectId])) continue;

    $openDate = $s['open_date'] ?? null;
    $closeDate = $s['close_date'] ?? null;
    $openWindow = (!empty($openDate) || !empty($closeDate))
        ? (!empty($openDate) ? date('d/m/Y', strtotime($openDate)) : '--') . ' - ' . (!empty($closeDate) ? date('d/m/Y', strtotime($closeDate)) : '--')
        : 'Chưa xác định';

    $today = date('Y-m-d');
    if (empty($openDate) || $openDate > $today || (!empty($closeDate) && $closeDate < $today)) {
        $computedStatus = '0';
    } else {
        $computedStatus = '1';
    }

    $unassignedAssignments[] = [
        'id'             => 'subject_pending_' . $subjectId,
        'csId'           => null,
        'subjectId'      => $subjectId,
        'subjectCode'    => $s['subject_code'],
        'subjectName'    => $s['subject_name'],
        'classCode'      => '--',
        'credits'        => (int)($s['credits'] ?? 0),
        'isOpen'         => (bool)($s['subject_open'] ?? 0),
        'year'           => $s['academic_year'] ?? '',
        'semester'       => normalizeSemesterCode($s['semester'] ?? ''),
        'openWindow'     => $openWindow,
        'computedStatus' => $computedStatus,
        'hasStudents'    => false,
        'studentCount'   => 0,
        'teacherMain'    => null,
        'teacherMainName'=> null,
        'groups'         => []
    ];
}

if (!empty($unassignedAssignments)) {
    $dbCourses[] = [
        'id'          => 'class-unassigned',
        'classCode'   => '--',
        'name'        => 'Môn chưa tạo lớp học phần',
        'hasOpen'     => true,
        'assignments' => $unassignedAssignments,
    ];
}

// Dataset riêng cho tab Master Schedule (lấy trực tiếp từ DB)
$rawMasterScheduleRows = db_fetch_all("
    SELECT
        cs.id               AS cs_id,
        c.class_name        AS class_name,
        s.subject_code      AS subject_code,
        s.subject_name      AS subject_name,
        sm.semester_name    AS semester_name,
        sm.academic_year    AS academic_year,
        COALESCE(csg.main_teacher_id, cs.teacher_id) AS teacher_main_id,
        csg.group_code      AS group_code,
        csg.day_of_week     AS day_of_week,
        csg.start_period    AS start_period,
        csg.end_period      AS end_period,
        csg.room            AS room
    FROM class_subject_groups csg
    JOIN class_subjects cs ON cs.id = csg.class_subject_id
    JOIN classes c ON c.id = cs.class_id
    JOIN subjects s ON s.id = cs.subject_id
    LEFT JOIN semesters sm ON sm.id = cs.semester_id
    WHERE csg.day_of_week IS NOT NULL
      AND csg.start_period IS NOT NULL
      AND csg.end_period IS NOT NULL
    ORDER BY sm.academic_year DESC, sm.semester_name ASC, c.class_name ASC, cs.id ASC, csg.group_code ASC
");

$masterCoursesByCsId = [];
foreach ($rawMasterScheduleRows as $row) {
    $csId = (int)($row['cs_id'] ?? 0);
    if ($csId <= 0) continue;

    if (!isset($masterCoursesByCsId[$csId])) {
        $masterCoursesByCsId[$csId] = [
            'id'        => $csId,
            'csId'      => $csId,
            'name'      => !empty($row['subject_code'])
                ? (($row['subject_code'] ?? '') . ' - ' . ($row['subject_name'] ?? ''))
                : ($row['subject_name'] ?? ''),
            'classCode' => $row['class_name'] ?? '',
            'year'      => $row['academic_year'] ?? '',
            'semester'  => $row['semester_name'] ?? '',
            'groups'    => []
        ];
    }

    $masterCoursesByCsId[$csId]['groups'][] = [
        'code'        => $row['group_code'] ?: 'N1',
        'teacherMain' => !empty($row['teacher_main_id']) ? (string)$row['teacher_main_id'] : null,
        'day'         => !empty($row['day_of_week']) ? (string)$row['day_of_week'] : null,
        'start'       => !empty($row['start_period']) ? (string)$row['start_period'] : null,
        'end'         => !empty($row['end_period']) ? (string)$row['end_period'] : null,
        'room'        => $row['room'] ?? null
    ];
}
$dbMasterCourses = array_values($masterCoursesByCsId);

// Lich ghi de theo ngay (chi ap dung cho 1 ngay cu the, khong doi lich ca nhom)
$hasExtraClassesTable = (int) db_count(
    "SELECT COUNT(*) AS total
     FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = 'extra_classes'"
) > 0;
$rawMasterOverrides = $hasExtraClassesTable ? db_fetch_all("
    SELECT
        cs.id            AS cs_id,
        csg.group_code   AS group_code,
        ec.extra_date    AS extra_date,
        ec.day_of_week   AS day_of_week,
        ec.start_period  AS start_period,
        ec.end_period    AS end_period,
        ec.room          AS room,
        ec.is_regular    AS is_regular
    FROM extra_classes ec
    JOIN class_subject_groups csg ON csg.id = ec.class_subject_group_id
    JOIN class_subjects cs ON cs.id = csg.class_subject_id
    ORDER BY ec.extra_date DESC, ec.id DESC
") : [];
$dbMasterOverrides = array_map(function ($row) {
    return [
        'csId'       => (int)($row['cs_id'] ?? 0),
        'groupCode'  => $row['group_code'] ?? 'N1',
        'date'       => $row['extra_date'] ?? '',
        'day'        => !empty($row['day_of_week']) ? (string)$row['day_of_week'] : null,
        'start'      => !empty($row['start_period']) ? (string)$row['start_period'] : null,
        'end'        => !empty($row['end_period']) ? (string)$row['end_period'] : null,
        'room'       => $row['room'] ?? null,
        'isRegular'  => isset($row['is_regular']) ? (int)$row['is_regular'] : 1
    ];
}, $rawMasterOverrides);
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
    <style>
        .filter-container {
            display: flex;
            gap: 12px;
        }
        .filter-container > * {
            flex: 1;
            min-width: 120px;
        }
    </style>
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
                <div class="assignment-page-shell">
                    <div class="assignment-page-header">
                        <h5 class="assignment-page-title mb-0">DANH SÁCH CÁC LỚP ĐỂ XẾP LỊCH</h5>
                        <div class="assignment-search-wrap">
                            <div class="input-group">
                                <input type="text" class="form-control assignment-search-input" placeholder="Tìm tên môn, mã lớp..." id="searchAssignment">
                                <button class="btn btn-primary assignment-search-btn" type="button" aria-label="Tìm kiếm"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="assignment-filter-panel">
                        <div class="row g-3 filter-container">
                            <div class="col-12 col-md-4">
                                <label class="assignment-filter-label">NĂM HỌC</label>
                                <select class="form-select assignment-filter-select" id="assignFilterYear">
                                    <option value="all">Chọn tất cả</option>
                                    <?php foreach ($dbYears as $y): ?>
                                        <option value="<?= e($y['academic_year']) ?>" <?= ($y['academic_year'] === $defaultYear) ? 'selected' : '' ?>><?= e($y['academic_year']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="assignment-filter-label">HỌC KỲ</label>
                                <select class="form-select assignment-filter-select" id="assignFilterSemester">
                                    <option value="all">Chọn tất cả</option>
                                    <?php foreach ($dbSemesters as $v => $l): ?>
                                        <option value="<?= $v ?>" <?= ($v === $defaultSemester) ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="assignment-filter-label">TRẠNG THÁI MÔN</label>
                                <select class="form-select assignment-filter-select" id="assignFilterOpenStatus">
                                    <option value="all">Tất cả</option>
                                    <option value="open">Đang mở</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>
                        </div>
                        <div class="assignment-filter-note">
                            <i class="bi bi-info-circle"></i>
                            <span>Thời gian mở môn được lấy từ cấu hình tại trang Quản lý Lớp &amp; Môn và chỉ hiển thị để tham chiếu.</span>
                        </div>
                    </div>

                    <div id="assignmentOfferingContainer" class="assignment-offering-list"></div>
                    <div id="assignmentTableBody" class="d-none"></div>
                </div>
            </div>

            <!-- Tab 2: TKB Tổng (Master) -->
            <div class="tab-pane fade" id="gridView" role="tabpanel">
                <div class="row g-2 g-md-3 mb-3 align-items-end">
                    <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                        <label class="form-label small fw-bold text-muted mb-1">HỌC KỲ</label>
                        <select class="form-select border-secondary shadow-sm" id="masterFilterSemester" onchange="handleMasterSemesterChange()">
                            <?php foreach ($dbSemestersWithYear as $s):
                                $val = e($s['semester_name']) . '|||' . e($s['academic_year']);
                                $label = formatSemesterNameLabel($s['semester_name'] ?? '', $dbSemesters) . ' - ' . e($s['academic_year']);
                                $selected = ($val === $currentSemesterValue) ? 'selected' : '';
                            ?>
                                <option value="<?= $val ?>" <?= $selected ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto align-self-start master-week-select-col">
                        <label class="form-label small fw-bold text-muted mb-1">TUẦN</label>
                        <div class="master-week-select-wrap">
                            <select class="form-select border-primary fw-bold text-primary shadow-sm" id="masterWeekSelect"></select>
                        </div>
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
            <div class="modal-header bg-success text-white border-bottom-0 pb-3" id="initModalHeader">
                <h5 class="modal-title fw-bold" id="initModalTitle"><i class="bi bi-calendar-plus me-2"></i>Thiết Lập Lịch Giảng Dạy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="return handleInitialScheduleSubmit(event)">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-primary bg-primary bg-opacity-10 border-0 mb-4" id="initClassInfoAlert">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="text-muted fw-bold d-block mb-1" style="font-size:0.75rem">MÃ LỚP <span class="text-danger">*</span></label>
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
                            <i class="bi bi-collection me-1"></i>Nhóm đang chỉnh: <span class="fw-bold text-dark" id="initGroupLabel">Nhóm: --</span>
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
                                <th>STT</th><th>Nhóm</th><th>Ngày học</th><th>Thứ</th><th>Tiết</th><th>Phòng</th><th>Trạng thái</th><th>Sửa</th>
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
                <p class="fw-bold mb-2">Cột chuẩn theo file điểm danh (khớp DB):</p>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item px-0 py-1"><strong>Cột A:</strong> STT <span class="text-muted">(có thể bỏ trống)</span></li>
                    <li class="list-group-item px-0 py-1"><strong>Cột B:</strong> MSSV <span class="text-danger">*</span></li>
                    <li class="list-group-item px-0 py-1"><strong>Cột C:</strong> Họ và tên <span class="text-danger">*</span></li>
                    <li class="list-group-item px-0 py-1"><strong>Cột D:</strong> Ngày sinh <span class="text-muted">— dd/mm/yyyy hoặc định dạng ngày Excel</span></li>
                    <li class="list-group-item px-0 py-1"><strong>Cột E:</strong> Lớp</li>
                </ul>
                <div class="alert alert-info py-2 mb-0 small">
                    <i class="bi bi-lightbulb me-1"></i>
                    <strong>Lưu ý:</strong> Hệ thống ưu tiên đọc đúng mẫu điểm danh: <code>STT | MSSV | HỌ VÀ TÊN | NGÀY SINH | LỚP</code>.
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

// Dữ liệu từ DB — classes catalogue với assignments lồng trong
window.allClasses = <?= json_encode($dbCourses, JSON_UNESCAPED_UNICODE) ?>;
// Flat version cho backward compat (openSessionManager, addGroupToClass, v.v.)
window.allAssignmentCourses = [];
window.allClasses.forEach(function(cls) {
    (cls.assignments || []).forEach(function(asg) {
        window.allAssignmentCourses.push({
            id:          asg.id,
            csId:        asg.csId,
            name:        asg.subjectName,
            credits:     asg.credits,
            classCode:   cls.classCode,
            year:        asg.year,
            semester:    asg.semester,
            startDate:   asg.startDate || null,
            endDate:     asg.endDate || null,
            isOpen:      asg.isOpen,
            openWindow:  asg.openWindow,
            groups:      asg.groups,
            subjectCode: asg.subjectCode,
            subjectId:   asg.subjectId
        });
    });
});

// Dataset Master Schedule lấy trực tiếp từ DB
window.masterScheduleCourses = <?= json_encode($dbMasterCourses, JSON_UNESCAPED_UNICODE) ?>;
window.masterScheduleOverrides = <?= json_encode($dbMasterOverrides, JSON_UNESCAPED_UNICODE) ?>;
window.semesterRanges = <?= json_encode($semesterRanges, JSON_UNESCAPED_UNICODE) ?>;

// Map tiết → giờ (phải khai báo trước khi renderMasterSchedule được gọi)
window.startTimeLabels = {1:'07:00',2:'07:45',3:'08:30',4:'09:15',5:'10:00',6:'13:00',7:'13:45',8:'14:30',9:'15:15',10:'16:00',11:'17:45',12:'18:30',13:'19:15',14:'20:00'};

// Helper: lấy room_name từ room_code
function getRoomName(roomCode) {
    if (!roomCode) return '—';
    var r = (window.rooms || []).find(function(r) { return r.code === roomCode; });
    return r ? r.name : roomCode;
}

// ─── MASTER SCHEDULE ────────────────────────────────────────────────────────
window.masterMondays = [];

function parseYmd(ymd) {
    if (!ymd || typeof ymd !== 'string') return null;
    const parts = ymd.split('-');
    if (parts.length !== 3) return null;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10) - 1;
    const d = parseInt(parts[2], 10);
    const date = new Date(y, m, d);
    return Number.isNaN(date.getTime()) ? null : date;
}

function toYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}

function toMonday(date) {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const day = d.getDay();
    d.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
    return d;
}

function formatShortDate(dateStr) {
    const d = parseYmd(dateStr);
    if (!d) return '--/--';
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
}

function normalizeSemesterToken(value) {
    const text = String(value || '').trim().toUpperCase();
    if (!text) return '';
    const match = text.match(/(?:HK)?\s*([123])$/);
    if (match) return match[1];
    return text;
}

function getMondaysBySemester(semesterValue) {
    const ranges = window.semesterRanges || {};
    const selected = ranges[semesterValue] || null;
    const start = selected ? parseYmd(selected.start) : null;
    const end = selected ? parseYmd(selected.end) : null;
    const mondays = [];

    if (start && end && start <= end) {
        let cursor = toMonday(start);
        const endMonday = toMonday(end);
        while (cursor <= endMonday) {
            mondays.push(toYmd(cursor));
            cursor.setDate(cursor.getDate() + 7);
        }
    }

    if (!mondays.length) {
        const nowMonday = toMonday(new Date());
        for (let w = -2; w <= 10; w++) {
            const d = new Date(nowMonday.getFullYear(), nowMonday.getMonth(), nowMonday.getDate());
            d.setDate(d.getDate() + (w * 7));
            mondays.push(toYmd(d));
        }
    }

    return mondays;
}

function rebuildMasterWeekOptions(keepCurrentValue) {
    const semesterSelect = document.getElementById('masterFilterSemester');
    const weekSelect = document.getElementById('masterWeekSelect');
    if (!semesterSelect || !weekSelect) return;

    const oldWeekValue = keepCurrentValue ? weekSelect.value : '';
    const semesterValue = semesterSelect.value || '';
    const mondays = getMondaysBySemester(semesterValue);
    window.masterMondays = mondays;

    weekSelect.innerHTML = '';
    mondays.forEach(function(monday) {
        const sun = parseYmd(monday);
        if (!sun) return;
        sun.setDate(sun.getDate() + 6);
        const opt = document.createElement('option');
        opt.value = monday;
        opt.textContent = 'Tuần ' + formatShortDate(monday) + ' - ' + formatShortDate(toYmd(sun)) + '/' + sun.getFullYear();
        weekSelect.appendChild(opt);
    });

    if (!weekSelect.options.length) return;

    if (oldWeekValue && mondays.indexOf(oldWeekValue) !== -1) {
        weekSelect.value = oldWeekValue;
        return;
    }

    const todayMondayStr = toYmd(toMonday(new Date()));
    if (mondays.indexOf(todayMondayStr) !== -1) {
        weekSelect.value = todayMondayStr;
    } else {
        weekSelect.value = mondays[0];
    }
}

function handleMasterSemesterChange() {
    rebuildMasterWeekOptions(false);
    renderMasterSchedule();
}

(function initMasterSchedule() {
    const weekSelect  = document.getElementById('masterWeekSelect');
    const prevBtn     = document.getElementById('btnPrevWeek');
    const nextBtn     = document.getElementById('btnNextWeek');
    const semesterSelect = document.getElementById('masterFilterSemester');

    rebuildMasterWeekOptions(false);

    if (weekSelect) {
        const closeWeekDropdown = function() {
            if (weekSelect.size !== 1) {
                weekSelect.size = 1;
            }
            weekSelect.classList.remove('week-select-expanded');
        };

        const openWeekDropdown = function() {
            const optionCount = weekSelect.options ? weekSelect.options.length : 0;
            weekSelect.size = Math.min(10, Math.max(2, optionCount || 2));
            weekSelect.classList.add('week-select-expanded');
            weekSelect.focus();
        };

        weekSelect.size = 1;

        weekSelect.addEventListener('mousedown', function(e) {
            if (weekSelect.size === 1) {
                e.preventDefault();
                openWeekDropdown();
            }
        });

        weekSelect.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && weekSelect.size === 1) {
                e.preventDefault();
                openWeekDropdown();
                return;
            }
            if (e.key === 'Escape') {
                closeWeekDropdown();
                weekSelect.blur();
            }
        });

        weekSelect.addEventListener('change', function() {
            closeWeekDropdown();
            renderMasterSchedule();
        });

        weekSelect.addEventListener('blur', function() {
            setTimeout(closeWeekDropdown, 80);
        });

        document.addEventListener('mousedown', function(e) {
            const wrap = weekSelect.closest('.master-week-select-wrap');
            if (!wrap) return;
            if (!wrap.contains(e.target)) {
                closeWeekDropdown();
            }
        });
    }
    if (prevBtn && weekSelect) prevBtn.addEventListener('click', function() {
        const mondays = window.masterMondays || [];
        const idx = mondays.indexOf(weekSelect.value);
        if (idx > 0) {
            weekSelect.value = mondays[idx - 1];
            renderMasterSchedule();
        }
    });
    if (nextBtn && weekSelect) nextBtn.addEventListener('click', function() {
        const mondays = window.masterMondays || [];
        const idx = mondays.indexOf(weekSelect.value);
        if (idx < mondays.length - 1) {
            weekSelect.value = mondays[idx + 1];
            renderMasterSchedule();
        }
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
    const [semFilterRaw, yearFilter] = semVal === 'all' ? ['all', 'all'] : semVal.split('|||');
    const semFilter = semFilterRaw === 'all' ? 'all' : normalizeSemesterToken(semFilterRaw);
    const occupied = {};
    [2,3,4,5,6,7,8].forEach(d => occupied[d] = {});
    const dayOffsetMap = {2:0,3:1,4:2,5:3,6:4,7:5,8:6};
    const primarySlots = {};
    const toYmd = function(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
    const normGroup = function(code) {
        const m = String(code || 'N1').match(/^N?(\d+)$/i);
        return m ? ('N' + m[1]) : String(code || 'N1');
    };
    const escHtml = function(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };
    const overrideMap = {};
    (window.masterScheduleOverrides || []).forEach(function(ov) {
        const key = String(ov.csId || '') + '|' + normGroup(ov.groupCode || 'N1') + '|' + String(ov.date || '');
        if (String(ov.csId || '') && String(ov.date || '')) {
            overrideMap[key] = ov;
        }
    });

    (window.masterScheduleCourses || []).forEach(function(course) {
        if (yearFilter !== 'all' && String(course.year || '') !== yearFilter) return;
        if (semFilter !== 'all' && normalizeSemesterToken(course.semester) !== semFilter) return;

        course.groups.forEach(function(g) {
            if (!g.day || !g.start || !g.end) return;
            const baseDay = parseInt(g.day, 10);
            if (!Number.isFinite(baseDay) || !Object.prototype.hasOwnProperty.call(dayOffsetMap, baseDay)) return;

            const targetDate = new Date(mondayDate);
            targetDate.setDate(mondayDate.getDate() + dayOffsetMap[baseDay]);
            const targetYmd = toYmd(targetDate);
            const overrideKey = String(course.csId || '') + '|' + normGroup(g.code || 'N1') + '|' + targetYmd;
            const override = overrideMap[overrideKey] || null;

            const day   = parseInt((override && override.day) ? override.day : g.day, 10);
            const start = parseInt((override && override.start) ? override.start : g.start, 10);
            const end   = parseInt((override && override.end) ? override.end : g.end, 10);
            const roomValue = (override && override.room) ? override.room : g.room;
            if (!Number.isFinite(day) || !Number.isFinite(start) || !Number.isFinite(end)) return;
            if (teacherFilter !== 'all' && g.teacherMain !== teacherFilter) return;
            if (roomFilter    !== 'all' && roomValue     !== roomFilter)    return;
            const teacherObj = (window.teachers || []).find(t => t.id === g.teacherMain);
            const teacherDisplay = teacherObj ? ((teacherObj.title ? teacherObj.title + '. ' : '') + teacherObj.name) : '—';
            const slotKey = String(day) + '-' + String(start);
            const slotItem = {
                subject: course.name || '',
                classCode: course.classCode || '',
                groupCode: (g.code === 'N1' ? 'Nhóm 1' : (g.code || 'N1')),
                roomName: getRoomName(roomValue),
                teacher: teacherDisplay
            };
            if (occupied[day] && occupied[day][start]) {
                if (primarySlots[slotKey]) primarySlots[slotKey].items.push(slotItem);
                return;
            }

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
            const block = document.createElement('div');
            block.className   = 'subject-block';
            block.style.height    = '100%';
            block.style.minHeight = (span * 35 - 6) + 'px';
            block.style.cursor    = 'pointer';
            block.title           = 'Click để chỉnh sửa lịch ngày này';
            block.innerHTML =
                '<div class="subject-title" style="font-size:0.86rem;font-weight:700">' + course.name + '</div>' +
                '<div style="font-size:0.78rem">Nhóm: <b class="text-primary">' + (g.code === "N1" ? "Nhóm 1" : g.code) + '</b></div>' +
                '<div style="font-size:0.78rem">🏛 <b>' + getRoomName(roomValue) + '</b></div>' +
                '<div style="font-size:0.78rem">👤 ' + tName + '</div>';

            (function(cc, cg, fixedDate, fixedDay, fixedStart, fixedEnd, fixedRoom) {
                block.addEventListener('click', function() {
                    const sd = new Date(fixedDate);
                    const sdStr = toYmd(sd);
                    const sdDisplay = sd.getDate().toString().padStart(2,'0') + '/' +
                                      (sd.getMonth()+1).toString().padStart(2,'0') + '/' +
                                      sd.getFullYear();
                    openEditSingleSession(
                        'edit_master', sdDisplay, sdStr,
                        String(fixedDay), String(fixedStart), String(fixedEnd),
                        fixedRoom || '', 'normal',
                        cc.classCode, cc.name,
                        cg.teacherMain || '', cg.code, cc.csId || ''
                    );
                });
            })(course, g, targetDate, day, start, end, roomValue);

            cell.appendChild(block);
            primarySlots[slotKey] = { block: block, items: [slotItem] };
        });
    });

    Object.keys(primarySlots).forEach(function(key) {
        const slot = primarySlots[key];
        if (!slot || !slot.block || !Array.isArray(slot.items) || slot.items.length <= 1) return;

        const extraCount = slot.items.length - 1;
        slot.block.style.position = 'relative';

        const badge = document.createElement('span');
        badge.textContent = '+' + extraCount;
        badge.style.position = 'absolute';
        badge.style.top = '4px';
        badge.style.right = '6px';
        badge.style.background = '#dc2626';
        badge.style.color = '#fff';
        badge.style.borderRadius = '999px';
        badge.style.padding = '1px 7px';
        badge.style.fontSize = '0.72rem';
        badge.style.fontWeight = '700';
        badge.style.lineHeight = '1.1';
        badge.style.boxShadow = '0 1px 3px rgba(0,0,0,0.25)';
        slot.block.appendChild(badge);

        const listHtml = slot.items.map(function(it, idx) {
            return '<div style="padding:6px 0;border-top:' + (idx ? '1px solid #e2e8f0' : '0') + ';">'
                + '<div style="font-weight:700;color:#0f172a;font-size:0.86rem;">' + escHtml(it.subject) + '</div>'
                + '<div style="font-size:0.8rem;color:#334155;">Nhóm: ' + escHtml(it.groupCode) + '</div>'
                + '<div style="font-size:0.8rem;color:#334155;">Phòng: ' + escHtml(it.roomName) + '</div>'
                + '<div style="font-size:0.8rem;color:#334155;">GV: ' + escHtml(it.teacher) + '</div>'
                + '</div>';
        }).join('');
        const popoverContent = '<div style="min-width:260px;max-width:340px;">'
            + '<div style="font-weight:700;color:#b91c1c;margin-bottom:4px;">Có ' + slot.items.length + ' môn cùng buổi học</div>'
            + listHtml
            + '</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            new bootstrap.Popover(slot.block, {
                trigger: 'hover focus',
                html: true,
                placement: 'auto',
                container: 'body',
                sanitize: false,
                content: popoverContent
            });
        }
    });
}
</script>
<script src="../../public/js/admin/assignments.js?v=<?= filemtime(__DIR__ . '/../../public/js/admin/assignments.js') ?>"></script>
</body>
</html>
