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
$classes = db_fetch_all("
    SELECT c.*, d.department_name,
           (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count
    FROM classes c
    LEFT JOIN departments d ON c.department_id = d.id
    ORDER BY c.class_name DESC
");

// Lấy danh sách môn học với môn tiên quyết
$subjects = db_fetch_all("
    SELECT s.*,
           p.subject_code AS prerequisite_code
    FROM subjects s
    LEFT JOIN subjects p ON s.prerequisite_id = p.id
    ORDER BY s.year_level ASC, s.subject_code ASC
");

// Lấy danh sách năm học từ semesters (không trùng)
$academicYears = db_fetch_all("SELECT DISTINCT academic_year FROM semesters ORDER BY academic_year DESC");

// Tính computed_status cho từng môn: phụ thuộc open_date / close_date
// Nếu chưa có ngày bắt đầu → mặc định Đã đóng
foreach ($subjects as &$subj) {
    $openDate  = $subj['open_date']  ?? '';
    $closeDate = $subj['close_date'] ?? '';
    $today = date('Y-m-d');

    if (empty($openDate)) {
        $subj['computed_status'] = '0'; // chưa có ngày mở → đóng
    } elseif ($openDate > $today) {
        $subj['computed_status'] = '0'; // chưa đến ngày mở → đóng
    } elseif (!empty($closeDate) && $closeDate < $today) {
        $subj['computed_status'] = '0'; // đã hết hạn → đóng
    } else {
        $subj['computed_status'] = '1'; // đang trong thời gian mở
    }
}
unset($subj);

// Map mã môn cho dropdown tiên quyết
$subjectCodeMap = [];
foreach ($subjects as $s) {
    $subjectCodeMap[$s['id']] = $s['subject_code'];
}

// Lấy danh sách ngành học
$departments = db_fetch_all("SELECT * FROM departments ORDER BY department_name");

// Lấy danh sách giáo viên cho dropdown
$teachers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");

// Lấy danh sách phòng học
$rooms = db_fetch_all("SELECT * FROM rooms ORDER BY room_code");

// Lấy danh sách năm học từ bảng semesters
$academicYears = db_fetch_all("SELECT DISTINCT academic_year FROM semesters ORDER BY academic_year DESC");

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

<?php
$activePage = 'classes-subjects';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>

<div class="main-content admin-main-content" id="mainContent">
<?php
$pageTitle  = 'QUẢN LÝ LỚP & MÔN HỌC';
$pageIcon   = 'bi-building';
require_once __DIR__ . '/../../layouts/admin-topbar.php';
?>

    <div class="p-4">
        <?php if (isset($_GET['class_success'])): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert" id="permanentAlert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <span>Thao tác thành công!</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['room_success'])): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert" id="permanentAlert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <span>Phòng học đã được lưu thành công!</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['room_error'])): ?>
            <?php
            $roomErrors = [
                'missing_data'   => 'Thiếu mã phòng hoặc tên phòng.',
                'duplicate_code' => 'Mã phòng đã tồn tại trong hệ thống.',
                'missing_id'     => 'Không tìm thấy phòng cần xóa.',
                '1'              => 'Đã xảy ra lỗi khi lưu phòng học.',
            ];
            $roomErrMsg = $roomErrors[$_GET['room_error']] ?? 'Lỗi không xác định.';
            ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert" id="permanentAlert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span><?= e($roomErrMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['import_done'])): ?>
            <?php
            $importOk = (int) ($_GET['import_ok'] ?? 0);
            $importSkip = (int) ($_GET['import_skip'] ?? 0);
            $importErr = (int) ($_GET['import_err'] ?? 0);
            $importCode = $_GET['import_code'] ?? '';

            $isFullSuccess = $importErr === 0 && $importOk > 0;
            $isFullFailure = $importOk === 0 && $importErr > 0;
            $alertClass = $isFullSuccess ? 'alert-success' : ($isFullFailure ? 'alert-danger' : 'alert-warning');
            $alertIcon = $isFullSuccess ? 'cloud-check-fill' : ($isFullFailure ? 'exclamation-triangle-fill' : 'info-circle-fill');
            ?>
            <div class="alert <?php echo $alertClass; ?> d-flex justify-content-between align-items-center" role="alert" id="permanentAlert">
                <div>
                    <i class="bi bi-<?php echo $alertIcon; ?> me-2"></i>
                    Import hoàn tất. Thành công: <strong><?php echo $importOk; ?></strong>,
                    Bỏ qua: <strong><?php echo $importSkip; ?></strong>,
                    Lỗi: <strong><?php echo $importErr; ?></strong>.
                </div>
                <?php if (!empty($importCode)): ?>
                    <?php
                    $errorMessages = [
                        'upload_error' => 'Lỗi tải file lên server',
                        'upload_tmp_missing' => 'File không được tải lên đúng cách',
                        'invalid_extension' => 'Định dạng file không được hỗ trợ',
                        'cannot_open_file' => 'Không thể mở file',
                        'xlsx_parse_failed' => 'Không thể đọc file Excel (.xlsx)',
                        'xls_binary_unsupported' => 'File Excel cũ (.xls) không được hỗ trợ. Vui lòng lưu lại dưới dạng .xlsx hoặc .csv',
                        'missing_class' => 'Chưa chọn lớp học',
                        'class_not_found' => 'Lớp học không tồn tại trong hệ thống',
                        'unexpected_error' => 'Đã xảy ra lỗi không mong muốn'
                    ];
                    $errorMsg = $errorMessages[$importCode] ?? 'Mã lỗi: ' . $importCode;
                    ?>
                    <span class="badge bg-dark"><?php echo e($errorMsg); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white pt-3 pb-0 border-0">
                <ul class="nav nav-tabs border-bottom-0" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="class-tab" data-bs-toggle="tab" data-bs-target="#class-pane" type="button" role="tab"><i class="bi bi-building-fill me-2"></i>LỚP HỌC</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="subject-tab" data-bs-toggle="tab" data-bs-target="#subject-pane" type="button" role="tab"><i class="bi bi-journal-bookmark-fill me-2"></i>DANH MỤC MÔN HỌC</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="room-tab" data-bs-toggle="tab" data-bs-target="#room-pane" type="button" role="tab"><i class="bi bi-door-open-fill me-2"></i>PHÒNG HỌC</button>
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
                                                        <button class="btn btn-light action-btn text-primary border" title="Sửa thông tin lớp" onclick="openClassModal('edit', '<?php echo e($class['class_name']); ?>', '<?php echo e($class['academic_year']); ?>', 'open', '<?php echo e($class['id']); ?>')" data-bs-toggle="modal" data-bs-target="#classModal">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <form method="POST" action="../../controllers/admin/classController.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lớp <?php echo e($class['class_name']); ?>?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo e($class['id']); ?>">
                                                            <button class="btn btn-light action-btn text-danger border" title="Xóa lớp">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
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
                                        <th class="ps-4 py-3">MÃ MH</th>
                                        <th class="py-3">TÊN MÔN HỌC</th>
                                        <th class="py-3 text-center">NĂM HỌC</th>
                                        <th class="py-3 text-center">HỌC KỲ</th>
                                        <th class="py-3">MÔN TIÊN QUYẾT</th>
                                        <th class="text-center py-3">SỐ TC</th>
                                        <th class="text-center py-3">TRẠNG THÁI</th>
                                        <th class="text-end pe-4 py-3">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="subjectTableBody">
                                    <?php if (count($subjects) > 0): ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr data-subject-id="<?php echo e($subject['id']); ?>">
                                                <td class="ps-4 fw-bold text-success"><?php echo e($subject['subject_code']); ?></td>
                                                <td class="fw-bold"><?php echo e($subject['subject_name']); ?></td>
                                                <td class="text-center text-muted">
                                                    <?php if (!empty($subject['academic_year'])): ?>
                                                        <?php echo e($subject['academic_year']); ?>
                                                    <?php else: ?>
                                                        <span class="fst-italic">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center text-muted">
                                                    <?php if (!empty($subject['semester'])): ?>
                                                        <?php echo e($subject['semester']); ?>
                                                    <?php else: ?>
                                                        <span class="fst-italic">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-muted">
                                                    <?php if (!empty($subject['prerequisite_code'])): ?>
                                                        <span class="badge bg-warning text-dark"><?php echo e($subject['prerequisite_code']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Không có</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo e($subject['credits']); ?></span></td>
                                                <td class="text-center subject-status-cell">
                                                    <?php if (!empty($subject['computed_status'])): ?>
                                                        <span class="badge class-status-open">Đang mở</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Đã đóng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-warning border" title="Lịch sử mở/đóng"
                                                        onclick="openSubjectHistoryModal2('<?php echo e($subject['id']); ?>', '[<?php echo e($subject['subject_code']); ?>] <?php echo e($subject['subject_name']); ?>', '<?php echo e($subject['academic_year'] ?? ''); ?>', '<?php echo e($subject['semester'] ?? ''); ?>', '<?php echo e($subject['open_date'] ?? ''); ?>', '<?php echo e($subject['close_date'] ?? ''); ?>', '<?php echo e($subject['computed_status']); ?>')">
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
                                                    <button class="btn btn-light action-btn text-primary border" title="Sửa môn học" onclick="openSubjectModal('edit', '<?php echo e($subject['subject_code']); ?>', '<?php echo e($subject['subject_name']); ?>', '<?php echo e($subject['prerequisite_code'] ?? ''); ?>', <?php echo e($subject['credits']); ?>, '<?php echo e($subject['id']); ?>', '<?php echo e($subject['computed_status']); ?>', '<?php echo e($subject['year_level'] ?? ''); ?>', '<?php echo e($subject['semester'] ?? ''); ?>', '<?php echo e($subject['open_date'] ?? ''); ?>', '<?php echo e($subject['close_date'] ?? ''); ?>', '<?php echo e($subject['academic_year'] ?? ''); ?>')" data-bs-toggle="modal" data-bs-target="#subjectModal">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form method="POST" action="../../controllers/admin/subjectController.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa môn <?php echo e($subject['subject_code']); ?> - <?php echo e($subject['subject_name']); ?>?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo e($subject['id']); ?>">
                                                        <button class="btn btn-light action-btn text-danger border" title="Xóa môn học">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Chưa có môn học nào.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Phòng Học -->
                    <div class="tab-pane fade" id="room-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="input-group input-group-sm" style="width:300px">
                                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" placeholder="Tìm mã phòng, tên phòng..." id="searchRoom">
                            </div>
                            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openRoomModal('add')">
                                <i class="bi bi-plus-circle-fill me-1"></i> THÊM PHÒNG HỌC
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">STT</th>
                                        <th class="py-3">TÊN PHÒNG</th>
                                        <th class="py-3">VỊ TRÍ</th>
                                        <th class="text-center py-3">SỨC CHỨA</th>
                                        <th class="text-center py-3">LOẠI</th>
                                        <th class="text-center py-3">TRẠNG THÁI</th>
                                        <th class="text-end pe-4 py-3">HÀNH ĐỘNG</th>
                                    </tr>
                                </thead>
                                <tbody id="roomTableBody">
                                    <?php if (count($rooms) > 0): ?>
                                        <?php foreach ($rooms as $idx => $room): ?>
                                            <?php
                                                $typeLabel = match($room['room_type']) {
                                                    'lab'      => '<span class="badge bg-warning text-dark">Lab</span>',
                                                    'computer' => '<span class="badge bg-info text-dark">Phòng máy</span>',
                                                    default    => '<span class="badge bg-secondary">Thường</span>'
                                                };
                                                $statusBadge = $room['is_active']
                                                    ? '<span class="badge class-status-open">Có thể sử dụng</span>'
                                                    : '<span class="badge bg-danger">Đang bảo trì</span>';
                                            ?>
                                            <tr>
                                                <td class="ps-4 text-muted"><?= $idx + 1 ?></td>
                                                <td class="fw-bold"><?= e($room['room_name']) ?><br><small class="text-muted fw-normal"><?= e($room['room_code']) ?></small></td>
                                                <td class="text-muted"><?= e($room['building'] ?? '--') ?></td>
                                                <td class="text-center"><?= (int)$room['capacity'] ?> chỗ</td>
                                                <td class="text-center"><?= $typeLabel ?></td>
                                                <td class="text-center"><?= $statusBadge ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-light action-btn text-primary border me-1" title="Sửa phòng"
                                                        onclick='openRoomModal("edit",<?= json_encode($room) ?>)'
                                                        data-bs-toggle="modal" data-bs-target="#roomModal">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form method="POST" action="../../controllers/admin/roomController.php" class="d-inline"
                                                        onsubmit="return confirm('Xóa phòng <?= e($room['room_code']) ?>?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= e($room['id']) ?>">
                                                        <button class="btn btn-light action-btn text-danger border" title="Xóa phòng" type="submit">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">Chưa có phòng học nào.</td></tr>
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
                <form id="classForm" method="POST" action="../../controllers/admin/classController.php" onsubmit="return handleDataSubmit(event, 'Lớp học', 'classModal')">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="classIdInput" value="">
          <div class="mb-3">
            <label class="form-label fw-bold">Tên Lớp (Mã Lớp) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control border-primary text-uppercase" name="class_name" id="modalClassName" placeholder="VD: 25TH01" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Ngành học</label>
                        <select class="form-select border-secondary" name="department_id" id="modalDepartment">
                <option value="">-- Chọn ngành --</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Niên Khóa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control border-secondary" name="academic_year" id="modalClassAcademicYear" placeholder="VD: 2025 - 2029" required>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">Trạng Thái Lớp Học <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                                <input class="form-check-input border-success" type="radio" name="class_status" id="classStatusOpen" value="open" checked>
                <label class="form-check-label" for="classStatusOpen">Đang mở</label>
              </div>
              <div class="form-check">
                                <input class="form-check-input border-danger" type="radio" name="class_status" id="classStatusClosed" value="closed">
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
            <form method="POST" action="../../controllers/admin/importController.php" enctype="multipart/form-data" id="importClassForm">
                <input type="hidden" name="action" value="import_classes">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel-fill me-2"></i>Import Danh sách Lớp</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info small border-0 py-2">
            <i class="bi bi-info-circle-fill me-1"></i> Tạo hàng loạt lớp học từ file CSV/Excel.
            <br><strong>Cấu trúc cột bắt buộc:</strong>
            <ul class="mb-0 mt-1">
                <li><strong>Cột A:</strong> Tên lớp (VD: 25TH01, 26CNTT)</li>
                <li><strong>Cột B:</strong> Niên khóa (VD: 2025-2029)</li>
            </ul>
        </div>
        <div class="alert alert-warning small border-warning border-opacity-50 py-2">
            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
            <strong>Lưu ý:</strong> Lớp đã tồn tại sẽ được cập nhật niên khóa mới.
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Chọn file (.csv, .xlsx, .xls) <span class="text-danger">*</span></label>
            <input type="file" class="form-control border-success" name="import_file" id="importClassFileInput" accept=".csv,.xlsx,.xls" required>
            <div class="form-text small mt-2">
                <strong>Hướng dẫn:</strong> Dòng đầu tiên sẽ tự động bỏ qua nếu là tiêu đề cột.
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="submit" class="btn btn-success fw-bold px-4" id="importClassBtn">
            <i class="bi bi-upload me-1"></i>TIẾN HÀNH IMPORT
        </button>
      </div>
            </form>
    </div>
  </div>
</div>

<!-- Modal Import Sinh Viên -->
<div class="modal fade" id="importStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
        <form method="POST" action="../../controllers/admin/importController.php" enctype="multipart/form-data" id="importStudentForm">
            <input type="hidden" name="action" value="import_students">
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
            <select class="form-select border-primary fw-bold text-dark" id="importStudentClassSelect" name="class_name" required>
                <option value="" disabled selected>-- Vui lòng chọn lớp để import sinh viên --</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo e($class['class_name']); ?>"><?php echo e($class['class_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
                        <label class="form-label fw-bold">Chọn file trúng tuyển (.csv, .xlsx, .xls) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control border-dark" name="import_file" id="importStudentFileInput" accept=".csv,.xlsx,.xls" required>
            <div class="form-text small mt-2">
                <strong>Cấu trúc bắt buộc:</strong> [STT] | [MSSV] | [Họ và tên] | [Ngày sinh] | [Email]
                <br>Dòng đầu tiên sẽ tự động bỏ qua nếu là tiêu đề cột.
            </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="submit" class="btn btn-dark fw-bold px-4" id="importStudentBtn"><i class="bi bi-cloud-upload-fill me-2"></i>TẢI LÊN & ĐỒNG BỘ</button>
      </div>
        </form>
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
                <form id="subjectForm" method="POST" action="../../controllers/admin/subjectController.php" onsubmit="return handleDataSubmit(event, 'Môn học', 'subjectModal')">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="subjectIdInput" value="">
          <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-bold">Mã MH <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-success text-uppercase" name="subject_code" id="modalSubjectCode" placeholder="VD: IT001" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Số Tín Chỉ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control border-secondary" name="credits" id="modalCredits" placeholder="VD: 3" min="1" max="10" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Trạng Thái</label>
                <div id="modalSubjectStatusDisplay" class="form-control border-secondary bg-light" style="cursor:not-allowed"></div>
                <input type="hidden" name="is_active" id="modalSubjectStatus" value="1">
              </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Năm học <span class="text-danger">*</span></label>
              <select class="form-select border-secondary" name="academic_year" id="modalAcademicYear" required>
                <option value="" disabled selected>-- Chọn năm học --</option>
                <?php foreach ($academicYears as $ay): ?>
                  <option value="<?php echo e($ay['academic_year']); ?>"><?php echo e($ay['academic_year']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Học Kỳ <span class="text-danger">*</span></label>
              <select class="form-select border-secondary" name="semester" id="modalSemester" required>
                <option value="" disabled selected>-- Chọn học kỳ --</option>
                <option value="HK1">HK1</option>
                <option value="HK2">HK2</option>
                <option value="HK3">HK3</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Dành cho <span class="text-danger">*</span></label>
              <select class="form-select border-secondary" name="year_level" id="modalYearLevel" required>
                <option value="" disabled selected>-- Chọn năm --</option>
                <option value="1">Năm 1</option>
                <option value="2">Năm 2</option>
                <option value="3">Năm 3</option>
                <option value="4">Năm 4</option>
                <option value="5">Năm 5</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Tên Môn Học <span class="text-danger">*</span></label>
            <input type="text" class="form-control border-secondary" name="subject_name" id="modalSubjectName" placeholder="VD: An ninh cơ sở dữ liệu" required>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Môn Tiên Quyết <span class="text-muted fw-normal small">(Nhập mã MH - VD: IT001)</span></label>
            <input type="text" class="form-control border-secondary" name="prerequisite_code" id="modalPrerequisiteCode" placeholder="VD: IT001 hoặc để trống nếu không có">
            <div class="form-text small">Để trống nếu không có môn tiên quyết.</div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Ngày bắt đầu</label>
              <input type="date" class="form-control border-secondary" name="open_date" id="modalOpenDate">
              <div class="form-text small text-muted">Ngày bắt đầu dạy.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Ngày kết thúc</label>
              <input type="date" class="form-control border-secondary" name="close_date" id="modalCloseDate">
              <div class="form-text small text-muted">Để trống nếu chưa xác định ngày kết thúc.</div>
            </div>
          </div>


          <div class="text-end mt-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-success fw-bold px-4" id="subjectModalBtn">LƯU MÔN HỌC</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Xem Lịch Sử Mở/Đóng Môn Học -->
<div class="modal fade" id="subjectHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fw-bold" id="subjectHistoryModalTitle">
          <i class="bi bi-clock-history me-2"></i>Lịch sử Môn đang xem
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Năm học + Học kỳ (readonly) -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold small text-muted">Năm học</label>
            <div class="form-control border-secondary bg-light fw-bold" id="historyAcademicYear"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold small text-muted">Học kỳ</label>
            <div class="form-control border-secondary bg-light fw-bold" id="historySemester"></div>
          </div>
        </div>

        <!-- Trạng thái + Thời gian (text view) -->
        <input type="hidden" id="historyOpenDate" value="">
        <input type="hidden" id="historyCloseDate" value="">
        <div class="row mb-3">
          <div class="col-12">
            <span class="fw-bold text-dark small" id="historyStatusTimeText"></span>
          </div>
        </div>

        <!-- Thiết lập Thời gian Mở/Đóng -->
        <div class="card border-secondary mb-3">
          <div class="card-header bg-secondary text-white py-2">
            <span class="fw-bold small"><i class="bi bi-calendar-event me-1"></i>Thiết lập Thời gian Mở/Đóng</span>
          </div>
          <div class="card-body py-3">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">Ngày bắt đầu</label>
                <input type="date" class="form-control border-secondary" id="historySetOpenDate">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">Ngày kết thúc</label>
                <input type="date" class="form-control border-secondary" id="historySetCloseDate">
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
          <button type="button" class="btn btn-primary fw-bold px-4" onclick="saveSubjectHistory()">
            <i class="bi bi-check-lg me-1"></i>LƯU THAY ĐỔI
          </button>
        </div>

        <!-- Lịch sử Mở/Đóng -->
        <div class="fw-bold text-dark small mb-2">
          <i class="bi bi-clock-history me-1"></i>Lịch sử Mở/Đóng Môn
        </div>
        <div class="table-responsive border rounded" style="max-height:300px;overflow-y:auto;">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th class="text-center" style="width:40px">STT</th>
                <th>Năm học</th>
                <th>Học kỳ</th>
                <th>Mở từ</th>
                <th>Đóng vào</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody id="subjectHistoryTableBody">
              <tr><td colspan="6" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Đang tải...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Thêm/Sửa Phòng Học -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="roomModalTitle"><i class="bi bi-door-open-fill me-2"></i>Thêm Phòng Học</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form id="roomForm" method="POST" action="../../controllers/admin/roomController.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="roomIdInput" value="">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Mã phòng <span class="text-danger">*</span></label>
                    <input type="text" class="form-control border-primary text-uppercase fw-bold" name="room_code" id="modalRoomCode" placeholder="VD: A201" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Sức chứa</label>
                    <input type="number" class="form-control border-secondary" name="capacity" id="modalRoomCapacity" min="1" max="500" value="40">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Trạng thái</label>
                    <select class="form-select border-secondary" name="is_active" id="modalRoomActive">
                        <option value="1">Có thể sử dụng</option>
                        <option value="0">Đang bảo trì</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Tên phòng đầy đủ <span class="text-danger">*</span></label>
                <input type="text" class="form-control border-secondary" name="room_name" id="modalRoomName" placeholder="VD: Phòng A201" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Vị trí</label>
                    <input type="text" class="form-control border-secondary" name="building" id="modalRoomBuilding" placeholder="VD: Tòa A, Tầng 2">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Loại phòng</label>
                    <select class="form-select border-secondary" name="room_type" id="modalRoomType">
                        <option value="lecture">Thường</option>
                        <option value="lab">Lab</option>
                        <option value="computer">Phòng máy</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Ghi chú</label>
                <textarea class="form-control border-secondary" name="note" id="modalRoomNote" rows="2" placeholder="Ghi chú thêm (tùy chọn)"></textarea>
            </div>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" id="roomModalBtn">LƯU PHÒNG HỌC</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // Mở modal phòng học
    function openRoomModal(mode, roomData = null) {
        document.getElementById('roomModalTitle').innerHTML = mode === 'add'
            ? '<i class="bi bi-plus-circle me-2"></i>Thêm Phòng Học Mới'
            : '<i class="bi bi-pencil-square me-2"></i>Chỉnh Sửa Phòng Học';
        document.getElementById('roomIdInput').value  = roomData?.id || '';
        document.getElementById('modalRoomCode').value = roomData?.room_code || '';
        document.getElementById('modalRoomName').value = roomData?.room_name || '';
        document.getElementById('modalRoomBuilding').value = roomData?.building || '';
        document.getElementById('modalRoomCapacity').value = roomData?.capacity || 40;
        document.getElementById('modalRoomType').value  = roomData?.room_type || 'lecture';
        document.getElementById('modalRoomActive').value = (roomData?.is_active !== undefined) ? roomData.is_active : 1;
        document.getElementById('modalRoomNote').value  = roomData?.note || '';
        document.getElementById('modalRoomCode').readOnly = mode === 'edit';
    }

    // Tìm kiếm phòng học
    document.getElementById('searchRoom').addEventListener('keyup', function() {
        const v = this.value.toLowerCase();
        document.querySelectorAll('#roomTableBody tr').forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(v) ? '' : 'none';
        });
    });

    // Auto-switch tab khi redirect về từ controller (tab=room, tab=subject)
    (function () {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab === 'room') {
            const btn = document.getElementById('room-tab');
            if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        } else if (tab === 'subject') {
            const btn = document.getElementById('subject-tab');
            if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    })();

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

    function openClassModal(mode, className = '', year = '', status = 'open', id = '') {
        const title = document.getElementById('classModalTitle');
        const btn = document.getElementById('classModalBtn');
        const inputName = document.getElementById('modalClassName');
        const inputId = document.getElementById('classIdInput');
        
        if (mode === 'add') {
            title.innerText = 'Thêm Lớp Hành Chính';
            btn.innerText = 'LƯU LỚP HỌC';
            document.getElementById('classForm').reset();
            inputName.removeAttribute('readonly');
            inputId.value = '';
            document.getElementById('classStatusOpen').checked = true;
        } else {
            title.innerText = 'Chỉnh sửa Lớp Hành Chính';
            btn.innerText = 'CẬP NHẬT LỚP';
            inputName.value = className;
            inputName.setAttribute('readonly', 'true'); 
            document.getElementById('modalClassAcademicYear').value = year;
            inputId.value = id;
            
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

    function semesterLabel(val) {
        const map = {HK1:'HK1',HK2:'HK2',HK3:'HK3'};
        return map[val] || val || '';
    }

    function openSubjectModal(mode, subCode = '', subName = '', prereqCode = '', credits = '', id = '', isActive = '1', yearLevel = '', semester = '', openDate = '', closeDate = '', academicYear = '') {
        const title = document.getElementById('subjectModalTitle');
        const btn = document.getElementById('subjectModalBtn');
        const inputCode = document.getElementById('modalSubjectCode');
        const inputId = document.getElementById('subjectIdInput');
        const statusDisplay = document.getElementById('modalSubjectStatusDisplay');
        const statusHidden = document.getElementById('modalSubjectStatus');
        const openDateInput = document.getElementById('modalOpenDate');
        const closeDateInput = document.getElementById('modalCloseDate');

        if (mode === 'add') {
            title.innerText = 'Thêm Môn Học Mới';
            btn.innerText = 'LƯU MÔN HỌC';
            document.getElementById('subjectForm').reset();
            inputCode.removeAttribute('readonly');
            inputId.value = '';
            // Mặc định "Đang mở" khi thêm mới
            statusHidden.value = '1';
            statusDisplay.textContent = 'Đang mở';
            statusDisplay.className = 'form-control border-secondary bg-success text-white fw-bold';
            document.getElementById('modalAcademicYear').value = '';
            document.getElementById('modalYearLevel').value = '';
            document.getElementById('modalSemester').value = '';
        } else {
            title.innerText = 'Chỉnh sửa Môn Học';
            btn.innerText = 'CẬP NHẬT MÔN';
            inputCode.value = subCode;
            inputCode.setAttribute('readonly', 'true');
            document.getElementById('modalSubjectName').value = subName;
            document.getElementById('modalCredits').value = credits;
            document.getElementById('modalPrerequisiteCode').value = prereqCode || '';
            statusHidden.value = isActive || '1';
            if (isActive == '1') {
                statusDisplay.textContent = 'Đang mở';
                statusDisplay.className = 'form-control border-secondary bg-success text-white fw-bold';
            } else {
                statusDisplay.textContent = 'Đã đóng';
                statusDisplay.className = 'form-control border-secondary bg-secondary text-white fw-bold';
            }
            // Readonly display
            document.getElementById('modalAcademicYear').value = academicYear || '';
            document.getElementById('modalYearLevel').value = yearLevel || '';
            document.getElementById('modalSemester').value = semester || '';
                openDateInput.value = openDate || '';
            closeDateInput.value = closeDate || '';
            inputId.value = id;
        }

        // Ràng buộc: ngày kết thúc không được trước ngày bắt đầu
        openDateInput.min = '';
        closeDateInput.min = '';
        if (openDateInput.value) {
            closeDateInput.min = openDateInput.value;
            if (closeDateInput.value && closeDateInput.value < openDateInput.value) {
                closeDateInput.value = openDateInput.value;
            }
        }
    }

    // --- Modal Lịch Sử Mở/Đóng ---
    let currentHistorySubjectId = null;

    function fmtDate(d) {
        if (!d) return '—';
        const [y, mo, day] = d.split('-');
        return `${day}/${mo}/${y}`;
    }

    function openSubjectHistoryModal2(id, label, academicYear, semester, openDate, closeDate, status) {
        currentHistorySubjectId = id;
        document.getElementById('subjectHistoryModalTitle').innerHTML =
            '<i class="bi bi-clock-history me-2"></i>Lịch sử: ' + label;
        document.getElementById('historyAcademicYear').textContent = academicYear || '—';
        document.getElementById('historySemester').textContent = semesterLabel(semester);
        // Hidden inputs cho ngày
        document.getElementById('historyOpenDate').value = openDate || '';
        document.getElementById('historyCloseDate').value = closeDate || '';
        // Card inputs
        document.getElementById('historySetOpenDate').value = openDate || '';
        document.getElementById('historySetCloseDate').value = closeDate || '';
        // Ràng buộc ngày card
        const o = document.getElementById('historySetOpenDate');
        const c = document.getElementById('historySetCloseDate');
        o.min = ''; c.min = '';
        o.addEventListener('change', function() { c.min = o.value; if (c.value && c.value < o.value) c.value = o.value; });
        renderStatusTimeText(status, openDate, closeDate);
        loadHistoryTable(id);
        new bootstrap.Modal(document.getElementById('subjectHistoryModal')).show();
    }

    function renderStatusTimeText(status, openDate, closeDate) {
        const stLabel = status == '1' ? 'Đang mở' : 'Đã đóng';
        const stColor = status == '1' ? 'success' : 'secondary';
        const from = fmtDate(openDate);
        const to = fmtDate(closeDate);
        document.getElementById('historyStatusTimeText').innerHTML =
            `<span class="fw-bold">Trạng thái:</span> <span class="badge bg-${stColor}">${stLabel}</span>
             <span class="mx-3 fw-bold">|</span>
             <span class="fw-bold">Thời gian:</span>
             Từ: <strong>${from}</strong> Đến: <strong>${to}</strong>`;
    }

    function saveSubjectHistory() {
        const subjectId = currentHistorySubjectId;
        const openDate = document.getElementById('historySetOpenDate').value;
        const closeDate = document.getElementById('historySetCloseDate').value;
        const todayStr = new Date().toISOString().split('T')[0];
        let status = '1';
        if (!openDate || openDate > todayStr || (closeDate && closeDate < todayStr)) {
            status = '0';
        }
        const params = new URLSearchParams({
            action: 'update_status',
            subject_id: subjectId,
            open_date: openDate,
            close_date: closeDate,
            status: status
        });
        fetch('../../controllers/admin/subjectController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                renderStatusTimeText(status, openDate, closeDate);
                loadHistoryTable(subjectId);
                const tr = document.querySelector(`tr[data-subject-id="${subjectId}"]`);
                if (tr) {
                    const statusCell = tr.querySelector('.subject-status-cell');
                    if (statusCell) {
                        statusCell.innerHTML = status == '1'
                            ? '<span class="badge class-status-open">Đang mở</span>'
                            : '<span class="badge bg-secondary">Đã đóng</span>';
                    }
                }
                alert('Đã lưu thay đổi!');
            } else {
                alert('Lỗi: ' + (data.msg || 'Không thể lưu.'));
            }
        })
        .catch(() => alert('Lỗi kết nối!'));
    }

    function loadHistoryTable(subjectId) {
        const tbody = document.getElementById('subjectHistoryTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Đang tải...</td></tr>';
        fetch('../../controllers/admin/subjectController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams({ action: 'get_history', subject_id: subjectId }).toString()
        })
        .then(r => {
            if (!r.ok) {
                throw new Error('Network response was not ok');
            }
            return r.json();
        })
        .then(data => {
            if (!data.ok || !data.history || !data.history.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><i class="bi bi-inbox me-1"></i>Chưa có lịch sử</td></tr>';
                return;
            }
            tbody.innerHTML = data.history.map((h, i) => {
                const icon = h.action_type === 'open'
                    ? '<span class="badge bg-success-subtle text-success"><i class="bi bi-play-fill"></i></span>'
                    : h.action_type === 'close'
                    ? '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-lg"></i></span>'
                    : '<span class="badge bg-warning-subtle text-warning"><i class="bi bi-calendar-event"></i></span>';
                const statusBadge = h.new_status == '1'
                    ? '<span class="badge bg-success">Đang mở</span>'
                    : '<span class="badge bg-secondary">Đã đóng</span>';
                return `<tr>
                    <td class="text-center text-muted">${i + 1}</td>
                    <td class="small">${h.academic_year || '—'}</td>
                    <td class="small">${semesterLabel(h.semester) || '—'}</td>
                    <td class="small">${fmtDate(h.new_open_date)}</td>
                    <td class="small">${fmtDate(h.new_close_date)}</td>
                    <td>${statusBadge}</td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3"><i class="bi bi-x-circle me-1"></i>Lỗi tải dữ liệu</td></tr>';
        });
    }

    function handleDataSubmit(event, entityName, modalId) {
        const action = event.submitter.innerText.includes('CẬP NHẬT') ? 'cập nhật' : 'thêm mới';
        
        if(confirm(`Bạn có chắc chắn muốn ${action} dữ liệu [${entityName}] này vào hệ thống?`)) {
            return true;
        }
        event.preventDefault();
        return false;
    }

    function confirmDelete(entityName, itemName) {
        if(confirm(`Bạn có chắc chắn muốn xóa [${entityName}: ${itemName}] không? Hành động này có thể ảnh hưởng đến các lớp học phần và sinh viên đang liên kết với nó.`)) {
            alert(`Đã xóa ${entityName} [${itemName}] khỏi hệ thống.`);
        }
    }

    // Validate import class file before submission
    document.getElementById('importClassForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('importClassFileInput');
        const file = fileInput.files[0];

        if (!file) {
            e.preventDefault();
            alert('Vui lòng chọn file để import!');
            return false;
        }

        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File quá lớn! Vui lòng chọn file có kích thước nhỏ hơn 5MB.');
            return false;
        }

        // Show loading state
        const btn = document.getElementById('importClassBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    });

    // Validate import student file before submission
    document.getElementById('importStudentForm').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('importStudentFileInput');
        const file = fileInput ? fileInput.files[0] : null;
        const classSelect = document.getElementById('importStudentClassSelect');

        if (classSelect && !classSelect.value) {
            e.preventDefault();
            alert('Vui lòng chọn lớp học!');
            return false;
        }

        if (!file) {
            e.preventDefault();
            alert('Vui lòng chọn file để import!');
            return false;
        }

        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File quá lớn! Vui lòng chọn file có kích thước nhỏ hơn 5MB.');
            return false;
        }

        // Show loading state
        const btn = document.getElementById('importStudentBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
        }
    });

    // Auto-hide alerts after 4 seconds with smooth collapse, then redirect to clear URL params
    setTimeout(function() {
        const alert = document.getElementById('permanentAlert');
        if (alert) {
            alert.style.transition = 'opacity 0.5s ease, max-height 0.5s ease, margin 0.5s ease, padding 0.5s ease';
            alert.style.opacity = '0';
            alert.style.maxHeight = '0';
            alert.style.marginBottom = '0';
            alert.style.paddingTop = '0';
            alert.style.paddingBottom = '0';
            setTimeout(function() {
                alert.remove();
                // Redirect to same page but remove import params from URL
                const url = new URL(window.location.href);
                url.searchParams.delete('import_done');
                url.searchParams.delete('import_ok');
                url.searchParams.delete('import_skip');
                url.searchParams.delete('import_err');
                url.searchParams.delete('import_code');
                url.searchParams.delete('class_success');
                window.history.replaceState({}, '', url);
            }, 500);
        }
    }, 4000);
</script>
</body>
</html>
