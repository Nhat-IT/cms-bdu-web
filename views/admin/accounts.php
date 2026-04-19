<?php
/**
 * CMS BDU - Quản Lý Tài Khoản Người Dùng
 * Trang quản lý tài khoản cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin được phép truy cập
requireRole('admin');

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Xử lý filter và tìm kiếm
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== 'all') {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

$whereClause = '';
if (count($whereConditions) > 0) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetch()['total'];

// Pagination
$perPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;
$totalPages = ceil($totalRecords / $perPage);

// Lấy danh sách users
$sql = "SELECT id, username, full_name, email, role, position, avatar, created_at 
        FROM users 
        $whereClause 
        ORDER BY created_at DESC 
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Lấy danh sách lớp học cho dropdown
$stmtClasses = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $stmtClasses->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Quản Lý Tài Khoản</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/accounts.css">
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
            <a href="accounts.php" class="active"><i class="bi bi-people"></i> Quản lý Tài khoản</a>
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
                <i class="bi bi-people-fill me-2 fs-3 text-warning"></i> QUẢN LÝ TÀI KHOẢN NGƯỜI DÙNG
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
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-xl-3 col-lg-4">
                        <label class="fw-bold small text-muted mb-1">TÌM KIẾM</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Nhập Mã GV/SV, Họ Tên..." value="<?php echo e($search); ?>">
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <label class="fw-bold small text-muted mb-1">LỌC VAI TRÒ</label>
                        <select class="form-select fw-bold text-dark" name="role">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin (Toàn quyền)</option>
                            <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Giảng viên</option>
                            <option value="bcs" <?php echo $roleFilter === 'bcs' ? 'selected' : ''; ?>>Ban Cán Sự</option>
                            <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-2">
                        <label class="fw-bold small text-muted mb-1">TRẠNG THÁI</label>
                        <select class="form-select fw-bold text-dark" name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="active">Hoạt động</option>
                            <option value="locked">Bị khóa</option>
                        </select>
                    </div>
                    <div class="col-xl-5 col-lg-12 text-xl-end mt-3 mt-xl-0 d-flex gap-2 justify-content-xl-end flex-wrap">
                        <button type="button" class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importAccountModal">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> IMPORT TÀI KHOẢN
                        </button>
                        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#accountModal" onclick="openAccountModal('add')">
                            <i class="bi bi-person-plus-fill me-1"></i> THÊM MỚI
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ul me-2 text-primary"></i>Danh sách Người dùng (<?php echo number_format($totalRecords); ?> bản ghi)</h5>
                <button class="btn btn-sm btn-outline-success fw-bold"><i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 border-top">
                        <thead class="table-light small text-muted fw-bold">
                            <tr>
                                <th class="ps-4 py-3 text-center" style="width: 60px;">STT</th>
                                <th class="py-3">MÃ GV/SV</th>
                                <th class="py-3">HỌ VÀ TÊN / EMAIL</th>
                                <th class="py-3 text-center">VAI TRÒ</th>
                                <th class="py-3 text-center">TRẠNG THÁI</th>
                                <th class="pe-4 py-3 text-end">HÀNH ĐỘNG</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $index => $user): ?>
                                    <?php
                                    $stt = $offset + $index + 1;
                                    $avatarUrl = getAvatarUrl($user['avatar'], $user['full_name']);
                                    $roles = [$user['role']];
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-center text-muted"><?php echo $stt; ?></td>
                                        <td class="fw-bold text-dark"><?php echo e($user['username']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo e($avatarUrl); ?>" class="table-avatar border me-3" alt="Avatar">
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo e($user['full_name'] ?: 'Chưa cập nhật'); ?></div>
                                                    <div class="small text-muted"><?php echo e($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $roleLabels = [
                                                'admin' => ['icon' => 'bi-shield-lock-fill', 'text' => 'Quản trị', 'class' => 'bg-danger'],
                                                'teacher' => ['icon' => 'bi-person-video3', 'text' => 'Giảng viên', 'class' => 'bg-primary'],
                                                'bcs' => ['icon' => 'bi-star-fill', 'text' => 'Ban Cán Sự', 'class' => 'bg-warning text-dark'],
                                                'student' => ['icon' => 'bi-person', 'text' => 'Sinh viên', 'class' => 'bg-info'],
                                            ];
                                            $roleInfo = $roleLabels[$user['role']] ?? ['icon' => 'bi-person', 'text' => $user['role'], 'class' => 'bg-secondary'];
                                            ?>
                                            <span class="badge <?php echo $roleInfo['class']; ?> text-white px-2 py-1">
                                                <i class="bi <?php echo $roleInfo['icon']; ?> me-1"></i><?php echo $roleInfo['text']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Đang hoạt động</span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <button class="btn btn-light action-btn text-warning border me-1" title="Khôi phục mật khẩu" onclick="confirmResetPassword('<?php echo e($user['username']); ?>', '<?php echo e($user['full_name']); ?>')"><i class="bi bi-key-fill"></i></button>
                                            <button class="btn btn-light action-btn text-primary border me-1" title="Sửa thông tin" data-bs-toggle="modal" data-bs-target="#accountModal" 
                                                onclick="openAccountModal('edit', '<?php echo e($user['username']); ?>', '<?php echo e($user['full_name']); ?>', '<?php echo e($user['email']); ?>', ['<?php echo $user['role']; ?>'], '', '')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-light action-btn text-danger border" title="Khóa tài khoản" onclick="confirmLockAccount('<?php echo e($user['username']); ?>', '<?php echo e($user['full_name']); ?>')"><i class="bi bi-lock-fill"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Không tìm thấy người dùng nào.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 text-center py-3">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>">Trước</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <li class="page-item active"><a class="page-link" href="#"><?php echo $i; ?></a></li>
                            <?php else: ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>"><?php echo $i; ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>">Tiếp</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><a class="page-link" href="#">Tiếp</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal Thêm/Sửa Tài Khoản -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="accountModalTitle"><i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Mới</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info small border-0 py-2 mb-3">
            <i class="bi bi-info-circle-fill me-1"></i> Mật khẩu mặc định: <strong>Bdu@123456</strong>
        </div>

        <form id="accountForm" onsubmit="return handleAccountSubmit(event)">
          <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Mã GV/MSSV <span class="text-danger">*</span></label>
                <input type="text" class="form-control border-primary fw-bold" id="modalCode" placeholder="22050111 hoặc GV..." required>
                <small class="text-muted">Dùng làm tên đăng nhập.</small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Địa chỉ Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control border-secondary" id="modalEmail" placeholder="user@bdu.edu.vn" required>
              </div>
          </div>

          <div class="row">
            <div class="col-md-5 mb-3">
                <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                <input type="text" class="form-control border-secondary" id="modalFullName" placeholder="Nhập đầy đủ họ tên..." required>
            </div>
            <div class="col-md-4 mb-3" id="academicRankGroup">
                <label class="form-label fw-bold">Học hàm / Học vị</label>
                <select class="form-select border-secondary" id="modalAcademicRank">
                    <option value="">Không áp dụng</option>
                    <option value="Cử nhân">Cử nhân (CN.)</option>
                    <option value="Thạc sĩ">Thạc sĩ (ThS.)</option>
                    <option value="Tiến sĩ">Tiến sĩ (TS.)</option>
                    <option value="PGS.TS">Phó Giáo sư (PGS.TS)</option>
                </select>
            </div>
            <div class="col-md-3 mb-3" id="classInputGroup" style="display: none;">
                <label class="form-label fw-bold">Lớp học <span class="text-danger">*</span></label>
                <select class="form-select border-warning fw-bold text-dark" id="modalClass">
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo e($class['class_name']); ?>"><?php echo e($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
          </div>
          
          <div class="mb-4 bg-light p-3 rounded border">
            <label class="form-label fw-bold d-block mb-3">Vai trò trong hệ thống <span class="text-danger">*</span></label>
            <div class="row role-checkbox-group">
                <div class="col-md-6 border-end border-secondary border-opacity-25">
                    <p class="small fw-bold text-muted mb-2">QUẢN TRỊ & GIẢNG DẠY</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input group-staff" type="checkbox" value="admin" id="roleSuperAdmin">
                        <label class="form-check-label fw-bold text-danger" for="roleSuperAdmin">Quản trị viên</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input group-staff" type="checkbox" value="teacher" id="roleTeacher">
                        <label class="form-check-label fw-bold text-success" for="roleTeacher">Giảng viên</label>
                    </div>
                </div>
                <div class="col-md-6 ps-md-4">
                    <p class="small fw-bold text-muted mb-2">KHỐI NGƯỜI HỌC</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input group-student" type="checkbox" value="bcs" id="roleBcs">
                        <label class="form-check-label fw-bold text-warning text-dark" for="roleBcs">Ban Cán Sự</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input group-student" type="checkbox" value="student" id="roleStudent">
                        <label class="form-check-label fw-bold text-info text-dark" for="roleStudent">Sinh viên</label>
                    </div>
                </div>
            </div>
            <div class="small text-danger mt-3" id="roleValidationMsg" style="display: none;">
                <i class="bi bi-x-circle-fill me-1"></i>Vui lòng chọn ít nhất 1 vai trò!
            </div>
          </div>

          <div class="text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
            <button type="submit" class="btn btn-primary fw-bold px-4" id="accountModalBtn">LƯU TÀI KHOẢN</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Import Tài Khoản -->
<div class="modal fade" id="importAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel-fill me-2"></i>Import Tài Khoản</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info small border-0 py-2 mb-3">
            <i class="bi bi-info-circle-fill me-1"></i> Cấu trúc cột bắt buộc:
            <br><strong>[STT] | [Mã GV/MSSV] | [Họ và tên] | [Ngày sinh] | [Lớp học] | [Email] | [Vai trò]</strong>
            <hr class="my-2">
            <span class="text-danger fw-bold">*Ghi chú:</span> 
            <br>- Cột Lớp học: Bỏ trống nếu là vai trò Admin/Giảng viên.
            <br>- Cột Vai trò: admin, teacher, bcs, student.
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn file Excel (.xlsx, .xls) <span class="text-danger">*</span></label>
            <input type="file" class="form-control border-success" accept=".xlsx, .xls" required>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
        <button type="button" class="btn btn-success fw-bold px-4" onclick="handleImportSubmit()">TIẾN HÀNH IMPORT</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    const staffCheckboxes = document.querySelectorAll('.group-staff');
    const studentCheckboxes = document.querySelectorAll('.group-student');
    const classInputGroup = document.getElementById('classInputGroup');
    const modalClassInput = document.getElementById('modalClass');
    const academicRankGroup = document.getElementById('academicRankGroup');
    const modalAcademicRank = document.getElementById('modalAcademicRank');

    function updateRoleConstraints() {
        const isStaffChecked = Array.from(staffCheckboxes).some(cb => cb.checked);
        const isStudentChecked = Array.from(studentCheckboxes).some(cb => cb.checked);
        const isBcsChecked = document.getElementById('roleBcs').checked;
        const isStdChecked = document.getElementById('roleStudent').checked;

        studentCheckboxes.forEach(cb => { cb.disabled = isStaffChecked; if (isStaffChecked) cb.checked = false; });
        staffCheckboxes.forEach(cb => { cb.disabled = isStudentChecked; if (isStudentChecked) cb.checked = false; });

        if (isStdChecked || isBcsChecked) {
            classInputGroup.style.display = 'block';
            modalClassInput.setAttribute('required', 'true');
            academicRankGroup.style.display = 'none';
            modalAcademicRank.value = '';
        } else {
            classInputGroup.style.display = 'none';
            modalClassInput.removeAttribute('required');
            modalClassInput.value = '';
            academicRankGroup.style.display = 'block';
        }

        if (isStaffChecked || isStudentChecked) document.getElementById('roleValidationMsg').style.display = 'none';
    }

    document.querySelectorAll('.role-checkbox-group .form-check-input').forEach(cb => {
        cb.addEventListener('change', updateRoleConstraints);
    });

    function openAccountModal(mode, code = '', fullName = '', email = '', roles = [], className = '', academicRank = '') {
        const title = document.getElementById('accountModalTitle');
        const inputCode = document.getElementById('modalCode');
        const inputAcademicRank = document.getElementById('modalAcademicRank');
        document.getElementById('accountForm').reset();
        
        document.querySelectorAll('.form-check-input').forEach(cb => { cb.checked = false; cb.disabled = false; });
        classInputGroup.style.display = 'none';
        academicRankGroup.style.display = 'block';

        if (mode === 'add') {
            title.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Mới';
            inputCode.removeAttribute('readonly'); 
            inputAcademicRank.value = '';
        } else {
            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập Nhật Thông Tin';
            inputCode.value = code;
            inputCode.setAttribute('readonly', 'true'); 
            document.getElementById('modalFullName').value = fullName;
            document.getElementById('modalEmail').value = email;
            modalClassInput.value = className;
            inputAcademicRank.value = academicRank;
            
            roles.forEach(role => {
                if (role === 'admin') document.getElementById('roleSuperAdmin').checked = true;
                if (role === 'teacher') document.getElementById('roleTeacher').checked = true;
                if (role === 'bcs') document.getElementById('roleBcs').checked = true;
                if (role === 'student') document.getElementById('roleStudent').checked = true;
            });
            updateRoleConstraints();
        }
    }

    function handleAccountSubmit(event) {
        event.preventDefault();
        const isRoleChecked = Array.from(document.querySelectorAll('.form-check-input')).some(cb => cb.checked);
        if (!isRoleChecked) { document.getElementById('roleValidationMsg').style.display = 'block'; return false; }
        alert("Đã lưu dữ liệu người dùng thành công!");
        bootstrap.Modal.getInstance(document.getElementById('accountModal')).hide();
        return false;
    }

    function handleImportSubmit() {
        alert("Đã xử lý file Excel và import thành công danh sách tài khoản kèm thông tin Lớp học!");
        bootstrap.Modal.getInstance(document.getElementById('importAccountModal')).hide();
    }

    function confirmResetPassword(code, fullName) {
        if(confirm(`Khôi phục mật khẩu cho [${fullName}] về Bdu@123456?`)) alert("Đã reset thành công!");
    }

    function confirmLockAccount(code, fullName) {
        if(confirm(`Khóa tài khoản của [${fullName}]?`)) alert("Đã khóa tài khoản thành công!");
    }
</script>
</body>
</html>
