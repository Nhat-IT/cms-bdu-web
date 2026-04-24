<?php
/**
 * CMS BDU - Phản hồi của tôi
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('student');

$userId = (int)($_SESSION['user_id'] ?? 0);
$pageTitle = 'Phản hồi của tôi';
$extraCss = ['layout.css', 'student/student-layout.css', 'student/my-feedback.css'];
$extraJs = ['student/student-layout.js'];

$submitSuccess = false;
$submitError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($action === 'submit_feedback') {
        $topic = trim((string)($_POST['topic'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));

        if ($topic === '' || $content === '') {
            $submitError = 'Vui lòng điền đầy đủ thông tin.';
        } else {
            try {
                db_query(
                    "INSERT INTO feedbacks (student_id, title, content, status) VALUES (?, ?, ?, 'Pending')",
                    [$userId, $topic, $content]
                );
                $submitSuccess = true;
            } catch (Throwable $e) {
                $submitError = 'Đã xảy ra lỗi khi gửi phản hồi.';
            }
        }
    }

    if ($action === 'update_feedback' || $action === 'delete_feedback') {
        header('Content-Type: application/json; charset=utf-8');
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);

        if ($feedbackId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Phản hồi không hợp lệ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            if ($action === 'update_feedback') {
                $topic = trim((string)($_POST['topic'] ?? ''));
                $content = trim((string)($_POST['content'] ?? ''));
                if ($topic === '' || $content === '') {
                    echo json_encode(['success' => false, 'error' => 'Vui lòng nhập đầy đủ nội dung'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                db_query(
                    "UPDATE feedbacks
                     SET title = ?, content = ?
                     WHERE id = ? AND student_id = ? AND status = 'Pending'",
                    [$topic, $content, $feedbackId, $userId]
                );

                echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }

            db_query(
                "DELETE FROM feedbacks
                 WHERE id = ? AND student_id = ? AND status = 'Pending'",
                [$feedbackId, $userId]
            );

            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Đã xảy ra lỗi xử lý dữ liệu'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$feedbacks = db_fetch_all(
    "SELECT id, title, content, status, reply_content, updated_at
     FROM feedbacks
     WHERE student_id = ?
     ORDER BY updated_at DESC",
    [$userId]
);

$totalFeedbacks = count($feedbacks);
$unreadNotifications = (int)db_count(
    "SELECT COUNT(*) as total FROM notification_logs WHERE user_id = ? AND is_read = 0",
    [$userId]
);
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
            <h5 class="m-0 text-white fw-bold d-flex align-items-center">CỔNG TƯƠNG TÁC SINH VIÊN</h5>
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
        <?php if ($submitSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Phản hồi của bạn đã được gửi thành công.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($submitError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= e($submitError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-4 border-0">
                        <h5 class="fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Gửi phản hồi mới</h5>
                        <p class="text-muted small">Mọi thắc mắc của bạn sẽ được xử lý sớm nhất.</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="submit_feedback">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Chủ đề <span class="text-danger">*</span></label>
                                <select class="form-select border-primary" name="topic" required>
                                    <option value="" selected disabled>-- Chọn chủ đề --</option>
                                    <option value="Thắc mắc điểm danh">Thắc mắc điểm danh</option>
                                    <option value="Nộp minh chứng bổ sung">Nộp minh chứng bổ sung</option>
                                    <option value="Góp ý tài liệu học tập">Góp ý tài liệu học tập</option>
                                    <option value="Vấn đề khác">Vấn đề khác</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nội dung chi tiết <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="content" rows="5" placeholder="Mô tả cụ thể vấn đề của bạn..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                <i class="bi bi-send-fill me-2"></i>GỬI PHẢN HỒI
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white pt-4 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark m-0"><i class="bi bi-clock-history text-secondary me-2"></i>Phản hồi đã gửi</h5>
                        <span class="badge bg-light text-dark border fs-6 fw-bold">Tổng số: <?= $totalFeedbacks ?></span>
                    </div>
                    <div class="card-body" id="feedbackList">
                        <?php if (!empty($feedbacks)): ?>
                            <?php foreach ($feedbacks as $feedback): ?>
                                <?php
                                $isPending = (string)$feedback['status'] === 'Pending';
                                $statusClass = $isPending ? 'bg-warning text-dark' : 'bg-success text-white';
                                $statusText = $isPending ? 'Chờ xử lý' : 'Đã xử lý';
                                ?>
                                <div class="feedback-item p-3 border rounded mb-3 bg-white shadow-sm"
                                     onclick='showFeedbackDetail(<?= json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) ?>)'
                                     style="cursor: pointer;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge <?= $statusClass ?> mb-1"><?= e($statusText) ?></span>
                                            <h6 class="fw-bold text-dark mb-0"><?= e($feedback['title']) ?></h6>
                                        </div>
                                        <small class="text-muted"><?= formatDateTime($feedback['updated_at'], 'd/m/Y H:i') ?></small>
                                    </div>
                                    <p class="text-muted small mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= e($feedback['content']) ?>
                                    </p>
                                    <?php if (!empty($feedback['reply_content'])): ?>
                                        <div class="mt-2 p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25">
                                            <small class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Đã có phản hồi</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                <h5>Bạn chưa gửi phản hồi nào</h5>
                                <p>Sử dụng form bên trái để gửi phản hồi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="feedbackDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="feedbackModalTitle">Chi tiết phản hồi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <span class="badge" id="feedbackModalStatus">Trạng thái</span>
                    <small class="text-muted" id="feedbackModalDate"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Chủ đề</label>
                    <input type="text" class="form-control" id="feedbackModalTopic" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nội dung phản hồi</label>
                    <textarea class="form-control" id="feedbackModalContent" rows="5" readonly></textarea>
                    <div class="form-text" id="feedbackEditHint"></div>
                </div>
                <div class="p-3 rounded feedback-reply-box d-none" id="feedbackReplyBox">
                    <strong><i class="bi bi-chat-left-text-fill me-1"></i>Phản hồi từ Ban Cán Sự:</strong>
                    <div id="feedbackModalReply" class="mt-2"></div>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="deleteFeedbackBtn" onclick="deleteCurrentFeedback()">
                    <i class="bi bi-trash me-1"></i>Xóa phản hồi
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="toggleEditFeedbackBtn" onclick="toggleEditFeedback()">
                        <i class="bi bi-pencil-square me-1"></i>Sửa nội dung
                    </button>
                    <button type="button" class="btn btn-primary d-none" id="saveFeedbackBtn" onclick="saveFeedbackChanges()">
                        <i class="bi bi-check2 me-1"></i>Lưu thay đổi
                    </button>
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
let currentFeedback = null;
let isEditing = false;

function showFeedbackDetail(feedback) {
    currentFeedback = feedback;
    isEditing = false;

    document.getElementById('feedbackModalTitle').textContent = feedback.title || 'Chi tiết phản hồi';
    document.getElementById('feedbackModalTopic').value = feedback.title || '';
    document.getElementById('feedbackModalContent').value = feedback.content || '';
    document.getElementById('feedbackModalDate').textContent = formatDateTime(feedback.updated_at);

    const statusBadge = document.getElementById('feedbackModalStatus');
    const isPending = feedback.status === 'Pending';
    if (isPending) {
        statusBadge.className = 'badge bg-warning text-dark';
        statusBadge.textContent = 'Chờ xử lý';
    } else {
        statusBadge.className = 'badge bg-success text-white';
        statusBadge.textContent = 'Đã xử lý';
    }

    const replyBox = document.getElementById('feedbackReplyBox');
    if (feedback.reply_content) {
        replyBox.classList.remove('d-none');
        document.getElementById('feedbackModalReply').textContent = feedback.reply_content;
    } else {
        replyBox.classList.add('d-none');
        document.getElementById('feedbackModalReply').textContent = '';
    }

    const editHint = document.getElementById('feedbackEditHint');
    const deleteBtn = document.getElementById('deleteFeedbackBtn');
    const editBtn = document.getElementById('toggleEditFeedbackBtn');
    const saveBtn = document.getElementById('saveFeedbackBtn');

    if (isPending) {
        editHint.textContent = 'Bạn có thể chỉnh sửa nội dung này.';
        deleteBtn.classList.remove('d-none');
        editBtn.classList.remove('d-none');
    } else {
        editHint.textContent = 'Phản hồi đã được xử lý, không thể chỉnh sửa.';
        deleteBtn.classList.add('d-none');
        editBtn.classList.add('d-none');
    }

    document.getElementById('feedbackModalContent').readOnly = true;
    document.getElementById('feedbackModalTopic').readOnly = true;
    saveBtn.classList.add('d-none');
    editBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Sửa nội dung';

    const modal = new bootstrap.Modal(document.getElementById('feedbackDetailModal'));
    modal.show();
}

function toggleEditFeedback() {
    if (!currentFeedback) return;

    isEditing = !isEditing;
    const contentInput = document.getElementById('feedbackModalContent');
    const topicInput = document.getElementById('feedbackModalTopic');
    const editBtn = document.getElementById('toggleEditFeedbackBtn');
    const saveBtn = document.getElementById('saveFeedbackBtn');

    if (isEditing) {
        contentInput.readOnly = false;
        topicInput.readOnly = false;
        saveBtn.classList.remove('d-none');
        editBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Hủy sửa';
        contentInput.focus();
    } else {
        contentInput.readOnly = true;
        topicInput.readOnly = true;
        saveBtn.classList.add('d-none');
        editBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Sửa nội dung';
        contentInput.value = currentFeedback.content || '';
        topicInput.value = currentFeedback.title || '';
    }
}

function saveFeedbackChanges() {
    if (!currentFeedback) return;

    const content = document.getElementById('feedbackModalContent').value;
    const topic = document.getElementById('feedbackModalTopic').value;

    fetch('my-feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_feedback&feedback_id=${currentFeedback.id}&topic=${encodeURIComponent(topic)}&content=${encodeURIComponent(content)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
            return;
        }
        alert(data.error || 'Không thể cập nhật phản hồi.');
    })
    .catch(() => alert('Không thể cập nhật phản hồi.'));
}

function deleteCurrentFeedback() {
    if (!currentFeedback) return;
    if (!confirm('Bạn có chắc chắn muốn xóa phản hồi này?')) return;

    fetch('my-feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_feedback&feedback_id=${currentFeedback.id}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
            return;
        }
        alert(data.error || 'Không thể xóa phản hồi.');
    })
    .catch(() => alert('Không thể xóa phản hồi.'));
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
}
</script>
</body>
</html>
