// Giao diện dùng chung BCS: xử lý nút mở/đóng sidebar trên mobile cho tất cả trang BCS.
document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');

    if (!sidebarToggle || !sidebar) {
        return;
    }

    sidebarToggle.classList.remove('d-md-none');
    sidebarToggle.classList.add('border-0');

    const toggleIcon = sidebarToggle.querySelector('i');
    if (toggleIcon) {
        toggleIcon.classList.remove('fs-4');
        toggleIcon.classList.add('fs-3');
    }

    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            toggleElement: sidebarToggle,
            sidebarElement: sidebar,
            mainContentElement: mainContent,
            topNavbarSelector: '.top-navbar-blue'
        });
    }
});
