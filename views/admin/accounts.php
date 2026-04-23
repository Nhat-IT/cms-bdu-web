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
$roleFilter = ($roleFilter === 'staff') ? 'support_admin' : $roleFilter;
$statusFilter = $_GET['status'] ?? 'all';
$exportType = $_GET['export'] ?? '';

function usersHasSecondaryRoleColumn() {
    $row = db_fetch_one("SELECT COUNT(*) as total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'secondary_role'");
    return ((int) ($row['total'] ?? 0)) > 0;
}

// is_active luôn tồn tại trong bảng users (1 = hoạt động, 0 = bị khóa)
$hasLockColumn = true;
$hasSecondaryRoleColumn = usersHasSecondaryRoleColumn();

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
    $roleFilterValues = ($roleFilter === 'support_admin') ? ['support_admin', 'staff'] : [$roleFilter];
    if ($hasSecondaryRoleColumn) {
        $placeholders = implode(',', array_fill(0, count($roleFilterValues), '?'));
        $whereConditions[] = "(role IN ($placeholders) OR secondary_role IN ($placeholders))";
        $params = array_merge($params, $roleFilterValues, $roleFilterValues);
    } else {
        $placeholders = implode(',', array_fill(0, count($roleFilterValues), '?'));
        $whereConditions[] = "role IN ($placeholders)";
        $params = array_merge($params, $roleFilterValues);
    }
}

if ($statusFilter !== 'all') {
    // is_active: 1 = hoạt động, 0 = bị khóa
    $whereConditions[] = "is_active = ?";
    $params[] = $statusFilter === 'locked' ? 0 : 1;
}

$whereClause = '';
if (count($whereConditions) > 0) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

if ($exportType === 'excel') {
    $exportSql = $hasSecondaryRoleColumn
        ? "SELECT username, full_name, academic_title, email, role, secondary_role, position, created_at FROM users $whereClause ORDER BY created_at DESC"
        : "SELECT username, full_name, academic_title, email, role, position, created_at FROM users $whereClause ORDER BY created_at DESC";
    $exportRows = db_fetch_all($exportSql, $params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="accounts-export-' . date('Ymd_His') . '.csv"');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $hasSecondaryRoleColumn ? ['username', 'full_name', 'academic_title', 'email', 'role', 'secondary_role', 'position', 'status', 'created_at'] : ['username', 'full_name', 'academic_title', 'email', 'role', 'position', 'status', 'created_at']);
    foreach ($exportRows as $row) {
        $createdAtDisplay = '';
        if (!empty($row['created_at'])) {
            $createdAtDisplay = date('d/m/Y H:i:s', strtotime((string)$row['created_at']));
        }
        $csvRow = [
            $row['username'] ?? '',
            $row['full_name'] ?? '',
            $row['academic_title'] ?? '',
            $row['email'] ?? '',
            $row['role'] ?? '',
        ];
        if ($hasSecondaryRoleColumn) {
            $csvRow[] = $row['secondary_role'] ?? '';
        }
        $csvRow[] = $row['position'] ?? '';
        $csvRow[] = 'active';
        $csvRow[] = $createdAtDisplay;
        fputcsv($out, $csvRow);
    }
    fclose($out);
    exit;
}

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$totalRecords = (int) db_count($countSql, $params);

// Pagination
$perPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;
$totalPages = ceil($totalRecords / $perPage);

// Lấy danh sách users

$fields = [
    'id',
    'username',
    'full_name',
    'email',
    'role',
    'academic_title',
    $hasSecondaryRoleColumn ? 'secondary_role' : 'NULL AS secondary_role',
    'position',
    'birth_date',
    'avatar',
    'created_at',
    '(SELECT cs.class_id FROM class_students cs WHERE cs.student_id = users.id ORDER BY cs.id DESC LIMIT 1) AS class_id',
    'is_active',
];
$fieldsSql = implode(",\n    ", $fields);
$sql = "SELECT\n    $fieldsSql\nFROM users\n$whereClause\nORDER BY created_at DESC\nLIMIT $perPage OFFSET $offset";
$users = db_fetch_all($sql, $params);

// Lấy danh sách lớp học cho dropdown
$classes = db_fetch_all("SELECT id, class_name FROM classes ORDER BY class_name");
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

<?php
$activePage = 'accounts';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = 'QUẢN LÝ TÀI KHOẢN NGƯỜI DÙNG';
$pageIcon   = 'bi-people-fill';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        <?php if (isset($_GET['import_done'])): ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <i class="bi bi-cloud-check-fill me-2"></i>
                    Import hoàn tất. Thành công: <strong><?php echo (int) ($_GET['import_ok'] ?? 0); ?></strong>,
                    Bỏ qua: <strong><?php echo (int) ($_GET['import_skip'] ?? 0); ?></strong>,
                    Lỗi: <strong><?php echo (int) ($_GET['import_err'] ?? 0); ?></strong>.
                </div>
                <?php if (!empty($_GET['import_code'])): ?>
                    <span class="badge bg-warning text-dark">Mã lỗi: <?php echo e($_GET['import_code']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['account_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Lưu tài khoản thành công.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($_GET['account_reset'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-key-fill me-2"></i> Đã khôi phục mật khẩu mặc định.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($_GET['account_lock_changed'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-lock-fill me-2"></i> Đã cập nhật trạng thái khóa tài khoản.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($_GET['account_error'])): ?>
            <?php
                $accountErrorMessages = [
                    'missing_data' => 'Vui lòng nhập đầy đủ thông tin và chọn vai trò.',
                    'missing_class' => 'Vai trò sinh viên/BCS cần chọn lớp học.',
                    'invalid_email' => 'Email không hợp lệ.',
                    'invalid_role' => 'Vai trò phụ không hợp lệ.',
                    'missing_id' => 'Thiếu mã tài khoản để thực hiện thao tác.',
                    'reset_failed' => 'Không thể khôi phục mật khẩu lúc này.',
                    'lock_column_missing' => 'Cơ sở dữ liệu chưa có cột khóa tài khoản.',
                    'user_not_found' => 'Không tìm thấy tài khoản cần thao tác.',
                    'toggle_lock_failed' => 'Không thể cập nhật trạng thái khóa tài khoản.',
                    'protected_account' => 'Tài khoản admin@bdu.edu.vn là tài khoản hệ thống, không thể khóa hoặc xóa.',
                    'invalid_password' => 'Mật khẩu xác nhận không đúng.',
                    'cannot_delete_self' => 'Không thể tự xóa tài khoản đang đăng nhập.',
                    'delete_failed' => 'Không thể xóa tài khoản lúc này.',
                ];
                $accountErrorCode = $_GET['account_error'];
                $accountErrorMessage = $accountErrorMessages[$accountErrorCode] ?? 'Đã xảy ra lỗi. Vui lòng thử lại.';
            ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($accountErrorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end" id="accountFilterForm">
                    <div class="col-xl-3 col-lg-4">
                        <label class="fw-bold small text-muted mb-1">TÌM KIẾM</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" id="searchFilterInput" name="search" placeholder="Nhập Mã GV/SV, Họ Tên..." value="<?php echo e($search); ?>">
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3">
                        <label class="fw-bold small text-muted mb-1">LỌC VAI TRÒ</label>
                        <select class="form-select fw-bold text-dark" id="roleFilterSelect" name="role">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="support_admin" <?php echo $roleFilter === 'support_admin' ? 'selected' : ''; ?>>Giáo vụ khoa</option>
                            <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Giảng viên</option>
                            <option value="bcs" <?php echo $roleFilter === 'bcs' ? 'selected' : ''; ?>>Ban Cán Sự</option>
                            <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-2">
                        <label class="fw-bold small text-muted mb-1">TRẠNG THÁI</label>
                        <select class="form-select fw-bold text-dark" id="statusFilterSelect" name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="locked" <?php echo $statusFilter === 'locked' ? 'selected' : ''; ?>>Bị khóa</option>
                        </select>
                    </div>
                    <div class="col-xl-5 col-lg-12 text-xl-end mt-3 mt-xl-0 d-flex gap-2 justify-content-xl-end flex-wrap">
                        <a href="accounts.php" class="btn btn-outline-secondary fw-bold shadow-sm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> XÓA LỌC
                        </a>
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
                <a class="btn btn-sm btn-outline-success fw-bold" href="?export=excel&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>"><i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel</a>
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
                                    $isProtectedAdmin = strtolower((string) ($user['email'] ?? '')) === 'admin@bdu.edu.vn';
                                    $roles = array_values(array_filter([$user['role'] ?? null, $user['secondary_role'] ?? null]));
                                    $rolePriority = [
                                        'admin' => 4,
                                        'support_admin' => 3,
                                        'staff' => 3,
                                        'teacher' => 2,
                                        'bcs' => 1,
                                        'student' => 0,
                                    ];

                                    $positionRaw = trim((string)($user['position'] ?? ''));
                                    $positionParts = array_values(array_filter(array_map('trim', preg_split('/\s*(?:\||,|;|\/)\s*/', $positionRaw))));
                                    $positionPrimary = $positionParts[0] ?? '';
                                    $positionSecondary = $positionParts[1] ?? '';

                                    usort($roles, function ($a, $b) use ($rolePriority) {
                                        $priorityA = $rolePriority[$a] ?? 0;
                                        $priorityB = $rolePriority[$b] ?? 0;
                                        return $priorityB <=> $priorityA;
                                    });
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
                                                    <?php if ($positionPrimary !== ''): ?>
                                                        <div class="small text-primary">Chức vụ 1: <?php echo e($positionPrimary); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($positionSecondary !== ''): ?>
                                                        <div class="small text-primary">Chức vụ 2: <?php echo e($positionSecondary); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $roleLabels = [
                                                'admin' => ['icon' => 'bi-shield-lock-fill', 'text' => 'Admin', 'class' => 'bg-danger'],
                                                'support_admin' => ['icon' => 'bi-person-badge-fill', 'text' => 'Giáo vụ khoa', 'class' => 'bg-secondary'],
                                                'staff' => ['icon' => 'bi-person-badge-fill', 'text' => 'Giáo vụ khoa', 'class' => 'bg-secondary'],
                                                'teacher' => ['icon' => 'bi-person-video3', 'text' => 'Giảng viên', 'class' => 'bg-primary'],
                                                'bcs' => ['icon' => 'bi-star-fill', 'text' => 'Ban Cán Sự', 'class' => 'bg-warning text-dark'],
                                                'student' => ['icon' => 'bi-person', 'text' => 'Sinh viên', 'class' => 'bg-info'],
                                            ];
                                            ?>
                                            <div class="d-inline-flex flex-column align-items-center gap-1">
                                                <?php foreach ($roles as $roleName): ?>
                                                    <?php
                                                    $roleInfo = $roleLabels[$roleName] ?? ['icon' => 'bi-person', 'text' => $roleName, 'class' => 'bg-secondary'];
                                                    $badgeClass = $roleInfo['class'];
                                                    $textColor = strpos($badgeClass, 'text-dark') !== false ? 'text-dark' : 'text-white';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?> <?php echo $textColor; ?> px-2 py-1">
                                                        <i class="bi <?php echo $roleInfo['icon']; ?> me-1"></i><?php echo e($roleInfo['text']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if (empty($user['is_active'])): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1">Bị khóa</span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Đang hoạt động</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <form method="POST" action="../../controllers/admin/accountController.php" class="d-inline">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="id" value="<?php echo e($user['id']); ?>">
                                                <button type="button" class="btn btn-light action-btn text-warning border me-1" title="Khôi phục mật khẩu về 123456@"
                                                    onclick="this.closest('form').submit();">
                                                    <i class="bi bi-key-fill"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-light action-btn text-primary border me-1" title="Sửa thông tin" data-bs-toggle="modal" data-bs-target="#accountModal" 
                                                onclick="openAccountModal('edit', '<?php echo e($user['id']); ?>', '<?php echo e($user['username']); ?>', '<?php echo e($user['full_name']); ?>', '<?php echo e($user['email']); ?>', '<?php echo e($user['role']); ?>', '<?php echo e($user['secondary_role'] ?? ''); ?>', '<?php echo e($user['class_id'] ?? ''); ?>', '<?php echo e($user['academic_title'] ?? ''); ?>', '<?php echo e($user['position'] ?? ''); ?>', '<?php echo e(!empty($user['birth_date']) ? date('Y-m-d', strtotime($user['birth_date'])) : ''); ?>')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if (!$isProtectedAdmin): ?>
                                                <!-- Toggle lock button -->
                                                <form method="POST" action="../../controllers/admin/accountController.php" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_lock">
                                                    <input type="hidden" name="id" value="<?php echo e($user['id']); ?>">
                                                    <button type="button" class="btn btn-light action-btn <?php echo empty($user['is_active']) ? 'text-success' : 'text-danger'; ?> border"
                                                        title="<?php echo empty($user['is_active']) ? 'Mở khóa tài khoản' : 'Khóa tài khoản'; ?>"
                                                        onclick="this.closest('form').submit();">
                                                        <i class="bi <?php echo empty($user['is_active']) ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                                                    </button>
                                                </form>

                                            <?php else: ?>
                                                <button class="btn btn-light action-btn text-muted border" title="Tài khoản admin gốc không thể khóa" disabled>
                                                    <i class="bi bi-shield-lock-fill"></i>
                                                </button>
                                            <?php endif; ?>
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
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>">Trước</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <li class="page-item active"><a class="page-link" href="#"><?php echo $i; ?></a></li>
                            <?php else: ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>"><?php echo $i; ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>">Tiếp</a></li>
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
            <i class="bi bi-info-circle-fill me-1"></i> Mật khẩu mặc định: <strong>123456@</strong>
        </div>

                <form id="accountForm" method="POST" action="../../controllers/admin/accountController.php" onsubmit="return handleAccountSubmit(event)" novalidate>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="accountIdInput" value="">
                    <input type="hidden" name="role" id="modalRoleValue" value="">
                    <input type="hidden" name="secondary_role" id="modalSecondaryRoleValue" value="">
                    <input type="hidden" name="delete_confirm_password" id="deleteConfirmPasswordHidden" value="">
          <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Mã GV/MSSV <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-primary fw-bold" name="username" id="modalCode" placeholder="22050111 hoặc GV..." required>
                <small class="text-muted">Dùng làm tên đăng nhập.</small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Địa chỉ Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control border-secondary" name="email" id="modalEmail" placeholder="user@bdu.edu.vn" required>
              </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                <input type="text" class="form-control border-secondary" name="full_name" id="modalFullName" placeholder="Nhập đầy đủ họ tên..." required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label fw-bold">Ngày sinh</label>
                <input type="date" class="form-control border-secondary" name="birth_date" id="modalBirthDate">
            </div>
            <div class="col-md-3 mb-3" id="academicTitleGroup">
                <label class="form-label fw-bold">Học hàm / Học vị</label>
                <select class="form-select border-secondary" name="academic_title" id="modalAcademicTitle">
                    <option value="">Không có</option>
                    <option value="GS.TS">Giáo sư (GS.TS)</option>
                    <option value="PGS.TS">Phó Giáo sư (PGS.TS)</option>
                    <option value="TS">Tiến sĩ (TS.)</option>
                    <option value="ThS">Thạc sĩ (ThS.)</option>
                    <option value="CN">Cử nhân (CN.)</option>
                </select>
            </div>
            <div class="col-md-2 mb-3" id="classInputGroup" style="display: none;">
                <label class="form-label fw-bold">Lớp học <span class="text-danger">*</span></label>
                <select class="form-select border-warning fw-bold text-dark" name="class_id" id="modalClass">
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo e($class['id']); ?>"><?php echo e($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Chức vụ 1</label>
                <input type="text" class="form-control border-secondary" id="modalPositionPrimary" name="position_primary" placeholder="Ví dụ: Trưởng bộ môn">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Chức vụ 2</label>
                <input type="text" class="form-control border-secondary" id="modalPositionSecondary" name="position_secondary" placeholder="Ví dụ: Cố vấn học tập">
            </div>
          </div>
          
          <div class="mb-4 bg-light p-3 rounded border">
            <label class="form-label fw-bold d-block mb-3">Vai trò trong hệ thống <span class="text-danger">*</span></label>
            <div class="row role-checkbox-group">
                <div class="col-md-6 border-end border-secondary border-opacity-25">
                    <p class="small fw-bold text-muted mb-2">GIÁO VỤ KHOA & GIẢNG DẠY</p>
                    <div class="form-check mb-2" id="roleStaffOption">
                        <input class="form-check-input" type="checkbox" value="support_admin" id="roleStaff">
                        <label class="form-check-label fw-bold" for="roleStaff" style="color:#9333ea;">Giáo vụ khoa</label>
                    </div>
                    <div class="form-check mb-2" id="roleTeacherOption">
                        <input class="form-check-input" type="checkbox" value="teacher" id="roleTeacher">
                        <label class="form-check-label fw-bold" for="roleTeacher" style="color:#0d6efd;">Giảng viên</label>
                    </div>
                </div>
                <div class="col-md-6 ps-md-4" id="adminSection">
                    <p class="small fw-bold text-muted mb-2" id="adminSectionTitle">QUẢN TRỊ HỆ THỐNG</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="admin" id="roleSuperAdmin">
                        <label class="form-check-label fw-bold text-danger" for="roleSuperAdmin">Admin</label>
                    </div>
                </div>
                <div class="col-md-6 ps-md-4 d-none" id="studentSection">
                    <p class="small fw-bold text-muted mb-2">NGƯỜI HỌC</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="bcs" id="roleBcs">
                        <label class="form-check-label fw-bold" for="roleBcs" style="color:#d97706;">Ban Cán Sự</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="student" id="roleStudent">
                        <label class="form-check-label fw-bold" for="roleStudent" style="color:#6c757d;">Sinh viên</label>
                    </div>
                </div>
            </div>
            <div class="small text-danger mt-3" id="roleValidationMsg" style="display: none;">
                <i class="bi bi-x-circle-fill me-1"></i>Chỉ được chọn 1-2 vai trò trong cùng một khối!
            </div>
          </div>

          <div class="text-end">
                        <div class="text-start mb-3" id="deleteAccountSection" style="display: none;">
                                <label for="deleteConfirmPasswordInput" class="form-label fw-bold text-danger">Nhập mật khẩu đăng nhập để xác nhận xóa tài khoản</label>
                                <input type="password" class="form-control border-danger" id="deleteConfirmPasswordInput" placeholder="Nhập mật khẩu đăng nhập của bạn để xác nhận xóa">
                        </div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="button" class="btn btn-outline-danger fw-bold px-4" id="deleteAccountBtn" style="display: none;" onclick="handleDeleteAccount(event)">XÓA TÀI KHOẢN</button>
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
            <form method="POST" action="../../controllers/admin/importController.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_accounts">
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
            <br>- Cột Lớp học: Bỏ trống nếu là vai trò Admin/Giáo vụ khoa/Giảng viên.
            <br>- Cột Vai trò: admin, support_admin, teacher, bcs, student.
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn file (.csv, .xlsx, .xls) <span class="text-danger">*</span></label>
            <input type="file" class="form-control border-success" name="import_file" accept=".csv,.xlsx,.xls" required>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="submit" class="btn btn-success fw-bold px-4">TIẾN HÀNH IMPORT</button>
      </div>
            </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    const roleCheckboxes = document.querySelectorAll('.role-checkbox-group .form-check-input');
    const staffCheckboxes = document.querySelectorAll('#roleStaff, #roleTeacher');
    const studentCheckboxes = document.querySelectorAll('#roleBcs, #roleStudent');
    const adminCheckboxes = document.querySelectorAll('#roleSuperAdmin');
    const classInputGroup = document.getElementById('classInputGroup');
    const modalClassInput = document.getElementById('modalClass');
    const academicTitleGroup = document.getElementById('academicTitleGroup');
    const modalAcademicTitle = document.getElementById('modalAcademicTitle');
    const roleValidationMsg = document.getElementById('roleValidationMsg');
    const filterForm = document.getElementById('accountFilterForm');
    const searchFilterInput = document.getElementById('searchFilterInput');
    const roleFilterSelect = document.getElementById('roleFilterSelect');
    const statusFilterSelect = document.getElementById('statusFilterSelect');
    const deleteAccountSection = document.getElementById('deleteAccountSection');
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');
    const roleTeacherOption = document.getElementById('roleTeacherOption');
    const roleStaffOption = document.getElementById('roleStaffOption');
    const deleteConfirmPasswordInput = document.getElementById('deleteConfirmPasswordInput');
    const deleteConfirmPasswordHidden = document.getElementById('deleteConfirmPasswordHidden');
    const modalRoleValue = document.getElementById('modalRoleValue');
    const modalSecondaryRoleValue = document.getElementById('modalSecondaryRoleValue');
    const adminSection = document.getElementById('adminSection');
    const studentSection = document.getElementById('studentSection');
    const adminSectionTitle = document.getElementById('adminSectionTitle');

    let searchDebounceTimer = null;
    let isRoleLockedForAdminAccount = false;

    function submitFilterForm() {
        if (!filterForm) return;
        filterForm.submit();
    }

    if (searchFilterInput) {
        searchFilterInput.addEventListener('input', function () {
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
            searchDebounceTimer = setTimeout(submitFilterForm, 350);
        });
    }

    if (roleFilterSelect) {
        roleFilterSelect.addEventListener('change', submitFilterForm);
    }

    if (statusFilterSelect) {
        statusFilterSelect.addEventListener('change', submitFilterForm);
    }

    function getRoleGroup(role) {
        if (role === 'admin') {
            return 'admin';
        }
        if (['support_admin', 'staff', 'teacher'].includes(role)) {
            return 'staff';
        }
        if (['student', 'bcs'].includes(role)) {
            return 'student';
        }
        return '';
    }

    function updateRoleConstraints() {
        if (isRoleLockedForAdminAccount) {
            roleCheckboxes.forEach(cb => {
                cb.checked = cb.value === 'admin';
                cb.disabled = true;
            });
            if (roleTeacherOption) {
                roleTeacherOption.style.display = 'none';
            }
            if (roleStaffOption) {
                roleStaffOption.style.display = 'none';
            }
            classInputGroup.style.display = 'none';
            modalClassInput.removeAttribute('required');
            modalClassInput.value = '';
            academicTitleGroup.style.display = 'block';
            roleValidationMsg.style.display = 'none';
            return;
        }

        const selectedRoles = Array.from(roleCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        const selectedGroup = selectedRoles.length > 0 ? getRoleGroup(selectedRoles[0]) : '';
        const hasStudentRole = selectedGroup === 'student';
        const hasStaffRole = selectedGroup === 'staff';
        const hasAdminRole = selectedGroup === 'admin';

        if (selectedRoles.length > 2) {
            roleValidationMsg.style.display = 'block';
            return;
        }

        if (selectedRoles.length > 1) {
            const mixedGroups = selectedRoles.some(role => getRoleGroup(role) !== selectedGroup);
            if (mixedGroups) {
                roleValidationMsg.style.display = 'block';
                return;
            }
        }

        adminCheckboxes.forEach(cb => {
            cb.disabled = hasStaffRole || hasStudentRole;
            if ((hasStaffRole || hasStudentRole) && cb.checked) {
                cb.checked = false;
            }
        });

        staffCheckboxes.forEach(cb => {
            cb.disabled = hasStudentRole || hasAdminRole;
            if ((hasStudentRole || hasAdminRole) && cb.checked) {
                cb.checked = false;
            }
        });

        studentCheckboxes.forEach(cb => {
            cb.disabled = hasStaffRole || hasAdminRole;
            if ((hasStaffRole || hasAdminRole) && cb.checked) {
                cb.checked = false;
            }
        });

        if (hasStudentRole) {
            classInputGroup.style.display = 'block';
            modalClassInput.setAttribute('required', 'true');
        } else {
            classInputGroup.style.display = 'none';
            modalClassInput.removeAttribute('required');
            modalClassInput.value = '';
        }

        if (hasStaffRole) {
            academicTitleGroup.style.display = 'block';
        } else {
            academicTitleGroup.style.display = 'none';
            modalAcademicTitle.value = '';
        }

        roleValidationMsg.style.display = selectedRoles.length > 0 ? 'none' : 'none';
    }

    roleCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const checkedRoles = Array.from(roleCheckboxes).filter(item => item.checked).map(item => item.value);
            if (checkedRoles.length > 2) {
                this.checked = false;
                roleValidationMsg.style.display = 'block';
                return;
            }

            if (checkedRoles.length > 1) {
                const firstGroup = getRoleGroup(checkedRoles[0]);
                const mixedGroups = checkedRoles.some(role => getRoleGroup(role) !== firstGroup);
                if (mixedGroups) {
                    this.checked = false;
                    roleValidationMsg.style.display = 'block';
                    return;
                }
            }

            updateRoleConstraints();
        });
    });

    function splitPositions(positionValue) {
        const raw = String(positionValue || '').trim();
        if (!raw) return ['', ''];
        const parts = raw
            .split(/\s*(?:\||,|;|\/)\s*/)
            .map(p => p.trim())
            .filter(Boolean);
        return [parts[0] || '', parts[1] || ''];
    }

    function openAccountModal(mode, id = '', code = '', fullName = '', email = '', primaryRole = '', secondaryRole = '', classId = '', academicTitle = '', position = '', birthDate = '') {
        const title = document.getElementById('accountModalTitle');
        const inputCode = document.getElementById('modalCode');
        const inputAcademicTitle = document.getElementById('modalAcademicTitle');
        const inputBirthDate = document.getElementById('modalBirthDate');
        const inputPositionPrimary = document.getElementById('modalPositionPrimary');
        const inputPositionSecondary = document.getElementById('modalPositionSecondary');
        const inputId = document.getElementById('accountIdInput');
        const normalizeRoleAlias = function(role) {
            return role === 'staff' ? 'support_admin' : role;
        };
        primaryRole = normalizeRoleAlias(String(primaryRole || '').trim());
        secondaryRole = normalizeRoleAlias(String(secondaryRole || '').trim());
        const isProtectedAdmin = (String(email || '').toLowerCase() === 'admin@bdu.edu.vn');
        const isAdminAccount = (mode === 'edit' && primaryRole === 'admin');
        document.getElementById('accountForm').reset();
        
        roleCheckboxes.forEach(cb => { cb.checked = false; cb.disabled = false; });
        classInputGroup.style.display = 'none';
        academicTitleGroup.style.display = 'block';
        modalRoleValue.value = '';
        modalSecondaryRoleValue.value = '';
        deleteConfirmPasswordHidden.value = '';
        if (deleteConfirmPasswordInput) {
            deleteConfirmPasswordInput.value = '';
        }
        deleteAccountSection.style.display = 'none';
        deleteAccountBtn.style.display = 'none';
        isRoleLockedForAdminAccount = false;
        if (roleTeacherOption) {
            roleTeacherOption.style.display = '';
        }
        if (roleStaffOption) {
            roleStaffOption.style.display = '';
        }

        if (mode === 'add') {
            title.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Mới';
            inputCode.removeAttribute('readonly');
            inputAcademicTitle.value = '';
            if (inputBirthDate) inputBirthDate.value = '';
            if (inputPositionPrimary) inputPositionPrimary.value = '';
            if (inputPositionSecondary) inputPositionSecondary.value = '';
            inputId.value = '';
            // Tạo mới: hiển thị NGƯỜI HỌC
            if (adminSection) adminSection.classList.add('d-none');
            if (studentSection) studentSection.classList.remove('d-none');
        } else {
            title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập Nhật Thông Tin';
            inputId.value = id;
            inputCode.value = code;
            inputCode.setAttribute('readonly', 'true'); 
            document.getElementById('modalFullName').value = fullName;
            document.getElementById('modalEmail').value = email;
            modalClassInput.value = classId;
            inputAcademicTitle.value = academicTitle;
            if (inputBirthDate) inputBirthDate.value = birthDate || '';
            const [positionPrimary, positionSecondary] = splitPositions(position);
            if (inputPositionPrimary) inputPositionPrimary.value = positionPrimary;
            if (inputPositionSecondary) inputPositionSecondary.value = positionSecondary;

            if (primaryRole) {
                const primaryCheckbox = document.querySelector('.role-checkbox-group .form-check-input[value="' + primaryRole + '"]');
                if (primaryCheckbox) primaryCheckbox.checked = true;
            }
            if (secondaryRole) {
                const secondaryCheckbox = document.querySelector('.role-checkbox-group .form-check-input[value="' + secondaryRole + '"]');
                if (secondaryCheckbox) secondaryCheckbox.checked = true;
            }

            if (isAdminAccount) {
                isRoleLockedForAdminAccount = true;
                if (roleTeacherOption) {
                    roleTeacherOption.style.display = 'none';
                }
                if (roleStaffOption) {
                    roleStaffOption.style.display = 'none';
                }
                // Admin: hiển thị QUẢN TRỊ HỆ THỐNG
                if (adminSection) adminSection.classList.remove('d-none');
                if (studentSection) studentSection.classList.add('d-none');
                roleCheckboxes.forEach(cb => {
                    cb.checked = cb.value === 'admin';
                    cb.disabled = true;
                });
                roleValidationMsg.style.display = 'none';
            } else {
                // Tài khoản không phải Admin: hiển thị NGƯỜI HỌC
                if (adminSection) adminSection.classList.add('d-none');
                if (studentSection) studentSection.classList.remove('d-none');
            }

            if (isProtectedAdmin) {
                roleCheckboxes.forEach(cb => {
                    cb.checked = cb.value === 'admin';
                    cb.disabled = true;
                });
            }

            if (!isProtectedAdmin) {
                deleteAccountSection.style.display = 'block';
                deleteAccountBtn.style.display = 'inline-block';
            }
            updateRoleConstraints();
        }
    }

    function handleAccountSubmit(event) {
        try {
            // Manual validation thay cho HTML5
            const username = (document.getElementById('modalCode')?.value || '').trim();
            const email = (document.getElementById('modalEmail')?.value || '').trim();
            const fullName = (document.getElementById('modalFullName')?.value || '').trim();

            if (!username || !email || !fullName) {
                alert('Vui lòng nhập đầy đủ: Mã GV/MSSV, Email và Họ tên.');
                return false;
            }

            const checkedRoles = Array.from(roleCheckboxes).filter(cb => cb.checked && !cb.disabled).map(cb => cb.value);
            if (checkedRoles.length < 1 || checkedRoles.length > 2) {
                document.getElementById('roleValidationMsg').style.display = 'block';
                alert('Vui lòng chọn ít nhất 1 vai trò hợp lệ.');
                return false;
            }

            if (checkedRoles.length === 2 && getRoleGroup(checkedRoles[0]) !== getRoleGroup(checkedRoles[1])) {
                document.getElementById('roleValidationMsg').style.display = 'block';
                return false;
            }

            // Kiểm tra class cho sinh viên/BCS
            const needsClass = checkedRoles.some(r => r === 'student' || r === 'bcs');
            if (needsClass) {
                const classVal = document.getElementById('modalClass')?.value || '';
                if (!classVal) {
                    alert('Vai trò Sinh viên / Ban Cán Sự cần chọn lớp học.');
                    return false;
                }
            }

            document.querySelector('#accountForm input[name="action"]').value = 'save';
            modalRoleValue.value = checkedRoles[0] || '';
            modalSecondaryRoleValue.value = checkedRoles[1] || '';
            deleteConfirmPasswordHidden.value = '';
            // Dùng event.preventDefault() + submit thủ công để tránh Chrome block confirm() trong modal context
            event.preventDefault();
            document.getElementById('accountForm').submit();
            return false;
        } catch (err) {
            console.error('handleAccountSubmit error:', err);
            alert('Lỗi JavaScript: ' + err.message);
            return false;
        }
    }

    function handleDeleteAccount(event) {
        event.preventDefault();
        const accountId = document.getElementById('accountIdInput').value;
        if (!accountId) {
            alert('Không tìm thấy tài khoản cần xóa.');
            return;
        }

        const confirmPassword = (deleteConfirmPasswordInput?.value || '').trim();
        if (!confirmPassword) {
            alert('Vui lòng nhập mật khẩu đăng nhập để xác nhận xóa tài khoản.');
            return;
        }

        if (!confirm('Bạn có chắc chắn muốn xóa tài khoản này? Hành động này không thể hoàn tác.')) {
            return;
        }

        document.querySelector('#accountForm input[name="action"]').value = 'delete_user';
        deleteConfirmPasswordHidden.value = confirmPassword;
        document.getElementById('accountForm').submit();
    }

</script>
</body>
</html>
