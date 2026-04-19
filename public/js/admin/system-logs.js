let adminSystemLogs = [];

function adminLogDateTime(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 19);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    const sec = String(d.getSeconds()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}:${sec}`;
}

function adminActionBadge(action) {
    const text = String(action || '').toLowerCase();
    if (text.includes('delete')) return '<span class="badge bg-danger">DELETE</span>';
    if (text.includes('create') || text.includes('insert')) return '<span class="badge bg-success">CREATE</span>';
    if (text.includes('update')) return '<span class="badge bg-primary">UPDATE</span>';
    if (text.includes('login')) return '<span class="badge bg-info text-dark">LOGIN</span>';
    return `<span class="badge bg-secondary">${action || 'LOG'}</span>`;
}

function renderAdminLogsTable() {
    const tbody = document.getElementById('adminSystemLogsBody');
    if (!tbody) return;

    if (!adminSystemLogs.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Chua co du lieu nhat ky he thong.</td></tr>';
        return;
    }

    tbody.innerHTML = adminSystemLogs.map((log) => {
        const actorName = log.full_name || 'System';
        const username = log.username || 'system';
        const role = log.role || 'system';
        const detail = `${log.action || ''} (${log.target_table || 'n/a'} #${log.target_id || '-'})`;

        return `
            <tr>
                <td class="ps-4 text-dark log-time">${adminLogDateTime(log.created_at)}</td>
                <td>
                    <div class="fw-bold text-dark">${actorName}</div>
                    <div class="small text-muted">${username} (${role})</div>
                </td>
                <td>${adminActionBadge(log.action)}</td>
                <td class="text-dark">${detail}</td>
                <td class="pe-4 text-end text-muted small">-</td>
            </tr>`;
    }).join('');
}

async function loadAdminSystemLogs() {
    const params = new URLSearchParams({
        keyword: (document.getElementById('adminLogKeyword')?.value || '').trim(),
        role: document.getElementById('adminLogRole')?.value || 'all',
        action: document.getElementById('adminLogAction')?.value || 'all',
        date: document.getElementById('adminLogDate')?.value || ''
    });

    const res = await fetch(`/api/admin/system-logs?${params.toString()}`, { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => []);
    if (!res.ok) {
        alert(data.error || 'Khong the tai system logs.');
        return;
    }

    adminSystemLogs = Array.isArray(data) ? data : [];
    renderAdminLogsTable();
}

function exportLogs() {
    if (!adminSystemLogs.length) {
        alert('Khong co du lieu de xuat log.');
        return;
    }

    const csvRows = [
        ['Time', 'Actor', 'Username', 'Role', 'Action', 'Target table', 'Target id'].join(','),
        ...adminSystemLogs.map((log) => {
            const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
            return [
                adminLogDateTime(log.created_at),
                log.full_name || '',
                log.username || '',
                log.role || '',
                log.action || '',
                log.target_table || '',
                log.target_id || ''
            ].map(esc).join(',');
        })
    ];

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `system_logs_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('adminLogKeyword')?.addEventListener('input', loadAdminSystemLogs);
    document.getElementById('adminLogRole')?.addEventListener('change', loadAdminSystemLogs);
    document.getElementById('adminLogAction')?.addEventListener('change', loadAdminSystemLogs);
    document.getElementById('adminLogDate')?.addEventListener('change', loadAdminSystemLogs);

    loadAdminSystemLogs();
});
