<?php
/**
 * CMS BDU - Quản Lý Lớp & Môn Học
 * Trang quản lý lớp học và môn học cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin được phép truy cập
requireRole('admin');

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Lấy danh sách lớp học
$stmtClasses = $pdo->query("
    SELECT c.*, d.department_name,
           (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count
    FROM classes c
    LEFT JOIN departments d ON c.department_id = d.id
    ORDER BY c.class_name DESC
");
$classes = $stmtClasses->fetchAll();

// Lấy danh sách môn học
$stmtSubjects = $pdo->query("
    SELECT s.*, pr.subject_name as prerequisite_name, pr.subject_code as prerequisite_code
    FROM subjects s
    LEFT JOIN subjects pr ON s.prerequisite_id = pr.id
    ORDER BY s.subject_code
");
$subjects = $stmtSubjects->fetchAll();

// Lấy danh sách ngành học
$stmtDepartments = $pdo->query("SELECT * FROM departments ORDER BY department_name");
$departments = $stmtDepartments->fetchAll();

// Lấy danh sách giáo viên cho dropdown
$stmtTeachers = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");
$teachers = $stmtTeachers->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Quản Lý Lớp & Môn Học</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/classes-subjects.css">
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
            <a href="classes-subjects.php" class="active"><i class="bi bi-building"></i> Quản lý Lớp & Môn</a>
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
                <i class="bi bi-building me-2 fs-3 text-warning"></i> QUẢN LÝ LỚP & MÔN HỌC
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
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white pt-3 pb-0 border-0">
                <ul class="nav nav-tabs border-bottom-0" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="class-tab" data-bs-toggle="tab" data-bs-target="#class-pane" type="button" role="tab"><i class="bi bi-building-fill me-2"></i>LỚP HỌC</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="subject-tab" data-bs-toggle="tab" data-bs-target="#subject-pane" type="button" role="tab"><i class="bi bi-journal-bookmark-fill me-2"></i>DANH MỤC MÔN HỌC</button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body bg-white pt-4">
                <div class="tab-content" id="myTabContent">
                    
                    <!-- Tab Lớp Học -->
                    <div class="tab-pane fade show active" id="class-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="input-group input-group-sm" style="width: 300px;">
                                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" placeholder="Tìm tên lớp, niên khóa..." id="searchClass">
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importStudentModal" onclick="openImportStudentModal()">
                                    <i class="bi bi-person-plus-fill me-1 text-success"></i> IMPORT SINH VIÊN
                                </button>
                                <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importClassModal">
                                    <i class="bi bi-file-earmark-excel-fill me-1"></i> IMPORT LỚP (EXCEL)
                                </button>
                                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#classModal" onclick="openClassModal('add')">
                                    <i class="bi bi-plus-circle-fill me-1"></i> THÊM LỚP MỚI
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">STT</th>
                                        <th class="py-3">TÊN LỚP HỌC</th>
                                        <th class="py-3">NGÀNH</th>
                                        <th class="py-3">NIÊN KHÓA</th>
                                        <th class="text-center py-3">SỐ LƯỢNG SV</th>
                                        <th class="text-center py-3">TRẠNG THÁI</th>
                                        <th class="text-end pe-4 py-3">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="classTableBody">
                                    <?php if (count($classes) > 0): ?>
                                        <?php foreach ($classes as $index => $class): ?>
                                            <tr>
                                                <td class="ps-4 text-muted"><?php echo $index + 1; ?></td>
                                                <td class="fw-bold text-primary fs-6"><?php echo e($class['class_name']); ?></td>
                                                <td><?php echo e($class['department_name'] ?? 'Chưa phân ngành'); ?></td>
                                                <td><?php echo e($class['academic_year']); ?></td>
                                                <td class="text-center">
                                                    <?php if ($class['student_count'] > 0): ?>
                                                        <span class="badge student-count-soft"><?php echo $class['student_count']; ?> SV</span>
                                                    <?php else: ?>
                                                        <span class="badge student-count-empty">Chưa có SV</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge class-status-open">Đang mở</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="action-btn-group">
                                                        <button class="btn btn-light action-btn text-success border" title="Nhập danh sách Sinh viên vào lớp" onclick="openImportStudentModal('<?php echo e($class['class_name']); ?>')" data-bs-toggle="modal" data-bs-target="#importStudentModal">
                                                            <i class="bi bi-person-lines-fill"></i>
                                                        </button>
                                                        <button class="btn btn-light action-btn text-primary border" title="Sửa thông tin lớp" onclick="openClassModal('edit', '<?php echo e($class['class_name']); ?>', '<?php echo e($class['academic_year']); ?>', 'open')" data-bs-toggle="modal" data-bs-target="#classModal">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button class="btn btn-light action-btn text-danger border" title="Xóa lớp" onclick="confirmDelete('Lớp hành chính', '<?php echo e($class['class_name']); ?>')">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Chưa có lớp học nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Môn Học -->
                    <div class="tab-pane fade" id="subject-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <div class="input-group input-group-sm" style="width: 300px;">
                                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control" placeholder="Tìm mã môn, tên môn..." id="searchSubject">
                                </div>
                            </div>
                            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="openSubjectModal('add')">
                                <i class="bi bi-plus-circle-fill me-1"></i> THÊM MÔN HỌC
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">MÃ MÔN</th>
                                        <th class="py-3">TÊN MÔN HỌC</th>
                                        <th class="py-3">MÔN TIÊN QUYẾT</th>
                                        <th class="text-center py-3">SỐ TÍN CHỈ</th>
                                        <th class="text-center py-3">TRẠNG THÁI</th>
                                        <th class="text-end pe-4 py-3">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="subjectTableBody">
                                    <?php if (count($subjects) > 0): ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-success"><?php echo e($subject['subject_code']); ?></td>
                                                <td class="fw-bold"><?php echo e($subject['subject_name']); ?></td>
                                                <td class="text-muted">
                                                    <?php if ($subject['prerequisite_code']): ?>
                                                        <i class="bi bi-link-45deg me-1"></i><?php echo e($subject['prerequisite_name']); ?> (<?php echo e($subject['prerequisite_code']); ?>)
                                                    <?php else: ?>
                                                        <i class="fst-italic">-- Không --</i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo e($subject['credits']); ?></span></td>
                                                <td class="text-center"><span class="badge class-status-open">Đang mở</span></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-warning border me-1" title="Quản lý trạng thái mở/đóng theo học kỳ" onclick="openSubjectStatusModal('<?php echo e($subject['subject_code']); ?>', '<?php echo e($subject['subject_name']); ?>')" data-bs-toggle="modal" data-bs-target="#subjectStatusModal">
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
                                                    <button class="btn btn-light action-btn text-primary border me-1" title="Sửa môn học" onclick="openSubjectModal('edit', '<?php echo e($subject['subject_code']); ?>', '<?php echo e($subject['subject_name']); ?>', '', <?php echo e($subject['credits']); ?>)" data-bs-toggle="modal" data-bs-target="#subjectModal">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button class="btn btn-light action-btn text-danger border" title="Xóa môn học" onclick="confirmDelete('Môn học', '<?php echo e($subject['subject_code']); ?> - <?php echo e($subject['subject_name']); ?>')">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Chưa có môn học nào.</td>
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

<!-- Modal Thêm/Sửa Lớp Học -->
<div class="modal fade" id="classModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="classModalTitle">Thêm Lớp Học</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="classForm" onsubmit="return handleDataSubmit(event, 'Lớp học', 'classModal')">
          <div class="mb-3">
            <label class="form-label fw-bold">Tên Lớp (Mã Lớp) <span class="text-danger">*</span></label>
            <input type="text" class="form-control border-primary text-uppercase" id="modalClassName" placeholder="VD: 25TH01" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Ngành học</label>
            <select class="form-select border-secondary" id="modalDepartment">
                <option value="">-- Chọn ngành --</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Niên Khóa <span class="text-danger">*</span></label>
            <input type="text" class="form-control border-secondary" id="modalAcademicYear" placeholder="VD: 2025 - 2029" required>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">Trạng Thái Lớp Học <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input border-success" type="radio" name="classStatus" id="classStatusOpen" value="open" checked>
                <label class="form-check-label" for="classStatusOpen">Đang mở</label>
              </div>
              <div class="form-check">
                <input class="form-check-input border-danger" type="radio" name="classStatus" id="classStatusClosed" value="closed">
                <label class="form-check-label" for="classStatusClosed">Đã đóng</label>
              </div>
            </div>
          </div>

          <div class="text-end mt-4">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-primary fw-bold px-4" id="classModalBtn">LƯU LỚP HỌC</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Import Lớp -->
<div class="modal fade" id="importClassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel-fill me-2"></i>Import Danh sách Lớp</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info small border-0 py-2">
            <i class="bi bi-info-circle-fill me-1"></i> Tạo hàng loạt lớp học từ file Excel.
            <br><strong>Cấu trúc cột:</strong> [Mã lớp] | [Niên khóa]
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn file Excel (.xlsx, .xls) <span class="text-danger">*</span></label>
            <input type="file" class="form-control border-success" accept=".xlsx, .xls" required>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
        <button type="button" class="btn btn-success fw-bold px-4" onclick="handleImportSubmit('importClassModal')">TIẾN HÀNH IMPORT</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Import Sinh Viên -->
<div class="modal fade" id="importStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
    <div class="modal-header subject-status-modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Nhập Sinh Viên Vào Lớp</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-warning small border-warning border-opacity-50 py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> Hệ thống sẽ tự động tạo tài khoản cho các sinh viên trong danh sách.
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn Lớp Học <span class="text-danger">*</span></label>
            <select class="form-select border-primary fw-bold text-dark" id="importStudentClassSelect" required>
                <option value="" disabled selected>-- Vui lòng chọn lớp để import sinh viên --</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo e($class['class_name']); ?>"><?php echo e($class['class_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn file Excel trúng tuyển <span class="text-danger">*</span></label>
            <input type="file" class="form-control border-dark" accept=".xlsx, .xls" required>
            <div class="form-text small mt-2"><strong>Cấu trúc:</strong> [STT] | [MSSV] | [Họ và tên] | [Ngày sinh] | [Email]</div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
        <button type="button" class="btn btn-dark fw-bold px-4" onclick="handleImportSubmit('importStudentModal')"><i class="bi bi-cloud-upload-fill me-2"></i>TẢI LÊN & ĐỒNG BỘ</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Thêm/Sửa Môn Học -->
<div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold" id="subjectModalTitle">Thêm Môn Học Mới</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="subjectForm" onsubmit="return handleDataSubmit(event, 'Môn học', 'subjectModal')">
          <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Mã Môn <span class="text-danger">*</span></label>
                <input type="text" class="form-control border-success text-uppercase" id="modalSubjectCode" placeholder="VD: IT001" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Số Tín Chỉ <span class="text-danger">*</span></label>
                <input type="number" class="form-control border-secondary" id="modalCredits" placeholder="VD: 3" min="1" max="10" required>
              </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Tên Môn Học <span class="text-danger">*</span></label>
            <input type="text" class="form-control border-secondary" id="modalSubjectName" placeholder="VD: An ninh cơ sở dữ liệu" required>
          </div>
          
          <div class="mb-4">
            <label class="form-label fw-bold">Môn Tiên Quyết <span class="text-muted fw-normal small">(Không bắt buộc)</span></label>
            <select class="form-select border-secondary" id="modalPrerequisite">
                <option value="">-- Không có môn tiên quyết --</option>
                <?php foreach ($subjects as $subj): ?>
                    <option value="<?php echo $subj['id']; ?>"><?php echo e($subj['subject_code']); ?> - <?php echo e($subj['subject_name']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>

          <div class="text-end mt-4">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-success fw-bold px-4" id="subjectModalBtn">LƯU MÔN HỌC</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Quản lý Trạng thái Môn Học -->
<div class="modal fade" id="subjectStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable subject-status-modal">
        <div class="modal-content border-0 shadow subject-status-modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fw-bold" id="subjectStatusModalTitle"><i class="bi bi-clock-history me-2"></i>Quản lý Trạng thái Mở/Đóng Môn Học</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
    <div class="modal-body p-4 subject-status-modal-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> Chức năng quản lý trạng thái mở/đóng môn học theo học kỳ đang được phát triển.
        </div>
      </div>
            <div class="modal-footer border-top subject-status-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // Tìm kiếm lớp học
    document.getElementById('searchClass').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#classTableBody tr');
        
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });

    // Tìm kiếm môn học
    document.getElementById('searchSubject').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#subjectTableBody tr');
        
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });

    function openClassModal(mode, className = '', year = '', status = 'open') {
        const title = document.getElementById('classModalTitle');
        const btn = document.getElementById('classModalBtn');
        const inputName = document.getElementById('modalClassName');
        
        if (mode === 'add') {
            title.innerText = 'Thêm Lớp Hành Chính';
            btn.innerText = 'LƯU LỚP HỌC';
            document.getElementById('classForm').reset();
            inputName.removeAttribute('readonly');
            document.getElementById('classStatusOpen').checked = true;
        } else {
            title.innerText = 'Chỉnh sửa Lớp Hành Chính';
            btn.innerText = 'CẬP NHẬT LỚP';
            inputName.value = className;
            inputName.setAttribute('readonly', 'true'); 
            document.getElementById('modalAcademicYear').value = year;
            
            if (status === 'closed') {
                document.getElementById('classStatusClosed').checked = true;
            } else {
                document.getElementById('classStatusOpen').checked = true;
            }
        }
    }

    function openImportStudentModal(className = '') {
        const classSelect = document.getElementById('importStudentClassSelect');
        if (className) {
            classSelect.value = className;
        } else {
            classSelect.value = '';
        }
    }

    function handleImportSubmit(modalId) {
        alert("Đã tải file lên và đồng bộ dữ liệu vào hệ thống thành công!");
        var modalEl = document.getElementById(modalId);
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    }

    function openSubjectModal(mode, subCode = '', subName = '', prereqId = '', credits = '') {
        const title = document.getElementById('subjectModalTitle');
        const btn = document.getElementById('subjectModalBtn');
        const inputCode = document.getElementById('modalSubjectCode');
        
        if (mode === 'add') {
            title.innerText = 'Thêm Môn Học Mới';
            btn.innerText = 'LƯU MÔN HỌC';
            document.getElementById('subjectForm').reset();
            inputCode.removeAttribute('readonly');
        } else {
            title.innerText = 'Chỉnh sửa Môn Học';
            btn.innerText = 'CẬP NHẬT MÔN';
            inputCode.value = subCode;
            inputCode.setAttribute('readonly', 'true'); 
            document.getElementById('modalSubjectName').value = subName;
            document.getElementById('modalCredits').value = credits;
            document.getElementById('modalPrerequisite').value = prereqId;
        }
    }

    function handleDataSubmit(event, entityName, modalId) {
        event.preventDefault();
        const action = event.submitter.innerText.includes('CẬP NHẬT') ? 'cập nhật' : 'thêm mới';
        
        if(confirm(`Bạn có chắc chắn muốn ${action} dữ liệu [${entityName}] này vào hệ thống?`)) {
            alert(`Đã ${action} ${entityName} thành công!`);
            var modalEl = document.getElementById(modalId);
            var modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
        }
        return false;
    }

    function confirmDelete(entityName, itemName) {
        if(confirm(`Bạn có chắc chắn muốn xóa [${entityName}: ${itemName}] không? Hành động này có thể ảnh hưởng đến các lớp học phần và sinh viên đang liên kết với nó.`)) {
            alert(`Đã xóa ${entityName} [${itemName}] khỏi hệ thống.`);
        }
    }

    function openSubjectStatusModal(subjectCode, subjectName) {
        const title = document.getElementById('subjectStatusModalTitle');
        title.innerHTML = `<i class="bi bi-clock-history me-2"></i>Quản lý Trạng thái [${subjectCode}] - ${subjectName}`;
    }
</script>
</body>
</html>
