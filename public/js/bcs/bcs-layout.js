// Giao diện dùng chung BCS: xử lý nút mở/đóng sidebar trên mobile cho tất cả trang BCS.
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (!sidebarToggle || !sidebar) {
        return;
    }

    sidebarToggle.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('active');
        }
    });
});
