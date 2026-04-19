(function () {
    const studentFeedbackForm = document.getElementById('studentFeedbackForm');
    const feedbackTopic = document.getElementById('feedbackTopic');
    const feedbackContent = document.getElementById('feedbackContent');
    const feedbackList = document.getElementById('feedbackList');
    const totalFeedbackBadge = document.getElementById('totalFeedbackBadge');

    const detailModal = new bootstrap.Modal(document.getElementById('feedbackDetailModal'));
    const modalStatus = document.getElementById('feedbackModalStatus');
    const modalDate = document.getElementById('feedbackModalDate');
    const modalTopic = document.getElementById('feedbackModalTopic');
    const modalContent = document.getElementById('feedbackModalContent');
    const modalReply = document.getElementById('feedbackModalReply');
    const replyBox = document.getElementById('feedbackReplyBox');
    const feedbackEditHint = document.getElementById('feedbackEditHint');
    const toggleEditFeedbackBtn = document.getElementById('toggleEditFeedbackBtn');
    const saveFeedbackBtn = document.getElementById('saveFeedbackBtn');
    const deleteFeedbackBtn = document.getElementById('deleteFeedbackBtn');

    let feedbacks = [];
    let activeFeedbackId = null;

    function formatDate(dateValue) {
        const d = new Date(dateValue);
        if (Number.isNaN(d.getTime())) return '--/--/----';
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    function esc(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function statusMeta(rawStatus) {
        const status = String(rawStatus || '').toLowerCase();
        if (status === 'resolved') {
            return {
                badge: '<span class="badge feedback-status-resolved px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Đã giải quyết</span>',
                editable: false,
                deletable: false,
                label: 'Đã giải quyết'
            };
        }
        return {
            badge: '<span class="badge feedback-status-pending px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Chưa giải quyết</span>',
            editable: true,
            deletable: true,
            label: 'Chưa giải quyết'
        };
    }

    async function loadFeedbacks() {
        const response = await fetch('/api/student/feedbacks');
        if (response.status === 401) {
            window.location.href = '/login.html';
            return;
        }
        if (!response.ok) {
            throw new Error('Không thể tải phản hồi');
        }
        feedbacks = await response.json();
    }

    function renderList() {
        if (!feedbacks.length) {
            feedbackList.innerHTML = '<div class="text-center text-muted py-4">Bạn chưa có phản hồi nào.</div>';
            totalFeedbackBadge.textContent = 'Tổng số: 0';
            return;
        }

        feedbackList.innerHTML = feedbacks.map((item) => {
            const meta = statusMeta(item.status);
            const replyBlock = item.reply_content
                ? `<div class="p-2 feedback-reply-box rounded small mb-3"><strong>BCS trả lời:</strong> ${esc(item.reply_content)}</div>`
                : '';

            return `
                <div class="feedback-item p-3 mb-3" data-feedback-id="${item.id}">
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <div>${meta.badge}</div>
                        <small class="text-muted">${formatDate(item.updated_at)}</small>
                    </div>
                    <h6 class="fw-bold text-dark">${esc(item.title)}</h6>
                    <p class="small text-muted mb-2 text-truncate">${esc(item.content)}</p>
                    ${replyBlock}
                    <div class="text-end border-top pt-2 mt-2">
                        <button class="btn btn-sm btn-outline-primary feedback-view-btn" data-feedback-id="${item.id}">
                            <i class="bi bi-eye me-1"></i>Xem chi tiết
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        totalFeedbackBadge.textContent = `Tổng số: ${feedbacks.length}`;
    }

    function findFeedback(id) {
        return feedbacks.find((item) => Number(item.id) === Number(id));
    }

    function setModalByItem(item) {
        const meta = statusMeta(item.status);
        modalTopic.value = item.title || '';
        modalContent.value = item.content || '';
        modalDate.textContent = formatDate(item.updated_at);
        modalStatus.className = 'badge';
        modalStatus.innerHTML = meta.badge;

        if (item.reply_content) {
            replyBox.classList.remove('d-none');
            modalReply.textContent = item.reply_content;
        } else {
            replyBox.classList.add('d-none');
            modalReply.textContent = '';
        }

        toggleEditFeedbackBtn.disabled = !meta.editable;
        deleteFeedbackBtn.disabled = !meta.deletable;
        toggleEditFeedbackBtn.classList.remove('d-none');
        saveFeedbackBtn.classList.add('d-none');
        modalContent.readOnly = true;

        feedbackEditHint.textContent = meta.editable
            ? 'Bạn có thể chỉnh sửa nội dung khi phản hồi còn ở trạng thái chờ xử lý.'
            : 'Phản hồi đã được xử lý nên không thể chỉnh sửa.';
    }

    function openDetailModal(id) {
        const item = findFeedback(id);
        if (!item) return;
        activeFeedbackId = Number(item.id);
        setModalByItem(item);
        detailModal.show();
    }

    async function createFeedback(event) {
        event.preventDefault();
        const title = (feedbackTopic.value || '').trim();
        const content = (feedbackContent.value || '').trim();
        if (!title || !content) return;

        const response = await fetch('/api/student/feedbacks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, content })
        });

        if (!response.ok) {
            throw new Error('Không thể gửi phản hồi');
        }

        studentFeedbackForm.reset();
        await loadFeedbacks();
        renderList();
        alert('Đã gửi phản hồi thành công.');
    }

    async function saveActiveFeedback() {
        if (!activeFeedbackId) return;
        const item = findFeedback(activeFeedbackId);
        if (!item) return;

        const title = (modalTopic.value || '').trim();
        const content = (modalContent.value || '').trim();
        if (!title || !content) {
            alert('Vui lòng nhập đầy đủ chủ đề và nội dung.');
            return;
        }

        const response = await fetch(`/api/student/feedbacks/${activeFeedbackId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, content })
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || 'Không thể cập nhật phản hồi');
        }

        await loadFeedbacks();
        renderList();
        openDetailModal(activeFeedbackId);
    }

    async function deleteActiveFeedback() {
        if (!activeFeedbackId) return;
        if (!window.confirm('Bạn có chắc chắn muốn xóa phản hồi này?')) return;

        const response = await fetch(`/api/student/feedbacks/${activeFeedbackId}`, {
            method: 'DELETE'
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || 'Không thể xóa phản hồi');
        }

        activeFeedbackId = null;
        detailModal.hide();
        await loadFeedbacks();
        renderList();
    }

    feedbackList.addEventListener('click', (event) => {
        const trigger = event.target.closest('.feedback-view-btn');
        if (!trigger) return;
        openDetailModal(Number(trigger.dataset.feedbackId));
    });

    toggleEditFeedbackBtn.addEventListener('click', () => {
        const item = findFeedback(activeFeedbackId);
        if (!item || !statusMeta(item.status).editable) return;
        modalContent.readOnly = false;
        toggleEditFeedbackBtn.classList.add('d-none');
        saveFeedbackBtn.classList.remove('d-none');
        modalContent.focus();
    });

    saveFeedbackBtn.addEventListener('click', async () => {
        try {
            await saveActiveFeedback();
        } catch (error) {
            console.error(error);
            alert(error.message || 'Không thể lưu phản hồi.');
        }
    });

    deleteFeedbackBtn.addEventListener('click', async () => {
        try {
            await deleteActiveFeedback();
        } catch (error) {
            console.error(error);
            alert(error.message || 'Không thể xóa phản hồi.');
        }
    });

    studentFeedbackForm.addEventListener('submit', async (event) => {
        try {
            await createFeedback(event);
        } catch (error) {
            console.error(error);
            alert(error.message || 'Không thể gửi phản hồi.');
        }
    });

    (async () => {
        try {
            await loadFeedbacks();
            renderList();
        } catch (error) {
            console.error(error);
            alert('Không thể tải phản hồi từ database.');
        }
    })();
})();
