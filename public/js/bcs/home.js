function toNumber(value) {
    const n = Number(value || 0);
    return Number.isFinite(n) ? n : 0;
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = String(value);
    }
}

function formatDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
}

function renderTodaySchedule(rows) {
    const tbody = document.getElementById('bcsTodayScheduleBody');
    if (!tbody) {
        return;
    }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="5">Hôm nay không có lịch học.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(function (row) {
        const periodText = `Tiết ${row.start_period || '?'} - ${row.end_period || '?'}`;
        const subjectName = row.subject_name || 'N/A';
        const room = row.room || 'Chưa cập nhật';
        const teacherName = row.teacher_name || 'Chưa cập nhật';

        return `
            <tr>
                <td><span class="badge bg-secondary">${periodText}</span></td>
                <td class="fw-bold text-dark">${subjectName}</td>
                <td class="text-muted">${room}</td>
                <td>${teacherName}</td>
                <td><a href="attendance.html" class="btn btn-sm btn-primary fw-bold shadow-sm">Điểm danh ngay</a></td>
            </tr>`;
    }).join('');
}

function renderAnnouncements(rows) {
    const container = document.getElementById('bcsAnnouncementList');
    if (!container) {
        return;
    }

    if (!rows.length) {
        container.innerHTML = '<div class="list-group-item py-3 border-bottom text-muted">Chưa có thông báo mới.</div>';
        return;
    }

    container.innerHTML = rows.map(function (item) {
        const title = item.title || 'Thông báo hệ thống';
        const message = item.message || 'Không có nội dung chi tiết.';
        const isUnread = Number(item.is_read) === 0;
        const created = formatDateTime(item.created_at);
        const badge = isUnread
            ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">Mới</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Đã đọc</span>';

        return `
            <a href="announcements.html" class="list-group-item list-group-item-action py-3 border-bottom">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 fw-bold text-dark">${title}</h6>
                    ${badge}
                </div>
                <p class="mb-1 text-muted small">${message}</p>
                <small class="text-muted">${created}</small>
            </a>`;
    }).join('');
}

async function loadBcsDashboard() {
    try {
        const response = await fetch('/api/bcs/dashboard', {
            headers: { Accept: 'application/json' }
        });

        if (response.status === 401) {
            window.location.href = '/login.html';
            return;
        }

        if (response.status === 403) {
            return;
        }

        if (!response.ok) {
            throw new Error('Không thể tải dashboard BCS');
        }

        const data = await response.json();
        const stats = data.stats || {};

        setText('bcsStatTotalStudents', toNumber(stats.totalStudents));
        setText('bcsStatAbsentToday', toNumber(stats.absentToday));
        setText('bcsStatPendingEvidence', toNumber(stats.pendingEvidence));
        setText('bcsStatNewFeedback', toNumber(stats.newFeedback));

        if (data.classInfo && data.classInfo.className) {
            setText('userClassName', data.classInfo.className);
            const classBadge = document.querySelector('.bcs-class-badge');
            if (classBadge) {
                classBadge.textContent = `LỚP: ${data.classInfo.className}`;
            }
        }

        renderTodaySchedule(Array.isArray(data.todaySchedule) ? data.todaySchedule : []);
        renderAnnouncements(Array.isArray(data.announcements) ? data.announcements : []);
    } catch (error) {
        const tbody = document.getElementById('bcsTodayScheduleBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td class="text-center text-danger py-4" colspan="5">Không tải được lịch học hôm nay.</td></tr>';
        }

        const announcements = document.getElementById('bcsAnnouncementList');
        if (announcements) {
            announcements.innerHTML = '<div class="list-group-item py-3 border-bottom text-danger">Không tải được thông báo.</div>';
        }
    }
}

document.addEventListener('DOMContentLoaded', loadBcsDashboard);
