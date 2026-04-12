(function () {
    const studentFeedbackForm = document.getElementById('studentFeedbackForm');
    const feedbackTopic = document.getElementById('feedbackTopic');
    const feedbackContent = document.getElementById('feedbackContent');
    const feedbackAttachment = document.getElementById('feedbackAttachment');
    const feedbackList = document.getElementById('feedbackList');
    const totalFeedbackBadge = document.getElementById('totalFeedbackBadge');

    const modalElement = document.getElementById('feedbackDetailModal');
    const detailModal = new bootstrap.Modal(modalElement);
    const modalTitle = document.getElementById('feedbackModalTitle');
    const modalStatus = document.getElementById('feedbackModalStatus');
    const modalDate = document.getElementById('feedbackModalDate');
    const modalTopic = document.getElementById('feedbackModalTopic');
    const modalContent = document.getElementById('feedbackModalContent');
    const modalReply = document.getElementById('feedbackModalReply');
    const replyBox = document.getElementById('feedbackReplyBox');
    const feedbackModalAttachmentName = document.getElementById('feedbackModalAttachmentName');
    const feedbackModalAttachmentDownload = document.getElementById('feedbackModalAttachmentDownload');
    const feedbackModalAttachmentInput = document.getElementById('feedbackModalAttachmentInput');
    const feedbackEditHint = document.getElementById('feedbackEditHint');
    const toggleEditFeedbackBtn = document.getElementById('toggleEditFeedbackBtn');
    const saveFeedbackBtn = document.getElementById('saveFeedbackBtn');
    const deleteFeedbackBtn = document.getElementById('deleteFeedbackBtn');

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
        } else {
            replyBox.classList.add('d-none');
            modalReply.textContent = '';
        }

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
            date: new Date().toLocaleDateString('vi-VN'),
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
})();
