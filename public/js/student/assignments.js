const listView = document.getElementById('assignmentListView');
const detailView = document.getElementById('assignmentDetailView');
const uploadedFilesContainer = document.getElementById('uploadedFilesContainer');
const btnAddFile = document.getElementById('addDropdownContainer');
const btnTurnIn = document.getElementById('btnTurnIn');
const btnUnsubmit = document.getElementById('btnUnsubmit');
const workStatusText = document.getElementById('workStatusText');
const teacherFeedbackDiv = document.getElementById('teacherFeedbackDiv');

let assignments = [];
let submittedItems = [];
let currentAssignment = null;

function formatDateTimeVN(dateValue) {
    if (!dateValue) return 'Không có hạn nộp';
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return 'Không có hạn nộp';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${hh}:${mi}, ${dd}/${mm}/${yyyy}`;
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function fetchAssignments() {
    const response = await fetch('/api/student/assignments');
    if (response.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (!response.ok) {
        throw new Error('Không thể tải bài tập');
    }
    assignments = await response.json();
}

function getStatusBadge(status) {
    if (status === 'Đã nộp') {
        return '<span class="badge bg-success text-white"><i class="bi bi-check-lg me-1"></i>Đã nộp</span>';
    }
    if (status === 'Nộp trễ') {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Nộp trễ</span>';
    }
    return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Đã giao</span>';
}

function renderTabCounts() {
    const tabs = document.querySelectorAll('#assignmentTabs .nav-link');
    if (!tabs.length) return;

    const pending = assignments.filter((x) => x.submission_status === 'Chưa nộp').length;
    const late = assignments.filter((x) => x.submission_status === 'Nộp trễ').length;
    const done = assignments.filter((x) => x.submission_status === 'Đã nộp').length;

    tabs[0].textContent = `Cần làm (${pending})`;
    tabs[1].textContent = `Thiếu (${late})`;
    tabs[2].textContent = `Đã xong (${done})`;
}

function renderAssignmentList() {
    const groupsContainerId = 'assignmentGroupsContainer';
    let groupsContainer = document.getElementById(groupsContainerId);
    if (!groupsContainer) {
        groupsContainer = document.createElement('div');
        groupsContainer.id = groupsContainerId;
        listView.appendChild(groupsContainer);
    }

    const grouped = assignments.reduce((acc, item) => {
        const key = `${item.subject_name} (${item.subject_code})`;
        if (!acc[key]) acc[key] = [];
        acc[key].push(item);
        return acc;
    }, {});

    const html = Object.entries(grouped).map(([subjectKey, items]) => {
        const cards = items.map((item) => `
            <div class="card assignment-card mb-2 border-0 shadow-sm" onclick="openAssignmentDetail(${item.id})">
                <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="assignment-icon-bg bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">${escapeHtml(item.title)}</h6>
                            <div class="small text-muted">${escapeHtml(item.subject_name)}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-danger mb-1">Đến hạn: ${formatDateTimeVN(item.deadline)}</div>
                        ${getStatusBadge(item.submission_status)}
                    </div>
                </div>
            </div>
        `).join('');

        return `
            <div class="mb-4 subject-group">
                <h5 class="fw-bold text-primary mb-3 border-bottom border-primary border-2 d-inline-block pb-1 subject-title">${escapeHtml(subjectKey)}</h5>
                ${cards}
            </div>
        `;
    }).join('');

    groupsContainer.innerHTML = html || '<div class="text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-3"></i>Chưa có bài tập nào được giao.</div>';
}

function filterSubjects() {
    const input = (document.getElementById('subjectFilter').value || '').toLowerCase();
    const subjectGroups = document.querySelectorAll('.subject-group');
    subjectGroups.forEach((group) => {
        const title = (group.querySelector('.subject-title')?.innerText || '').toLowerCase();
        group.style.display = title.includes(input) ? '' : 'none';
    });
}

function renderItems(isReadOnly = false) {
    uploadedFilesContainer.innerHTML = '';

    if (submittedItems.length === 0) {
        btnTurnIn.innerText = 'Đánh dấu là đã hoàn thành';
        return;
    }

    btnTurnIn.innerText = 'Nộp bài';

    submittedItems.forEach((item, index) => {
        const iconHTML = item.type === 'file'
            ? '<i class="bi bi-file-earmark-text-fill text-danger fs-4 me-3"></i>'
            : '<i class="bi bi-link-45deg text-success fs-3 me-2"></i>';

        const removeBtnHTML = isReadOnly ? '' : `<button class="btn btn-link text-muted p-0 ms-2" onclick="removeItem(${index})"><i class="bi bi-x-lg"></i></button>`;

        uploadedFilesContainer.insertAdjacentHTML('beforeend', `
            <div class="file-upload-item shadow-sm">
                <div class="d-flex align-items-center overflow-hidden">
                    ${iconHTML}
                    <div class="text-truncate">
                        <div class="fw-bold small text-dark mb-0 text-truncate">${escapeHtml(item.name)}</div>
                        <div class="text-muted text-truncate" style="font-size: 0.7rem;">${escapeHtml(item.extra)}</div>
                    </div>
                </div>
                ${removeBtnHTML}
            </div>
        `);
    });
}

function openAssignmentDetail(assignmentId) {
    const item = assignments.find((x) => Number(x.id) === Number(assignmentId));
    if (!item) return;

    currentAssignment = item;
    submittedItems = [];

    document.getElementById('detailTitle').innerText = item.title || '';
    document.getElementById('detailAuthor').innerText = `${item.subject_name || ''} • ${item.subject_code || ''}`;
    document.getElementById('detailScore').innerText = item.score != null ? `${Number(item.score).toFixed(1)} điểm` : 'Chưa chấm';
    document.getElementById('detailDeadline').innerText = `Đến hạn: ${formatDateTimeVN(item.deadline)}`;
    document.getElementById('detailDesc').innerText = item.description || 'Không có mô tả chi tiết.';

    const isDone = item.submission_status !== 'Chưa nộp';
    workStatusText.innerText = item.submission_status;
    workStatusText.className = isDone ? 'fw-bold text-dark' : 'fw-bold text-success';

    if (isDone) {
        btnAddFile.classList.add('d-none');
        btnTurnIn.classList.add('d-none');
        btnUnsubmit.classList.remove('d-none');
    } else {
        btnAddFile.classList.remove('d-none');
        btnTurnIn.classList.remove('d-none');
        btnUnsubmit.classList.add('d-none');
    }

    if (item.feedback) {
        teacherFeedbackDiv.classList.remove('d-none');
        teacherFeedbackDiv.querySelector('p').innerText = `"${item.feedback}"`;
    } else {
        teacherFeedbackDiv.classList.add('d-none');
    }

    if (item.drive_link) {
        submittedItems.push({ type: 'link', name: 'Bài đã nộp', extra: item.drive_link });
    }
    renderItems(isDone);

    listView.classList.add('d-none');
    detailView.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closeAssignmentDetail() {
    detailView.classList.add('d-none');
    listView.classList.remove('d-none');
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        submittedItems.push({
            type: 'file',
            name: file.name,
            extra: file.name.split('.').pop().toUpperCase(),
            fileRef: file
        });
        renderItems();
    }
}

function addLinkItem() {
    const url = (document.getElementById('linkInput').value || '').trim();
    if (!url) return;
    try {
        const domain = new URL(url).hostname.replace('www.', '');
        submittedItems.push({ type: 'link', name: `Liên kết: ${domain}`, extra: url });
        document.getElementById('linkInput').value = '';
        bootstrap.Modal.getInstance(document.getElementById('addLinkModal')).hide();
        renderItems();
    } catch {
        alert('Liên kết không hợp lệ.');
    }
}

function removeItem(index) {
    submittedItems.splice(index, 1);
    renderItems();
}

async function uploadFileToDrive(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('/api/upload-to-drive', {
        method: 'POST',
        body: formData
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || payload.error || 'Upload file thất bại');
    }

    return response.json();
}

async function turnInWork() {
    if (!currentAssignment) return;

    try {
        let driveLink = '';
        let driveFileId = null;

        const linkItem = submittedItems.find((x) => x.type === 'link' && x.extra);
        if (linkItem) {
            driveLink = linkItem.extra;
        }

        const fileItem = submittedItems.find((x) => x.type === 'file' && x.fileRef);
        const file = fileItem?.fileRef || null;
        if (file) {
            const upload = await uploadFileToDrive(file);
            driveLink = upload.link || driveLink;
            driveFileId = upload.fileId || null;
        }

        if (!driveLink) {
            const confirmed = confirm('Bạn chưa đính kèm file hoặc link. Hệ thống sẽ lưu một bản nộp trống, tiếp tục?');
            if (!confirmed) return;
            driveLink = 'N/A';
        }

        const response = await fetch(`/api/student/assignments/${currentAssignment.id}/submit`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ driveLink, driveFileId })
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || 'Không thể nộp bài');
        }

        alert('Nộp bài thành công.');
        await fetchAssignments();
        renderTabCounts();
        renderAssignmentList();
        closeAssignmentDetail();
    } catch (error) {
        console.error(error);
        alert(error.message || 'Nộp bài thất bại.');
    }
}

async function unsubmitWork() {
    if (!currentAssignment) return;
    if (!confirm('Bạn có chắc chắn muốn hủy nộp bài này?')) return;

    try {
        const response = await fetch(`/api/student/assignments/${currentAssignment.id}/submission`, {
            method: 'DELETE'
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || 'Không thể hủy nộp bài');
        }

        alert('Đã hủy nộp bài.');
        await fetchAssignments();
        renderTabCounts();
        renderAssignmentList();
        closeAssignmentDetail();
    } catch (error) {
        console.error(error);
        alert(error.message || 'Hủy nộp bài thất bại.');
    }
}

async function initAssignmentsPage() {
    try {
        await fetchAssignments();
        renderTabCounts();
        renderAssignmentList();
    } catch (error) {
        console.error(error);
        alert('Không thể tải dữ liệu bài tập.');
    }
}

document.addEventListener('DOMContentLoaded', initAssignmentsPage);

window.filterSubjects = filterSubjects;
window.openAssignmentDetail = openAssignmentDetail;
window.closeAssignmentDetail = closeAssignmentDetail;
window.handleFileSelect = handleFileSelect;
window.addLinkItem = addLinkItem;
window.removeItem = removeItem;
window.turnInWork = turnInWork;
window.unsubmitWork = unsubmitWork;
