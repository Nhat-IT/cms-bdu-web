let adminSystemLogs = [];
let systemLogsAbortController = null;
let systemLogsRequestId = 0;

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

function buildSystemLogParams() {
    return new URLSearchParams({
        keyword: (document.getElementById('adminLogKeyword')?.value || '').trim(),
        role: document.getElementById('adminLogRole')?.value || 'all',
        action: document.getElementById('adminLogAction')?.value || 'all',
        date: document.getElementById('adminLogDate')?.value || ''
    });
}

async function loadAdminSystemLogs() {
    if (systemLogsAbortController) {
        systemLogsAbortController.abort();
    }

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    systemLogsAbortController = controller;
    const requestId = ++systemLogsRequestId;

    try {
        const params = buildSystemLogParams();
        const fetchOptions = {
            headers: { Accept: 'application/json' }
        };
        if (controller) {
            fetchOptions.signal = controller.signal;
        }

        const res = await fetch(`/api/admin/system-logs?${params.toString()}`, fetchOptions);
        if (res.status === 401) {
            window.location.href = '/login.html';
            return;
        }

        const data = await res.json().catch(() => []);
        if (!res.ok) {
            alert(data.error || 'Khong the tai system logs.');
            return;
        }

        if (requestId !== systemLogsRequestId) {
            return;
        }

        adminSystemLogs = Array.isArray(data) ? data : [];
        renderAdminLogsTable();
    } catch (error) {
        if (error && error.name === 'AbortError') {
            return;
        }
        throw error;
    } finally {
        if (requestId === systemLogsRequestId) {
            systemLogsAbortController = null;
        }
    }
}

function debounce(callback, wait) {
    let timeout = null;

    return function () {
        const context = this;
        const args = arguments;

        clearTimeout(timeout);
        timeout = setTimeout(function () {
            callback.apply(context, args);
        }, wait);
    };
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

async function safeLoadAdminSystemLogs() {
    try {
        await loadAdminSystemLogs();
    } catch (error) {
        console.error('Load admin system logs error:', error);
        alert('Khong the tai system logs. Vui long thu lai.');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const debouncedKeywordLoad = debounce(function () {
        safeLoadAdminSystemLogs();
    }, 300);

    document.getElementById('adminLogKeyword')?.addEventListener('input', debouncedKeywordLoad);
    document.getElementById('adminLogRole')?.addEventListener('change', function () {
        safeLoadAdminSystemLogs();
    });
    document.getElementById('adminLogAction')?.addEventListener('change', function () {
        safeLoadAdminSystemLogs();
    });
    document.getElementById('adminLogDate')?.addEventListener('change', function () {
        safeLoadAdminSystemLogs();
    });

    safeLoadAdminSystemLogs();
});
