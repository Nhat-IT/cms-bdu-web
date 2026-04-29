<?php
/**
 * CMS BDU - Trang Tổng quan Quản trị
 * Dashboard chính cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin và support_admin được phép truy cập
requireRole(['admin', 'support_admin']);

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Đếm tổng số sinh viên (từ bảng users)
$totalStudents = (int) db_count("SELECT COUNT(*) FROM users WHERE role = 'student'");

// Đếm tổng số giảng viên
$totalTeachers = (int) db_count("SELECT COUNT(*) FROM users WHERE role = 'teacher'");

// Đếm tổng số lớp học
$totalClasses = (int) db_count("SELECT COUNT(*) FROM classes");

// Đếm số lớp học phần đang mở
$openClassSubjects = (int) db_count(
    "SELECT COUNT(DISTINCT cs.id) FROM class_subjects cs WHERE cs.start_date <= CURDATE() AND cs.end_date >= CURDATE()"
);

// Lấy danh sách lớp học phần gần đây
$classSubjects = db_fetch_all(
    "SELECT
        cs.id,
        cs.semester,
        c.class_name,
        s.subject_code,
        s.subject_name,
        u.full_name as teacher_name,
        u.academic_title as teacher_title,
        cs.start_date,
        cs.end_date,
        CASE
            WHEN cs.start_date <= CURDATE() AND cs.end_date >= CURDATE() THEN 'open'
            ELSE 'closed'
        END as status
    FROM class_subjects cs
    LEFT JOIN classes c ON cs.class_id = c.id
    LEFT JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    ORDER BY cs.id DESC
    LIMIT 10"
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
                            <p class="text-danger fw-bold mb-1 small">MÔN HỌC ĐANG MỞ</p>
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
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-diagram-3 text-primary me-2"></i>Tình trạng Lớp Học Phần</h5>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Tìm mã lớp, tên môn..." id="searchClassSubject">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4">MÃ LỚP</th>
                                        <th>MÔN HỌC</th>
                                        <th>GIẢNG VIÊN</th>
                                        <th class="text-center">TRẠNG THÁI</th>
                                        <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="classSubjectTableBody">
                                    <?php if (count($classSubjects) > 0): ?>
                                        <?php foreach ($classSubjects as $index => $cs): ?>
                                            <tr>
                                                <td class="fw-bold ps-4"><?php echo e($cs['class_name'] ?? '--'); ?></td>
                                                <td><?php echo e($cs['subject_name']); ?></td>
                                                <td>
                                                    <?php if ($cs['teacher_name']): ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo e(($cs['teacher_title'] ? $cs['teacher_title'] . '. ' : '') . $cs['teacher_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Chưa phân công</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($cs['status'] === 'open'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Đang mở</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Đã đóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($cs['status'] === 'open'): ?>
                                                        <a href="../../views/admin/assignments.php" class="btn btn-light action-btn text-primary border" title="Xếp lịch">
                                                            <i class="bi bi-calendar-week"></i> Xếp lịch
                                                        </a>
                                                    <?php else: ?>
                                                        <form method="POST" action="../../controllers/admin/classSubjectController.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn mở lại lớp <?php echo e($cs['subject_code'] . ' - ' . $cs['subject_name']); ?>?');">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="status" value="open">
                                                            <input type="hidden" name="class_subject_id" value="<?php echo e($cs['id']); ?>">
                                                            <input type="hidden" name="return" value="home">
                                                            <button class="btn btn-light action-btn text-success border" title="Mở lại lớp này">
                                                                <i class="bi bi-unlock-fill"></i> Mở lớp
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Chưa có lớp học phần nào.</td>
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
