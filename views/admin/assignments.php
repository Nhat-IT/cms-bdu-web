<?php
/**
 * CMS BDU - Phân Công Giảng Dạy
 * Trang điều phối lịch dạy cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin được phép truy cập
requireRole('admin');

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Lấy danh sách lớp học phần với thông tin đầy đủ
$stmtClassSubjects = $pdo->query("
    SELECT 
        cs.id,
        cs.semester,
        s.subject_code,
        s.subject_name,
        u.full_name as teacher_name,
        c.class_name,
        cs.start_date,
        cs.end_date,
        CASE 
            WHEN cs.start_date <= CURDATE() AND cs.end_date >= CURDATE() THEN 'open'
            ELSE 'closed'
        END as status
    FROM class_subjects cs
    LEFT JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    LEFT JOIN classes c ON cs.class_id = c.id
    ORDER BY cs.created_at DESC
");
$classSubjects = $stmtClassSubjects->fetchAll();

// Lấy danh sách giảng viên cho dropdown
$stmtTeachers = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");
$teachers = $stmtTeachers->fetchAll();

// Lấy danh sách môn học cho dropdown
$stmtSubjects = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$subjects = $stmtSubjects->fetchAll();

// Lấy danh sách lớp học cho dropdown
$stmtClasses = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $stmtClasses->fetchAll();

// Lấy danh sách học kỳ cho dropdown
$stmtSemesters = $pdo->query("SELECT id, semester_name, academic_year, start_date, end_date FROM semesters ORDER BY academic_year DESC, semester_name");
$semesters = $stmtSemesters->fetchAll();
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
    <link rel="stylesheet" href="../../public/css/admin/assignments.css">
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
            <a href="org-settings.php"><i class="bi bi-gear-wide-connected"></i> Cấu hình Học vụ</a>
            <a href="accounts.php"><i class="bi bi-people"></i> Quản lý Tài khoản</a>
            <a href="classes-subjects.php"><i class="bi bi-building"></i> Quản lý Lớp & Môn</a>
            <a href="assignments.php" class="active"><i class="bi bi-diagram-3-fill"></i> Phân công Giảng dạy</a>
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
                <i class="bi bi-calendar-range me-2 fs-3 text-warning"></i> HỆ THỐNG ĐIỀU PHỐI LỊCH DẠY
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
            
            <!-- Tab Danh sách Lớp Học Phần -->
            <div class="tab-pane fade show active" id="listView" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark">DANH SÁCH CÁC LỚP ĐỂ XẾP LỊCH</h6>
                        <div class="input-group input-group-sm admin-assignments-search">
                            <input type="text" class="form-control" placeholder="Tìm tên môn, mã lớp..." id="searchClassSubject">
                            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    <div class="card-body border-bottom bg-light-subtle py-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">NĂM HỌC</label>
                                <select class="form-select form-select-sm" id="assignFilterYear">
                                    <option value="all">Chọn tất cả</option>
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?php echo e($sem['academic_year']); ?>"><?php echo e($sem['academic_year']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">HỌC KỲ</label>
                                <select class="form-select form-select-sm" id="assignFilterSemester">
                                    <option value="all">Chọn tất cả</option>
                                    <option value="1">Học kỳ 1</option>
                                    <option value="2">Học kỳ 2</option>
                                    <option value="3">Học kỳ 3 (Hè)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">TRẠNG THÁI MÔN</label>
                                <select class="form-select form-select-sm" id="assignFilterOpenStatus">
                                    <option value="all">Tất cả</option>
                                    <option value="open">Đang mở</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>Thời gian mở môn được lấy từ cấu hình tại trang Quản lý Lớp & Môn và chỉ hiển thị để tham chiếu.
                        </div>
                    </div>
                    <div class="card-body p-0" id="assignmentOfferingContainer">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">MÃ LỚP</th>
                                        <th class="py-3">MÔN HỌC</th>
                                        <th class="py-3">LỚP HÀNH CHÍNH</th>
                                        <th class="py-3">GIẢNG VIÊN</th>
                                        <th class="text-center py-3">NGÀY BẮT ĐẦU</th>
                                        <th class="text-center py-3">NGÀY KẾT THÚC</th>
                                        <th class="text-center py-3">TRẠNG THÁI</th>
                                        <th class="pe-4 py-3 text-end">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="classSubjectListBody">
                                    <?php if (count($classSubjects) > 0): ?>
                                        <?php foreach ($classSubjects as $cs): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-primary"><?php echo e($cs['subject_code'] . '-' . $cs['semester']); ?></td>
                                                <td><?php echo e($cs['subject_name']); ?></td>
                                                <td><?php echo e($cs['class_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($cs['teacher_name']): ?>
                                                        <span class="badge bg-light text-dark border"><?php echo e($cs['teacher_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Chưa phân công</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?php echo formatDate($cs['start_date']); ?></td>
                                                <td class="text-center"><?php echo formatDate($cs['end_date']); ?></td>
                                                <td class="text-center">
                                                    <?php if ($cs['status'] === 'open'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Đang mở</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Đã đóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <button class="btn btn-sm btn-primary" title="Phân công giảng dạy">
                                                        <i class="bi bi-calendar-plus"></i> Xếp lịch
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Chưa có lớp học phần nào. Vui lòng tạo lớp học phần tại trang Quản lý Lớp & Môn.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Thời Khóa Biểu Tổng -->
            <div class="tab-pane fade" id="gridView" role="tabpanel">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">CHỌN HỌC KỲ</label>
                        <select class="form-select border-primary fw-bold text-primary shadow-sm">
                            <?php if (count($semesters) > 0): ?>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo $sem['id']; ?>"><?php echo e($sem['semester_name']); ?> - <?php echo e($sem['academic_year']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option>2025 - 2026 (HK1)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">LỌC THEO GIẢNG VIÊN</label>
                        <select class="form-select border-secondary shadow-sm">
                            <option value="all">-- Tất cả Giảng viên --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo e($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> Chức năng Thời Khóa Biểu Tổng (Master) đang được phát triển. Vui lòng sử dụng tab "Xem Theo Lớp Học Phần" để quản lý.
                </div>

                <div class="schedule-wrapper shadow-sm">
                    <table class="table master-schedule-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><i class="bi bi-arrow-left fs-4 week-nav-btn"></i></th>
                                <th>Thứ 2</th>
                                <th>Thứ 3</th>
                                <th>Thứ 4</th>
                                <th>Thứ 5</th>
                                <th>Thứ 6</th>
                                <th>Thứ 7</th>
                                <th>Chủ Nhật</th>
                                <th style="width: 80px;"><i class="bi bi-arrow-right fs-4 week-nav-btn"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="edge-col">Tiết 1-5</td>
                                <td colspan="5" class="text-center text-muted">-- Trống --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                            <tr>
                                <td class="edge-col">Tiết 6-10</td>
                                <td colspan="5" class="text-center text-muted">-- Trống --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                            <tr>
                                <td class="edge-col">Tiết 11-14</td>
                                <td colspan="5" class="text-center text-muted">-- Trống --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Phân công Giảng dạy -->
<div class="modal fade" id="initialScheduleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white border-bottom-0 pb-3" id="initModalHeader">
                <h5 class="modal-title fw-bold" id="initModalTitle"><i class="bi bi-calendar-plus me-2"></i>Thiết Lập Lịch Giảng Dạy Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="return handleInitialScheduleSubmit(event)">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-warning fw-bold small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Chức năng đang được phát triển.
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Đóng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<input type="file" id="assignmentStudentUploadInput" class="d-none" accept=".csv,.xlsx,.xls">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // Tìm kiếm lớp học phần
    document.getElementById('searchClassSubject').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#classSubjectListBody tr');
        
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });

    // Filter theo học kỳ
    document.getElementById('assignFilterYear').addEventListener('change', filterClassSubjects);
    document.getElementById('assignFilterSemester').addEventListener('change', filterClassSubjects);
    document.getElementById('assignFilterOpenStatus').addEventListener('change', filterClassSubjects);

    function filterClassSubjects() {
        const yearFilter = document.getElementById('assignFilterYear').value;
        const semesterFilter = document.getElementById('assignFilterSemester').value;
        const statusFilter = document.getElementById('assignFilterOpenStatus').value;
        
        const rows = document.querySelectorAll('#classSubjectListBody tr');
        
        rows.forEach(function(row) {
            let show = true;
            
            if (yearFilter !== 'all' && !row.textContent.includes(yearFilter)) {
                show = false;
            }
            
            if (semesterFilter !== 'all') {
                const semCol = row.querySelector('td:nth-child(1)');
                if (semCol && !semCol.textContent.includes(semesterFilter)) {
                    show = false;
                }
            }
            
            if (statusFilter !== 'all') {
                const statusCol = row.querySelector('td:nth-child(7)');
                if (statusCol) {
                    if (statusFilter === 'open' && !statusCol.textContent.includes('Đang mở')) {
                        show = false;
                    }
                    if (statusFilter === 'closed' && !statusCol.textContent.includes('Đã đóng')) {
                        show = false;
                    }
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }

    function handleInitialScheduleSubmit(event) {
        event.preventDefault();
        alert('Chức năng đang được phát triển!');
        return false;
    }
</script>
</body>
</html>
