<?php
/**
 * CMS BDU - Trang Tổng quan Quản trị
 * Dashboard chính cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin và support_admin được phép truy cập

// ── Auth guard: browser page requests phải redirect, không trả JSON ──────────
if (!isLoggedIn()) {
    header('Location: /cms/login.php');
    exit;
}
if (!hasRole(['admin', 'support_admin'])) {
    header('Location: /cms/login.php?error=forbidden');
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────
requireRole(['admin', 'support_admin']);

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Tổng sinh viên = tổng DISTINCT mssv trong bảng đăng ký (cùng công thức với classes-subjects.php)
$totalStudents = (int) db_count(
    "SELECT COUNT(DISTINCT mssv) FROM student_subject_registration WHERE mssv IS NOT NULL AND mssv != ''"
);

// Tổng giảng viên (giảng viên + giáo vụ khoa)
$totalTeachers = (int) db_count(
    "SELECT COUNT(*) FROM users WHERE role IN ('teacher','support_admin') AND is_active = 1"
);

// Tổng lớp học
$totalClasses = (int) db_count("SELECT COUNT(*) FROM classes");

// Đếm môn đang mở từ dữ liệu đã lọc (theo học kỳ hiện tại / đã chọn)
// Moved after $classSubjects is defined (see below)

// Phản hồi chưa xử lý (bỏ, không dùng)

// Lấy học kỳ hiện tại để lọc mặc định
$currentSemester = getCurrentSemester();
$defaultSemesterId = $currentSemester['id'] ?? null;

// Lấy tất cả học kỳ để làm bộ lọc
$allSemesters = db_fetch_all(
    "SELECT id, semester_name, academic_year
     FROM semesters
     ORDER BY academic_year DESC, semester_name ASC"
);

// Xử lý bộ lọc GET
if (array_key_exists('semester_id', $_GET)) {
    if ($_GET['semester_id'] === 'all' || $_GET['semester_id'] === '') {
        $filterSemesterId = null; // Tất cả học kỳ
    } else {
        $filterSemesterId = (int) $_GET['semester_id'];
    }
} else {
    $filterSemesterId = $defaultSemesterId; // Mặc định: học kỳ hiện tại
}
$filterStatus = isset($_GET['status']) && $_GET['status'] !== ''
    ? trim($_GET['status'])
    : '';

// Lấy danh sách lớp học phần (lọc theo học kỳ + trạng thái)
$where = [];
$params = [];
if ($filterSemesterId) {
    $where[] = "cs.semester_id = ?";
    $params[] = $filterSemesterId;
}
if ($filterStatus !== '') {
    if ($filterStatus === 'open') {
        $where[] = "s.open_date IS NOT NULL AND s.open_date <= CURDATE() AND (s.close_date IS NULL OR s.close_date >= CURDATE()) AND (cs.end_date IS NULL OR cs.end_date >= CURDATE())";
    } elseif ($filterStatus === 'closed') {
        $where[] = "(s.open_date IS NULL OR s.open_date > CURDATE() OR s.close_date < CURDATE() OR cs.end_date < CURDATE())";
    }
}
$whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$classSubjects = db_fetch_all(
    "SELECT
        cs.id,
        cs.semester_id,
        sm.semester_name,
        sm.academic_year,
        c.class_name,
        s.subject_code,
        s.subject_name,
        u.full_name as teacher_name,
        u.academic_title as teacher_title,
        cs.start_date,
        cs.end_date,
        s.open_date,
        s.close_date
    FROM class_subjects cs
    JOIN semesters sm ON cs.semester_id = sm.id
    LEFT JOIN classes c ON cs.class_id = c.id
    LEFT JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    {$whereClause}
    ORDER BY sm.academic_year DESC, sm.semester_name ASC, c.class_name ASC, s.subject_name ASC
    LIMIT 50",
    $params
);

// Tính trạng thái môn: chỉ dùng subjects.open_date / close_date
// — giống hệt logic trong classes-subjects.php (computed_status)
$today = date('Y-m-d');
foreach ($classSubjects as &$cs) {
    $openDate  = $cs['open_date']  ?? '';
    $closeDate = $cs['close_date'] ?? '';

    if (empty($openDate) || $openDate > $today) {
        $cs['computed_status'] = '0';
    } elseif (!empty($closeDate) && $closeDate < $today) {
        $cs['computed_status'] = '0';
    } else {
        $cs['computed_status'] = '1';
    }

    $cs['displayStatus'] = ($cs['computed_status'] === '1') ? 'open' : 'closed';
}
unset($cs);

// Đếm DISTINCT môn đang mở — cùng công thức với classes-subjects.php
// (chỉ tính theo subjects.open_date / close_date, không phụ thuộc cs.end_date)
$openClassSubjects = (int) db_count(
    "SELECT COUNT(*) FROM subjects
     WHERE open_date IS NOT NULL
       AND open_date <= CURDATE()
       AND (close_date IS NULL OR close_date >= CURDATE())"
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Cổng Quản Trị Hệ Thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
</head>
<body class="dashboard-body">

<?php
$activePage = 'home';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = 'TRUNG TÂM ĐIỀU HÀNH';
$pageIcon   = 'bi-shield-lock-fill';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        
        <div class="row g-4 mb-4 mt-1">
            <div class="col-md-3">
                <div class="card stat-card-custom border-left-primary h-100 p-3">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary"><i class="bi bi-mortarboard-fill"></i></div>
                        <div>
                            <p class="text-primary fw-bold mb-1 small">TỔNG SINH VIÊN</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($totalStudents); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-left-success h-100 p-3">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success"><i class="bi bi-person-workspace"></i></div>
                        <div>
                            <p class="text-success fw-bold mb-1 small">GIẢNG VIÊN</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($totalTeachers); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-left-warning h-100 p-3">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-warning me-3 text-warning"><i class="bi bi-building"></i></div>
                        <div>
                            <p class="text-warning fw-bold mb-1 small">LỚP HỌC</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($totalClasses); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-left-danger h-100 p-3">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger"><i class="bi bi-journal-bookmark-fill"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1 small">MÔN ĐANG MỞ</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($openClassSubjects); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-9">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-diagram-3 text-primary me-2"></i>Lớp Học Phần</h5>
                        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                            <select name="semester_id" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
                                <option value="all" <?php if ($filterSemesterId === null) echo 'selected'; ?>>— Tất cả học kỳ —</option>
                                <?php foreach ($allSemesters as $sm): ?>
                                    <option value="<?php echo (int)$sm['id']; ?>" <?php if ($filterSemesterId == $sm['id']) echo 'selected'; ?>>
                                        <?php echo e($sm['semester_name']); ?> — <?php echo e($sm['academic_year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
                                <option value="">— Tất cả —</option>
                                <option value="open" <?php if ($filterStatus === 'open') echo 'selected'; ?>>Đang mở</option>
                                <option value="closed" <?php if ($filterStatus === 'closed') echo 'selected'; ?>>Đã đóng</option>
                            </select>
                            <div class="input-group input-group-sm" style="width:200px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Tìm mã lớp, tên môn..." id="searchClassSubject">
                            </div>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4">MÃ LỚP</th>
                                        <th>MÔN HỌC</th>
                                        <th>GIẢNG VIÊN</th>
                                        <th>HỌC KỲ</th>
                                        <th class="text-center">TRẠNG THÁI</th>
                                        <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="classSubjectTableBody">
                                    <?php if (count($classSubjects) > 0): ?>
                                        <?php foreach ($classSubjects as $index => $cs): ?>
                                            <tr>
                                                <td class="fw-bold ps-4"><?php echo !empty($cs['class_name'])   ? e($cs['class_name'])   : '--'; ?></td>
                                                <td>
                                                    <span class="text-dark fw-semibold"><?php echo !empty($cs['subject_name']) ? e($cs['subject_name']) : '--'; ?></span>
                                                    <br><small class="text-muted"><?php echo !empty($cs['subject_code']) ? e($cs['subject_code']) : '--'; ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($cs['teacher_name'])): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo e(($cs['teacher_title'] ? $cs['teacher_title'] . '. ' : '') . $cs['teacher_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="small"><?php echo !empty($cs['semester_name']) ? e($cs['semester_name']) : '--'; ?></span>
                                                    <br><small class="text-muted"><?php echo !empty($cs['academic_year'])  ? e($cs['academic_year'])  : '--'; ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($cs['displayStatus'] === 'open'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Đang mở</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Đã đóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="../../views/admin/assignments.php"
                                                       class="btn btn-light action-btn text-primary border"
                                                       title="Xếp lịch & Phân nhóm">
                                                        <i class="bi bi-calendar-week"></i> Xếp lịch
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Chưa có lớp học phần nào trong học kỳ này.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 border-0">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-tools text-secondary me-2"></i>Tác vụ nhanh</h5>
                    </div>
                    <div class="card-body">
                        <a class="btn btn-outline-dark w-100 mb-3 text-start fw-bold p-3" href="../../views/admin/accounts.php">
                            <i class="bi bi-person-plus-fill fs-5 text-success me-2"></i> Cấp TK Giảng viên
                        </a>
                        <a class="btn btn-outline-dark w-100 mb-3 text-start fw-bold p-3" href="../../views/admin/classes-subjects.php">
                            <i class="bi bi-journal-bookmark-fill fs-5 text-warning me-2"></i> Quản lý môn học
                        </a>
                        <a class="btn btn-outline-dark w-100 text-start fw-bold p-3" href="../../views/admin/accounts.php">
                            <i class="bi bi-key-fill fs-5 text-danger me-2"></i> Reset mật khẩu
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // Xác nhận cho các Tác vụ nhanh bên phải
    function confirmQuickAction(actionName) {
        if(confirm(`Chuyển đến trang ${actionName}?`)) {
            // Chuyển link ở đây khi có trang thực tế
        }
    }

    // Tìm kiếm lớp học phần
    document.getElementById('searchClassSubject').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#classSubjectTableBody tr');
        
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>
