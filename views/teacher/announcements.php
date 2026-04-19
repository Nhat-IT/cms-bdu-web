<?php
/**
 * CMS BDU - Teacher Announcements
 * Trang đăng bảng tin
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

// Lấy danh sách lớp học phần của giảng viên (cho dropdown)
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

// Lấy bảng tin chung (từ các nguồn khác nhau - giả lập vì chưa có bảng announcements)
$generalAnnouncements = [];

// Đếm bảng tin đã đăng của giảng viên (giả lập)
$myAnnouncementsCount = 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Đăng bảng tin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/teacher-layout.css">
    <link rel="stylesheet" href="../../public/css/teacher/announcements.css">
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
            <a href="documents.php" class="nav-link text-white-50 hover-white py-2 mb-1"><i class="bi bi-folder2-open me-2"></i> Kho tài liệu</a>
            <a href="announcements.php" class="nav-link text-white active bg-primary bg-opacity-25 rounded py-2 mb-1"><i class="bi bi-megaphone-fill me-2"></i> Đăng bảng tin</a>
        </nav>
        </div>
    </div>

    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../logout.php" class="nav-link logout-btn" title="Đăng xuất">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<div class="main-content" id="mainContent">
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3 announcements-header shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">
                ĐĂNG BẢNG TIN
            </h4>
        </div>

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

    <div class="p-3 p-lg-4 announcements-content-wrap">

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-danger border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger rounded p-2"><i class="bi bi-bell-fill fs-4"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.85rem;">BẢNG TIN ĐÃ ĐĂNG</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo e($myAnnouncementsCount); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-primary border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary rounded p-2"><i class="bi bi-file-earmark-arrow-up-fill fs-4"></i></div>
                        <div>
                            <p class="text-primary fw-bold mb-1" style="font-size: 0.85rem;">BẢNG TIN KHOA</p>
                            <h3 class="mb-0 fw-bold text-dark">--</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-success border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success rounded p-2"><i class="bi bi-send-check-fill fs-4"></i></div>
                        <div>
                            <p class="text-success fw-bold mb-1" style="font-size: 0.85rem;">BẢNG TIN THEO LỚP</p>
                            <h3 class="mb-0 fw-bold text-dark"><?php echo e(count($classSubjects)); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 announcement-card">
            <div class="card-header bg-white border-0 pt-3 pb-0 px-4">
                <ul class="nav nav-tabs card-header-tabs border-0 gap-2" id="announcementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-panel" type="button" role="tab" aria-controls="general-panel" aria-selected="true">
                            <i class="bi bi-bell me-1"></i> Bảng tin chung
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="publish-tab" data-bs-toggle="tab" data-bs-target="#publish-panel" type="button" role="tab" aria-controls="publish-panel" aria-selected="false">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Đăng bản tin
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="general-panel" role="tabpanel" aria-labelledby="general-tab">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">                           
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm border-secondary" placeholder="Tìm tiêu đề hoặc nguồn..." style="width: 220px;" id="searchAnnouncement" onkeyup="filterAnnouncements()">
                                <select class="form-select form-select-sm w-auto border-secondary" id="sourceFilter" onchange="filterAnnouncements()">
                                    <option value="">Tất cả nguồn</option>
                                    <option>Phòng Đào tạo</option>
                                    <option>Phòng CTSV</option>
                                    <option>Khoa CNTT</option>
                                </select>
                            </div>
                        </div>

                        <div class="list-group" id="announcementList">
                            <div class="list-group-item announcement-item border rounded-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge rounded-pill announcement-pill px-3 py-2 fw-normal">Mới</span>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 fw-normal">Phòng Đào tạo</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Lịch nghỉ giữa kỳ và quy định bổ sung hồ sơ học vụ</h6>
                                        <p class="text-muted mb-2"><?php echo date('d/m/Y, H:i'); ?></p>
                                        <p class="mb-0 text-dark">Sinh viên theo dõi lịch nghỉ và cập nhật giấy tờ học vụ đúng thời hạn.</p>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-outline-primary btn-sm fw-bold mb-2" onclick="openViewModal('Lịch nghỉ giữa kỳ', 'Phòng Đào tạo', '<?php echo date('d/m/Y, H:i'); ?>', 'Mới', 'Sinh viên theo dõi lịch nghỉ và cập nhật giấy tờ học vụ đúng thời hạn.')"><i class="bi bi-eye me-1"></i>Xem</button>
                                        <button class="btn btn-sm btn-light border" onclick="downloadFile()"><i class="bi bi-download me-1"></i>Tải file</button>
                                    </div>
                                </div>
                            </div>

                            <div class="list-group-item announcement-item border rounded-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge rounded-pill bg-warning text-dark px-3 py-2 fw-normal">Quan trọng</span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 fw-normal">Phòng CTSV</span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Thông báo nộp minh chứng và cập nhật ảnh hồ sơ sinh viên</h6>
                                        <p class="text-muted mb-2"><?php echo date('d/m/Y, H:i', strtotime('-1 day')); ?></p>
                                        <p class="mb-0 text-dark">Các lớp nhắc sinh viên rà soát ảnh hồ sơ, minh chứng vắng học và hoàn tất trước hạn.</p>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-outline-primary btn-sm fw-bold mb-2" onclick="openViewModal('Nộp minh chứng', 'Phòng CTSV', '<?php echo date('d/m/Y, H:i', strtotime('-1 day')); ?>', 'Quan trọng', 'Các lớp nhắc sinh viên rà soát ảnh hồ sơ.')"><i class="bi bi-eye me-1"></i>Xem</button>
                                        <button class="btn btn-sm btn-light border" onclick="downloadFile()"><i class="bi bi-download me-1"></i>Tải file</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="publish-panel" role="tabpanel" aria-labelledby="publish-tab">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Đăng bản tin mới</h5>
                                <p class="text-muted mb-0">Soạn nội dung và đính kèm file để gửi thông báo cho lớp học phần phụ trách.</p>
                            </div>
                            <button class="btn btn-outline-primary fw-bold panel-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#processPanel" aria-expanded="false" aria-controls="processPanel">
                                <i class="bi bi-chevron-down me-1"></i> Xem quy trình hiển thị
                            </button>
                        </div>

                        <div class="collapse" id="processPanel">
                            <div class="process-panel mb-4">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="step-card rounded-3 p-3 h-100">
                                            <div class="fw-bold text-dark mb-1">1. Soạn và tải file</div>
                                            <div class="text-muted small">Giảng viên nhập nội dung, đính kèm PDF/DOCX/XLSX và chọn phạm vi gửi.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="step-card rounded-3 p-3 h-100">
                                            <div class="fw-bold text-dark mb-1">2. Gửi cảnh báo qua chuông</div>
                                            <div class="text-muted small">Sau khi đăng, hệ thống gửi cảnh báo qua chuông để sinh viên biết có thông báo mới.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="step-card rounded-3 p-3 h-100">
                                            <div class="fw-bold text-dark mb-1">3. Sinh viên đọc / tải</div>
                                            <div class="text-muted small">Sinh viên mở bản tin, đọc nội dung và tải file đính kèm nếu cần.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="p-4 rounded-4 announcement-empty h-100">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-send-check text-primary me-2"></i>Soạn / cập nhật bản tin</h5>
                                            <p class="text-muted mb-0">Người đăng tin có thể tạo bản tin mới và gửi thông báo ngay tại đây.</p>
                                        </div>
                                        <span class="badge bg-white text-primary border border-primary px-3 py-2">Giảng viên quản lý bản tin</span>
                                    </div>

                                    <form id="newsForm">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Tiêu đề bản tin</label>
                                            <input type="text" class="form-control" id="newsTitle" placeholder="VD: Thông báo học bù tuần 8" required>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Nguồn phát hành</label>
                                                <select class="form-select" id="newsSource">
                                                    <option selected>Khoa CNTT</option>
                                                    <option>Phòng Đào tạo</option>
                                                    <option>Phòng CTSV</option>
                                                    <option>Ban Giám hiệu</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Phạm vi gửi</label>
                                                <select class="form-select" id="newsScope">
                                                    <option value="">-- Chọn lớp --</option>
                                                    <?php foreach ($classSubjects as $cs): ?>
                                                    <option value="<?php echo e($cs['id']); ?>"><?php echo e($cs['class_name']); ?> - <?php echo e($cs['subject_name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Loại nội dung</label>
                                                <select class="form-select" id="newsCategory">
                                                    <option selected>Thông báo</option>
                                                    <option>Bản tin</option>
                                                    <option>Biểu mẫu</option>
                                                    <option>Khác</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">File đính kèm</label>
                                                <input type="file" class="form-control" id="newsFile">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nội dung bản tin</label>
                                            <textarea class="form-control" id="newsContent" rows="6" placeholder="Nhập nội dung thông báo, thời hạn, lưu ý cho sinh viên..."></textarea>
                                        </div>

                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="pinToBell" checked>
                                                <label class="form-check-label fw-semibold" for="pinToBell">Đẩy lên chuông thông báo cho sinh viên</label>
                                            </div>

                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="pinPost">
                                                <label class="form-check-label fw-semibold text-warning" for="pinPost">Ghim bài đăng này</label>
                                            </div>

                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-outline-secondary fw-bold" onclick="saveDraft()"><i class="bi bi-save me-1"></i>Lưu nháp</button>
                                                <button type="button" class="btn btn-primary fw-bold" onclick="publishNews()"><i class="bi bi-send-check me-1"></i>Đăng bản tin</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Bản tin đã đăng</h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2">Đang hiển thị</span>
                                        </div>
                                        <div class="d-grid gap-3" id="myAnnouncementsList">
                                            <div class="p-3 rounded-3 bg-light border text-center text-muted">
                                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                                Chưa có bản tin nào được đăng.
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="newsModalTitle"><i class="bi bi-pencil-square me-2"></i>Chi tiết bản tin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="newsModalForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tiêu đề</label>
                        <input type="text" class="form-control" id="modalTitleInput">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nội dung</label>
                        <textarea class="form-control" rows="5" id="modalContentInput"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light justify-content-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="announcementViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="announcementViewTitle">Chi tiết thông báo</h5>
                    <div class="small text-white-50" id="announcementViewMeta"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 mb-3" id="announcementViewBadges"></div>
                <p class="text-dark mb-0 lh-lg" id="announcementViewContent"></p>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 bg-light justify-content-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/teacher/teacher-layout.js"></script>

<script src="../../public/js/teacher/announcements.js"></script>
<script>
    function filterAnnouncements() {
        const search = document.getElementById('searchAnnouncement').value.toLowerCase();
        const source = document.getElementById('sourceFilter').value;
        const items = document.querySelectorAll('.announcement-item');
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(search) ? '' : 'none';
        });
    }
    
    function openViewModal(title, source, date, badge, content) {
        document.getElementById('announcementViewTitle').textContent = title;
        document.getElementById('announcementViewMeta').textContent = source + ' - ' + date;
        document.getElementById('announcementViewBadges').innerHTML = '<span class="badge bg-primary">' + badge + '</span>';
        document.getElementById('announcementViewContent').textContent = content;
        
        new bootstrap.Modal(document.getElementById('announcementViewModal')).show();
    }
    
    function saveDraft() {
        alert('Lưu nháp thành công!');
    }
    
    function publishNews() {
        const title = document.getElementById('newsTitle').value;
        const content = document.getElementById('newsContent').value;
        const scope = document.getElementById('newsScope').value;
        
        if (!title) {
            alert('Vui lòng nhập tiêu đề bản tin!');
            return;
        }
        
        // AJAX call to save announcement
        fetch('api/announcements.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content) + '&scope=' + encodeURIComponent(scope)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Đăng bản tin thành công!');
                location.reload();
            }
        })
        .catch(() => {
            alert('Có lỗi xảy ra!');
        });
    }
    
    function downloadFile() {
        alert('Tải file đính kèm.');
    }
</script>
</body>
</html>
