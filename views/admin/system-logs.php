<?php
/**
 * CMS BDU - Nhật Ký Hệ Thống
 * Trang xem nhật ký hệ thống cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bảo vệ trang - chỉ admin được phép truy cập
requireRole('admin');

// Lấy thông tin admin hiện tại
$currentUser = getCurrentUser();

// Xử lý filter
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? 'all';
$actionFilter = $_GET['action'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';
$exportType = $_GET['export'] ?? '';

// Build query cho logs
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(sl.action LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== 'all') {
    $whereConditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

if ($actionFilter !== 'all') {
    if ($actionFilter === 'login') {
        $whereConditions[] = "(sl.action LIKE '%login%' OR sl.action LIKE '%đăng nhập%' OR sl.action LIKE '%logout%' OR sl.action LIKE '%đăng xuất%')";
    } elseif ($actionFilter === 'create') {
        $whereConditions[] = "(sl.action LIKE '%create%' OR sl.action LIKE '%thêm%' OR sl.action LIKE '%import%')";
    } elseif ($actionFilter === 'update') {
        $whereConditions[] = "(sl.action LIKE '%update%' OR sl.action LIKE '%sửa%' OR sl.action LIKE '%edit%')";
    } elseif ($actionFilter === 'delete') {
        $whereConditions[] = "(sl.action LIKE '%delete%' OR sl.action LIKE '%xóa%')";
    } elseif ($actionFilter === 'warning') {
        $whereConditions[] = "(sl.action LIKE '%warning%' OR sl.action LIKE '%cảnh báo%' OR sl.action LIKE '%failed%')";
    }
}

if ($dateFilter) {
    $whereConditions[] = "DATE(sl.created_at) = ?";
    $params[] = $dateFilter;
}

$whereClause = '';
if (count($whereConditions) > 0) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

if ($exportType === 'csv') {
    $exportSql = "SELECT sl.created_at, sl.action, sl.target_table, sl.target_id, u.username, u.full_name, u.role, u.email
                  FROM system_logs sl
                  LEFT JOIN users u ON sl.user_id = u.id
                  $whereClause
                  ORDER BY sl.created_at DESC";
    $exportRows = db_fetch_all($exportSql, $params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="system-logs-' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['created_at', 'username', 'full_name', 'role', 'email', 'action', 'target_table', 'target_id']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['created_at'] ?? '',
            $row['username'] ?? '',
            $row['full_name'] ?? '',
            $row['role'] ?? '',
            $row['email'] ?? '',
            $row['action'] ?? '',
            $row['target_table'] ?? '',
            $row['target_id'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Đếm tổng số bản ghi
$countSql = "SELECT COUNT(*) as total FROM system_logs sl 
             LEFT JOIN users u ON sl.user_id = u.id 
             $whereClause";
$totalRecords = (int) db_count($countSql, $params);

// Pagination
$perPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $perPage;
$totalPages = ceil($totalRecords / $perPage);

// Lấy danh sách logs
$sql = "SELECT sl.*, u.full_name, u.username, u.role, u.email 
        FROM system_logs sl
        LEFT JOIN users u ON sl.user_id = u.id
        $whereClause
        ORDER BY sl.created_at DESC
        LIMIT $perPage OFFSET $offset";
$logs = db_fetch_all($sql, $params);

// Hàm phân loại action type
function getActionType($action) {
    $action = strtolower($action);
    if (strpos($action, 'delete') !== false || strpos($action, 'xóa') !== false) {
        return ['type' => 'delete', 'label' => 'XÓA (DELETE)', 'class' => 'bg-danger'];
    }
    if (strpos($action, 'create') !== false || strpos($action, 'thêm') !== false || strpos($action, 'import') !== false) {
        return ['type' => 'create', 'label' => 'THÊM (CREATE)', 'class' => 'bg-success'];
    }
    if (strpos($action, 'update') !== false || strpos($action, 'sửa') !== false || strpos($action, 'edit') !== false) {
        return ['type' => 'update', 'label' => 'SỬA (UPDATE)', 'class' => 'bg-primary'];
    }
    if (strpos($action, 'login') !== false || strpos($action, 'đăng nhập') !== false || strpos($action, 'logout') !== false) {
        return ['type' => 'login', 'label' => 'ĐĂNG NHẬP', 'class' => 'bg-info text-dark'];
    }
    if (strpos($action, 'warning') !== false || strpos($action, 'cảnh báo') !== false || strpos($action, 'failed') !== false) {
        return ['type' => 'warning', 'label' => 'CẢNH BÁO', 'class' => 'bg-warning text-dark'];
    }
    return ['type' => 'other', 'label' => 'KHÁC', 'class' => 'bg-secondary'];
}

// Hàm lấy icon và màu role
function getRoleDisplay($role, $fullName) {
    if (!$role || !$fullName) {
        return [
            'icon' => 'bi-question-circle',
            'icon_class' => 'text-warning',
            'badge_class' => 'bg-warning text-dark',
            'label' => 'Unknown / Guest'
        ];
    }
    
    $roleConfig = [
        'admin' => ['icon' => 'bi-shield-fill', 'icon_class' => 'text-danger', 'badge_class' => 'bg-danger text-white', 'label' => 'Quản trị viên'],
        'teacher' => ['icon' => 'bi-person-video3', 'icon_class' => 'text-primary', 'badge_class' => 'bg-primary text-white', 'label' => 'Giảng viên'],
        'bcs' => ['icon' => 'bi-star-fill', 'icon_class' => 'text-warning', 'badge_class' => 'bg-warning text-dark', 'label' => 'Ban Cán Sự'],
        'student' => ['icon' => 'bi-person', 'icon_class' => 'text-info', 'badge_class' => 'bg-info text-white', 'label' => 'Sinh viên'],
    ];
    
    return $roleConfig[$role] ?? ['icon' => 'bi-person', 'icon_class' => 'text-secondary', 'badge_class' => 'bg-secondary text-white', 'label' => $role];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Nhật Ký Hệ Thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/system-logs.css">
</head>
<body class="dashboard-body">

<?php
$activePage = 'system-logs';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = 'NHẬT KÝ HỆ THỐNG (SYSTEM LOGS)';
$pageIcon   = 'bi-shield-lock-fill';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="fw-bold small text-muted mb-1">TỪ KHÓA / TÀI KHOẢN</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Nhập nội dung cần tìm..." value="<?php echo e($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="fw-bold small text-muted mb-1">VAI TRÒ</label>
                        <select name="role" class="form-select fw-bold">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                            <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Giảng viên</option>
                            <option value="bcs" <?php echo $roleFilter === 'bcs' ? 'selected' : ''; ?>>Ban Cán Sự</option>
                            <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="fw-bold small text-muted mb-1">LOẠI HÀNH ĐỘNG</label>
                        <select name="action" class="form-select fw-bold">
                            <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="login" <?php echo $actionFilter === 'login' ? 'selected' : ''; ?>>Đăng nhập / Đăng xuất</option>
                            <option value="create" <?php echo $actionFilter === 'create' ? 'selected' : ''; ?>>Thêm mới (Create)</option>
                            <option value="update" <?php echo $actionFilter === 'update' ? 'selected' : ''; ?>>Chỉnh sửa (Update)</option>
                            <option value="delete" <?php echo $actionFilter === 'delete' ? 'selected' : ''; ?>>Xóa (Delete)</option>
                            <option value="warning" <?php echo $actionFilter === 'warning' ? 'selected' : ''; ?>>Cảnh báo bảo mật</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold small text-muted mb-1">THỜI GIAN</label>
                        <input type="date" name="date" class="form-control fw-bold" value="<?php echo e($dateFilter); ?>">
                    </div>
                    <div class="col-md-2 text-md-end">
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm w-100">
                            <i class="bi bi-search me-1"></i> TÌM KIẾM
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark m-0">
                    <i class="bi bi-list-columns-reverse me-2 text-primary"></i>Chi tiết Nhật ký hệ thống
                    <span class="badge bg-secondary ms-2"><?php echo number_format($totalRecords); ?> bản ghi</span>
                </h5>
                <a class="btn btn-outline-danger fw-bold shadow-sm" href="?export=csv&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>">
                    <i class="bi bi-download me-1"></i> XUẤT LOG
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted fw-bold">
                            <tr>
                                <th class="ps-4 py-3" style="width: 180px;">THỜI GIAN</th>
                                <th class="py-3" style="width: 220px;">NGƯỜI THỰC HIỆN</th>
                                <th class="py-3" style="width: 150px;">LOẠI HÀNH ĐỘNG</th>
                                <th class="py-3">CHI TIẾT TÁC VỤ</th>
                                <th class="pe-4 py-3 text-end" style="width: 120px;">BẢNG / ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php 
                                    $actionType = getActionType($log['action']);
                                    $roleDisplay = getRoleDisplay($log['role'], $log['full_name']);
                                    $isWarning = $actionType['type'] === 'warning';
                                    ?>
                                    <tr class="<?php echo $isWarning ? 'bg-warning bg-opacity-10' : ''; ?>">
                                        <td class="ps-4 text-dark log-time <?php echo $isWarning ? 'fw-bold text-danger' : ''; ?>">
                                            <?php echo formatDateTime($log['created_at']); ?>
                                        </td>
                                        <td>
                                            <?php if ($log['full_name']): ?>
                                                <div class="fw-bold text-dark"><?php echo e($log['full_name']); ?></div>
                                                <div class="small text-muted">
                                                    <i class="bi <?php echo $roleDisplay['icon']; ?> <?php echo $roleDisplay['icon_class']; ?> me-1"></i>
                                                    <?php echo e($log['username']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="fw-bold text-dark">Unknown / Guest</div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-question-circle text-warning me-1"></i>Unauthenticated
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $actionType['class']; ?>"><?php echo $actionType['label']; ?></span>
                                        </td>
                                        <td class="text-dark"><?php echo e($log['action']); ?></td>
                                        <td class="pe-4 text-end text-muted small">
                                            <?php if ($log['target_table']): ?>
                                                <span class="badge bg-light text-dark border"><?php echo e($log['target_table']); ?></span>
                                                <?php if ($log['target_id']): ?>
                                                    <br>ID: <?php echo e($log['target_id']); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                        Không tìm thấy nhật ký nào.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <span class="small text-muted">
                        Hiển thị <?php echo min($offset + 1, $totalRecords); ?> đến <?php echo min($offset + $perPage, $totalRecords); ?> của <?php echo number_format($totalRecords); ?> bản ghi
                    </span>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>">Trước</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>">1</a></li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><a class="page-link" href="#">...</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <li class="page-item active"><a class="page-link" href="#"><?php echo $i; ?></a></li>
                            <?php else: ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>"><?php echo $i; ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><a class="page-link" href="#">...</a></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>"><?php echo $totalPages; ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo e($roleFilter); ?>&action=<?php echo e($actionFilter); ?>&date=<?php echo e($dateFilter); ?>">Sau</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><a class="page-link" href="#">Sau</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
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
</script>
</body>
</html>
