<<<<<<< HEAD
    let newsModal;

    document.addEventListener('DOMContentLoaded', function () {
        newsModal = new bootstrap.Modal(document.getElementById('newsModal'));

        document.getElementById('newsSource').addEventListener('change', function () {
            handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
        });

        document.getElementById('modalSourceInput').addEventListener('change', function () {
            handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
        });

        document.getElementById('newsCategory').addEventListener('change', function () {
            handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
        });

        document.getElementById('modalCategoryInput').addEventListener('change', function () {
            handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        });

        document.getElementById('publishNow').addEventListener('change', function () {
            handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        });

        document.getElementById('publishSchedule').addEventListener('change', function () {
            handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        });

        document.getElementById('modalPublishNow').addEventListener('change', function () {
            handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
        });

        document.getElementById('modalPublishSchedule').addEventListener('change', function () {
            handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
        });

        handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
        handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
        handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
        handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
    });

    function handleSourceSelection(selectId, wrapId, inputId) {
        const sourceSelect = document.getElementById(selectId);
        const customWrap = document.getElementById(wrapId);
        const customInput = document.getElementById(inputId);
        const isCustom = sourceSelect.value === 'Khac';

        customWrap.classList.toggle('d-none', !isCustom);
        customInput.required = isCustom;

        if (!isCustom) {
            customInput.value = '';
        }
    }

    function handleCategorySelection(selectId, wrapId, inputId) {
        const categorySelect = document.getElementById(selectId);
        const customWrap = document.getElementById(wrapId);
        const customInput = document.getElementById(inputId);
        const isCustom = categorySelect.value === 'Khac';

        customWrap.classList.toggle('d-none', !isCustom);
        customInput.required = isCustom;

        if (!isCustom) {
            customInput.value = '';
        }
    }

    function handlePublishMode(nowRadioId, wrapId, inputIds) {
        const isNow = document.getElementById(nowRadioId).checked;
        const scheduleWrap = document.getElementById(wrapId);
        const scheduleInputs = inputIds.map(id => document.getElementById(id));

        scheduleWrap.classList.toggle('d-none', isNow);
        scheduleInputs.forEach(input => {
            input.required = !isNow;
        });

        if (isNow) {
            scheduleInputs.forEach(input => {
                input.value = '';
            });
        }
    }

    function confirmAction(actionText, onConfirm) {
        if (confirm('Bạn có chắc chắn muốn ' + actionText + '?')) {
            onConfirm();
        }
    }

    function saveDraft() {
        confirmAction('lưu bản tin vào nháp', function () {
            alert('Đã lưu bản tin vào nháp.');
        });
    }

    function formatScheduleDateTimeVi(dateStr, timeStr) {
        const [year, month, day] = dateStr.split('-');
        return day + '/' + month + '/' + year + ' ' + timeStr;
    }

    function publishNews() {
        confirmAction('đăng bản tin và đẩy thông báo đến sinh viên', function () {
            const isPinned = document.getElementById('pinPost').checked;
            const isNow = document.getElementById('publishNow').checked;
            const scheduleDate = document.getElementById('schedulePublishDate').value;
            const scheduleTime = document.getElementById('schedulePublishTime').value;
            const pinText = isPinned ? ' Bài đăng đã được ghim.' : '';

            if (!isNow && (!scheduleDate || !scheduleTime)) {
                alert('Vui lòng chọn đầy đủ ngày và giờ đăng.');
                return;
            }

            const publishText = isNow
                ? 'Đã đăng bản tin và đẩy thông báo đến sinh viên.'
                : 'Đã hẹn đăng bản tin vào: ' + formatScheduleDateTimeVi(scheduleDate, scheduleTime) + '.';

            alert(publishText + pinText);
        });
    }

    function togglePin(title) {
        confirmAction('ghim bài đăng "' + title + '"', function () {
            alert('Đã ghim bài đăng: ' + title);
        });
    }

    function confirmDelete(title) {
        confirmAction('xóa bản tin "' + title + '"', function () {
            alert('Đã xóa bản tin: ' + title);
        });
    }

    function openViewModal(title) {
        alert('Mở chi tiết bản tin: ' + title);
    }

    function openEditModal(title, source, scope, category, content) {
        document.getElementById('newsModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh sửa bản tin';
        document.getElementById('modalTitleInput').value = title;

        const modalSource = document.getElementById('modalSourceInput');
        const knownSources = Array.from(modalSource.options).map(option => option.value);

        if (knownSources.includes(source)) {
            modalSource.value = source;
            document.getElementById('customModalSource').value = '';
        } else {
            modalSource.value = 'Khac';
            document.getElementById('customModalSource').value = source;
        }

        handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');

        const modalCategory = document.getElementById('modalCategoryInput');
        const knownCategories = Array.from(modalCategory.options).map(option => option.value);

        if (knownCategories.includes(category)) {
            modalCategory.value = category;
            document.getElementById('customModalCategory').value = '';
        } else {
            modalCategory.value = 'Khac';
            document.getElementById('customModalCategory').value = category;
        }

        handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        document.getElementById('modalScopeInput').value = scope;
        document.getElementById('modalContentInput').value = content;
        newsModal.show();
    }
=======
let newsModal;
let bcsAnnouncements = [];
let bcsEditingAnnouncementId = 0;

function bcsFormatDateTime(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 16);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy}, ${hh}:${min}`;
}

function handleSourceSelection(selectId, wrapId, inputId) {
    const sourceSelect = document.getElementById(selectId);
    const customWrap = document.getElementById(wrapId);
    const customInput = document.getElementById(inputId);
    const isCustom = sourceSelect.value === 'Khac';

    customWrap.classList.toggle('d-none', !isCustom);
    customInput.required = isCustom;

    if (!isCustom) {
        customInput.value = '';
    }
}

function handleCategorySelection(selectId, wrapId, inputId) {
    const categorySelect = document.getElementById(selectId);
    const customWrap = document.getElementById(wrapId);
    const customInput = document.getElementById(inputId);
    const isCustom = categorySelect.value === 'Khac';

    customWrap.classList.toggle('d-none', !isCustom);
    customInput.required = isCustom;

    if (!isCustom) {
        customInput.value = '';
    }
}

function handlePublishMode(nowRadioId, wrapId, inputIds) {
    const isNow = document.getElementById(nowRadioId).checked;
    const scheduleWrap = document.getElementById(wrapId);
    const scheduleInputs = inputIds.map((id) => document.getElementById(id));

    scheduleWrap.classList.toggle('d-none', isNow);
    scheduleInputs.forEach((input) => {
        input.required = !isNow;
    });

    if (isNow) {
        scheduleInputs.forEach((input) => {
            input.value = '';
        });
    }
}

function confirmAction(actionText, onConfirm) {
    if (confirm('Ban co chac chan muon ' + actionText + '?')) {
        onConfirm();
    }
}

function bcsRenderAnnouncementStats() {
    const statCards = document.querySelectorAll('.stat-card-custom h3');
    if (statCards.length >= 3) {
        const total = bcsAnnouncements.length;
        statCards[0].textContent = String(total);
        statCards[1].textContent = String(total);
        statCards[2].textContent = String(total);
    }
}

function bcsRenderAnnouncementLists() {
    const listRoot = document.getElementById('bcsAnnouncementList');
    const manageRoot = document.getElementById('bcsAnnouncementManageList');
    if (!listRoot || !manageRoot) return;

    if (!bcsAnnouncements.length) {
        listRoot.innerHTML = '<div class="text-muted">Chua co thong bao nao.</div>';
        manageRoot.innerHTML = '<div class="text-muted">Chua co ban tin da dang.</div>';
        return;
    }

    listRoot.innerHTML = bcsAnnouncements.map((item, idx) => {
        const isPinned = idx === 0;
        return `
            <div class="list-group-item announcement-item border rounded-3 mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge rounded-pill ${isPinned ? 'bg-warning text-dark' : 'bg-success text-white'} px-3 py-2 fw-normal">${isPinned ? 'Dang ghim' : 'Da dang'}</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 fw-normal">Thong bao lop</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">${item.title || ''}</h6>
                        <p class="text-muted mb-2">${bcsFormatDateTime(item.created_at)}</p>
                        <p class="mb-0 text-dark">${item.note || ''}</p>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-outline-primary btn-sm fw-bold mb-2" onclick="openViewModal(${item.id})"><i class="bi bi-eye me-1"></i>Xem</button>
                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                            <button class="btn btn-sm btn-light border" onclick="openEditModalById(${item.id})"><i class="bi bi-pencil-square me-1"></i>Sua</button>
                            <button class="btn btn-sm btn-light border text-danger" onclick="confirmDelete(${item.id})"><i class="bi bi-trash me-1"></i>Xoa</button>
                        </div>
                    </div>
                </div>
            </div>`;
    }).join('');

    manageRoot.innerHTML = bcsAnnouncements.slice(0, 8).map((item) => `
        <div class="p-3 rounded-3 bg-light border">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold text-dark mb-1">${item.title || ''}</div>
                    <div class="text-muted small">Da dang: ${bcsFormatDateTime(item.created_at)}</div>
                </div>
                <span class="badge bg-success">Dang hien thi</span>
            </div>
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button class="btn btn-sm btn-outline-secondary" onclick="openEditModalById(${item.id})"><i class="bi bi-pencil-square me-1"></i>Sua</button>
                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${item.id})"><i class="bi bi-trash me-1"></i>Xoa</button>
            </div>
        </div>`).join('');
}

async function loadAnnouncements() {
    const keyword = (document.getElementById('bcsAnnouncementKeyword')?.value || '').trim();
    const query = new URLSearchParams({ keyword }).toString();
    const res = await fetch(`/api/bcs/announcements?${query}`, { headers: { Accept: 'application/json' } });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => []);
    if (!res.ok) {
        alert(data.error || 'Khong the tai danh sach thong bao.');
        return;
    }

    bcsAnnouncements = Array.isArray(data) ? data : [];
    bcsRenderAnnouncementStats();
    bcsRenderAnnouncementLists();
}

function saveDraft() {
    alert('Ban nhap xong noi dung va bam Dang ban tin de luu vao CSDL.');
}

async function publishNews() {
    const title = (document.getElementById('newsTitle')?.value || '').trim();
    const content = (document.getElementById('newsContent')?.value || '').trim();

    if (!title) {
        alert('Vui long nhap tieu de ban tin.');
        return;
    }

    const res = await fetch('/api/bcs/announcements', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            title,
            content,
            driveLink: (document.getElementById('newsFile')?.files?.[0]?.name || '').trim()
        })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the dang ban tin.');
        return;
    }

    alert('Da dang ban tin thanh cong.');
    document.getElementById('newsForm')?.reset();
    await loadAnnouncements();
}

function openViewModal(idOrTitle) {
    const item = typeof idOrTitle === 'number'
        ? bcsAnnouncements.find((x) => Number(x.id) === Number(idOrTitle))
        : bcsAnnouncements.find((x) => x.title === idOrTitle || Number(x.id) === Number(idOrTitle));
    if (!item) return;
    alert(`${item.title}\n\n${item.note || ''}`);
}

function openEditModalById(id) {
    const item = bcsAnnouncements.find((x) => Number(x.id) === Number(id));
    if (!item) return;

    bcsEditingAnnouncementId = Number(id);
    document.getElementById('newsModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chinh sua ban tin';
    document.getElementById('modalTitleInput').value = item.title || '';
    document.getElementById('modalContentInput').value = item.note || '';

    newsModal.show();
}

function openEditModal(title) {
    const item = bcsAnnouncements.find((x) => x.title === title);
    if (!item) return;
    openEditModalById(item.id);
}

async function saveEditedAnnouncement() {
    if (!bcsEditingAnnouncementId) return;

    const title = (document.getElementById('modalTitleInput')?.value || '').trim();
    const content = (document.getElementById('modalContentInput')?.value || '').trim();
    if (!title) {
        alert('Vui long nhap tieu de ban tin.');
        return;
    }

    const res = await fetch(`/api/bcs/announcements/${bcsEditingAnnouncementId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ title, content })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the cap nhat ban tin.');
        return;
    }

    alert('Da luu thay doi ban tin.');
    newsModal.hide();
    await loadAnnouncements();
}

async function confirmDelete(idOrTitle) {
    const item = typeof idOrTitle === 'number'
        ? bcsAnnouncements.find((x) => Number(x.id) === Number(idOrTitle))
        : bcsAnnouncements.find((x) => x.title === idOrTitle);

    if (!item) return;

    if (!confirm(`Ban co chac muon xoa ban tin "${item.title}"?`)) {
        return;
    }

    const res = await fetch(`/api/bcs/announcements/${item.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' }
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the xoa ban tin.');
        return;
    }

    await loadAnnouncements();
}

function togglePin() {
    alert('Thong bao moi nhat se duoc hien thi dau danh sach.');
}

document.addEventListener('DOMContentLoaded', function () {
    newsModal = new bootstrap.Modal(document.getElementById('newsModal'));

    document.getElementById('newsSource').addEventListener('change', function () {
        handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
    });

    document.getElementById('modalSourceInput').addEventListener('change', function () {
        handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
    });

    document.getElementById('newsCategory').addEventListener('change', function () {
        handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
    });

    document.getElementById('modalCategoryInput').addEventListener('change', function () {
        handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
    });

    document.getElementById('publishNow').addEventListener('change', function () {
        handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
    });

    document.getElementById('publishSchedule').addEventListener('change', function () {
        handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
    });

    document.getElementById('modalPublishNow').addEventListener('change', function () {
        handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
    });

    document.getElementById('modalPublishSchedule').addEventListener('change', function () {
        handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
    });

    handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
    handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
    handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
    handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
    handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
    handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);

    document.getElementById('bcsAnnouncementKeyword')?.addEventListener('input', loadAnnouncements);
    document.getElementById('bcsAnnouncementSaveEditBtn')?.addEventListener('click', saveEditedAnnouncement);

    loadAnnouncements();
});
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
