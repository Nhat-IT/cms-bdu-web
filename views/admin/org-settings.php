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
$departments = db_fetch_all("SELECT * FROM departments ORDER BY department_name");

// Lấy danh sách học kỳ
$semesters = db_fetch_all("SELECT * FROM semesters ORDER BY academic_year DESC, semester_name");

// Xác định học kỳ hiện tại: ưu tiên lựa chọn thủ công từ session, fallback theo ngày.
$currentSemester = null;
$forcedSemesterId = isset($_SESSION['current_semester_id']) ? (int) $_SESSION['current_semester_id'] : 0;
if ($forcedSemesterId > 0) {
    $currentSemester = db_fetch_one('SELECT * FROM semesters WHERE id = ?', [$forcedSemesterId]);
}

if (!$currentSemester) {
    $currentSemester = db_fetch_one(
        "SELECT * FROM semesters WHERE CURDATE() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1"
    );
}

if (!$currentSemester) {
    $currentSemester = db_fetch_one("SELECT * FROM semesters ORDER BY start_date DESC LIMIT 1");
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

<?php
$activePage = 'org-settings';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = 'CẤU HÌNH HỌC VỤ';
$pageIcon   = 'bi-gear-fill';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        <?php
        // Determine active tab from URL or default to 'major'
        // If there are action params (success/error), stay on that tab
        $hasMajorAction = isset($_GET['major_success']) || isset($_GET['major_deleted']) || isset($_GET['major_error']);
        $hasSemesterAction = isset($_GET['semester_success']) || isset($_GET['semester_deleted']) || isset($_GET['semester_current']) || isset($_GET['semester_error']);
        
        if ($hasMajorAction) {
            $activeTab = 'major';
        } elseif ($hasSemesterAction) {
            $activeTab = 'semester';
        } elseif (isset($_GET['tab']) && $_GET['tab'] === 'semester') {
            $activeTab = 'semester';
        } else {
            $activeTab = 'major';
        }
        ?>
        
        <?php if ($hasMajorAction || $hasSemesterAction): ?>
            <div class="alert alert-dismissible fade show" id="actionAlert" role="alert">
                <i class="bi me-2"></i>
                <span id="alertMessage"></span>
            </div>
        <?php endif; ?>
        
        <ul class="nav nav-tabs mb-4 border-bottom border-secondary border-opacity-25" id="orgTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $activeTab === 'major' ? 'active' : ''; ?> px-4 py-3" id="major-tab" data-bs-toggle="tab" data-bs-target="#major-panel" type="button" role="tab">
                    <i class="bi bi-book-half me-2"></i> Quản lý Ngành Học
                </button>
            </li>
            <li class="nav-item ms-2" role="presentation">
                <button class="nav-link <?php echo $activeTab === 'semester' ? 'active' : ''; ?> px-4 py-3" id="semester-tab" data-bs-toggle="tab" data-bs-target="#semester-panel" type="button" role="tab">
                    <i class="bi bi-calendar-event me-2"></i> Quản lý Học kỳ
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Ngành Học -->
            <div class="tab-pane fade <?php echo $activeTab === 'major' ? 'show active' : ''; ?>" id="major-panel" role="tabpanel">
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
                                                <td class="ps-4 fw-bold text-primary">
                                                    <?php 
                                                    $code = $dept['department_code'] ?? '';
                                                    if (empty($code)): ?>
                                                        <span class="text-danger">Chưa có</span>
                                                    <?php else: ?>
                                                        <?php echo e($code); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold text-dark"><?php echo e($dept['department_name']); ?></td>
                                                <td><?php echo formatDate($dept['created_at']); ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-primary border me-1" data-bs-toggle="modal" data-bs-target="#majorModal" 
                                                        onclick="openMajorModal('edit', '<?php echo e($dept['id']); ?>', '<?php echo e($dept['department_name']); ?>', '<?php echo e($dept['department_code'] ?? '') ?>')" title="Sửa">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="../../controllers/admin/departmentController.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa ngành <?php echo e($dept['department_name']); ?>?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo e($dept['id']); ?>">
                                                        <button class="btn btn-light action-btn text-danger border" title="Xóa"><i class="bi bi-trash"></i></button>
                                                    </form>
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
            <div class="tab-pane fade <?php echo $activeTab === 'semester' ? 'show active' : ''; ?>" id="semester-panel" role="tabpanel">
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
                                        <th class="ps-4">NĂM HỌC</th>
                                        <th>HỌC KỲ</th>
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
                                                <td class="ps-4 fw-bold text-dark"><?php echo e($sem['academic_year']); ?></td>
                                                <td class="fw-bold <?php echo $isCurrent ? 'text-success' : 'text-muted'; ?>"><?php echo e($sem['semester_name']); ?></td>
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
                                                        <form method="POST" action="../../controllers/admin/semesterController.php" class="d-inline" onsubmit="return confirm('Bạn có muốn chuyển học kỳ hiện tại sang <?php echo e($sem['semester_name']); ?> - <?php echo e($sem['academic_year']); ?>?');">
                                                            <input type="hidden" name="action" value="set_current">
                                                            <input type="hidden" name="id" value="<?php echo e($sem['id']); ?>">
                                                            <button class="btn btn-sm btn-outline-success border" title="Thiết lập làm Học kỳ hiện tại">
                                                                <i class="bi bi-check2-circle"></i> Chọn hiện tại
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" action="../../controllers/admin/semesterController.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa học kỳ <?php echo e($sem['semester_name']); ?> - <?php echo e($sem['academic_year']); ?>?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo e($sem['id']); ?>">
                                                        <button class="btn btn-sm btn-outline-danger border" title="Xóa học kỳ"><i class="bi bi-trash"></i></button>
                                                    </form>
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
                        <label class="form-label fw-bold">Mã ngành <span class="text-danger">*</span></label>
                        <input type="text" name="department_code" id="majorCodeInput" class="form-control border-primary" placeholder="Ví dụ: CNTT" required>
                    </div>
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
                            <select name="semester_name" id="semNameInput" class="form-select border-success" required>
                                <option value="">-- Chọn học kỳ --</option>
                                <option value="HK1">HK1</option>
                                <option value="HK2">HK2</option>
                                <option value="HK3">HK3</option>
                            </select>
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

    function openMajorModal(mode, id = '', name = '', code = '') {
        const modalTitle = document.querySelector('#majorModal .modal-title');
        const nameField = document.querySelector('#majorNameInput');
        const codeField = document.querySelector('#majorCodeInput');
        const idField = document.querySelector('#majorIdInput');
        
        if (mode === 'add') {
            modalTitle.innerHTML = '<i class="bi bi-tags me-2"></i>Thêm Ngành Học mới';
            nameField.value = '';
            codeField.value = '';
            idField.value = '';
        } else {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Ngành Học';
            nameField.value = name;
            codeField.value = code;
            idField.value = id;
        }
    }

    function openSemesterModal(mode, id = '', name = '', year = '', start = '', end = '') {
        const modalTitle = document.querySelector('#semesterModal .modal-title');
        
        if (mode === 'add') {
            modalTitle.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Cấu hình Học kỳ mới';
            document.querySelector('#semIdInput').value = '';
            document.querySelector('#semNameInput').value = '';
            document.querySelector('#semYearInput').value = '';
            document.querySelector('#semStartInput').value = '';
            document.querySelector('#semEndInput').value = '';
        } else {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Học kỳ';
            document.querySelector('#semIdInput').value = id;
            document.querySelector('#semNameInput').value = name;
            document.querySelector('#semYearInput').value = year;
            document.querySelector('#semStartInput').value = start;
            document.querySelector('#semEndInput').value = end;
        }
    }

    // Handle action alert messages and auto-hide
    const alertEl = document.getElementById('actionAlert');
    if (alertEl) {
        const urlParams = new URLSearchParams(window.location.search);
        let alertClass = 'alert-success';
        let iconClass = 'bi-check-circle-fill';
        let message = '';
        
        if (urlParams.has('major_success') || urlParams.has('major_deleted')) {
            message = urlParams.has('major_deleted') ? 'Đã xóa ngành học thành công!' : 'Thao tác thành công!';
        } else if (urlParams.has('major_error')) {
            alertClass = 'alert-danger';
            iconClass = 'bi-exclamation-triangle-fill';
            message = 'Đã xảy ra lỗi. Vui lòng thử lại.';
        } else if (urlParams.has('semester_current')) {
            message = 'Đã đặt học kỳ hiện tại thành công!';
        } else if (urlParams.has('semester_deleted')) {
            message = 'Đã xóa học kỳ thành công!';
        } else if (urlParams.has('semester_success')) {
            message = 'Thao tác thành công!';
        } else if (urlParams.has('semester_error')) {
            alertClass = 'alert-danger';
            iconClass = 'bi-exclamation-triangle-fill';
            message = 'Đã xảy ra lỗi. Vui lòng thử lại.';
        }
        
        alertEl.classList.add(alertClass);
        alertEl.querySelector('.bi').classList.add(iconClass);
        document.getElementById('alertMessage').textContent = message;
        
        // Auto-hide after 4 seconds with smooth collapse
        setTimeout(function() {
            alertEl.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, margin 0.5s ease, padding 0.5s ease';
            alertEl.style.opacity = '0';
            alertEl.style.maxHeight = '0';
            alertEl.style.marginBottom = '0';
            alertEl.style.paddingTop = '0';
            alertEl.style.paddingBottom = '0';
            setTimeout(function() {
                alertEl.remove();
                // Clear URL params without reload
                const url = new URL(window.location.href);
                url.searchParams.delete('major_success');
                url.searchParams.delete('major_deleted');
                url.searchParams.delete('major_error');
                url.searchParams.delete('semester_success');
                url.searchParams.delete('semester_deleted');
                url.searchParams.delete('semester_current');
                url.searchParams.delete('semester_error');
                url.searchParams.delete('tab');
                window.history.replaceState({}, '', url);
            }, 500);
        }, 4000);
    }
</script>
</body>
</html>
