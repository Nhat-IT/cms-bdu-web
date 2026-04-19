<<<<<<< HEAD
    function loadDashboardData() {
        const year = document.getElementById('filterYear').options[document.getElementById('filterYear').selectedIndex].text;
        const sem = document.getElementById('filterSemester').options[document.getElementById('filterSemester').selectedIndex].text;
        
        // Mô phỏng hiệu ứng tải dữ liệu
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang tải...';
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert(`✅ Đã tải dữ liệu Thống kê cho:\n- ${year}\n- ${sem}\n- Lớp 25TH01`);
        }, 800);
    }

    function exportDetailExcel() {
        alert('📥 Hệ thống đang trích xuất Báo cáo chi tiết ra file Excel. Quá trình này có thể mất vài giây...');
    }
=======
let bcsDetailData = { stats: {}, rows: [] };

function bcsDetailDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function bcsRenderDetailStats() {
    const cards = document.querySelectorAll('.stat-card-custom h2');
    if (cards.length >= 3) {
        cards[0].textContent = String(bcsDetailData.stats.totalStudents || 0);
        cards[1].textContent = String(bcsDetailData.stats.warningStudents || 0);
        cards[2].textContent = String(bcsDetailData.stats.warningSubjects || 0);
    }
}

function bcsRenderDetailTable() {
    const tbody = document.getElementById('bcsDashboardDetailBody');
    if (!tbody) return;

    const rows = Array.isArray(bcsDetailData.rows) ? bcsDetailData.rows : [];
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Chua co du lieu vang hoc.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((item, idx) => {
        const statusText = Number(item.status) === 2
            ? '<span class="text-warning text-dark fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Co phep</span>'
            : '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Khong phep</span>';
        const evidenceButton = item.drive_link
            ? `<a class="btn btn-sm btn-primary rounded-circle shadow-sm" href="${item.drive_link}" target="_blank" rel="noopener noreferrer"><i class="bi bi-file-earmark-medical"></i></a>`
            : '<button class="btn btn-sm btn-light rounded-circle" disabled><i class="bi bi-eye-slash text-muted"></i></button>';

        return `
            <tr>
                <td class="text-center align-middle border-end">${idx + 1}</td>
                <td class="align-middle pe-4 border-end">
                    <div class="fw-bold text-dark" style="font-size: 0.95rem;">${item.full_name || ''}</div>
                    <div class="text-muted small">${item.username || ''}</div>
                </td>
                <td class="py-3">
                    <span class="badge bg-white text-dark border border-secondary px-3 py-2 fw-normal rounded-1 shadow-sm">${item.subject_name || ''}</span>
                </td>
                <td class="text-dark fw-bold">${bcsDetailDate(item.attendance_date)}</td>
                <td class="text-dark">${item.study_session || ''}</td>
                <td>${statusText}</td>
                <td class="text-center">${evidenceButton}</td>
                <td class="text-center align-middle fw-bold text-danger fs-5 border-start">${item.total_absent_in_subject || 0}</td>
            </tr>`;
    }).join('');
}

async function loadDashboardData() {
    const keyword = (document.getElementById('bcsDashboardDetailKeyword')?.value || '').trim();
    const query = new URLSearchParams({ keyword }).toString();

    const res = await fetch(`/api/bcs/dashboard-detail?${query}`, { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the tai du lieu thong ke chi tiet.');
        return;
    }

    bcsDetailData = data || { stats: {}, rows: [] };
    bcsRenderDetailStats();
    bcsRenderDetailTable();
}

function exportDetailExcel() {
    const rows = Array.isArray(bcsDetailData.rows) ? bcsDetailData.rows : [];
    if (!rows.length) {
        alert('Khong co du lieu de xuat.');
        return;
    }

    const csvRows = [
        ['MSSV', 'Ho ten', 'Mon hoc', 'Ngay vang', 'Buoi', 'Trang thai', 'Tong vang mon'].join(','),
        ...rows.map((r) => {
            const status = Number(r.status) === 2 ? 'Co phep' : 'Khong phep';
            const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
            return [
                r.username || '',
                r.full_name || '',
                r.subject_name || '',
                bcsDetailDate(r.attendance_date),
                r.study_session || '',
                status,
                r.total_absent_in_subject || 0
            ].map(esc).join(',');
        })
    ];

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bcs_dashboard_detail_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('bcsDashboardDetailKeyword')?.addEventListener('input', loadDashboardData);
    loadDashboardData();
});
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
