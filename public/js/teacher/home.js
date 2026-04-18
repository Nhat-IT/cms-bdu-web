function formatDayOfWeek(day) {
    const map = {
        2: 'Thứ 2',
        3: 'Thứ 3',
        4: 'Thứ 4',
        5: 'Thứ 5',
        6: 'Thứ 6',
        7: 'Thứ 7',
        8: 'Chủ nhật'
    };
    return map[Number(day)] || 'N/A';
}

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

function buildClassRow(item) {
    const className = item.class_name || 'N/A';
    const subjectName = item.subject_name || 'N/A';
    const studentCount = toNumber(item.student_count);
    const day = formatDayOfWeek(item.day_of_week);
    const period = `Tiết ${item.start_period || '?'}-${item.end_period || '?'}`;

    return `
        <tr>
            <td class="fw-bold ps-4 text-primary">${className}</td>
            <td class="fw-bold text-dark">${subjectName}<br><small class="text-muted fw-normal"><i class="bi bi-clock me-1"></i>${day} (${period})</small></td>
            <td>${studentCount} SV</td>
            <td class="text-end pe-4">
                <a href="attendance.html" class="btn btn-light action-btn text-success border me-1" title="Điểm danh"><i class="bi bi-person-check"></i></a>
                <a href="class-assignments.html" class="btn btn-light action-btn text-warning border me-1" title="Chấm bài"><i class="bi bi-journal-check"></i></a>
                <a href="class-grades.html" class="btn btn-light action-btn text-info border" title="Bảng điểm"><i class="bi bi-bar-chart-fill"></i></a>
            </td>
        </tr>`;
}

function renderClassTable(items) {
    const tbody = document.getElementById('teacherClassTableBody');
    if (!tbody) {
        return;
    }

    if (!items.length) {
        tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="4">Chưa có lớp học phần được phân công.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(buildClassRow).join('');
}

function renderReminders(stats) {
    const container = document.getElementById('teacherReminderList');
    if (!container) {
        return;
    }

    const pendingEvidence = toNumber(stats.pendingEvidence);
    const pendingGrading = toNumber(stats.pendingGrading);
    const weeklySessions = toNumber(stats.weeklySessions);

    container.innerHTML = `
        <div class="reminder-card reminder-danger">
            <h6 class="fw-bold mb-1 text-danger">Minh chứng chờ duyệt</h6>
            <p class="mb-0 small text-dark">Bạn đang có ${pendingEvidence} minh chứng cần xử lý.</p>
        </div>
        <div class="reminder-card reminder-warning">
            <h6 class="fw-bold mb-1 text-warning" style="color: #e6a800 !important;">Bài tập chờ chấm</h6>
            <p class="mb-0 small text-dark">Hiện có ${pendingGrading} bài nộp chưa được chấm điểm.</p>
        </div>
        <div class="reminder-card reminder-primary">
            <h6 class="fw-bold mb-1 text-primary">Lịch dạy tuần này</h6>
            <p class="mb-0 small text-dark">Bạn có ${weeklySessions} buổi dạy lặp theo tuần trong học kỳ hiện tại.</p>
        </div>`;
}

function attachSearch(classes) {
    const input = document.getElementById('teacherClassSearch');
    if (!input) {
        return;
    }

    input.addEventListener('input', function () {
        const keyword = input.value.trim().toLowerCase();
        if (!keyword) {
            renderClassTable(classes);
            return;
        }

        const filtered = classes.filter(function (item) {
            const className = String(item.class_name || '').toLowerCase();
            const subjectName = String(item.subject_name || '').toLowerCase();
            return className.includes(keyword) || subjectName.includes(keyword);
        });
        renderClassTable(filtered);
    });
}

async function loadTeacherDashboard() {
    try {
        const response = await fetch('/api/teacher/dashboard', {
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
            throw new Error('Không thể tải dashboard giảng viên');
        }

        const data = await response.json();
        const stats = data.stats || {};
        const classes = Array.isArray(data.classes) ? data.classes : [];

        setText('teacherStatClassCount', toNumber(stats.classCount));
        setText('teacherStatWeeklySessions', toNumber(stats.weeklySessions));
        setText('teacherStatPendingGrading', toNumber(stats.pendingGrading));
        setText('teacherStatPendingEvidence', toNumber(stats.pendingEvidence));

        renderClassTable(classes);
        renderReminders(stats);
        attachSearch(classes);
    } catch (error) {
        const tbody = document.getElementById('teacherClassTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td class="text-center text-danger py-4" colspan="4">Không tải được dữ liệu giảng dạy.</td></tr>';
        }

        const reminderList = document.getElementById('teacherReminderList');
        if (reminderList) {
            reminderList.innerHTML = '<div class="reminder-card reminder-danger"><h6 class="fw-bold mb-1 text-danger">Lỗi tải dữ liệu</h6><p class="mb-0 small text-dark">Vui lòng tải lại trang hoặc kiểm tra phiên đăng nhập.</p></div>';
        }
    }
}

document.addEventListener('DOMContentLoaded', loadTeacherDashboard);
