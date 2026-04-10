// Giao diện dùng chung Student: xử lý nút thu gọn/mở sidebar cho tất cả trang student.
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (!sidebarToggle || !sidebar || !mainContent) {
        return;
    }

    sidebarToggle.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    });
});
