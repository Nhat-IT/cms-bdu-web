// Giao diện dùng chung Student: xử lý nút thu gọn/mở sidebar cho tất cả trang student.
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const bellLink = document.querySelector('.top-navbar-blue a[href="notifications-all.html"].text-decoration-none');

    if (!sidebarToggle || !sidebar || !mainContent) {
        return;
    }

    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            toggleElement: sidebarToggle,
            sidebarElement: sidebar,
            mainContentElement: mainContent,
            topNavbarSelector: '.top-navbar-blue'
        });
    }

    if (!bellLink) {
        return;
    }

    const notificationItems = [
        { source: 'BCS', time: '11/04/2026 08:20', title: 'Nhắc nộp minh chứng chuyên cần tuần 8' },
        { source: 'Giảng viên', time: '10/04/2026 16:40', title: 'Thông báo đổi phòng học môn Lập trình Web' },
        { source: 'Khoa CNTT', time: '10/04/2026 10:15', title: 'Lịch seminar chuyên đề tháng 4' },
        { source: 'BCS', time: '09/04/2026 09:00', title: 'Cập nhật danh sách bài tập tuần này' }
    ];

    bellLink.classList.add('student-notify-trigger');

    const wrapper = document.createElement('div');
    wrapper.className = 'student-notify-wrapper';
    bellLink.parentNode.insertBefore(wrapper, bellLink);
    wrapper.appendChild(bellLink);

    const dropdown = document.createElement('div');
    dropdown.className = 'student-notify-dropdown';

    const header = document.createElement('div');
    header.className = 'student-notify-header';
    header.innerHTML = '<div class="fw-bold text-dark"><i class="bi bi-bell-fill text-primary me-1"></i> Thông báo mới</div>';
    dropdown.appendChild(header);

    notificationItems.forEach(function (item) {
        const row = document.createElement('a');
        row.href = 'notifications-all.html';
        row.className = 'student-notify-item';
        row.innerHTML =
            '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<span class="student-notify-source text-primary">' + item.source + '</span>' +
                '<span class="student-notify-time">' + item.time + '</span>' +
            '</div>' +
            '<div class="student-notify-title">' + item.title + '</div>';
        dropdown.appendChild(row);
    });

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
});
