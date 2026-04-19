<<<<<<< HEAD
        // JS Xử lý truyền dữ liệu vào Modal
        function openEvidenceModal(name, id, className, date, imageUrl, status) {
            // Gán thông tin
            document.getElementById('modalStudentName').innerText = name;
            document.getElementById('modalStudentId').innerText = 'MSSV: ' + id;
            document.getElementById('modalClass').innerText = className;
            document.getElementById('modalDate').innerText = date;
            
            // Gán ảnh giả lập (Thực tế sẽ là link Cloudinary trong bảng attendance_evidences)
            document.getElementById('modalEvidenceImage').src = imageUrl;
            
            // Xử lý ẩn/hiện nút bấm dựa vào trạng thái (status)
            const actionDiv = document.getElementById('actionButtonsDiv');
            const rejectDiv = document.getElementById('rejectReasonDiv');
            const messageDiv = document.getElementById('statusMessageDiv');
            
            if(status === 'pending') {
                actionDiv.classList.remove('d-none'); // Hiện 2 nút Duyệt/Từ chối
                rejectDiv.classList.remove('d-none'); // Hiện ô nhập lý do từ chối
                messageDiv.classList.add('d-none');   // Ẩn thông báo đã xử lý
            } else {
                actionDiv.classList.add('d-none');    // Ẩn 2 nút vì đã xử lý rồi
                rejectDiv.classList.add('d-none');    // Ẩn ô nhập
                messageDiv.classList.remove('d-none'); // Hiện thông báo "Đã xử lý"
            }

            // Mở Modal
            var evidenceModal = new bootstrap.Modal(document.getElementById('evidenceModal'));
            evidenceModal.show();
        }

        // JS Giả lập xử lý Duyệt/Từ chối
        function processEvidence(actionType) {
            if(actionType === 'Approved') {
                alert("✅ Đã DUYỆT minh chứng! Trạng thái sinh viên trong bảng điểm danh sẽ được chuyển thành: Vắng CÓ PHÉP.");
            } else {
                alert("❌ Đã TỪ CHỐI minh chứng! Trạng thái sinh viên trong bảng điểm danh sẽ bị đánh dấu: Vắng KHÔNG PHÉP.");
            }
            // Đóng Modal sau khi xử lý
            var evidenceModal = bootstrap.Modal.getInstance(document.getElementById('evidenceModal'));
            evidenceModal.hide();
        }
=======
const evidenceState = {
    groups: [],
    evidences: [],
    current: null
};

function formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function mapStatus(status) {
    if (status === 'Approved') return 'approved';
    if (status === 'Rejected') return 'rejected';
    return 'pending';
}

function statusBadge(status) {
    if (status === 'Approved') {
        return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill me-1"></i>Approved</span>';
    }
    if (status === 'Rejected') {
        return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>';
    }
    return '<span class="badge bg-warning text-dark border border-warning">Pending</span>';
}

function populateClassFilter(groups) {
    const select = document.getElementById('classFilter');
    if (!select) return;

    const unique = new Map();
    groups.forEach((g) => unique.set(Number(g.group_id), `${g.class_name} - ${g.subject_name} (G${g.group_code || ''})`));

    select.innerHTML = '<option value="">-- All groups --</option>' + Array.from(unique.entries()).map(([id, label]) => {
        return `<option value="${id}">${label}</option>`;
    }).join('');
}

function renderTable(rows) {
    const tbody = document.getElementById('teacherEvidenceTableBody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No evidences found.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((row) => {
        const classLabel = `${row.class_name} - ${row.subject_name} (G${row.group_code || ''})`;
        const uploaded = row.uploaded_at ? new Date(row.uploaded_at).toLocaleString('vi-VN') : '';
        const reviewBtnClass = row.evidence_status === 'Pending' ? 'btn-primary fw-bold' : 'btn-outline-secondary';
        const reviewBtnLabel = row.evidence_status === 'Pending' ? 'Review' : 'Viewed';
        return `
            <tr>
                <td class="ps-4 fw-bold text-dark">${formatDate(row.attendance_date)}</td>
                <td class="fw-bold text-primary">${row.username || ''}</td>
                <td class="fw-bold">${row.full_name || ''}</td>
                <td class="text-muted small">${classLabel}</td>
                <td>${uploaded}</td>
                <td class="text-center">${statusBadge(row.evidence_status)}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm ${reviewBtnClass}" data-evidence-id="${row.evidence_id}">
                        <i class="bi bi-eye-fill me-1"></i> ${reviewBtnLabel}
                    </button>
                </td>
            </tr>`;
    }).join('');

    tbody.querySelectorAll('button[data-evidence-id]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const evidenceId = Number(btn.getAttribute('data-evidence-id'));
            const item = evidenceState.evidences.find((x) => Number(x.evidence_id) === evidenceId);
            if (item) openEvidenceModal(item);
        });
    });
}

function openEvidenceModal(item) {
    evidenceState.current = item;
    document.getElementById('modalStudentName').textContent = item.full_name || '';
    document.getElementById('modalStudentId').textContent = `MSSV: ${item.username || ''}`;
    document.getElementById('modalClass').textContent = `${item.class_name} - ${item.subject_name} (G${item.group_code || ''})`;
    document.getElementById('modalDate').textContent = formatDate(item.attendance_date);
    document.getElementById('modalEvidenceImage').src = item.drive_link || 'https://via.placeholder.com/800x500?text=No+Preview';

    const actionDiv = document.getElementById('actionButtonsDiv');
    const rejectDiv = document.getElementById('rejectReasonDiv');
    const messageDiv = document.getElementById('statusMessageDiv');
    const pending = item.evidence_status === 'Pending';

    actionDiv.classList.toggle('d-none', !pending);
    rejectDiv.classList.toggle('d-none', !pending);
    messageDiv.classList.toggle('d-none', pending);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('evidenceModal')).show();
}

async function reviewCurrentEvidence(action) {
    if (!evidenceState.current) return;

    const evidenceId = Number(evidenceState.current.evidence_id);
    const res = await fetch(`/api/teacher/evidences/${evidenceId}/review`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ action })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot review evidence.');
        return;
    }

    bootstrap.Modal.getInstance(document.getElementById('evidenceModal'))?.hide();
    await loadEvidences();
}

async function loadEvidences() {
    const status = document.getElementById('statusFilter')?.value || 'all';
    const groupId = document.getElementById('classFilter')?.value || '';
    const keyword = document.getElementById('evidenceSearchInput')?.value?.trim() || '';

    const query = new URLSearchParams();
    query.set('status', status);
    if (groupId) query.set('groupId', groupId);
    if (keyword) query.set('keyword', keyword);

    const res = await fetch(`/api/teacher/evidences?${query.toString()}`, {
        headers: { Accept: 'application/json' }
    });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    if (!res.ok) {
        alert('Cannot load evidences.');
        return;
    }

    const rows = await res.json();
    evidenceState.evidences = Array.isArray(rows) ? rows : [];
    renderTable(evidenceState.evidences);

    const pendingCount = evidenceState.evidences.filter((x) => mapStatus(x.evidence_status) === 'pending').length;
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        const pendingOption = statusFilter.querySelector('option[value="pending"]');
        if (pendingOption) {
            pendingOption.textContent = `Pending (${pendingCount})`;
        }
    }
}

async function loadTeacherEvidencePage() {
    const groupsRes = await fetch('/api/teacher/groups', { headers: { Accept: 'application/json' } });
    if (groupsRes.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (groupsRes.ok) {
        const groups = await groupsRes.json();
        evidenceState.groups = Array.isArray(groups) ? groups : [];
        populateClassFilter(evidenceState.groups);
    }

    document.getElementById('statusFilter')?.addEventListener('change', loadEvidences);
    document.getElementById('classFilter')?.addEventListener('change', loadEvidences);
    document.getElementById('evidenceSearchInput')?.addEventListener('input', loadEvidences);
    document.getElementById('teacherApproveEvidenceBtn')?.addEventListener('click', function () {
        reviewCurrentEvidence('approve');
    });
    document.getElementById('teacherRejectEvidenceBtn')?.addEventListener('click', function () {
        reviewCurrentEvidence('reject');
    });

    loadEvidences();
}

document.addEventListener('DOMContentLoaded', loadTeacherEvidencePage);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
