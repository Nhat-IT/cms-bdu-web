<?php
/**
 * CMS BDU - Teacher Documents
 * Trang quản lý kho tài liệu & bài giảng
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('teacher');

// Lấy thông tin giảng viên
$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Lấy teacher_id
$stmtTeacher = $pdo->prepare("
    SELECT t.id as teacher_id 
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.id = ?
");
$stmtTeacher->execute([$userId]);
$teacherInfo = $stmtTeacher->fetch();
$teacherId = $teacherInfo['teacher_id'] ?? $userId;

// Lấy danh sách lớp học phần của giảng viên
$stmtClassSubjects = $pdo->prepare("
    SELECT cs.id,
           c.class_name,
           s.subject_code, s.subject_name
    FROM class_subjects cs
    INNER JOIN semesters sem ON cs.semester_id = sem.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ? AND sem.end_date >= CURDATE()
    ORDER BY c.class_name
");
$stmtClassSubjects->execute([$teacherId]);
$classSubjects = $stmtClassSubjects->fetchAll();

// Lấy danh sách tài liệu
$stmtDocs = $pdo->prepare("
    SELECT d.*, cs.class_name, s.subject_name
    FROM documents d
    INNER JOIN class_subjects cs ON d.class_subject_id = cs.id
    INNER JOIN classes c ON cs.class_id = c.id
    INNER JOIN subjects s ON cs.subject_id = s.id
    WHERE d.uploader_id = ?
    ORDER BY d.created_at DESC
");
$stmtDocs->execute([$userId]);
$documents = $stmtDocs->fetchAll();

// Đếm minh chứng chờ duyệt
$stmtCountEvidence = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM attendance_evidences ae
    INNER JOIN attendance_records ar ON ae.attendance_record_id = ar.id
    INNER JOIN attendance_sessions ass ON ar.session_id = ass.id
    INNER JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
    INNER JOIN class_subjects cs ON csg.class_subject_id = cs.id
    WHERE cs.teacher_id = ? AND ae.status = 'Pending'
");
$stmtCountEvidence->execute([$teacherId]);
$pendingEvidences = $stmtCountEvidence->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Kho tài liệu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/documents.css">
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
            <div class="sidebar-scrollable w-100">
            <nav class="nav flex-column ps-2 pe-2">
                <a href="home.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-speedometer2 me-2"></i> Tổng quan</a>
                <a href="attendance.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-calendar-check me-2"></i> Lịch & Điểm danh</a>
                <a href="class-assignments.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-journal-text me-2"></i> Quản lý Bài tập</a>
                <a href="class-grades.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i> Bảng điểm</a>
                <a href="approve-evidences.php" class="nav-link text-white-50 hover-white py-2 mb-1 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical me-2"></i> Duyệt minh chứng</span>
                    <?php if ($pendingEvidences > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo e($pendingEvidences); ?></span>
                    <?php endif; ?>
                </a>
                <a href="documents.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1"><i class="bi bi-folder2-open me-2"></i> Kho tài liệu</a>
                <a href="announcements.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-megaphone-fill me-2"></i> Đăng bảng tin</a>
            </nav>
            </div>
        </div>
        
        <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
            <a href="../logout.php" class="nav-link logout-btn" title="Đăng xuất">
                <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3 shadow-sm mb-4">
            <h4 class="m-0 fw-bold d-flex align-items-center text-white">
                KHO TÀI LIỆU & BÀI GIẢNG
            </h4>
            <div class="d-flex align-items-center text-white">
                <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
                    <div class="fs-6">Giảng viên: <b class="text-info"><?php echo e($user['full_name'] ?? 'GV'); ?></b></div>
                </div>
                
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown">
                        <img src="<?php echo e(getAvatarUrl($user['avatar'] ?? '', $user['full_name'] ?? 'GV', 40)); ?>" id="headerAvatar" alt="Avatar" class="rounded-circle border border-white" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                        <li><a class="dropdown-item fw-bold" href="teacher-profile.php"><i class="bi bi-person-vcard text-info me-2"></i>Hồ sơ cá nhân</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="../logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="p-4 pt-0">
            
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select class="form-select form-select-sm border-secondary fw-bold text-dark" style="width: 220px;" id="classFilter" onchange="filterDocuments()">
                            <option value="">-- Tất cả Lớp học phần --</option>
                            <?php foreach ($classSubjects as $cs): ?>
                            <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm border-secondary" style="width: 180px;" id="categoryFilter" onchange="filterDocuments()">
                            <option value="">-- Mọi danh mục --</option>
                            <option value="Học liệu">Bài giảng / Học liệu</option>
                            <option value="Tham khảo">Tài liệu tham khảo</option>
                            <option value="Đề cương">Đề cương chi tiết</option>
                        </select>
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" placeholder="Tìm tên tài liệu..." id="searchDoc" onkeyup="filterDocuments()">
                        </div>
                    </div>
                    
                    <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#docModal" onclick="openDocModal('add')">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Tải tài liệu lên
                    </button>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="documentsTable">
                            <thead class="table-light small text-muted fw-bold">
                                <tr>
                                    <th class="ps-4">TÊN TÀI LIỆU</th>
                                    <th>DANH MỤC</th>
                                    <th>LỚP HỌC PHẦN</th>
                                    <th>NGÀY ĐĂNG</th>
                                    <th class="text-end pe-4">HÀNH ĐỘNG</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Chưa có tài liệu nào được tải lên.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                <?php
                                    $iconClass = 'icon-pdf';
                                    $iconType = 'pdf';
                                    if ($doc['icon_type']) {
                                        $iconType = $doc['icon_type'];
                                        $iconClass = 'icon-' . $doc['icon_type'];
                                    } elseif (stripos($doc['title'], '.ppt') !== false || stripos($doc['title'], '.pptx') !== false) {
                                        $iconClass = 'icon-ppt';
                                        $iconType = 'ppt';
                                    } elseif (stripos($doc['title'], '.doc') !== false || stripos($doc['title'], '.docx') !== false) {
                                        $iconClass = 'icon-word';
                                        $iconType = 'word';
                                    } elseif (stripos($doc['title'], '.zip') !== false || stripos($doc['title'], '.rar') !== false) {
                                        $iconClass = 'icon-zip';
                                        $iconType = 'zip';
                                    }
                                    
                                    $categoryClass = 'bg-primary';
                                    $categoryText = 'text-primary';
                                    if ($doc['category'] === 'Tham khảo') {
                                        $categoryClass = 'bg-info';
                                        $categoryText = 'text-info';
                                    } elseif ($doc['category'] === 'Đề cương') {
                                        $categoryClass = 'bg-success';
                                        $categoryText = 'text-success';
                                    }
                                ?>
                                <tr data-class-id="<?php echo e($doc['class_subject_id']); ?>" data-category="<?php echo e($doc['category'] ?? ''); ?>" data-title="<?php echo e(strtolower($doc['title'])); ?>">
                                    <td class="ps-4">
                                        <a href="<?php echo e($doc['drive_link'] ?? '#'); ?>" target="_blank" class="file-link d-flex align-items-center">
                                            <i class="bi bi-file-earmark-<?php echo e($iconType); ?>-fill file-icon <?php echo e($iconClass); ?> me-3"></i>
                                            <div>
                                                <h6 class="mb-0 fw-bold file-title"><?php echo e($doc['title']); ?></h6>
                                                <?php if ($doc['note']): ?>
                                                <small class="text-muted"><?php echo e($doc['note']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($doc['category']): ?>
                                        <span class="badge <?php echo e($categoryClass); ?> bg-opacity-10 <?php echo e($categoryText); ?> border <?php echo e($categoryText); ?> border-opacity-25"><?php echo e($doc['category']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted small">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo e($doc['class_name']); ?> - <?php echo e($doc['subject_name']); ?></td>
                                    <td><?php echo formatDate($doc['created_at']); ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-light action-btn text-primary border me-1" title="Sửa thông tin" data-bs-toggle="modal" data-bs-target="#docModal" onclick='openDocModal("edit", "<?php echo e($doc['id']); ?>", "<?php echo e(addslashes($doc['title'])); ?>", "<?php echo e(addslashes($doc['note'] ?? '')); ?>", "<?php echo e($doc['category'] ?? ''); ?>", "<?php echo e($doc['class_subject_id']); ?>")'><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-light action-btn text-danger border" title="Xóa" onclick="confirmDeleteDoc('<?php echo e($doc['id']); ?>')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-muted small">
                <i class="bi bi-info-circle me-1"></i> File tải lên sẽ được tự động đồng bộ và lưu trữ an toàn trên hệ thống Google Drive của trường. Giới hạn 50MB/file.
            </div>

        </div>
    </div>

    <div class="modal fade" id="docModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="docModalTitle"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải tài liệu lên</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="docForm" action="api/documents.php" method="POST">
                        <input type="hidden" name="action" id="docAction" value="create">
                        <input type="hidden" name="doc_id" id="docId" value="">
                        
                        <div class="mb-3" id="fileUploadContainer">
                            <label class="form-label fw-bold">Chọn File <span class="text-danger">*</span></label>
                            <input class="form-control" type="file" id="docFile" name="file">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tiêu đề hiển thị <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="docTitle" name="title" placeholder="VD: Slide Bài giảng Chương 1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phân loại danh mục <span class="text-danger">*</span></label>
                            <select class="form-select" id="docCategory" name="category" onchange="toggleCustomCategory()">
                                <option value="Học liệu">Bài giảng / Học liệu</option>
                                <option value="Tham khảo">Tài liệu tham khảo</option>
                                <option value="Đề cương">Đề cương chi tiết</option>
                                <option value="Thông báo">Thông báo</option>
                                <option value="Khác">Khác (Tự nhập)...</option>
                            </select>
                            <div id="customCategoryDiv" class="mt-2 d-none">
                                <input type="text" class="form-control border-primary" id="customCategoryInput" name="custom_category" placeholder="Nhập tên danh mục (VD: Bài tập mẫu, Đề thi cũ...)">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Chọn Lớp học phần <span class="text-danger">*</span></label>
                            <select class="form-select" id="docClass" name="class_subject_id" required>
                                <option value="">-- Chọn lớp --</option>
                                <?php foreach ($classSubjects as $cs): ?>
                                <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Ghi chú thêm (Note)</label>
                            <textarea class="form-control" id="docNote" name="note" rows="2" placeholder="VD: Sinh viên đọc trước ở nhà..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-primary px-4 fw-bold" id="docModalSubmitBtn" onclick="saveDocument()">Tải lên</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/script.js"></script>
    <script src="../../public/js/teacher/teacher-layout.js"></script>
    
    <script src="../../public/js/teacher/documents.js"></script>
    <script>
        function filterDocuments() {
            const classId = document.getElementById('classFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const search = document.getElementById('searchDoc').value.toLowerCase();
            
            const rows = document.querySelectorAll('#documentsTable tbody tr');
            rows.forEach(row => {
                const matchClass = !classId || row.dataset.classId === classId;
                const matchCategory = !category || row.dataset.category === category;
                const matchSearch = !search || row.dataset.title.includes(search);
                
                if (matchClass && matchCategory && matchSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function toggleCustomCategory() {
            const category = document.getElementById('docCategory').value;
            const customDiv = document.getElementById('customCategoryDiv');
            if (category === 'Khác') {
                customDiv.classList.remove('d-none');
            } else {
                customDiv.classList.add('d-none');
            }
        }
        
        function openDocModal(mode, id, title, note, category, classId) {
            document.getElementById('docAction').value = mode === 'add' ? 'create' : 'update';
            document.getElementById('docId').value = id || '';
            document.getElementById('docTitle').value = title || '';
            document.getElementById('docNote').value = note || '';
            document.getElementById('docCategory').value = category || 'Học liệu';
            document.getElementById('docClass').value = classId || '';
            
            const fileContainer = document.getElementById('fileUploadContainer');
            const submitBtn = document.getElementById('docModalSubmitBtn');
            const modalTitle = document.getElementById('docModalTitle');
            
            if (mode === 'edit') {
                fileContainer.classList.add('d-none');
                submitBtn.textContent = 'Cập nhật';
                modalTitle.innerHTML = '<i class="bi bi-pencil me-2"></i>Cập nhật tài liệu';
            } else {
                fileContainer.classList.remove('d-none');
                submitBtn.textContent = 'Tải lên';
                modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải tài liệu lên';
            }
            
            toggleCustomCategory();
        }
        
        function saveDocument() {
            const form = document.getElementById('docForm');
            const formData = new FormData(form);
            
            fetch('api/documents.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Có lỗi xảy ra');
                }
            })
            .catch(() => {
                alert('Có lỗi xảy ra');
            });
        }
        
        function confirmDeleteDoc(id) {
            if (confirm('Bạn có chắc muốn xóa tài liệu này?')) {
                fetch('api/documents.php?action=delete&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
    </script>
</body>
</html>
