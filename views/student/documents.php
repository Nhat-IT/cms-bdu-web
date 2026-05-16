<?php
/**
 * CMS BDU - Kho tài liệu lớp (Sinh viên)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
$studentMssv = trim((string)($_SESSION['username'] ?? ''));  // MSSV = username
$pageTitle = 'Kho tài liệu';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/documents.css'];
$extraJs = ['student/student-layout.js'];

function studentDocSemesterLabel($semesterName, $academicYear): string {
    $name = strtoupper(trim((string)$semesterName));
    $year = trim((string)$academicYear);
    if (preg_match('/^(HK)?\s*([123])$/i', $name, $m)) {
        return 'Học kỳ ' . $m[2] . ($year !== '' ? (' - ' . $year) : '');
    }
    return trim((string)$semesterName . ($year !== '' ? (' - ' . $year) : ''));
}

function studentDocFormatFileSize($bytes): string {
    $size = (int)$bytes;
    if ($size < 1024) return $size . ' B';
    if ($size < 1024 * 1024) return round($size / 1024, 1) . ' KB';
    if ($size < 1024 * 1024 * 1024) return round($size / (1024 * 1024), 1) . ' MB';
    return round($size / (1024 * 1024 * 1024), 1) . ' GB';
}

function studentDocSizeLabel($doc): string {
    $dbSize = (int)($doc['file_size'] ?? 0);
    if ($dbSize > 0) return studentDocFormatFileSize($dbSize);

    $link = trim((string)($doc['drive_link'] ?? ''));
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
    return studentDocFormatFileSize($bytes);
}

$semesters = db_fetch_all(
    "SELECT id, semester_name, academic_year, start_date, end_date
     FROM semesters
     ORDER BY academic_year DESC,
              FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
              start_date DESC"
);

$currentSemester = null;
$today = date('Y-m-d');
foreach ($semesters as $sem) {
    $start = $sem['start_date'] ?? '';
    $end = $sem['end_date'] ?? '';
    if ($start !== '' && $end !== '' && $today >= $start && $today <= $end) {
        $currentSemester = $sem;
        break;
    }
}
if ($currentSemester === null && !empty($semesters)) {
    $currentSemester = $semesters[0];
}

$selectedSemesterId = (int)($_GET['semester_id'] ?? ($currentSemester['id'] ?? 0));

// Query gốc: tài liệu có môn học cụ thể
$documents = db_fetch_all(
    "SELECT DISTINCT d.id, d.title, d.note, d.category, d.drive_link, d.drive_file_id,
            d.icon_type, d.custom_icon, d.class_subject_id, d.uploader_id,
            d.file_size, d.file_mime, d.original_filename, d.created_at,
            s.subject_name, s.subject_code,
            uploader.full_name as uploader_name,
            sm.semester_name, sm.academic_year
     FROM documents d
     JOIN class_subjects cs ON d.class_subject_id = cs.id
     JOIN subjects s ON cs.subject_id = s.id
     LEFT JOIN users uploader ON d.uploader_id = uploader.id
     LEFT JOIN semesters sm ON cs.semester_id = sm.id
     WHERE cs.class_id IN (
        SELECT DISTINCT cs2.class_id
        FROM student_subject_registration ssr
        JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
        JOIN class_subjects cs2 ON csg.class_subject_id = cs2.id
        WHERE (ssr.student_id = ? OR ssr.mssv = ?)
          AND ssr.status = 'Đang học'
          AND cs2.class_id IS NOT NULL
     )
       AND LOWER(COALESCE(uploader.role, '')) IN ('bcs', 'admin', 'support_admin')
       AND (? <= 0 OR cs.semester_id = ?)
     ORDER BY d.created_at DESC",
    [$userId, $studentMssv, $selectedSemesterId, $selectedSemesterId]
);

// Query thêm: tài liệu "Khác" (class_subject_id = NULL) từ cùng lớp với sinh viên
$selectedSemesterName2 = '';
foreach ($semesters as $sem) {
    if ((int)($sem['id'] ?? 0) === $selectedSemesterId) {
        $selectedSemesterName2 = strtoupper(trim((string)($sem['semester_name'] ?? '')));
        break;
    }
}
$docsKhac = db_fetch_all(
    "SELECT DISTINCT d.id, d.title, d.note, d.category, d.drive_link, d.drive_file_id,
            d.icon_type, d.custom_icon, d.class_subject_id, d.uploader_id,
            d.file_size, d.file_mime, d.original_filename, d.created_at,
            NULL as subject_name, NULL as subject_code,
            uploader.full_name as uploader_name,
            NULL as semester_name, NULL as academic_year
     FROM documents d
     LEFT JOIN users uploader ON d.uploader_id = uploader.id
     WHERE d.class_subject_id IS NULL
       AND LOWER(COALESCE(uploader.role, '')) IN ('bcs', 'admin', 'support_admin')
       AND d.uploader_id IN (
           SELECT cls.student_id FROM class_students cls
           WHERE cls.class_id IN (
               SELECT DISTINCT cs2.class_id
               FROM student_subject_registration ssr
               JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
               JOIN class_subjects cs2 ON csg.class_subject_id = cs2.id
               WHERE (ssr.student_id = ? OR ssr.mssv = ?)
                 AND ssr.status = 'Đang học'
                 AND cs2.class_id IS NOT NULL
           )
       )
       AND (? <= 0 OR UPPER(COALESCE(d.semester, '')) = ?)
     ORDER BY d.created_at DESC",
    [$userId, $studentMssv, $selectedSemesterId, $selectedSemesterName2]
);

if (!empty($docsKhac)) {
    $existingIds = array_column($documents, 'id');
    foreach ($docsKhac as $dk) {
        if (!in_array($dk['id'], $existingIds)) {
            $documents[] = $dk;
        }
    }
    usort($documents, fn($a, $b) => strtotime((string)($b['created_at'] ?? '0')) - strtotime((string)($a['created_at'] ?? '0')));
}

$unreadNotifications = (int)db_count(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);

// Lấy toàn bộ môn học trong lớp của sinh viên từ DB (không phụ thuộc filter học kỳ)
$allSubjectRows = db_fetch_all(
    "SELECT DISTINCT s.subject_code, s.subject_name
     FROM subjects s
     JOIN class_subjects cs ON cs.subject_id = s.id
     WHERE cs.class_id IN (
         SELECT DISTINCT cs2.class_id
         FROM student_subject_registration ssr
         JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
         JOIN class_subjects cs2 ON csg.class_subject_id = cs2.id
         WHERE (ssr.student_id = ? OR ssr.mssv = ?)
           AND ssr.status = 'Đang học'
           AND cs2.class_id IS NOT NULL
     )
     ORDER BY s.subject_name ASC",
    [$userId, $studentMssv]
);
$uniqueSubjects = [];
foreach ($allSubjectRows as $row) {
    $code = (string)($row['subject_code'] ?? '');
    $name = (string)($row['subject_name'] ?? '');
    if ($code !== '') {
        $uniqueSubjects[$code] = $name;
    }
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">KHO TÀI LIỆU LỚP</h5>
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
            <div>Tài liệu hiển thị từ kho tài liệu do BCS lớp đăng.</div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ul me-2 text-secondary"></i>Danh sách tài liệu</h5>

                <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                    <select class="form-select form-select-sm w-auto fw-bold text-primary border-primary" name="semester_id" onchange="this.form.submit()">
                        <option value="0" <?= $selectedSemesterId <= 0 ? 'selected' : '' ?>>Tất cả học kỳ</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= (int)$sem['id'] ?>" <?= (int)$sem['id'] === (int)$selectedSemesterId ? 'selected' : '' ?>>
                                <?= e(studentDocSemesterLabel($sem['semester_name'] ?? '', $sem['academic_year'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm w-auto" id="subjectFilter" onchange="filterDocuments()">
                        <option value="">Tất cả môn học</option>
                        <?php foreach ($uniqueSubjects as $code => $name): ?>
                            <option value="<?= e($code) ?>"><?= e($name) ?></option>
                        <?php endforeach; ?>
                        <option value="__khac__">Khác</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" id="categoryFilter" onchange="filterDocuments()">
                        <option value="">Tất cả danh mục</option>
                        <option value="Thông báo">Thông báo</option>
                        <option value="Biên bản">Biên bản</option>
                        <option value="Học liệu">Học liệu</option>
                        <option value="Danh sách lớp">Danh sách lớp</option>
                        <option value="Khác">Khác</option>
                    </select>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Tìm tài liệu, môn học..." onkeyup="filterDocuments()">
                    </div>
                </form>
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
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                    <?php
                                    $title = (string)($doc['title'] ?? '');
                                    $iconType = strtolower((string)($doc['icon_type'] ?? ''));
                                    $fileIcon = 'bi-file-earmark-fill';
                                    $iconColor = 'text-secondary';
                                    if ($iconType === 'pdf') { $fileIcon = 'bi-file-earmark-pdf-fill'; $iconColor = 'text-danger'; }
                                    elseif ($iconType === 'doc') { $fileIcon = 'bi-file-earmark-word-fill'; $iconColor = 'text-primary'; }
                                    elseif ($iconType === 'xls') { $fileIcon = 'bi-file-earmark-excel-fill'; $iconColor = 'text-success'; }
                                    elseif ($iconType === 'zip') { $fileIcon = 'bi-file-earmark-zip-fill'; $iconColor = 'text-warning'; }
                                    else {
                                        $target = strtolower($title . ' ' . ($doc['drive_link'] ?? '') . ' ' . ($doc['original_filename'] ?? ''));
                                        if (preg_match('/\.pdf(\?|$)/i', $target)) { $fileIcon = 'bi-file-earmark-pdf-fill'; $iconColor = 'text-danger'; }
                                        elseif (preg_match('/\.(doc|docx)(\?|$)/i', $target)) { $fileIcon = 'bi-file-earmark-word-fill'; $iconColor = 'text-primary'; }
                                        elseif (preg_match('/\.(xls|xlsx)(\?|$)/i', $target)) { $fileIcon = 'bi-file-earmark-excel-fill'; $iconColor = 'text-success'; }
                                        elseif (preg_match('/\.(zip|rar|7z)(\?|$)/i', $target)) { $fileIcon = 'bi-file-earmark-zip-fill'; $iconColor = 'text-warning'; }
                                    }

                                    $dbHasFile = (int)($doc['file_size'] ?? 0) > 0 || !empty($doc['original_filename']);
                                    $hasDriveLink = !empty($doc['drive_link']);
                                    if ($dbHasFile) {
                                        $fileUrl = BASE_URL . '/api/serve-file.php?id=' . (int)$doc['id'] . '&action=view';
                                        $dlUrl   = BASE_URL . '/api/serve-file.php?id=' . (int)$doc['id'] . '&action=download';
                                    } else {
                                        $fileUrl = (string)($doc['drive_link'] ?? '');
                                        $dlUrl   = (string)($doc['drive_link'] ?? '');
                                    }
                                    $hasAnyFile = $dbHasFile || $hasDriveLink;

                                    $category = (string)($doc['category'] ?? 'Khác');
                                    $categoryClass = 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                                    if (stripos($category, 'Thông báo') !== false) {
                                        $categoryClass = 'bg-danger bg-opacity-10 text-danger border-danger';
                                    } elseif (stripos($category, 'Học liệu') !== false) {
                                        $categoryClass = 'bg-primary bg-opacity-10 text-primary border-primary';
                                    } elseif (stripos($category, 'Biên bản') !== false) {
                                        $categoryClass = 'bg-warning bg-opacity-10 text-dark border-warning';
                                    } elseif (stripos($category, 'Danh sách') !== false) {
                                        $categoryClass = 'bg-success bg-opacity-10 text-success border-success';
                                    }
                                    ?>
                                    <tr data-category="<?= e(strtolower($category)) ?>" data-subject="<?= ($doc['subject_code'] ?? '') !== '' ? e(strtolower($doc['subject_code'])) : '__khac__' ?>" data-title="<?= e(strtolower(($title) . ' ' . ($doc['subject_name'] ?? '') . ' ' . ($doc['note'] ?? ''))) ?>">
                                        <td class="ps-4 py-3">
                                            <?php if ($hasAnyFile): ?>
                                                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer" class="file-link d-flex align-items-center">
                                                    <i class="bi <?= $fileIcon ?> fs-3 <?= $iconColor ?> me-3"></i>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark file-title"><?= e($title) ?></h6>
                                                        <small class="text-muted"><?= e('Môn: ' . (($doc['subject_name'] ?? '') !== '' ? $doc['subject_name'] : 'Khác')) ?></small>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi <?= $fileIcon ?> fs-3 <?= $iconColor ?> me-3"></i>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark file-title"><?= e($title) ?></h6>
                                                        <small class="text-muted"><?= e($doc['note'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $categoryClass ?> border px-2 py-1"><?= e($category) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i><?= e($doc['uploader_name'] ?? 'BCS') ?></span>
                                        </td>
                                        <td class="text-center text-dark"><?= formatDate($doc['created_at'] ?? null, 'd/m/Y') ?></td>
                                        <td class="text-center text-muted small"><?= e(studentDocSizeLabel($doc)) ?></td>
                                        <td class="pe-4 text-end">
                                            <?php if ($hasAnyFile): ?>
                                                <a href="<?= e($dlUrl) ?>" <?= $dbHasFile ? '' : 'target="_blank" rel="noopener noreferrer"' ?> class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill shadow-sm">
                                                    <i class="bi bi-download me-1"></i>Tải file
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
                                        Chưa có tài liệu BCS chia sẻ trong học kỳ này.
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<?php foreach ($extraJs as $js): ?>
    <script src="../../public/js/<?= e($js) ?>"></script>
<?php endforeach; ?>
<script>
function filterDocuments() {
    const subject = (document.getElementById('subjectFilter')?.value || '').toLowerCase();
    const category = (document.getElementById('categoryFilter')?.value || '').toLowerCase();
    const keyword = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const rows = document.querySelectorAll('#documentsTable tbody tr[data-category]');

    rows.forEach((row) => {
        const rowSubject = row.getAttribute('data-subject') || '';
        const rowCategory = row.getAttribute('data-category') || '';
        const rowTitle = row.getAttribute('data-title') || '';
        const matchSubject = !subject || rowSubject === subject;
        const matchCategory = !category || rowCategory.includes(category);
        const matchKeyword = !keyword || rowTitle.includes(keyword);
        row.style.display = (matchSubject && matchCategory && matchKeyword) ? '' : 'none';
    });
}
</script>
</body>
</html>
