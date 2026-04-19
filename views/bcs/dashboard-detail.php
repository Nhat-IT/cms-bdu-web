<?php
/**
 * CMS BDU - Dashboard Chi Tiết Lớp (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Dashboard Chi Tiết Lớp';

// Lấy class_id của BCS từ class_students
$stmt = $pdo->prepare("
    SELECT cs.class_id, c.class_name 
    FROM class_students cs
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.student_id = ?
");
$stmt->execute([$userId]);
$classInfo = $stmt->fetch();
$classId = $classInfo['class_id'] ?? null;
$className = $classInfo['class_name'] ?? '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$fullName = $currentUser['full_name'] ?? '';
$position = $currentUser['position'] ?? 'Ban Cán Sự';
$avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 55);

// Lấy tổng sinh viên
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM class_students WHERE class_id = ?");
$stmt->execute([$classId]);
$totalStudents = $stmt->fetch()['total'] ?? 0;

// Lấy số SV cảnh báo (vắng >= 3 buổi trên 1 môn)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ar.student_id) as total
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs ON cs.student_id = ssr.student_id AND cs.class_id = ?
    WHERE ar.status = 3
    GROUP BY ar.student_id, csg.class_subject_id
    HAVING COUNT(*) >= 3
");
$stmt->execute([$classId]);
$warningStudents = $stmt->fetchAll();
$warningStudentCount = count($warningStudents);

// Lấy số môn cảnh báo
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT csg.class_subject_id) as total
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs ON cs.student_id = ssr.student_id AND cs.class_id = ?
    WHERE ar.status = 3
    GROUP BY csg.class_subject_id
    HAVING COUNT(*) >= 3
");
$stmt->execute([$classId]);
$warningSubjects = $stmt->fetchAll();
$warningSubjectCount = count($warningSubjects);

// Lấy chi tiết vắng học theo sinh viên
$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name, u.email as student_code,
           s.subject_name, a_s.attendance_date, csg.study_session,
           ar.status, ae.id as evidence_id, ae.status as evidence_status
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
    JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
    JOIN users u ON ar.student_id = u.id
    LEFT JOIN attendance_evidences ae ON ae.attendance_record_id = ar.id
    WHERE ar.status = 3
    ORDER BY u.full_name, a_s.attendance_date DESC
    LIMIT 50
");
$stmt->execute([$classId]);
$absenceDetails = $stmt->fetchAll();

// Đếm notification
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['total'] ?? 0;

// Nhóm absence theo student
$groupedAbsences = [];
foreach ($absenceDetails as $abs) {
    $sid = $abs['student_id'];
    if (!isset($groupedAbsences[$sid])) {
        $groupedAbsences[$sid] = [
            'student_name' => $abs['full_name'],
            'student_code' => $abs['student_code'],
            'absences' => []
        ];
    }
    $groupedAbsences[$sid]['absences'][] = $abs;
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
    <style>
        .custom-detail-table th,
        .custom-detail-table td {
            border-right: 1px solid #dee2e6;
        }

        .custom-detail-table thead th {
            border-top: 1px solid #dee2e6;
        }

        .custom-detail-table th:first-child,
        .custom-detail-table td:first-child {
            border-left: 1px solid #dee2e6;
        }

        .custom-detail-table th:last-child,
        .custom-detail-table td:last-child {
            border-right: none;
        }
    </style>
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
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">THỐNG KÊ CHI TIẾT</h4>
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
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-primary border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary rounded p-2"><i class="bi bi-people-fill fs-4"></i></div>
                        <div>
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">TỔNG SINH VIÊN</p>
                            <h2 class="mb-0 fw-bold text-dark"><?= $totalStudents ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-danger border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger rounded p-2"><i class="bi bi-person-x-fill fs-4"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.85rem;">SV CẢNH BÁO</p>
                            <h2 class="mb-0 fw-bold text-danger"><?= $warningStudentCount ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-warning border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-warning me-3 text-warning rounded p-2"><i class="bi bi-journal-x fs-4"></i></div>
                        <div>
                            <p class="text-warning fw-bold mb-1" style="font-size: 0.85rem;">MÔN CẢNH BÁO</p>
                            <h2 class="mb-0 fw-bold text-warning"><?= $warningSubjectCount ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Chi tiết vắng học theo sinh viên</h5>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm border-secondary" id="searchInput" placeholder="Tìm MSSV hoặc Tên..." style="width: 200px;">
                    <button class="btn btn-success btn-sm fw-bold shadow-sm" onclick="exportDetailExcel()"><i class="bi bi-file-earmark-excel me-1"></i> Xuất Excel</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 custom-detail-table" id="attendanceTable">
                        <thead class="text-muted small fw-bold" style="background-color: #f8f9fa;">
                            <tr>
                                <th class="text-center py-3 border-end">STT</th>
                                <th class="py-3">HỌ VÀ TÊN</th>
                                <th class="py-3">MÔN HỌC</th>
                                <th class="py-3">NGÀY VẮNG</th>
                                <th class="py-3">BUỔI</th>
                                <th class="py-3">TRẠNG THÁI</th>
                                <th class="text-center py-3">MINH CHỨNG</th>
                                <th class="text-center py-3 border-start">TỔNG VẮNG (MÔN)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($groupedAbsences)): ?>
                            <?php $stt = 1; foreach ($groupedAbsences as $studentId => $data): ?>
                            <?php $rowCount = count($data['absences']); $first = true; foreach ($data['absences'] as $idx => $abs): ?>
                            <tr>
                                <?php if ($first): ?>
                                <td rowspan="<?= $rowCount ?>" class="text-center align-middle bg-white border-end"><?= $stt ?></td>
                                <td rowspan="<?= $rowCount ?>" class="align-middle pe-4 bg-white border-end">
                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= e($data['student_name']) ?></div>
                                    <div class="text-muted small"><?= e($data['student_code']) ?></div>
                                </td>
                                <?php $first = false; endif; ?>
                                <td class="py-3<?= $idx > 0 ? ' border-bottom' : '' ?>">
                                    <span class="badge bg-white text-dark border border-secondary px-3 py-2 fw-normal rounded-1 shadow-sm"><?= e($abs['subject_name']) ?></span>
                                </td>
                                <td class="text-dark fw-bold<?= $idx > 0 ? ' border-bottom' : '' ?>"><?= formatDate($abs['attendance_date']) ?></td>
                                <td class="text-dark<?= $idx > 0 ? ' border-bottom' : '' ?>"><?= e($abs['study_session'] ?? 'Sáng') ?></td>
                                <td<?= $idx > 0 ? ' class="border-bottom"' : '' ?>>
                                    <?php if ($abs['status'] == 2): ?>
                                    <span class="text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Có phép</span>
                                    <?php else: ?>
                                    <span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Không phép</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center<?= $idx > 0 ? ' border-bottom' : '' ?>">
                                    <?php if ($abs['evidence_id']): ?>
                                    <button class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Xem minh chứng"><i class="bi bi-file-earmark-medical"></i></button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-light rounded-circle" title="Không có minh chứng" disabled><i class="bi bi-eye-slash text-muted"></i></button>
                                    <?php endif; ?>
                                </td>
                                <?php if ($idx == 0): ?>
                                <td rowspan="<?= $rowCount ?>" class="text-center align-middle fw-bold text-<?= $rowCount >= 3 ? 'danger' : 'warning' ?> fs-4 bg-white border-start"><?= $rowCount ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php $stt++; endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Không có dữ liệu vắng học</td>
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
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/dashboard-detail.js"></script>
</body>
</html>
