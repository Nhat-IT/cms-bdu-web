// Mô tả: Khởi tạo hành vi layout riêng cho trang Admin.
document.addEventListener('DOMContentLoaded', function () {
    // Dùng chung engine menu hiện có, nhưng chỉ định rõ selector cho Admin.
    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            sidebarSelector: '#sidebar',
            mainContentSelector: '#mainContent',
            topNavbarSelector: '.top-navbar-admin',
            toggleSelector: '#sidebarToggle'
        });
    }

    // Đồng bộ xác nhận cho nút đăng xuất ở sidebar admin.
    const logoutLinks = document.querySelectorAll('#sidebar .logout-btn');
    logoutLinks.forEach(function (link) {
        if (link.dataset.adminLogoutBound === '1') {
            return;
        }

        link.dataset.adminLogoutBound = '1';
        link.addEventListener('click', function (event) {
            const ok = window.confirm('Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');
            if (!ok) {
                event.preventDefault();
            }
        });
    });
});
