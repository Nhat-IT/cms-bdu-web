<?php
/**
 * CMS BDU - Kho Lưu Trữ Lớp (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Kho Lưu Trữ Lớp';
$dbError = '';
$classWarning = '';
$classId = null;
$className = '';
$fullName = $_SESSION['full_name'] ?? 'BCS';
$position = $_SESSION['position'] ?? 'Ban Cán Sự';
$avatar = getAvatarUrl($_SESSION['avatar'] ?? null, $fullName, 55);
$unreadCount = 0;
$semesters = [];
$selectedSemesterId = 0;
$selectedSemesterName = '';
$documents = [];
$currentSemester = null;

function bcsFormatFileSize($bytes) {
    $size = (int)$bytes;
    if ($size < 1024) return $size . ' B';
    if ($size < 1024 * 1024) return round($size / 1024, 1) . ' KB';
    if ($size < 1024 * 1024 * 1024) return round($size / (1024 * 1024), 1) . ' MB';
    return round($size / (1024 * 1024 * 1024), 1) . ' GB';
}

function bcsDocumentSizeLabel($driveLink) {
    $link = trim((string)$driveLink);
    if ($link === '') return '-';

    $path = (string)(parse_url($link, PHP_URL_PATH) ?? '');
    if ($path === '') return '-';

    $basePath = (string)(parse_url(BASE_URL, PHP_URL_PATH) ?? '/cms');
    $prefix = rtrim($basePath, '/') . '/public/uploads/documents/';
    if (strpos($path, $prefix) !== 0) return '-';

    $fileName = basename(urldecode(substr($path, strlen($prefix))));
    if ($fileName === '') return '-';

    $fullPath = BASE_PATH . '/public/uploads/documents/' . $fileName;
    if (!is_file($fullPath)) return '-';

    $bytes = @filesize($fullPath);
    if ($bytes === false) return '-';
    return bcsFormatFileSize($bytes);
}

try {
    getDBConnection();

    // Lấy class_id của BCS từ class_students
    $classInfo = db_fetch_one(
        "SELECT cs.class_id, c.class_name
         FROM class_students cs
         JOIN classes c ON cs.class_id = c.id
         WHERE cs.student_id = ?",
        [$userId]
    );
    $classId = (int)($classInfo['class_id'] ?? 0);
    $className = $classInfo['class_name'] ?? '';

    // Lấy thông tin user
    $currentUser = db_fetch_one("SELECT * FROM users WHERE id = ?", [$userId]);
    $fullName = $currentUser['full_name'] ?? '';
    $position = $currentUser['position'] ?? 'Ban Cán Sự';
    $avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 55);

    // Lấy số thông báo
    $unreadCount = (int)(db_fetch_one(
        "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['total'] ?? 0);

    // Lấy danh sách học kỳ và năm học
    $semesters = db_fetch_all(
        "SELECT id, semester_name, academic_year, start_date, end_date
         FROM semesters
         ORDER BY academic_year DESC,
                  FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
                  start_date DESC"
    );
    // Học kỳ mặc định theo ngày hiện tại
    foreach ($semesters as $sem) {
        if (!empty($sem['start_date']) && !empty($sem['end_date']) && date('Y-m-d') >= $sem['start_date'] && date('Y-m-d') <= $sem['end_date']) {
            $currentSemester = $sem;
            break;
        }
    }
    if (!$currentSemester && !empty($semesters)) $currentSemester = $semesters[0];

    $selectedSemesterId = (int)($_GET['semester_id'] ?? 0);
    if ($selectedSemesterId <= 0) {
        $selectedSemesterId = (int)($currentSemester['id'] ?? 0);
    }
    foreach ($semesters as $sem) {
        if ((int)($sem['id'] ?? 0) === $selectedSemesterId) {
            $selectedSemesterName = strtoupper(trim((string)($sem['semester_name'] ?? '')));
            break;
        }
    }

    // Lấy tài liệu theo lớp + bộ lọc học kỳ
    if ($classId > 0) {
        $documents = db_fetch_all(
            "SELECT d.*, s.subject_name, u.full_name as uploader_name, sm.semester_name, sm.academic_year
             FROM documents d
             JOIN class_subjects cs ON d.class_subject_id = cs.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON d.uploader_id = u.id
             LEFT JOIN semesters sm ON cs.semester_id = sm.id
             WHERE cs.class_id = ?
               AND (? <= 0 OR cs.semester_id = ? OR UPPER(COALESCE(d.semester, '')) = ?)
             ORDER BY d.created_at DESC",
            [$classId, $selectedSemesterId, $selectedSemesterId, $selectedSemesterName]
        );
    } else {
        $classWarning = 'Tài khoản BCS chưa được gán lớp trong hệ thống.';
    }
} catch (Exception $e) {
    error_log('BCS documents load error: ' . $e->getMessage());
    $dbError = '';
    $fullName = $_SESSION['full_name'] ?? 'BCS';
    $position = $_SESSION['position'] ?? 'Ban Cán Sự';
    $avatar = getAvatarUrl($_SESSION['avatar'] ?? null, $fullName, 55);
}

// Đếm theo danh mục
$stats = [
    'announcements' => 0,
    'minutes' => 0,
    'materials' => 0,
    'reports' => 0
];
foreach ($documents as $doc) {
    $cat = strtolower($doc['category'] ?? '');
    if (strpos($cat, 'thông báo') !== false) $stats['announcements']++;
    elseif (strpos($cat, 'biên bản') !== false) $stats['minutes']++;
    elseif (strpos($cat, 'học liệu') !== false) $stats['materials']++;
    else $stats['reports']++;
}
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
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/bcs/bcs-layout.css">
    <link rel="stylesheet" href="../../public/css/bcs/documents.css">
</head>
<body class="dashboard-body">

<div class="sidebar" id="sidebar">
    <div>
        <div class="brand-container flex-shrink-0">
            <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                <span class="fs-4 fw-bold hide-on-collapse">CMS BDU</span>
            </a>
        </div>
        <div class="bcs-profile-container text-center flex-shrink-0">
            <a href="profile.php" class="bcs-profile-trigger" title="Xem hồ sơ ban cán sự">
                <img src="<?= e($avatar) ?>" class="rounded-circle shadow-sm mb-2 border border-2 border-primary" width="55" alt="Avatar BCS">
                <div class="hide-on-collapse">
                    <div class="text-white fw-bold fs-6"><?= e($fullName) ?></div>
                    <div class="text-white-50 small mb-1" style="font-size: 0.8rem;">Vai trò: <?= e($position) ?></div>
                </div>
            </a>
            <span class="badge bcs-class-badge mt-1 hide-on-collapse">LỚP: <?= e($className) ?></span>
        </div>
        <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <a href="home.php"><i class="bi bi-grid-1x2-fill"></i> Bảng điều khiển</a>
            <a href="attendance.php"><i class="bi bi-calendar-check"></i> Quản lý Chuyên cần</a>
            <a href="documents.php" class="active"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>
            
            <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CÁ NHÂN</div>
            <a href="../student/home.php" class="text-warning"><i class="bi bi-arrow-repeat"></i> Về Cổng Sinh Viên</a>
        </nav>
        </div>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../../views/logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<div class="main-content" id="mainContent" style="padding: 0; background-color: #f4f6f9; min-height: 100vh;">
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">KHO LƯU TRỮ LỚP <span class="text-warning ms-2"><?= e($className) ?></span></h4>
        </div>
        
        <div class="bcs-header-meta d-flex align-items-center text-white">
            <span class="bcs-header-label fw-bold">BAN CÁN SỰ</span>
            <a href="feedback.php" class="bcs-notification-link" title="Có <?= $unreadCount ?> thông báo hệ thống">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="bcs-notification-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="p-4">
        <?php if ($dbError !== ''): ?>
        <div class="alert alert-danger mb-4" role="alert"><?= e($dbError) ?></div>
        <?php endif; ?>
        <?php if ($classWarning !== ''): ?>
        <div class="alert alert-warning mb-4" role="alert"><?= e($classWarning) ?></div>
        <?php endif; ?>
    
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card-custom border-start border-danger border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger rounded p-2"><i class="bi bi-megaphone-fill fs-4"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.8rem;">THÔNG BÁO</p>
                            <h4 class="mb-0 fw-bold text-dark"><?= $stats['announcements'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-start border-warning border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-warning me-3 text-warning rounded p-2"><i class="bi bi-journal-text fs-4"></i></div>
                        <div>
                            <p class="text-warning fw-bold mb-1" style="font-size: 0.8rem;">BIÊN BẢN LỚP</p>
                            <h4 class="mb-0 fw-bold text-dark"><?= $stats['minutes'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-start border-primary border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary rounded p-2"><i class="bi bi-book-half fs-4"></i></div>
                        <div>
                            <p class="text-primary fw-bold mb-1" style="font-size: 0.8rem;">HỌC LIỆU</p>
                            <h4 class="mb-0 fw-bold text-dark"><?= $stats['materials'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-custom border-start border-success border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success rounded p-2"><i class="bi bi-file-earmark-excel-fill fs-4"></i></div>
                        <div>
                            <p class="text-success fw-bold mb-1" style="font-size: 0.8rem;">BÁO CÁO</p>
                            <h4 class="mb-0 fw-bold text-dark"><?= $stats['reports'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <h5 class="fw-bold text-dark m-0 d-flex align-items-center">
                <i class="bi bi-hdd-network-fill me-2 text-primary"></i> Quản lý Tài liệu Lớp
            </h5>
            <button class="btn btn-primary fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal" onclick="openDocModal('add')">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i> Tải Tài Liệu Lên
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ul me-2 text-secondary"></i>Danh sách Tài liệu</h5>
                
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto fw-bold text-primary border-primary" id="filterSemester">
                        <?php foreach ($semesters as $sem): ?>
                        <?php
                            $code = strtoupper((string)($sem['semester_name'] ?? ''));
                            $label = preg_match('/^(HK)?([123])$/', $code, $m)
                                ? ('Học kỳ ' . $m[2] . ' - ' . ($sem['academic_year'] ?? ''))
                                : (($sem['semester_name'] ?? '') . ' - ' . ($sem['academic_year'] ?? ''));
                        ?>
                        <option value="<?= (int)($sem['id'] ?? 0) ?>" <?= ((int)($sem['id'] ?? 0) === $selectedSemesterId) ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm w-auto" id="filterCategory">
                        <option value="">Tất cả danh mục</option>
                        <option value="Thông báo">Thông báo</option>
                        <option value="Biên bản">Biên bản</option>
                        <option value="Học liệu">Học liệu</option>
                    </select>
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Tìm tên tài liệu...">
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
                                <th class="py-3">NGÀY ĐĂNG</th>
                                <th class="py-3 text-center">KÍCH THƯỚC</th>
                                <th class="pe-4 py-3 text-end">HÀNH ĐỘNG</th>
                            </tr>
                        </thead>
                        <tbody id="bcsDocumentsTableBody">
                            <?php if (!empty($documents)): ?>
                            <?php foreach ($documents as $doc): ?>
                            <?php 
                            $iconClass = 'bi-file-earmark-fill text-secondary';
                            $iconColor = 'text-secondary';
                            if ($doc['icon_type'] == 'pdf') { $iconClass = 'bi-file-earmark-pdf-fill text-danger'; $iconColor = 'text-danger'; }
                            elseif ($doc['icon_type'] == 'doc') { $iconClass = 'bi-file-earmark-word-fill text-primary'; $iconColor = 'text-primary'; }
                            elseif ($doc['icon_type'] == 'xls') { $iconClass = 'bi-file-earmark-excel-fill text-success'; $iconColor = 'text-success'; }
                            elseif ($doc['icon_type'] == 'zip') { $iconClass = 'bi-file-earmark-zip-fill text-warning'; $iconColor = 'text-warning'; }
                            ?>
                            <tr data-title="<?= e(strtolower($doc['title'] ?? '')) ?>" data-note="<?= e(strtolower($doc['note'] ?? '')) ?>" data-category="<?= e(strtolower($doc['category'] ?? '')) ?>">
                                <td class="ps-4 py-3">
                                    <a href="#" class="file-link d-flex align-items-center" onclick="handleFileView(event, '<?= e($doc['drive_link'] ?? '') ?>')">
                                        <i class="bi <?= $iconClass ?> fs-3 me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-dark file-title"><?= e($doc['title']) ?></h6>
                                            <small class="text-muted"><?= e($doc['note'] ?? '') ?></small>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $badgeClass = 'bg-secondary';
                                    if (strpos(strtolower($doc['category'] ?? ''), 'thông báo') !== false) $badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger';
                                    elseif (strpos(strtolower($doc['category'] ?? ''), 'biên bản') !== false) $badgeClass = 'bg-warning bg-opacity-10 text-dark border border-warning';
                                    elseif (strpos(strtolower($doc['category'] ?? ''), 'học liệu') !== false) $badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary';
                                    ?>
                                    <span class="badge <?= $badgeClass ?> px-2 py-1"><?= e($doc['category'] ?? 'Khác') ?></span>
                                </td>
                                <td><?= e($doc['uploader_name'] ?? 'Không xác định') ?></td>
                                <td><?= formatDate($doc['created_at']) ?></td>
                                <td class="text-center text-muted small"><?= e(bcsDocumentSizeLabel($doc['drive_link'] ?? '')) ?></td>
                                <td class="pe-4 text-end">
                                    <?php if ($doc['uploader_id'] == $userId): ?>
                                    <button class="btn btn-sm btn-light text-success border me-1" title="Sửa" data-bs-toggle="modal" data-bs-target="#uploadModal" onclick="openDocModal('edit', <?= htmlspecialchars(json_encode($doc)) ?>)"><i class="bi bi-pencil-square"></i></button>
                                    <button class="btn btn-sm btn-light text-danger border" title="Xóa" onclick="deleteDocument(<?= $doc['id'] ?>)"><i class="bi bi-trash"></i></button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-light border" title="Tải xuống" onclick="downloadDocument('<?= e($doc['drive_link'] ?? '') ?>')"><i class="bi bi-download"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-folder2-open fs-1"></i>
                                    <p class="mt-2">Chưa có tài liệu nào</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="docModalTitle"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải Tài Liệu Lên</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="uploadDocForm">
          <input type="hidden" id="docId" value="">
          
          <div class="mb-3" id="fileUploadContainer">
            <label class="form-label fw-bold">Chọn File <span class="text-danger">*</span></label>
            <input class="form-control" type="file" id="docFile">
            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Hệ thống sẽ tự động nhận diện Icon dựa trên đuôi file tải lên.</small>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Link Google Drive (nếu có)</label>
            <input type="url" class="form-control" id="docDriveLink" placeholder="https://drive.google.com/...">
            <small class="text-muted">Nếu chưa chọn file tải lên Drive, bạn có thể dán link thủ công.</small>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Tiêu đề tài liệu <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="docTitle" placeholder="VD: Slide chương 1 - Java" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Phân loại danh mục <span class="text-danger">*</span></label>
            <select class="form-select" id="docCategory" onchange="toggleCustomCategory()">
                <option value="Thông báo">Thông báo</option>
                <option value="Biên bản">Biên bản họp lớp</option>
                <option value="Học liệu">Tài liệu học tập / Tham khảo</option>
                <option value="Khác" class="fw-bold text-primary">Khác (Tự nhập)...</option>
            </select>
            <div id="customCategoryDiv" class="mt-2 d-none">
                <input type="text" class="form-control border-primary" id="customCategoryInput" placeholder="Nhập tên danh mục...">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Ghi chú thêm (Note)</label>
            <textarea class="form-control" id="docNote" rows="2" placeholder="VD: Bản Word có chữ ký số..."></textarea>
          </div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label fw-bold">Năm học</label>
              <input type="text" class="form-control" id="docAcademicYear" readonly value="<?= e($currentSemester['academic_year'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Học kỳ lưu</label>
              <select class="form-select" id="docSemesterId">
                <?php foreach ($semesters as $sem): ?>
                <?php
                    $code = strtoupper((string)($sem['semester_name'] ?? ''));
                    $label = preg_match('/^(HK)?([123])$/', $code, $m)
                        ? ('Học kỳ ' . $m[2] . ' - ' . ($sem['academic_year'] ?? ''))
                        : (($sem['semester_name'] ?? '') . ' - ' . ($sem['academic_year'] ?? ''));
                ?>
                <option value="<?= (int)($sem['id'] ?? 0) ?>" data-year="<?= e($sem['academic_year'] ?? '') ?>" <?= ((int)($sem['id'] ?? 0) === $selectedSemesterId) ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

        </form>
      </div>
      <div class="modal-footer border-0 pb-4 px-4 bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" class="btn btn-primary fw-bold px-4" id="docModalSubmitBtn" onclick="saveDocument()">XÁC NHẬN</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/documents.js"></script>
</body>
</html>
