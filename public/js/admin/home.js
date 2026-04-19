function formatDateRange(startDate, endDate) {
    const start = startDate ? String(startDate).slice(0, 10) : '';
    const end = endDate ? String(endDate).slice(0, 10) : '';
    if (!start && !end) {
        return 'Không có mốc thời gian';
    }
    return `${start || '?'} - ${end || '?'}`;
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = String(value);
    }
}

function renderClassSubjects(rows) {
    const tbody = document.getElementById('adminClassSubjectsTableBody');
    if (!tbody) {
        return;
    }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu lớp học phần.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(function (row) {
        const className = row.class_name || 'N/A';
        const subjectName = row.subject_name || 'N/A';
        const teacherName = row.teacher_name || 'Chưa phân công';
        const active = row.start_date && row.end_date;
        const statusHtml = active
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Đang mở</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Chưa mở</span>';

        return `
            <tr>
                <td class="fw-bold ps-4">${className}</td>
                <td>${subjectName}<br><small class="text-muted">${formatDateRange(row.start_date, row.end_date)}</small></td>
                <td><span class="badge bg-light text-dark border">${teacherName}</span></td>
                <td class="text-center">${statusHtml}</td>
                <td class="text-end pe-4">
                    <a class="btn btn-light action-btn text-primary border" href="assignments.html" title="Đi tới phân công">
                        <i class="bi bi-arrow-right-circle"></i>
                    </a>
                </td>
            </tr>`;
    }).join('');
}

async function loadAdminHome() {
    try {
        const [meRes, dashboardRes] = await Promise.all([
            fetch('/api/me', { headers: { Accept: 'application/json' } }),
            fetch('/api/admin/dashboard', { headers: { Accept: 'application/json' } })
        ]);

        if (meRes.status === 401 || dashboardRes.status === 401) {
            window.location.href = '/login.html';
            return;
        }

        if (meRes.ok) {
            const me = await meRes.json();
            const operator = document.querySelector('.admin-operator-name');
            if (operator) {
                operator.textContent = me.full_name || me.username || 'Admin';
            }
        }

        if (dashboardRes.status === 403) {
            return;
        }

        if (!dashboardRes.ok) {
            throw new Error('Không thể tải dashboard admin');
        }

        const data = await dashboardRes.json();
        const stats = data.stats || {};

        setText('adminStatStudents', Number(stats.totalStudents || 0));
        setText('adminStatTeachers', Number(stats.totalTeachers || 0));
        setText('adminStatClasses', Number(stats.totalClasses || 0));
        setText('adminStatOpenClasses', Number(stats.totalOpenClassSubjects || 0));

        renderClassSubjects(Array.isArray(data.classSubjects) ? data.classSubjects : []);
    } catch (error) {
        const tbody = document.getElementById('adminClassSubjectsTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Không tải được dữ liệu admin dashboard.</td></tr>';
        }
    }
}

document.addEventListener('DOMContentLoaded', loadAdminHome);
