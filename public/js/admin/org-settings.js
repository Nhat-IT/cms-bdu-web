let adminDepartments = [];
let adminSemesters = [];

function adminFormatDate(value) {
    if (!value) return '--';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        const raw = String(value).slice(0, 10);
        const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : raw;
    }
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function renderDepartments() {
    const tbody = document.getElementById('adminDepartmentsBody');
    if (!tbody) return;

    if (!adminDepartments.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Chua co du lieu nganh hoc.</td></tr>';
        return;
    }

    tbody.innerHTML = adminDepartments.map((d) => `
        <tr>
            <td class="ps-4 fw-bold text-primary">${d.department_name || ''}</td>
            <td class="fw-bold text-dark">${d.department_name || ''}</td>
            <td>${adminFormatDate(d.created_at)}</td>
            <td class="text-end pe-4">
                <button class="btn btn-light action-btn text-primary border me-1" title="Sua" data-bs-toggle="modal" data-bs-target="#majorModal" onclick="openMajorModal('edit', '${(d.department_name || '').replace(/'/g, "\\'")}', '${(d.department_name || '').replace(/'/g, "\\'")}')"><i class="bi bi-pencil"></i></button>
            </td>
        </tr>`).join('');
}

function renderSemesters() {
    const tbody = document.getElementById('adminSemestersBody');
    if (!tbody) return;

    if (!adminSemesters.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Chua co du lieu hoc ky.</td></tr>';
        return;
    }

    const now = new Date();
    tbody.innerHTML = adminSemesters.map((s) => {
        const start = s.start_date ? new Date(s.start_date) : null;
        const end = s.end_date ? new Date(s.end_date) : null;
        const isActive = start && end && start <= now && now <= end;
        const statusBadge = isActive
            ? '<span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>Hoc ky hien tai</span>'
            : '<span class="badge bg-secondary bg-opacity-25 text-secondary border">Da ket thuc / Chua den</span>';

        return `
            <tr class="${isActive ? 'bg-success bg-opacity-10' : ''}">
                <td class="ps-4 fw-bold ${isActive ? 'text-success' : 'text-muted'}">${s.semester_name || ''}</td>
                <td class="fw-bold text-dark">${s.academic_year || ''}</td>
                <td>${adminFormatDate(s.start_date)} <i class="bi bi-arrow-right mx-1 text-muted"></i> ${adminFormatDate(s.end_date)}</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-light action-btn text-primary border" data-bs-toggle="modal" data-bs-target="#semesterModal" onclick="openSemesterModal('edit', '${s.semester_name || ''}', '${s.academic_year || ''}', '${(s.start_date || '').slice(0, 10)}', '${(s.end_date || '').slice(0, 10)}', ${isActive ? 'true' : 'false'})" title="Sua"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>`;
    }).join('');
}

async function loadAdminOrgSettings() {
    const res = await fetch('/api/admin/org-settings', { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the tai cau hinh hoc vu.');
        return;
    }

    adminDepartments = Array.isArray(data.departments) ? data.departments : [];
    adminSemesters = Array.isArray(data.semesters) ? data.semesters : [];

    renderDepartments();
    renderSemesters();
}

document.addEventListener('DOMContentLoaded', loadAdminOrgSettings);
