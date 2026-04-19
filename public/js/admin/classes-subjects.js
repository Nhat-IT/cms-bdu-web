let adminClassesData = [];
let adminSubjectsData = [];

function renderAdminClasses() {
    const tbody = document.getElementById('adminClassTableBody');
    if (!tbody) return;

    const keyword = (document.getElementById('adminClassSearch')?.value || '').trim().toLowerCase();
    const rows = adminClassesData.filter((item) => {
        const text = `${item.class_name || ''} ${item.academic_year || ''}`.toLowerCase();
        return !keyword || text.includes(keyword);
    });

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Khong co du lieu lop hoc.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((item, idx) => {
        const count = Number(item.student_count || 0);
        const isOpen = item.status === 'open';
        const badgeCount = count > 0
            ? `<span class="badge student-count-soft">${count} SV</span>`
            : '<span class="badge student-count-empty">Chua co SV</span>';
        const statusBadge = isOpen
            ? '<span class="badge class-status-open">Dang mo</span>'
            : '<span class="badge class-status-closed">Da dong</span>';

        return `
            <tr>
                <td class="ps-4 text-muted">${idx + 1}</td>
                <td class="fw-bold text-primary fs-6">${item.class_name || ''}</td>
                <td>${item.academic_year || ''}</td>
                <td class="text-center">${badgeCount}</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-end pe-4">
                    <div class="action-btn-group">
                        <button class="btn btn-light action-btn text-success border" title="Import sinh vien" onclick="openImportStudentModal('${item.class_name || ''}')"><i class="bi bi-person-lines-fill"></i></button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

function renderAdminSubjects() {
    const tbody = document.getElementById('adminSubjectTableBody');
    if (!tbody) return;

    const keyword = (document.getElementById('adminSubjectSearch')?.value || '').trim().toLowerCase();
    const rows = adminSubjectsData.filter((item) => {
        const text = `${item.subject_code || ''} ${item.subject_name || ''}`.toLowerCase();
        return !keyword || text.includes(keyword);
    });

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Khong co du lieu mon hoc.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((item) => {
        const isOpen = item.status === 'open';
        return `
            <tr>
                <td class="ps-4 fw-bold text-success">${item.subject_code || ''}</td>
                <td class="fw-bold">${item.subject_name || ''}</td>
                <td class="text-muted fst-italic">-- Khong --</td>
                <td class="text-center"><span class="badge bg-light text-dark border">${item.credits || 0}</span></td>
                <td class="text-center">${isOpen ? '<span class="badge class-status-open">Dang mo</span>' : '<span class="badge class-status-closed">Da dong</span>'}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-light action-btn text-warning border me-1" title="Quan ly trang thai" onclick="openSubjectStatusModal('${item.subject_code || ''}', '${(item.subject_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-clock-history"></i></button>
                </td>
            </tr>`;
    }).join('');
}

async function loadAdminClassesSubjects() {
    const res = await fetch('/api/admin/classes-subjects', { headers: { Accept: 'application/json' } });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the tai du lieu lop va mon hoc.');
        return;
    }

    adminClassesData = Array.isArray(data.classes) ? data.classes : [];
    adminSubjectsData = Array.isArray(data.subjects) ? data.subjects : [];

    renderAdminClasses();
    renderAdminSubjects();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('adminClassSearch')?.addEventListener('input', renderAdminClasses);
    document.getElementById('adminSubjectSearch')?.addEventListener('input', renderAdminSubjects);
    loadAdminClassesSubjects();
});
