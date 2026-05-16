<?php
/**
 * CMS BDU - Quản Lý Chuyên Cần (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Quản Lý Chuyên Cần';
$dbError = '';
$classWarning = '';
$classId = null;
$className = '';
$semesters = [];
$academicYears = [];
$currentSemester = null;
$subjects = [];
$unreadCount = 0;

try {
    getDBConnection();

    // Lấy thông tin lớp của BCS (hỗ trợ cả class_students và group_students)
    $classInfo = getUserClassInfo($userId);
    $classId = $classInfo['class_id'];
    $className = $classInfo['class_name'];
    $sourceType = $classInfo['source'];

    // Lấy thông tin user
    $currentUser = db_fetch_one("SELECT * FROM users WHERE id = ?", [$userId]);
    $fullName = $currentUser['full_name'] ?? '';
    $position = $currentUser['position'] ?? 'Ban Cán Sự';
    $avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 55);

    // Đếm notification
    $unreadCount = (int)(db_fetch_one(
        "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
        [$userId]
    )['total'] ?? 0);

    if ($sourceType !== '') {
        // Lấy học kỳ hiện tại theo cấu hình admin (org-settings.php)
        $currentSemester = getCurrentSemester();

        // Lấy danh sách học kỳ
        $semesters = db_fetch_all(
            "SELECT *
             FROM semesters
             ORDER BY academic_year DESC,
                      FIELD(UPPER(semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
                      start_date DESC"
        );
        foreach ($semesters as $sem) {
            $year = trim((string) ($sem['academic_year'] ?? ''));
            if ($year !== '') {
                $academicYears[$year] = true;
            }
        }

        // Lấy danh sách môn học (BCS chỉ thấy môn mà sv lớp đã đăng ký)
        $subjects = db_fetch_all(
            "SELECT DISTINCT cs.id as class_subject_id, s.id as subject_id, s.subject_name, s.subject_code
             FROM class_subjects cs
             JOIN subjects s ON cs.subject_id = s.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
             WHERE ssr.status = 'Đang học'
             ORDER BY s.subject_name",
            [$classId]
        );
    } else {
        $classWarning = 'Tài khoản BCS chưa được gán lớp trong hệ thống.';
    }
} catch (Exception $e) {
    $dbError = 'Không thể kết nối cơ sở dữ liệu hoặc tải dữ liệu điểm danh.';
    $fullName = $_SESSION['full_name'] ?? 'BCS';
    $position = $_SESSION['position'] ?? 'Ban Cán Sự';
    $avatar = getAvatarUrl($_SESSION['avatar'] ?? null, $fullName, 55);
}

function attendanceSemesterLabel($semesterName, $academicYear) {
    $code = strtoupper(trim((string)($semesterName ?? '')));
    if (preg_match('/^(HK)?([123])$/', $code, $m)) {
        return 'Học kỳ ' . $m[2] . ' - ' . (string)($academicYear ?? '');
    }
    return trim((string)$semesterName . ' - ' . (string)$academicYear);
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
    <link rel="stylesheet" href="../../public/css/bcs/attendance.css">
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
            <a href="attendance.php" class="active"><i class="bi bi-calendar-check"></i> Quản lý Chuyên cần</a>
            <a href="documents.php"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>
            <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CÁ NHÂN</div>
            <a href="../switch-role.php?role=student&next=home" class="text-warning"><i class="bi bi-arrow-repeat"></i> Về Cổng Sinh Viên</a>
        </nav>
        </div>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="#" class="nav-link logout-btn" title="Đăng xuất"
           data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">Đăng xuất</span>
        </a>
    </div>
</div>

<!-- Modal xác nhận đăng xuất -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center py-4 px-3">
                <div class="mb-3">
                    <i class="bi bi-box-arrow-left text-danger" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="fw-bold mb-1">Xác nhận đăng xuất</h6>
                <p class="text-muted small mb-4">Bạn có chắc chắn muốn đăng xuất không?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Hủy</button>
                    <a href="../../views/logout.php" class="btn btn-danger btn-sm px-4">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="main-content" id="mainContent" style="padding: 0; background-color: #f4f6f9; min-height: 100vh;">
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">ĐIỂM DANH LỚP</h4>
        </div>
        
        <div class="bcs-header-meta d-flex align-items-center text-white">
            <span class="bcs-header-label fw-bold">BAN CÁN SỰ</span>
            <a href="../switch-role.php?role=student&next=notifications" class="bcs-notification-link" title="Có <?= $unreadCount ?> thông báo hệ thống">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="bcs-notification-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="p-4">
        <?php if ($dbError !== ''): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <?= e($dbError) ?>
        </div>
        <?php endif; ?>

        <?php if ($classWarning !== ''): ?>
        <div class="alert alert-warning mb-4" role="alert">
            <?= e($classWarning) ?>
        </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="fw-bold small text-muted mb-1">HỌC KỲ</label>
                        <select class="form-select border-primary bg-light fw-bold text-primary bcs-compact-select bcs-short-select" id="filterSemester">
                            <?php foreach ($semesters as $sem): ?>
                            <option value="<?= e($sem['semester_name']) ?>" data-year="<?= e($sem['academic_year'] ?? '') ?>" <?= (($currentSemester['semester_name'] ?? '') === ($sem['semester_name'] ?? '') && ($currentSemester['academic_year'] ?? '') === ($sem['academic_year'] ?? '')) ? 'selected' : '' ?>>
                                <?= e(attendanceSemesterLabel($sem['semester_name'] ?? '', $sem['academic_year'] ?? '')) ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="all">-- Xem tất cả học kỳ --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="fw-bold small text-muted mb-1">MÔN HỌC</label>
                        <select class="form-select border-primary fw-bold text-dark bcs-compact-select" id="subjectSelect" onchange="onSubjectChange()">
                            <option value="">-- Chọn môn học --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="fw-bold small text-muted mb-1">NGÀY HỌC</label>
                        <input type="date" class="form-control fw-bold bcs-short-input" id="attendanceDate" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="row g-2 align-items-end mt-1">
                    <div class="col-12 col-md-3">
                        <label class="fw-bold small text-muted mb-1">NHÓM</label>
                        <select class="form-select border-primary fw-bold text-dark bcs-group-select bcs-short-select" id="groupSelect" onchange="onGroupChange()">
                            <option value="">-- Chọn nhóm --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="fw-bold small text-muted mb-1">BUỔI</label>
                        <select class="form-select fw-bold text-dark bcs-compact-select bcs-short-select" id="attendanceSession" onchange="checkSessionReason()">
                            <option value="Sáng">Sáng</option>
                            <option value="Chiều">Chiều</option>
                            <option value="Tối">Tối</option>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mt-2 align-items-center">
                    <div class="col-12 col-md-8">
                        <div class="p-2 bg-light rounded text-dark small border-start border-3 border-primary fixed-info-box" id="subjectInfoBox">
                            <div class="d-flex flex-wrap gap-3">
                                <span><i class="bi bi-person-badge text-primary me-1"></i>Giảng viên: <strong id="lblTeacher">...</strong></span>
                                <span style="white-space: nowrap;"><i class="bi bi-calendar2-range text-primary me-1"></i>Thời gian: <strong id="lblTime">...</strong></span>
                                <span><i class="bi bi-geo-alt text-primary me-1"></i>Phòng: <strong id="lblRoom">...</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 invisible" id="sessionReasonDiv">
                        <input type="text" class="form-control border-warning bg-warning bg-opacity-10" id="sessionReason" placeholder="Nhập lý do đổi buổi (*Bắt buộc)">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-check me-2 text-primary"></i>Danh sách Sinh viên</h5>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Tìm MSSV, Họ tên..." onkeyup="filterTable()">
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="bi bi-person-plus-fill me-1"></i>Thêm SV
                    </button>
                    <button class="btn btn-sm btn-success fw-bold shadow-sm" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i>Xuất file điểm danh
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle custom-detail-table" id="attendanceTable">
                        <thead class="table-light text-muted small fw-bold">
                            <tr>
                                <th class="text-center" style="width: 60px;">STT</th>
                                <th style="width: 150px;">MSSV</th>
                                <th style="min-width: 160px;">HỌ VÀ TÊN</th>
                                <th style="width: 110px;">NGÀY SINH</th>
                                <th style="width: 90px;">LỚP</th>
                                <th style="width: 150px;">TRẠNG THÁI</th>
                                <th>GHI CHÚ / MINH CHỨNG</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <tr><td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-arrow-left-circle fs-3 d-block mb-2 mx-auto text-muted" style="opacity:0.4"></i>
                                Vui lòng chọn <strong>môn học</strong> và <strong>nhóm</strong> để hiển thị danh sách sinh viên.
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer attendance-footer bg-white p-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary" id="studentCountText">Sĩ số: <span id="totalCount">0</span> | Có mặt: <span id="presentCount">0</span> | Vắng: <span id="absentCount">0</span></span>
                <span id="autoSaveStatus" class="small"></span>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Thêm sinh viên bị sót</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info small border-0 py-2">
            <i class="bi bi-info-circle-fill me-1"></i> Sinh viên được thêm sẽ mặc định <b>"Có mặt"</b> và lưu vào danh sách nhóm hiện tại.
        </div>
        <div id="addStudentError" class="alert alert-danger small py-2 d-none"></div>
        <form id="addStudentForm">
          <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Mã số (MSSV) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="newMssv" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Lớp học <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="newClass" placeholder="VD: <?= e($className) ?>" required value="<?= e($className) ?>">
              </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Họ và Tên Sinh viên <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="newFullName" required>
          </div>
          <div class="mb-2">
            <label class="form-label fw-bold">Ngày sinh</label>
            <input type="date" class="form-control" id="newDob">
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 pb-4 px-4 bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
        <button type="button" class="btn btn-primary fw-bold" onclick="addStudentToTable()"><i class="bi bi-plus-circle me-1"></i>Thêm vào danh sách</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/attendance.js"></script>
<script>
// Pass data to JavaScript
const CLASS_ID = <?= json_encode($classId) ?>;
const SOURCE_TYPE = <?= json_encode($sourceType) ?>;
const CLASS_NAME = <?= json_encode($className) ?>;
</script>
<style>
    .bcs-compact-select {
        width: 100%;
    }
    .bcs-group-select {
        width: 100%;
        min-width: 0;
        max-width: none;
    }
    .bcs-short-select {
        max-width: 220px;
    }
    .bcs-short-input {
        max-width: 220px;
    }
    .name-cell {
        white-space: nowrap;
    }
    .attendance-footer {
        position: sticky;
        bottom: 0;
        z-index: 10;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
    }
    @keyframes bcs-spin { to { transform: rotate(360deg); } }
    .bcs-spin { display: inline-block; animation: bcs-spin 0.7s linear infinite; }
</style>
</body>
</html>
