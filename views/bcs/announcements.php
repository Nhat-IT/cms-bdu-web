<?php
/**
 * CMS BDU - Thông Báo & Bản Tin (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Thông Báo & Bản Tin';

// Lấy thông tin lớp của BCS (hỗ trợ cả class_students và group_students)
$classInfo = getUserClassInfo($userId);
$classId = $classInfo['class_id'];
$className = $classInfo['class_name'];
$sourceType = $classInfo['source'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['news_action'])) {
    $action = strtolower(trim((string)($_POST['news_action'] ?? '')));
    $id = (int)($_POST['news_id'] ?? 0);
    $title = trim((string)($_POST['news_title'] ?? ''));
    $content = trim((string)($_POST['news_content'] ?? ''));

    try {
        if ($action === 'create') {
            if ($title !== '') {
                db_query(
                    "INSERT INTO notification_logs (user_id, title, message, is_read) VALUES (?, ?, ?, 1)",
                    [$userId, $title, $content !== '' ? $content : null]
                );

                if ($classId) {
                    $students = db_fetch_all(
                        "SELECT student_id FROM class_students WHERE class_id = ? AND student_id <> ?",
                        [$classId, $userId]
                    );
                    foreach ($students as $st) {
                        $sid = (int)($st['student_id'] ?? 0);
                        if ($sid <= 0) {
                            continue;
                        }
                        db_query(
                            "INSERT INTO notification_logs (user_id, title, message, is_read) VALUES (?, ?, ?, 0)",
                            [$sid, $title, $content !== '' ? $content : null]
                        );
                    }
                }
            }
        } elseif ($action === 'update' && $id > 0 && $title !== '') {
            db_query(
                "UPDATE notification_logs SET title = ?, message = ? WHERE id = ? AND user_id = ?",
                [$title, $content !== '' ? $content : null, $id, $userId]
            );
        } elseif ($action === 'delete' && $id > 0) {
            db_query("DELETE FROM notification_logs WHERE id = ? AND user_id = ?", [$id, $userId]);
        }
    } catch (Throwable $e) {
        error_log('bcs announcements save error: ' . $e->getMessage());
    }

    header('Location: announcements.php');
    exit;
}

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
$fullName = $currentUser['full_name'] ?? '';
$position = $currentUser['position'] ?? 'Ban Cán Sự';
$avatar = getAvatarUrl($currentUser['avatar'] ?? null, $fullName, 55);

// Lấy notification
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['total'] ?? 0;

// Lấy thông báo chung (hệ thống)
$stmt = $pdo->prepare("
    SELECT n.*, u.full_name as creator_name
    FROM notification_logs n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute();
$systemAnnouncements = $stmt->fetchAll();

// Lấy số bản tin đã đăng (do BCS tạo)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM notification_logs 
    WHERE user_id = ? AND title IS NOT NULL
");
$stmt->execute([$userId]);
$postedCount = $stmt->fetch()['total'] ?? 0;

// Lấy bản tin đã đăng của BCS
$stmt = $pdo->prepare("
    SELECT * FROM notification_logs 
    WHERE user_id = ? AND title IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$myAnnouncements = $stmt->fetchAll();
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
    <link rel="stylesheet" href="../../public/css/bcs/announcements.css">
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
            <a href="documents.php"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php" class="active"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>

            <div class="px-4 mt-3 mb-2 small text-white-50 fw-bold hide-on-collapse" style="font-size: 0.7rem; letter-spacing: 1px;">CÁ NHÂN</div>
            <a href="../switch-role.php?role=student&next=home" class="text-warning"><i class="bi bi-arrow-repeat"></i> Về Cổng Sinh Viên</a>
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
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3 announcements-header">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">THÔNG BÁO & BẢN TIN <span class="text-warning ms-2"><?= e($className) ?></span></h4>
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

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-danger border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-danger me-3 text-danger rounded p-2"><i class="bi bi-bell-fill fs-4"></i></div>
                        <div>
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.85rem;">BẢN TIN ĐÃ ĐĂNG</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= $postedCount ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-primary border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary rounded p-2"><i class="bi bi-file-earmark-arrow-up-fill fs-4"></i></div>
                        <div>
                            <p class="text-primary fw-bold mb-1" style="font-size: 0.85rem;">KHOA CNTT</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= count($systemAnnouncements) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-success border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success rounded p-2"><i class="bi bi-send-check-fill fs-4"></i></div>
                        <div>
                            <p class="text-success fw-bold mb-1" style="font-size: 0.85rem;">LỚP <?= e($className) ?></p>
                            <h3 class="mb-0 fw-bold text-dark"><?= count($myAnnouncements) ?></h3>
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
                            <i class="bi bi-bell me-1"></i> Thông báo chung
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
                                <input type="text" class="form-control form-control-sm border-secondary" id="searchInput" placeholder="Tìm tiêu đề hoặc nguồn..." style="width: 220px;">
                                <select class="form-select form-select-sm w-auto border-secondary" id="filterSource">
                                    <option value="">Tất cả nguồn</option>
                                    <option>Phòng Đào tạo</option>
                                    <option>Phòng CTSV</option>
                                    <option>Khoa CNTT</option>
                                </select>
                            </div>
                        </div>

                        <div class="list-group">
                            <?php if (!empty($systemAnnouncements)): ?>
                            <?php foreach ($systemAnnouncements as $idx => $ann): ?>
                            <div class="list-group-item announcement-item border rounded-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <?php if ($idx < 3): ?>
                                            <span class="badge rounded-pill <?= $idx == 0 ? 'bg-warning text-dark' : 'announcement-pill' ?> px-3 py-2 fw-normal"><?= $idx == 0 ? 'Đang ghim' : 'Mới' ?></span>
                                            <?php else: ?>
                                            <span class="badge rounded-pill bg-success text-white px-3 py-2 fw-normal">Đã đọc</span>
                                            <?php endif; ?>
                                            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 px-3 py-2 fw-normal"><?= e($ann['creator_name'] ?? 'Hệ thống') ?></span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1"><?= e($ann['title'] ?? 'Thông báo') ?></h6>
                                        <p class="text-muted mb-2"><?= formatDateTime($ann['created_at']) ?></p>
                                        <p class="mb-0 text-dark"><?= e($ann['message'] ?? '') ?></p>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-outline-primary btn-sm fw-bold mb-2" onclick='openViewModal(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, "UTF-8") ?>)'><i class="bi bi-eye me-1"></i>Xem</button>
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <button class="btn btn-sm btn-light border" onclick='downloadAnnouncementFile(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, "UTF-8") ?>)'><i class="bi bi-download me-1"></i>Tải file</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fs-1"></i>
                                <p class="mt-2">Chưa có thông báo nào</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="publish-panel" role="tabpanel" aria-labelledby="publish-tab">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Đăng bản tin mới</h5>
                                <p class="text-muted mb-0">Soạn nội dung và đính kèm file để phát đến sinh viên trong lớp.</p>
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
                                            <div class="text-muted small">BCS nhập nội dung, đính kèm PDF/DOCX/XLSX và chọn phạm vi gửi.</div>
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
                            <div class="col-lg-7">
                                <div class="p-4 rounded-4 announcement-empty h-100">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-send-check text-primary me-2"></i>Soạn / cập nhật bản tin</h5>
                                            <p class="text-muted mb-0">Người đăng tin có thể sửa các tin đã đăng hoặc tạo tin mới ngay tại đây.</p>
                                        </div>
                                        <span class="badge bg-white text-primary border border-primary px-3 py-2">Có quyền chỉnh sửa</span>
                                    </div>

                                    <form id="newsForm">
                                        <input type="hidden" id="newsId" value="">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Tiêu đề bản tin</label>
                                            <input type="text" class="form-control" id="newsTitle" placeholder="VD: Thông báo học bù tuần 8" required>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Nguồn phát hành</label>
                                                <select class="form-select" id="newsSource">
                                                    <option selected>Phòng Đào tạo</option>
                                                    <option>Phòng CTSV</option>
                                                    <option>Khoa CNTT</option>
                                                    <option>Ban Giám hiệu</option>
                                                    <option value="Khac">Khác</option>
                                                </select>
                                                <div class="mt-2 d-none" id="customNewsSourceWrap">
                                                    <input type="text" class="form-control" id="customNewsSource" placeholder="Nhập nguồn phát hành...">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Phạm vi gửi</label>
                                                <select class="form-select" id="newsScope">
                                                    <option>Khoa CNTT</option>
                                                    <option selected>Lớp <?= e($className) ?></option>                                                
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
                                                    <option value="Khac">Khác</option>
                                                </select>
                                                <div class="mt-2 d-none" id="customNewsCategoryWrap">
                                                    <input type="text" class="form-control" id="customNewsCategory" placeholder="Nhập loại nội dung...">
                                                </div>
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

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Thời điểm đăng</label>
                                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="publishMode" id="publishNow" value="now" checked>
                                                    <label class="form-check-label fw-semibold" for="publishNow">Đăng ngay</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="publishMode" id="publishSchedule" value="schedule">
                                                    <label class="form-check-label fw-semibold" for="publishSchedule">Hẹn ngày giờ đăng</label>
                                                </div>
                                            </div>
                                            <div class="mt-2 d-none" id="schedulePublishWrap">
                                                <div class="schedule-grid">
                                                    <div>
                                                        <div class="schedule-label">Ngày đăng</div>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                                            <input type="date" class="form-control" id="schedulePublishDate">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="schedule-label">Giờ đăng</div>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light"><i class="bi bi-clock"></i></span>
                                                            <input type="time" class="form-control" id="schedulePublishTime">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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

                            <div class="col-lg-5">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Bản tin đã đăng</h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2">Có thể sửa</span>
                                        </div>
                                        <div class="d-grid gap-3">
                                            <?php if (!empty($myAnnouncements)): ?>
                                            <?php foreach ($myAnnouncements as $myAnn): ?>
                                            <div class="p-3 rounded-3 bg-light border">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <div class="fw-bold text-dark mb-1"><?= e($myAnn['title']) ?></div>
                                                        <div class="text-muted small">Đã đăng: <?= formatDate($myAnn['created_at']) ?></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="openEditModal(<?= htmlspecialchars(json_encode($myAnn)) ?>)"><i class="bi bi-pencil-square me-1"></i>Sửa</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $myAnn['id'] ?>)"><i class="bi bi-trash me-1"></i>Xóa</button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-1"></i>
                                                <p class="mt-2">Chưa có bản tin nào được đăng</p>
                                            </div>
                                            <?php endif; ?>
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

<div class="modal fade" id="announcementDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye me-2"></i>Chi tiết thông báo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold text-dark mb-2" id="announcementDetailTitle">-</h6>
                <div class="text-muted small mb-3" id="announcementDetailMeta">-</div>
                <div class="p-3 bg-light border rounded text-dark" id="announcementDetailContent">-</div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="newsModalTitle"><i class="bi bi-pencil-square me-2"></i>Đăng bản tin</h5>
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
            <div class="modal-footer border-0 pb-4 px-4 bg-light justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentAnnouncement()"><i class="bi bi-trash me-1"></i>Xóa</button>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-primary fw-bold" onclick="saveEditedAnnouncement()"><i class="bi bi-check2-all me-1"></i>Lưu thay đổi</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/announcements.js"></script>
</body>
</html>
