let bcsEditingAnnouncementId = 0;

function bcsSubmitAnnouncement(action, payload = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'announcements.php';

    const fields = Object.assign({ news_action: action }, payload);
    Object.keys(fields).forEach((key) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = String(fields[key] ?? '');
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

function bcsGetFormValue(id) {
    return String(document.getElementById(id)?.value || '').trim();
}

window.saveDraft = function () {
    const draft = {
        title: bcsGetFormValue('newsTitle'),
        content: bcsGetFormValue('newsContent')
    };
    localStorage.setItem('bcs_news_draft', JSON.stringify(draft));
    alert('Đã lưu nháp trên trình duyệt.');
};

window.publishNews = function () {
    const title = bcsGetFormValue('newsTitle');
    const content = bcsGetFormValue('newsContent');

    if (!title) {
        alert('Vui lòng nhập tiêu đề bản tin.');
        return;
    }

    localStorage.removeItem('bcs_news_draft');
    bcsSubmitAnnouncement('create', {
        news_title: title,
        news_content: content
    });
};

window.openViewModal = function (announcement) {
    const item = (announcement && typeof announcement === 'object') ? announcement : null;
    if (!item) return;

    const title = String(item.title || 'Thông báo').trim();
    const content = String(item.message || item.content || 'Không có nội dung.').trim();
    const creator = String(item.creator_name || 'Hệ thống').trim();
    const createdAt = String(item.created_at || '').trim();

    const titleEl = document.getElementById('announcementDetailTitle');
    const metaEl = document.getElementById('announcementDetailMeta');
    const contentEl = document.getElementById('announcementDetailContent');
    if (titleEl) titleEl.textContent = title;
    if (metaEl) metaEl.textContent = `${creator} • ${createdAt || 'Không rõ thời gian'}`;
    if (contentEl) contentEl.textContent = content;

    const modalEl = document.getElementById('announcementDetailModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
};

window.downloadAnnouncementFile = function (announcement) {
    const item = (announcement && typeof announcement === 'object') ? announcement : null;
    if (!item) return;

    const text = String(item.message || item.content || '');
    const urlMatch = text.match(/https?:\/\/[^\s]+/i);
    if (!urlMatch) {
        alert('Thông báo này chưa có file đính kèm để tải xuống.');
        return;
    }

    window.open(urlMatch[0], '_blank', 'noopener');
};

window.openEditModal = function (announcement) {
    if (!announcement || typeof announcement !== 'object') return;

    bcsEditingAnnouncementId = Number(announcement.id || 0);
    document.getElementById('newsModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh sửa bản tin';
    document.getElementById('modalTitleInput').value = announcement.title || '';
    document.getElementById('modalContentInput').value = announcement.message || announcement.content || '';
};

window.editAnnouncement = function (announcement) {
    window.openEditModal(announcement);
    const modalEl = document.getElementById('newsModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
};

window.saveEditedAnnouncement = function () {
    if (!bcsEditingAnnouncementId) {
        alert('Không xác định được bản tin cần sửa.');
        return;
    }

    const title = bcsGetFormValue('modalTitleInput');
    const content = bcsGetFormValue('modalContentInput');
    if (!title) {
        alert('Vui lòng nhập tiêu đề bản tin.');
        return;
    }

    bcsSubmitAnnouncement('update', {
        news_id: bcsEditingAnnouncementId,
        news_title: title,
        news_content: content
    });
};

window.deleteCurrentAnnouncement = async function () {
    if (!bcsEditingAnnouncementId) {
        alert('Không xác định được bản tin cần xóa.');
        return;
    }
    if (!confirm('Bạn có chắc muốn xóa bản tin này?')) return;
    await window.confirmDelete(bcsEditingAnnouncementId, false);
};

window.confirmDelete = async function (id, askConfirm = true) {
    const itemId = Number(id || 0);
    if (!itemId) return;
    if (askConfirm && !confirm('Bạn có chắc muốn xóa bản tin này?')) return;

    bcsSubmitAnnouncement('delete', {
        news_id: itemId
    });
};

document.addEventListener('DOMContentLoaded', function () {
    const draftRaw = localStorage.getItem('bcs_news_draft');
    if (!draftRaw) return;
    try {
        const draft = JSON.parse(draftRaw);
        if (draft && typeof draft === 'object') {
            if (!document.getElementById('newsTitle')?.value) {
                document.getElementById('newsTitle').value = draft.title || '';
            }
            if (!document.getElementById('newsContent')?.value) {
                document.getElementById('newsContent').value = draft.content || '';
            }
        }
    } catch (_) {
        // ignore corrupted local draft
    }
});
