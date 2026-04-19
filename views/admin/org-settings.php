<?php
/**
 * CMS BDU - Cấu Hình Học Vụ
 * Trang cấu hình ngành học và học kỳ cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin được phép truy cập
requireRole('admin');

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Lấy danh sách ngành học (departments)
$stmtDepartments = $pdo->query("SELECT * FROM departments ORDER BY department_name");
$departments = $stmtDepartments->fetchAll();

// Lấy danh sách học kỳ
$stmtSemesters = $pdo->query("SELECT * FROM semesters ORDER BY academic_year DESC, semester_name");
$semesters = $stmtSemesters->fetchAll();

// Xác định học kỳ hiện tại (đang active hoặc trong khoảng thời gian)
$currentSemester = null;
$stmtCurrentSem = $pdo->query("
    SELECT * FROM semesters 
    WHERE CURDATE() BETWEEN start_date AND end_date 
    ORDER BY start_date DESC LIMIT 1
");
$currentSemester = $stmtCurrentSem->fetch();

if (!$currentSemester) {
    // Nếu không có học kỳ trong khoảng thời gian, lấy học kỳ mới nhất
    $stmtLatestSem = $pdo->query("SELECT * FROM semesters ORDER BY start_date DESC LIMIT 1");
    $currentSemester = $stmtLatestSem->fetch();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Cấu hình Học vụ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/org-settings.css">
</head>
<body class="dashboard-body">

<div class="sidebar sidebar-admin" id="sidebar">
    <div>
        <div class="brand-container flex-shrink-0">
            <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                <span class="fs-4 fw-bold hide-on-collapse">CMS ADMIN</span>
            </a>
        </div>
        <div class="text-center mb-3 text-white-50 small fw-bold hide-on-collapse">QUẢN TRỊ HỆ THỐNG</div>
        <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <a href="home.php"><i class="bi bi-speedometer2"></i> Tổng quan hệ thống</a>
            <a href="org-settings.php" class="active"><i class="bi bi-gear-wide-connected"></i> Cấu hình Học vụ</a>
            <a href="accounts.php"><i class="bi bi-people"></i> Quản lý Tài khoản</a>
            <a href="classes-subjects.php"><i class="bi bi-building"></i> Quản lý Lớp & Môn</a>
            <a href="assignments.php"><i class="bi bi-diagram-3-fill"></i> Phân công Giảng dạy</a>
            <a href="system-logs.php"><i class="bi bi-shield-lock"></i> Nhật ký hệ thống</a>
        </nav>
        </div>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<div class="main-content admin-main-content" id="mainContent">
    
    <div class="top-navbar-admin d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">
                <i class="bi bi-gear-fill me-2 fs-3 text-warning"></i> CẤU HÌNH HỌC VỤ
            </h4>
        </div>
        
        <div class="d-flex align-items-center text-white">
            <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
                <div class="fs-6">Quản trị viên: <span class="fw-bold admin-operator-name"><?php echo e($currentUser['full_name'] ?? 'Admin'); ?></span></div>
            </div>
            
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-2"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                    <li><a class="dropdown-item fw-bold" href="admin-profile.php"><i class="bi bi-person-vcard text-primary me-2"></i>Hồ sơ cá nhân</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item fw-bold text-danger" href="../logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i>Đăng xuất</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="p-4">
        
        <ul class="nav nav-tabs mb-4 border-bottom border-secondary border-opacity-25" id="orgTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active px-4 py-3" id="major-tab" data-bs-toggle="tab" data-bs-target="#major-panel" type="button" role="tab">
                    <i class="bi bi-book-half me-2"></i> Quản lý Ngành Học
                </button>
            </li>
            <li class="nav-item ms-2" role="presentation">
                <button class="nav-link px-4 py-3" id="semester-tab" data-bs-toggle="tab" data-bs-target="#semester-panel" type="button" role="tab">
                    <i class="bi bi-calendar-event me-2"></i> Quản lý Học kỳ
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Ngành Học -->
            <div class="tab-pane fade show active" id="major-panel" role="tabpanel">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-tags text-primary me-2"></i>Danh mục Ngành Đào tạo</h5>
                        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#majorModal" onclick="openMajorModal('add')">
                            <i class="bi bi-plus-lg me-1"></i> Thêm Ngành mới
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4">MÃ NGÀNH</th>
                                        <th>TÊN NGÀNH ĐÀO TẠO</th>
                                        <th>NGÀY TẠO</th>
                                        <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="departmentTableBody">
                                    <?php if (count($departments) > 0): ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-primary"><?php echo e($dept['department_code'] ?? substr($dept['department_name'], 0, 3)); ?></td>
                                                <td class="fw-bold text-dark"><?php echo e($dept['department_name']); ?></td>
                                                <td><?php echo formatDate($dept['created_at']); ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-primary border me-1" data-bs-toggle="modal" data-bs-target="#majorModal" 
                                                        onclick="openMajorModal('edit', '<?php echo e($dept['id']); ?>', '<?php echo e($dept['department_name']); ?>')" title="Sửa">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-light action-btn text-danger border" onclick="confirmDelete('Ngành <?php echo e($dept['department_name']); ?>')" title="Xóa"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Chưa có ngành học nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Học kỳ -->
            <div class="tab-pane fade" id="semester-panel" role="tabpanel">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-3 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-calendar-event text-success me-2"></i>Danh sách Học kỳ</h5>
                        <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#semesterModal" onclick="openSemesterModal('add')">
                            <i class="bi bi-plus-lg me-1"></i> Thêm Học kỳ
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4">HỌC KỲ</th>
                                        <th>NĂM HỌC</th>
                                        <th>BẮT ĐẦU - KẾT THÚC</th>
                                        <th class="text-center">TRẠNG THÁI</th>
                                        <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="semesterTableBody">
                                    <?php if (count($semesters) > 0): ?>
                                        <?php foreach ($semesters as $sem): 
                                            $isCurrent = $currentSemester && $currentSemester['id'] == $sem['id'];
                                        ?>
                                            <tr class="<?php echo $isCurrent ? 'bg-success bg-opacity-10' : ''; ?>">
                                                <td class="ps-4 fw-bold <?php echo $isCurrent ? 'text-success' : 'text-muted'; ?>"><?php echo e($sem['semester_name']); ?></td>
                                                <td class="fw-bold text-dark"><?php echo e($sem['academic_year']); ?></td>
                                                <td><?php echo formatDate($sem['start_date']); ?> <i class="bi bi-arrow-right mx-1 text-muted"></i> <?php echo formatDate($sem['end_date']); ?></td>
                                                <td class="text-center">
                                                    <?php if ($isCurrent): ?>
                                                        <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>Học kỳ Hiện tại</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-25 text-secondary border">Không hoạt động</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-primary border" data-bs-toggle="modal" data-bs-target="#semesterModal" 
                                                        onclick="openSemesterModal('edit', '<?php echo e($sem['id']); ?>', '<?php echo e($sem['semester_name']); ?>', '<?php echo e($sem['academic_year']); ?>', '<?php echo e($sem['start_date']); ?>', '<?php echo e($sem['end_date']); ?>')" title="Sửa">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if (!$isCurrent): ?>
                                                        <button class="btn btn-sm btn-outline-success border" onclick="setActiveSemester('<?php echo e($sem['id']); ?>', '<?php echo e($sem['semester_name']); ?>', '<?php echo e($sem['academic_year']); ?>')" title="Thiết lập làm Học kỳ hiện tại">
                                                            <i class="bi bi-check2-circle"></i> Chọn hiện tại
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Chưa có học kỳ nào.</td>
                                        </tr>
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

<!-- Modal Ngành Học -->
<div class="modal fade" id="majorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="majorModalTitle"><i class="bi bi-tags me-2"></i>Thêm Ngành Học Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/admin/departmentController.php">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="majorIdInput" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên Ngành Đào tạo <span class="text-danger">*</span></label>
                        <input type="text" name="department_name" id="majorNameInput" class="form-control border-primary" placeholder="Ví dụ: Kỹ thuật Phần mềm" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Học kỳ -->
<div class="modal fade" id="semesterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="semesterModalTitle"><i class="bi bi-calendar-check me-2"></i>Cấu hình Học kỳ mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../../controllers/admin/semesterController.php">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="semIdInput" value="">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tên học kỳ <span class="text-danger">*</span></label>
                            <input type="text" name="semester_name" id="semNameInput" class="form-control border-success" placeholder="VD: HK1, HK2..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Năm học <span class="text-danger">*</span></label>
                            <input type="text" name="academic_year" id="semYearInput" class="form-control" placeholder="VD: 2025 - 2026" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-success">Ngày bắt đầu <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="semStartInput" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-danger">Ngày kết thúc <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="semEndInput" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4 bg-light">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    function confirmDelete(itemName) {
        if(confirm(`Bạn có chắc chắn muốn xóa [${itemName}] không?\nHành động này không thể hoàn tác và có thể ảnh hưởng đến dữ liệu các Lớp thuộc ngành này!`)) {
            alert(`Đã xóa ${itemName} thành công!`);
        }
    }

    function setActiveSemester(semId, semName, semYear) {
        if(confirm(`Bạn có muốn chuyển Học kỳ hệ thống sang [${semName} - ${semYear}] không?`)) {
            alert(`Đã thay đổi Học kỳ hiện tại thành công!`);
            location.reload();
        }
    }

    function openMajorModal(mode, id = '', name = '') {
        const modalTitle = document.querySelector('#majorModal .modal-title');
        const nameField = document.querySelector('#majorNameInput');
        const idField = document.querySelector('#majorIdInput');
        
        if (mode === 'add') {
            modalTitle.innerHTML = '<i class="bi bi-tags me-2"></i>Thêm Ngành Học mới';
            nameField.value = '';
            idField.value = '';
        } else {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Ngành Học';
            nameField.value = name;
            idField.value = id;
        }
    }

    function openSemesterModal(mode, id = '', name = '', year = '', start = '', end = '') {
        const modalTitle = document.querySelector('#semesterModal .modal-title');
        
        if (mode === 'add') {
            modalTitle.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Cấu hình Học kỳ mới';
            document.querySelector('#semIdInput').value = '';
            document.querySelector('#semNameInput').value = '';
            document.querySelector('#semNameInput').removeAttribute('readonly');
            document.querySelector('#semYearInput').value = '';
            document.querySelector('#semStartInput').value = '';
            document.querySelector('#semEndInput').value = '';
        } else {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Học kỳ';
            document.querySelector('#semIdInput').value = id;
            document.querySelector('#semNameInput').value = name;
            document.querySelector('#semNameInput').setAttribute('readonly', 'true');
            document.querySelector('#semYearInput').value = year;
            document.querySelector('#semStartInput').value = start;
            document.querySelector('#semEndInput').value = end;
        }
    }
</script>
</body>
</html>
