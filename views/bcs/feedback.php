<?php
/**
 * CMS BDU - Cổng Tương Tác (BCS)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('bcs');

$userId = $_SESSION['user_id'];
$pageTitle = 'Cổng Tương Tác';

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

// Lấy thống kê phản hồi
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM feedbacks f
    JOIN class_students cs ON f.student_id = cs.student_id
    WHERE cs.class_id = ?
");
$stmt->execute([$classId]);
$totalFeedback = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM feedbacks f
    JOIN class_students cs ON f.student_id = cs.student_id
    WHERE cs.class_id = ? AND f.status = 'Pending'
");
$stmt->execute([$classId]);
$pendingFeedback = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM feedbacks f
    JOIN class_students cs ON f.student_id = cs.student_id
    WHERE cs.class_id = ? AND f.status = 'Resolved'
");
$stmt->execute([$classId]);
$resolvedFeedback = $stmt->fetch()['total'] ?? 0;

// Lấy danh sách phản hồi
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name, u.username as student_code
    FROM feedbacks f
    JOIN users u ON f.student_id = u.id
    JOIN class_students cs ON f.student_id = cs.student_id
    WHERE cs.class_id = ?
    ORDER BY 
        CASE WHEN f.status = 'Pending' THEN 0 ELSE 1 END,
        f.updated_at DESC
    LIMIT 50
");
$stmt->execute([$classId]);
$feedbacks = $stmt->fetchAll();

// Đếm notification
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['total'] ?? 0;
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
        .feedback-content-preview {
            max-width: 320px;
        }

        .feedback-content-box {
            font-size: 0.95rem;
            line-height: 1.65;
            white-space: pre-line;
            word-break: break-word;
            max-height: 320px;
            overflow-y: auto;
            background: #f8fafc;
            border: 1px solid #d7dde7;
        }

        .btn-view-feedback {
            min-width: 86px;
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
            <a href="attendance.php"><i class="bi bi-calendar-check"></i> Quản lý Chuyên cần</a>
            <a href="documents.php"><i class="bi bi-folder2-open"></i> Kho Lưu Trữ</a>
            <a href="announcements.php"><i class="bi bi-megaphone-fill"></i> Thông báo & Bản tin</a>
            <a href="feedback.php" class="active"><i class="bi bi-chat-dots"></i> Cổng Tương Tác</a>
            
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
    
    <div class="top-navbar-blue d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">CỔNG TƯƠNG TÁC LỚP <span class="text-warning ms-2"><?= e($className) ?></span></h4>
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
                <div class="card stat-card-custom border-start border-primary border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-primary me-3 text-primary rounded p-2"><i class="bi bi-inbox-fill fs-4"></i></div>
                        <div>
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.85rem;">TỔNG PHẢN HỒI</p>
                            <h3 class="mb-0 fw-bold text-dark"><?= $totalFeedback ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-warning border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-warning me-3 text-warning rounded p-2"><i class="bi bi-hourglass-split fs-4"></i></div>
                        <div>
                            <p class="text-warning fw-bold mb-1" style="font-size: 0.85rem;">CHỜ XỬ LÝ</p>
                            <h3 class="mb-0 fw-bold text-warning"><?= $pendingFeedback ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-custom border-start border-success border-4 h-100 p-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-custom bg-light-success me-3 text-success rounded p-2"><i class="bi bi-check-circle-fill fs-4"></i></div>
                        <div>
                            <p class="text-success fw-bold mb-1" style="font-size: 0.85rem;">ĐÃ GIẢI QUYẾT</p>
                            <h3 class="mb-0 fw-bold text-success"><?= $resolvedFeedback ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark m-0"><i class="bi bi-envelope-paper me-2 text-primary"></i>Hộp thư góp ý từ Sinh viên</h5>
                
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm border-secondary" id="searchInput" placeholder="Tìm MSSV hoặc Tên..." style="width: 200px;">
                    <select class="form-select form-select-sm w-auto fw-bold text-secondary border-secondary" id="filterStatus">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="pending">Chờ xử lý</option>
                        <option value="resolved">Đã giải quyết</option>
                    </select>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle custom-detail-table mb-0" id="feedbackTable">
                        <thead class="text-muted small fw-bold" style="background-color: #f8f9fa;">
                            <tr>
                                <th class="ps-4 py-3">NGƯỜI GỬI</th>
                                <th class="py-3">CHỦ ĐỀ</th>
                                <th class="py-3">THỜI GIAN</th>
                                <th class="py-3">TRẠNG THÁI</th>
                                <th class="pe-4 py-3 text-end">XEM CHI TIẾT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($feedbacks)): ?>
                            <?php foreach ($feedbacks as $fb): ?>
                            <?php $isPending = $fb['status'] == 'Pending'; ?>
                            <?php
                                $rawContent = trim((string)($fb['content'] ?? ''));
                                $preview = mb_substr($rawContent, 0, 80);
                                $preview .= (mb_strlen($rawContent) > 80) ? '...' : '';
                            ?>
                            <tr class="<?= $isPending ? 'bg-light' : '' ?>" data-search="<?= e(strtolower(($fb['full_name'] ?? '') . ' ' . ($fb['student_code'] ?? '') . ' ' . ($fb['title'] ?? '') . ' ' . ($fb['content'] ?? ''))) ?>" data-status="<?= $isPending ? 'pending' : 'resolved' ?>">
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark"><?= e($fb['full_name']) ?></div>
                                    <div class="text-muted small">MSSV: <?= e($fb['student_code']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-1"><?= e($fb['title'] ?? 'Phản hồi') ?></div>
                                    <div class="text-muted small text-truncate feedback-content-preview"><?= e($preview) ?></div>
                                </td>
                                <td class="text-dark small"><?= formatDateTime($fb['updated_at']) ?></td>
                                <td>
                                    <?php if ($isPending): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-hourglass me-1"></i>Chờ xử lý</span>
                                    <?php else: ?>
                                    <span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>Đã giải quyết</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm <?= $isPending ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold shadow-sm btn-view-feedback" 
                                            data-bs-toggle="modal" data-bs-target="#feedbackModal" 
                                            onclick="openFeedbackModal(<?= htmlspecialchars(json_encode($fb, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-eye me-1"></i> Xem
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2">Chưa có phản hồi nào</p>
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

<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-envelope-open me-2"></i>Chi tiết Phản hồi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        
        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
            <div>
                <h6 class="fw-bold text-dark mb-1" id="fbSenderName">-</h6>
                <small class="text-muted" id="fbTime">-</small>
            </div>
            <div>
                <span class="badge bg-warning text-dark border border-warning" id="fbStatusBadge">Chờ xử lý</span>
            </div>
        </div>

        <h6 class="fw-bold text-primary mb-2" id="fbSubject">-</h6>
        <div class="p-3 rounded mb-3 text-dark feedback-content-box" id="fbContent">
            -
        </div>

        <div class="mb-4" id="fbReplyBox">
            <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-reply-fill me-1"></i>Trả lời / Ghi chú xử lý của BCS:</h6>
            <textarea class="form-control border-success" rows="3" placeholder="Nhập câu trả lời để phản hồi lại sinh viên..." id="fbReply"></textarea>
        </div>

      </div>
      <div class="modal-footer border-0 pb-4 px-4 bg-light justify-content-between">
        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteFeedback" onclick="deleteFeedback()"><i class="bi bi-trash me-1"></i>Xóa</button>
        <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            <button type="button" class="btn btn-success fw-bold px-4" id="btnMarkResolved" onclick="resolveFeedback()" data-bs-dismiss="modal"><i class="bi bi-check2-all me-1"></i>Lưu & Đánh dấu Đã giải quyết</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/bcs/bcs-layout.js"></script>
<script src="../../public/js/bcs/feedback.js"></script>
</body>
</html>
