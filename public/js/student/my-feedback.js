(function () {
    const studentFeedbackForm = document.getElementById('studentFeedbackForm');
    const feedbackTopic = document.getElementById('feedbackTopic');
    const feedbackContent = document.getElementById('feedbackContent');
<<<<<<< HEAD
    const feedbackAttachment = document.getElementById('feedbackAttachment');
    const feedbackList = document.getElementById('feedbackList');
    const totalFeedbackBadge = document.getElementById('totalFeedbackBadge');

    const modalElement = document.getElementById('feedbackDetailModal');
    const detailModal = new bootstrap.Modal(modalElement);
    const modalTitle = document.getElementById('feedbackModalTitle');
=======
    const feedbackList = document.getElementById('feedbackList');
    const totalFeedbackBadge = document.getElementById('totalFeedbackBadge');

    const detailModal = new bootstrap.Modal(document.getElementById('feedbackDetailModal'));
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
    const modalStatus = document.getElementById('feedbackModalStatus');
    const modalDate = document.getElementById('feedbackModalDate');
    const modalTopic = document.getElementById('feedbackModalTopic');
    const modalContent = document.getElementById('feedbackModalContent');
    const modalReply = document.getElementById('feedbackModalReply');
    const replyBox = document.getElementById('feedbackReplyBox');
<<<<<<< HEAD
    const feedbackModalAttachmentName = document.getElementById('feedbackModalAttachmentName');
    const feedbackModalAttachmentDownload = document.getElementById('feedbackModalAttachmentDownload');
    const feedbackModalAttachmentInput = document.getElementById('feedbackModalAttachmentInput');
=======
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
    const feedbackEditHint = document.getElementById('feedbackEditHint');
    const toggleEditFeedbackBtn = document.getElementById('toggleEditFeedbackBtn');
    const saveFeedbackBtn = document.getElementById('saveFeedbackBtn');
    const deleteFeedbackBtn = document.getElementById('deleteFeedbackBtn');

<<<<<<< HEAD
    let activeFeedbackId = null;
    let isEditing = false;

    const feedbacks = [
        {
            id: 1,
            topic: 'Xin nộp bổ sung giấy khám bệnh',
            content: 'Mình gửi kèm file ảnh giấy khám bệnh của tuần trước, do mình đi viện gấp nên chưa kịp xin phép.',
            date: '10/03/2026',
            status: 'resolved',
            reply: 'Đã check giấy và cập nhật lại điểm danh cho bạn thành Có phép rồi nhé.',
            editedOnce: false,
            attachmentName: 'giay-kham-benh-tuan-8.jpg',
            attachmentUrl: ''
        },
        {
            id: 2,
            topic: 'Thắc mắc điểm danh tuần 9',
            content: 'Buổi thứ 5 tuần 9 mình có mặt nhưng hệ thống hiển thị vắng. Nhờ BCS kiểm tra giúp.',
            date: '09/04/2026',
            status: 'pending',
            reply: '',
            editedOnce: false,
            attachmentName: '',
            attachmentUrl: ''
        },
        {
            id: 3,
            topic: 'Góp ý tài liệu học tập',
            content: 'Môn CSDL thiếu file ví dụ chương 4 trong thư mục tài liệu lớp.',
            date: '08/04/2026',
            status: 'pending',
            reply: '',
            editedOnce: true,
            attachmentName: 'gop-y-tai-lieu-csdl.pdf',
            attachmentUrl: ''
        }
    ];

    function buildAttachmentFromFile(file) {
        if (!file) {
            return { attachmentName: '', attachmentUrl: '' };
        }

        return {
            attachmentName: file.name,
            attachmentUrl: URL.createObjectURL(file)
        };
    }

    function formatTodayDisplay() {
        const today = new Date();
        const day = String(today.getDate()).padStart(2, '0');
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const year = today.getFullYear();
        return day + '/' + month + '/' + year;
    }

    function revokeAttachmentUrl(item) {
        if (!item || !item.attachmentUrl) {
            return;
        }

        if (item.attachmentUrl.indexOf('blob:') === 0) {
            URL.revokeObjectURL(item.attachmentUrl);
        }
    }

    function getStatusBadge(status) {
        if (status === 'resolved') {
            return '<span class="badge feedback-status-resolved px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Đã giải quyết</span>';
        }

        return '<span class="badge feedback-status-pending px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Chưa giải quyết</span>';
    }

    function renderList() {
        if (!feedbackList) {
            return;
        }

        if (feedbacks.length === 0) {
            feedbackList.innerHTML = '<div class="text-center text-muted py-4">Bạn chưa có phản hồi nào.</div>';
            updateTotalBadge();
            return;
        }

        feedbackList.innerHTML = feedbacks.map(function (item) {
            const editedTag = item.editedOnce
                ? '<span class="badge feedback-edited-tag ms-2">Đã sửa 1 lần</span>'
                : '';
            const replyBlock = item.reply
                ? '<div class="p-2 feedback-reply-box rounded small mb-3"><strong>BCS trả lời:</strong> ' + item.reply + '</div>'
                : '';
            const attachmentBlock = item.attachmentName
                ? '<div class="small text-secondary mb-2"><i class="bi bi-paperclip me-1"></i>' + item.attachmentName + '</div>'
                : '';

            return '' +
                '<div class="feedback-item p-3 mb-3" data-feedback-id="' + item.id + '">' +
                    '<div class="d-flex justify-content-between mb-2 align-items-center">' +
                        '<div>' + getStatusBadge(item.status) + editedTag + '</div>' +
                        '<small class="text-muted">' + item.date + '</small>' +
                    '</div>' +
                    '<h6 class="fw-bold text-dark">' + item.topic + '</h6>' +
                    '<p class="small text-muted mb-2 text-truncate">' + item.content + '</p>' +
                    attachmentBlock +
                    replyBlock +
                    '<div class="text-end border-top pt-2 mt-2">' +
                        '<button class="btn btn-sm btn-outline-primary feedback-view-btn" data-feedback-id="' + item.id + '">' +
                            '<i class="bi bi-eye me-1"></i>Xem chi tiết' +
                        '</button>' +
                    '</div>' +
                '</div>';
        }).join('');

        updateTotalBadge();
    }

    function updateTotalBadge() {
        if (!totalFeedbackBadge) {
            return;
        }

        totalFeedbackBadge.textContent = 'Tổng số: ' + feedbacks.length;
    }

    function findFeedbackById(id) {
        return feedbacks.find(function (item) {
            return item.id === id;
        });
    }

    function setModalEditMode(enable) {
        isEditing = enable;
        modalContent.readOnly = !enable;
        feedbackModalAttachmentInput.classList.toggle('d-none', !enable);
        saveFeedbackBtn.classList.toggle('d-none', !enable);
        toggleEditFeedbackBtn.classList.toggle('d-none', enable);
        if (!enable) {
            feedbackModalAttachmentInput.value = '';
        }
        if (enable) {
            modalContent.focus();
        }
    }

    function syncModalButtons(item) {
        const canEdit = item.status === 'pending' && !item.editedOnce;
        toggleEditFeedbackBtn.disabled = !canEdit;
        toggleEditFeedbackBtn.classList.toggle('d-none', !canEdit);
        saveFeedbackBtn.classList.add('d-none');
        modalContent.readOnly = true;
        isEditing = false;

        if (item.status === 'resolved') {
            feedbackEditHint.textContent = 'Phản hồi đã giải quyết nên không thể chỉnh sửa.';
        } else if (item.editedOnce) {
            feedbackEditHint.textContent = 'Bạn đã dùng lượt chỉnh sửa duy nhất cho phản hồi này.';
        } else {
            feedbackEditHint.textContent = 'Bạn có thể sửa nội dung và thay tệp đính kèm 1 lần duy nhất trước khi được giải quyết.';
        }
    }

    function openDetailModal(id) {
        const item = findFeedbackById(id);
        if (!item) {
            return;
        }

        activeFeedbackId = item.id;
        modalTitle.textContent = 'Chi tiết phản hồi';
        modalDate.textContent = item.date;
        modalTopic.value = item.topic;
        modalContent.value = item.content;
        feedbackModalAttachmentName.textContent = item.attachmentName || 'Chưa có tệp đính kèm.';
        if (item.attachmentUrl) {
            feedbackModalAttachmentDownload.classList.remove('d-none');
            feedbackModalAttachmentDownload.href = item.attachmentUrl;
            feedbackModalAttachmentDownload.download = item.attachmentName || 'tep-dinh-kem';
        } else {
            feedbackModalAttachmentDownload.classList.add('d-none');
            feedbackModalAttachmentDownload.removeAttribute('href');
            feedbackModalAttachmentDownload.removeAttribute('download');
        }
        feedbackModalAttachmentInput.value = '';

        if (item.status === 'resolved') {
            modalStatus.className = 'badge feedback-status-resolved';
            modalStatus.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Đã giải quyết';
        } else {
            modalStatus.className = 'badge feedback-status-pending';
            modalStatus.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Chưa giải quyết';
        }

        if (item.reply) {
            replyBox.classList.remove('d-none');
            modalReply.textContent = item.reply;
=======
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
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
        } else {
            replyBox.classList.add('d-none');
            modalReply.textContent = '';
        }

<<<<<<< HEAD
        syncModalButtons(item);
        detailModal.show();
    }

    function deleteActiveFeedback() {
        if (activeFeedbackId === null) {
            return;
        }

        const item = findFeedbackById(activeFeedbackId);
        if (!item) {
            return;
        }

        const confirmDelete = window.confirm('Bạn có chắc chắn muốn xóa phản hồi này?');
        if (!confirmDelete) {
            return;
        }

        const targetIndex = feedbacks.findIndex(function (row) {
            return row.id === activeFeedbackId;
        });
        if (targetIndex === -1) {
            return;
        }

        revokeAttachmentUrl(feedbacks[targetIndex]);
        feedbacks.splice(targetIndex, 1);
        activeFeedbackId = null;
        detailModal.hide();
        renderList();
    }

    function saveActiveFeedback() {
        if (activeFeedbackId === null) {
            return;
        }

        const item = findFeedbackById(activeFeedbackId);
        if (!item) {
            return;
        }

        const canEdit = item.status === 'pending' && !item.editedOnce;
        if (!canEdit) {
            syncModalButtons(item);
            return;
        }

        const nextValue = modalContent.value.trim();
        if (!nextValue) {
            alert('Nội dung phản hồi không được để trống.');
            return;
        }

        item.content = nextValue;
        const newAttachmentFile = feedbackModalAttachmentInput.files[0];
        if (newAttachmentFile) {
            revokeAttachmentUrl(item);
            const nextAttachment = buildAttachmentFromFile(newAttachmentFile);
            item.attachmentName = nextAttachment.attachmentName;
            item.attachmentUrl = nextAttachment.attachmentUrl;
        }
        item.editedOnce = true;
        setModalEditMode(false);
        syncModalButtons(item);
        renderList();
        openDetailModal(item.id);
    }

    if (feedbackList) {
        feedbackList.addEventListener('click', function (event) {
            const trigger = event.target.closest('.feedback-view-btn');
            if (!trigger) {
                return;
            }

            const id = Number(trigger.dataset.feedbackId);
            openDetailModal(id);
        });
    }

    toggleEditFeedbackBtn.addEventListener('click', function () {
        if (activeFeedbackId === null) {
            return;
        }

        const item = findFeedbackById(activeFeedbackId);
        if (!item || item.status !== 'pending' || item.editedOnce) {
            return;
        }

        setModalEditMode(true);
    });

    saveFeedbackBtn.addEventListener('click', saveActiveFeedback);
    deleteFeedbackBtn.addEventListener('click', deleteActiveFeedback);

    studentFeedbackForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const topic = feedbackTopic.value;
        const content = feedbackContent.value.trim();
        const attachmentFile = feedbackAttachment.files[0];
        if (!topic || !content) {
            return;
        }

        const nextAttachment = buildAttachmentFromFile(attachmentFile);

        const newItem = {
            id: feedbacks.length ? Math.max.apply(null, feedbacks.map(function (item) { return item.id; })) + 1 : 1,
            topic: topic,
            content: content,
            date: formatTodayDisplay(),
            status: 'pending',
            reply: '',
            editedOnce: false,
            attachmentName: nextAttachment.attachmentName,
            attachmentUrl: nextAttachment.attachmentUrl
        };

        feedbacks.unshift(newItem);
        studentFeedbackForm.reset();
        renderList();
        alert('Đã gửi phản hồi thành công.');
    });

    renderList();
=======
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
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
})();
