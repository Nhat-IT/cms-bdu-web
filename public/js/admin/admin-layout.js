// Mô tả: Khởi tạo hành vi layout riêng cho trang Admin.
resetAdminLayoutPlaceholders();

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

    hydrateAdminSharedData();
});

function resetAdminLayoutPlaceholders() {
    document.querySelectorAll('.admin-operator-name, .admin-display-name').forEach(function (node) {
        node.textContent = 'Đang tải...';
    });

    const headerAvatar = document.getElementById('headerAvatar');
    if (headerAvatar) {
        headerAvatar.src = 'https://ui-avatars.com/api/?name=Admin&background=6c757d&color=fff';
    }
}

async function hydrateAdminSharedData() {
    try {
        const res = await fetch('/api/me', { headers: { Accept: 'application/json' } });
        if (res.status === 401) {
            window.location.href = '/login.html';
            return;
        }
        if (!res.ok) {
            return;
        }

        const me = await res.json();
        const displayName = me.full_name || me.username || 'Admin';
        const avatar = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0d6efd&color=fff`;
        const departmentName = me.department_name || '';

        document.querySelectorAll('.admin-operator-name, .admin-display-name').forEach(function (node) {
            node.textContent = displayName;
        });

        const headerAvatar = document.getElementById('headerAvatar');
        if (headerAvatar) {
            headerAvatar.src = avatar;
        }

        const leafNodes = document.querySelectorAll('span, b, strong, p, h5, td, option');
        leafNodes.forEach(function (node) {
            if (node.children.length > 0) {
                return;
            }
            const text = String(node.textContent || '');
            let next = text
                .replace(/Admin\s+Khoa\s*CNTT/gi, displayName)
                .replace(/Giáo\s*vụ\s*khoa\s*CNTT/gi, displayName)
                .replace(/\b25TH01\b/g, me.class_name || '--');

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
                let next = raw
                    .replace(/Admin\s+Khoa\s*CNTT/gi, displayName)
                    .replace(/Giáo\s*vụ\s*khoa\s*CNTT/gi, displayName)
                    .replace(/\b25TH01\b/g, me.class_name || '--');

                if (departmentName) {
                    next = next.replace(/\bKHOA\s*CNTT\b/gi, departmentName);
                    next = next.replace(/\bKhoa\s*CNTT\b/g, departmentName);
                }

                if (next !== raw) {
                    node.setAttribute(attr, next);
                }
            });
        });
    } catch (error) {
        console.error('Admin shared data hydration error:', error);
    }
}
