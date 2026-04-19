<?php
/**
 * CMS BDU - Kho Tài Liệu Lớp
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = $_SESSION['user_id'];
$pageTitle = 'Kho Tài Liệu';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/documents.css'];
$extraJs = ['student/student-layout.js', 'student/documents.js'];

// Lấy học kỳ hiện tại
$stmt = $pdo->prepare("SELECT id, semester_name, academic_year FROM semesters ORDER BY id DESC LIMIT 1");
$stmt->execute();
$currentSemester = $stmt->fetch();

// Lấy danh sách tài liệu của sinh viên
$stmt = $pdo->prepare("
    SELECT d.*, s.subject_name, s.subject_code, uploader.full_name as uploader_name,
           CASE 
               WHEN uploader.role = 'teacher' THEN 'Giảng Viên'
               WHEN uploader.role = 'bcs' THEN 'BCS'
               ELSE 'Sinh viên'
           END as uploader_type
    FROM documents d
    JOIN class_subjects cs ON d.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users uploader ON d.uploader_id = uploader.id
    WHERE cs.id IN (
        SELECT DISTINCT csg.class_subject_id 
        FROM student_subject_registration ssr
        JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
        WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
    )
    ORDER BY d.created_at DESC
");
$stmt->execute([$userId]);
$documents = $stmt->fetchAll();

// Lấy số thông báo chưa đọc
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotifications = $stmt->fetch()['total'];

// Phân trang
$perPage = 10;
$totalDocs = count($documents);
$totalPages = ceil($totalDocs / $perPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;
$documentsPage = array_slice($documents, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="../../public/css/<?= e($css) ?>">
    <?php endforeach; ?>
</head>
<body class="dashboard-body">

<?php include_once __DIR__ . '/../../layouts/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 shadow-sm">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light me-3 border-0" id="sidebarToggle"><i class="bi bi-list fs-3"></i></button>
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">
                KHO TÀI LIỆU LỚP
            </h5>
        </div>
        
        <div class="d-flex align-items-center text-white">
            <a href="notifications-all.php" class="text-white text-decoration-none" title="Xem thông báo">
                <i class="bi bi-bell fs-5 text-white position-relative cursor-pointer">
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </i>
            </a>
        </div>
    </div>

    <div class="p-4">
        
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-info-circle-fill fs-4 text-info me-3"></i>
            <div>Đây là kho lưu trữ tài liệu chung. Bạn có thể xem trực tiếp hoặc tải về máy tính.</div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ul me-2 text-secondary"></i>Danh sách Tài liệu</h5>
                
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select class="form-select form-select-sm w-auto fw-bold text-primary border-primary" disabled>
                        <option selected><?= e($currentSemester['semester_name'] ?? 'HK2') ?> (<?= e($currentSemester['academic_year'] ?? '25-26') ?>)</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" id="categoryFilter" onchange="filterDocuments()">
                        <option value="">Tất cả danh mục</option>
                        <option value="Thông báo">Thông báo</option>
                        <option value="Biên bản">Biên bản</option>
                        <option value="Học liệu">Học liệu</option>
                        <option value="Tài liệu khác">Tài liệu khác</option>
                    </select>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Tìm tên tài liệu, môn học..." onkeyup="filterDocuments()">
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle custom-detail-table mb-0" id="documentsTable">
                        <thead class="text-muted small fw-bold table-light">
                            <tr>
                                <th class="ps-4 py-3">TÊN TÀI LIỆU</th>
                                <th class="py-3">DANH MỤC</th>
                                <th class="py-3">NGƯỜI ĐĂNG</th>
                                <th class="py-3 text-center">NGÀY ĐĂNG</th>
                                <th class="py-3 text-center">KÍCH THƯỚC</th>
                                <th class="pe-4 py-3 text-end">HÀNH ĐỘNG</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($documentsPage)): ?>
                                <?php foreach ($documentsPage as $doc): ?>
                                    <?php
                                    $fileIcon = 'bi-file-earmark-fill';
                                    $iconColor = 'text-secondary';
                                    if ($doc['drive_link']) {
                                        if (preg_match('/\.pdf$/i', $doc['drive_link'])) {
                                            $fileIcon = 'bi-file-earmark-pdf-fill';
                                            $iconColor = 'text-danger';
                                        } elseif (preg_match('/\.(doc|docx)$/i', $doc['drive_link'])) {
                                            $fileIcon = 'bi-file-earmark-word-fill';
                                            $iconColor = 'text-primary';
                                        } elseif (preg_match('/\.(xls|xlsx)$/i', $doc['drive_link'])) {
                                            $fileIcon = 'bi-file-earmark-excel-fill';
                                            $iconColor = 'text-success';
                                        } elseif (preg_match('/\.(zip|rar|7z)$/i', $doc['drive_link'])) {
                                            $fileIcon = 'bi-file-earmark-zip-fill';
                                            $iconColor = 'text-warning';
                                        }
                                    }
                                    
                                    $categoryClass = 'bg-secondary';
                                    $categoryTextClass = 'text-secondary';
                                    if ($doc['category'] === 'Thông báo') {
                                        $categoryClass = 'bg-danger bg-opacity-10';
                                        $categoryTextClass = 'text-danger border-danger border-opacity-25';
                                    } elseif ($doc['category'] === 'Học liệu') {
                                        $categoryClass = 'bg-primary bg-opacity-10';
                                        $categoryTextClass = 'text-primary border-primary border-opacity-25';
                                    }
                                    ?>
                                    <tr data-category="<?= e($doc['category'] ?? '') ?>" data-title="<?= e(strtolower($doc['title'] . ' ' . ($doc['subject_name'] ?? ''))) ?>">
                                        <td class="ps-4 py-3">
                                            <?php if ($doc['drive_link']): ?>
                                                <a href="<?= e($doc['drive_link']) ?>" target="_blank" class="file-link d-flex align-items-center">
                                                    <i class="bi <?= $fileIcon ?> fs-3 <?= $iconColor ?> me-3"></i>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark file-title"><?= e($doc['title']) ?></h6>
                                                        <small class="text-muted"><?= e($doc['subject_name'] ? 'Môn: ' . $doc['subject_name'] : ($doc['note'] ?? '')) ?></small>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi <?= $fileIcon ?> fs-3 <?= $iconColor ?> me-3"></i>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark file-title"><?= e($doc['title']) ?></h6>
                                                        <small class="text-muted"><?= e($doc['note'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $categoryClass ?> <?= $categoryTextClass ?> border px-2 py-1" style="background-color: rgba(var(--bs-<?= str_replace('bg-', '', $categoryClass) ?>-rgb), 0.1);">
                                                <?= e($doc['category'] ?? 'Khác') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($doc['uploader_type'] === 'Giảng Viên'): ?>
                                                <span class="badge bg-primary text-white"><i class="bi bi-person-video3 me-1"></i><?= e($doc['uploader_name'] ?? 'GV') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i><?= e($doc['uploader_name'] ?? 'SV') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center text-dark"><?= formatDate($doc['created_at'], 'd/m/Y') ?></td>
                                        <td class="text-center text-muted small">--</td>
                                        <td class="pe-4 text-end">
                                            <?php if ($doc['drive_link']): ?>
                                                <a href="<?= e($doc['drive_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill shadow-sm">
                                                    <i class="bi bi-download me-1"></i> Mở file
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">Không có file</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-folder2-open fs-1 d-block mb-2"></i>
                                        Chưa có tài liệu nào được chia sẻ.
                                    </td>
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
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $currentPage - 1 ?>">Trước</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Trước</span></li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <li class="page-item active"><span class="page-link"><?= $i ?></span></li>
                            <?php else: ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $currentPage + 1 ?>">Tiếp</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Tiếp</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<?php foreach ($extraJs as $js): ?>
    <script src="../../public/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
