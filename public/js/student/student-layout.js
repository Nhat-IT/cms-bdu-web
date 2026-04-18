// Giao diện dùng chung Student: sidebar toggle, hồ sơ sidebar và thông báo topbar từ DB.
resetStudentLayoutPlaceholders();

document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }

    initSharedStudentData().catch(function (error) {
        console.error('Shared student layout data error:', error);
    });
});

function resetStudentLayoutPlaceholders() {
    const nameNode = document.querySelector('.profile-container .text-white.fw-bold.fs-6');
    if (nameNode) {
        nameNode.textContent = 'Đang tải...';
    }

    const mssvNode = document.querySelector('.profile-container .text-white-50.small.mb-1');
    if (mssvNode) {
        mssvNode.textContent = 'MSSV: --';
    }

    document.querySelectorAll('.student-class-badge, .profile-class').forEach(function (node) {
        node.textContent = 'LỚP: --';
    });

    document.querySelectorAll('.notification-badge, [data-notification-count]').forEach(function (node) {
        node.textContent = '0';
        node.style.display = 'none';
    });

    // Reset placeholder values on student pages to avoid showing demo data before API hydrate.
    const textFallbacks = [
        ['#detailAuthor', 'Đang tải...'],
        ['#detailScore', '--'],
        ['#detailDeadline', 'Đang tải...'],
        ['#feedbackModalAttachmentName', 'Chưa có tệp đính kèm.'],
        ['#notifyDetailTime', 'Đang tải...'],
        ['#notifyDetailSource', 'Hệ thống'],
        ['#notifyDetailContent', 'Đang tải nội dung thông báo...']
    ];

    textFallbacks.forEach(function (entry) {
        const node = document.querySelector(entry[0]);
        if (node) {
            node.textContent = entry[1];
        }
    });

    const valueFallbacks = [
        ['#profileFullName', ''],
        ['#profileBirthDate', ''],
        ['#profileEmail', ''],
        ['#profilePhoneNumber', ''],
        ['#profileAddress', '']
    ];

    valueFallbacks.forEach(function (entry) {
        const input = document.querySelector(entry[0]);
        if (input) {
            input.value = entry[1];
        }
    });
}

function formatDateTimeVN(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '--/--/---- --:--';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function fetchSharedStudentData() {
    const [profileRes, classesRes, notificationsRes] = await Promise.all([
        fetch('/api/student/profile'),
        fetch('/api/student/classes'),
        fetch('/api/student/notifications')
    ]);

    if (profileRes.status === 401 || classesRes.status === 401 || notificationsRes.status === 401) {
        window.location.href = '/login.html';
        return null;
    }

    const profile = profileRes.ok ? await profileRes.json() : null;
    const classes = classesRes.ok ? await classesRes.json() : [];
    const notifications = notificationsRes.ok ? await notificationsRes.json() : [];

    return { profile, classes, notifications };
}

function updateSidebarProfile(profile, classes) {
    if (!profile) return;

    const avatar = profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.full_name || 'User')}&background=0d6efd&color=fff`;

    document.querySelectorAll('.profile-container img[alt="Avatar"], #sidebarAvatar').forEach(function (img) {
        img.src = avatar;
    });

    const nameNode = document.querySelector('.profile-container .text-white.fw-bold.fs-6');
    if (nameNode) {
        nameNode.textContent = profile.full_name || 'Chưa cập nhật';
    }

    const mssvNode = document.querySelector('.profile-container .text-white-50.small.mb-1');
    if (mssvNode) {
        mssvNode.textContent = `MSSV: ${profile.username || '--'}`;
    }

    const classBadge = document.querySelector('.student-class-badge');
    if (classBadge) {
        const className = classes && classes.length ? classes[0].class_name : '--';
        classBadge.textContent = `LỚP: ${className || '--'}`;
    }
}

function replaceStaticStudentPlaceholders(profile, classes) {
    const className = (classes && classes.length && classes[0].class_name) || profile?.class_name || '--';
    const departmentName = profile?.department_name || '';

    document.querySelectorAll('.student-class-badge, .profile-class, #userClassName').forEach(function (node) {
        node.textContent = `LỚP: ${className}`;
    });

    const leafNodes = document.querySelectorAll('span, b, strong, p, h4, h5, td, option');
    leafNodes.forEach(function (node) {
        if (node.children.length > 0) {
            return;
        }
        const text = String(node.textContent || '');
        let next = text.replace(/\b25TH01\b/g, className);
        if (departmentName) {
            next = next.replace(/\bKHOA\s*CNTT\b/gi, departmentName);
            next = next.replace(/\bKhoa\s*CNTT\b/g, departmentName);
        }
        if (next !== text) {
            node.textContent = next;
        }
    });

    document.querySelectorAll('*').forEach(function (node) {
        ['value', 'placeholder', 'title', 'onclick', 'data-source'].forEach(function (attr) {
            if (!node.hasAttribute(attr)) {
                return;
            }
            const raw = String(node.getAttribute(attr) || '');
            let next = raw.replace(/\b25TH01\b/g, className);
            if (departmentName) {
                next = next.replace(/\bKHOA\s*CNTT\b/gi, departmentName);
                next = next.replace(/\bKhoa\s*CNTT\b/g, departmentName);
            }
            if (next !== raw) {
                node.setAttribute(attr, next);
            }
        });
    });
}

function updateNotificationBadge(notifications) {
    const unread = (notifications || []).filter(function (x) {
        return Number(x.is_read) === 0;
    }).length;

    document.querySelectorAll('.notification-badge, .top-navbar-blue .badge.rounded-pill.bg-danger').forEach(function (badge) {
        badge.textContent = String(unread);
        if (unread > 0) {
            badge.style.display = 'inline-block';
        }
    });
}

function mountNotificationDropdown(notifications) {
    const bellLink = document.querySelector('.top-navbar-blue a[href="notifications-all.html"].text-decoration-none');
    if (!bellLink || bellLink.closest('.student-notify-wrapper')) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'student-notify-wrapper';
    bellLink.parentNode.insertBefore(wrapper, bellLink);
    wrapper.appendChild(bellLink);

    const dropdown = document.createElement('div');
    dropdown.className = 'student-notify-dropdown';
    dropdown.innerHTML = '<div class="student-notify-header"><div class="fw-bold text-dark"><i class="bi bi-bell-fill text-primary me-1"></i> Thông báo mới</div></div>';

    const latest = (notifications || []).slice(0, 5);
    if (!latest.length) {
        const empty = document.createElement('div');
        empty.className = 'student-notify-item';
        empty.textContent = 'Không có thông báo mới.';
        dropdown.appendChild(empty);
    } else {
        latest.forEach(function (item) {
            const row = document.createElement('a');
            row.href = 'notifications-all.html';
            row.className = 'student-notify-item';
            row.innerHTML =
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<span class="student-notify-source text-primary">Thông báo</span>' +
                    '<span class="student-notify-time">' + formatDateTimeVN(item.created_at) + '</span>' +
                '</div>' +
                '<div class="student-notify-title">' + escapeHtml(item.title || '') + '</div>';
            dropdown.appendChild(row);
        });
    }

    const footer = document.createElement('div');
    footer.className = 'student-notify-footer';
    footer.innerHTML = '<a href="notifications-all.html" class="student-notify-view-all text-primary">Xem tất cả <i class="bi bi-arrow-right"></i></a>';
    dropdown.appendChild(footer);
    wrapper.appendChild(dropdown);

    bellLink.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        dropdown.classList.toggle('show');
    });

    dropdown.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    document.addEventListener('click', function () {
        dropdown.classList.remove('show');
    });
}

async function initSharedStudentData() {
    const data = await fetchSharedStudentData();
    if (!data) return;
    updateSidebarProfile(data.profile, data.classes);
    replaceStaticStudentPlaceholders(data.profile, data.classes);
    updateNotificationBadge(data.notifications);
    mountNotificationDropdown(data.notifications);
}
