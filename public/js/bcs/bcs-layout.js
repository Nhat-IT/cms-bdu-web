// Giao diện dùng chung BCS: xử lý dữ liệu sidebar và menu.
resetBcsLayoutPlaceholders();

document.addEventListener('DOMContentLoaded', function () {
    // Use shared menu initializer to avoid double-binding click handlers.
    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            sidebarSelector: '#sidebar',
            mainContentSelector: '#mainContent, .main-content',
            toggleSelector: '#sidebarToggle',
            topNavbarSelector: '.top-navbar-blue'
        });
    }

    hydrateBcsSharedData();
});

function resetBcsLayoutPlaceholders() {
    const profileImg = document.querySelector('.bcs-profile-container img[alt="Avatar BCS"]');
    if (profileImg) {
        profileImg.src = 'https://ui-avatars.com/api/?name=BCS&background=6c757d&color=fff';
    }

    const nameNode = document.querySelector('.bcs-profile-container .text-white.fw-bold.fs-6');
    if (nameNode) {
        nameNode.textContent = 'Đang tải...';
    }

    const roleNode = document.querySelector('.bcs-profile-container .text-white-50.small.mb-1');
    if (roleNode) {
        roleNode.textContent = 'Chức vụ: --';
    }

    document.querySelectorAll('.bcs-class-badge, #userClassName').forEach(function (node) {
        if (node.classList.contains('bcs-class-badge')) {
            node.textContent = 'LỚP: --';
            return;
        }
        node.textContent = '--';
    });

    document.querySelectorAll('.bcs-notification-count').forEach(function (node) {
        node.textContent = '0';
    });

    const textFallbacks = [
        ['#fbSenderName', 'Đang tải...'],
        ['#fbTime', 'Đang tải...'],
        ['#fbStatusBadge', 'Đang tải'],
        ['#fbSubject', 'Đang tải chủ đề...'],
        ['#lblTeacher', '--'],
        ['#lblTime', '--']
    ];

    textFallbacks.forEach(function (entry) {
        const node = document.querySelector(entry[0]);
        if (node) {
            node.textContent = entry[1];
        }
    });
}

async function hydrateBcsSharedData() {
    const toUrl = window.cmsUrl || function (path) { return path; };
    try {
        const [meRes, unreadRes] = await Promise.all([
            fetch(toUrl('/api/me'), { headers: { Accept: 'application/json' } }),
            fetch(toUrl('/api/notifications/unread-count'), { headers: { Accept: 'application/json' } })
        ]);

        if (meRes.status === 401 || unreadRes.status === 401) {
            window.location.href = toUrl('/login.php');
            return;
        }

        if (meRes.ok) {
            const me = await meRes.json();
            const displayName = me.full_name || me.username || 'BCS';
            const avatar = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0d6efd&color=fff`;
            const className = me.class_name || '--';
            const departmentName = me.department_name || '';
            const position = me.position || '--';

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
                roleNode.textContent = `Chức vụ: ${position}`;
            }

            document.querySelectorAll('.bcs-class-badge, #userClassName').forEach(function (node) {
                if (node.classList.contains('bcs-class-badge')) {
                    node.textContent = `LỚP: ${className}`;
                    return;
                }
                node.textContent = className;
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

        if (unreadRes.ok) {
            const data = await unreadRes.json();
            const count = Number(data.unreadCount || 0);
            document.querySelectorAll('.bcs-notification-count').forEach(function (node) {
                node.textContent = String(count);
            });
        }
    } catch (error) {
        console.error('BCS shared data hydration error:', error);
    }
}
