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

    if (mainContent) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }

    hydrateBcsSharedData();

});

async function hydrateBcsSharedData() {
    try {
        const [meRes, unreadRes] = await Promise.all([
            fetch('/api/me', { headers: { Accept: 'application/json' } }),
            fetch('/api/notifications/unread-count', { headers: { Accept: 'application/json' } })
        ]);

        if (meRes.status === 401 || unreadRes.status === 401) {
            window.location.href = '/login.html';
            return;
        }

        if (meRes.ok) {
            const me = await meRes.json();
            const displayName = me.full_name || me.username || 'BCS';
            const avatar = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0d6efd&color=fff`;

            const profileImg = document.querySelector('.bcs-profile-container img[alt="Avatar BCS"]');
            if (profileImg) {
                profileImg.src = avatar;
            }

            const nameNode = document.querySelector('.bcs-profile-container .text-white.fw-bold.fs-6');
            if (nameNode) {
                nameNode.textContent = displayName;
            }

            const roleNode = document.querySelector('.bcs-profile-container .text-white-50.small.mb-1');
            if (roleNode) {
                roleNode.textContent = `Vai trò: ${me.role || 'bcs'}`;
            }
        }

        if (unreadRes.ok) {
            const data = await unreadRes.json();
            const count = Number(data.unreadCount || 0);
            document.querySelectorAll('.bcs-notification-count').forEach(function (node) {
                node.textContent = String(count);
            });
        }
    } catch (error) {
        // Keep static fallback.
    }
}
