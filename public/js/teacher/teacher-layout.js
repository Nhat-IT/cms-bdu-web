// Shared Teacher layout: collapse/expand sidebar like student pages.
resetTeacherLayoutPlaceholders();

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const topNavbar = document.querySelector('.top-navbar-blue');

    if (!sidebar || !mainContent || !topNavbar) {
        return;
    }

    const topInfoBlock = topNavbar.querySelector('.text-end.me-3');
    const topAvatar = topNavbar.querySelector('#headerAvatar');
    const profileNameNode = topInfoBlock ? topInfoBlock.querySelector('b') : null;
    const profileName = profileNameNode ? profileNameNode.textContent.trim() : 'Giang vien';
    const pageTitle = topNavbar.querySelector('h4');

    const sidebarTopWrapper = sidebar.querySelector(':scope > div');
    const sidebarBrand = sidebarTopWrapper
        ? sidebarTopWrapper.querySelector('.brand-container, .brand')
        : null;

    if (sidebarTopWrapper && sidebarBrand && !sidebar.querySelector('.menu-profile-container')) {
        const profileContainer = document.createElement('div');
        profileContainer.className = 'menu-profile-container';

        const profileLink = document.createElement('a');
        profileLink.href = 'teacher-profile.html';
        profileLink.className = 'menu-profile-trigger';
        profileLink.title = 'Xem ho so giang vien';

        const avatar = document.createElement('img');
        avatar.src = topAvatar ? topAvatar.src : 'https://ui-avatars.com/api/?name=GV&background=0dcaf0&color=fff';
        avatar.alt = 'Teacher Avatar';
        avatar.className = 'rounded-circle border border-2 border-info';
        avatar.width = 42;
        avatar.height = 42;

        const meta = document.createElement('div');
        meta.className = 'menu-profile-meta';

        const name = document.createElement('div');
        name.className = 'menu-profile-name';
        name.textContent = profileName;

        const role = document.createElement('div');
        role.className = 'menu-profile-role';
        role.textContent = 'Giang vien';

        meta.appendChild(name);
        meta.appendChild(role);
        profileLink.appendChild(avatar);
        profileLink.appendChild(meta);
        profileContainer.appendChild(profileLink);

        const badge = document.createElement('span');
        badge.className = 'badge menu-profile-badge';
        badge.textContent = '';
        badge.classList.add('d-none');
        profileContainer.appendChild(badge);

        sidebarBrand.insertAdjacentElement('afterend', profileContainer);
    }

    if (sidebarTopWrapper && !sidebarTopWrapper.querySelector('.menu-scroll')) {
        const menuHeading = sidebarTopWrapper.querySelector('.text-start.small.fw-bold');
        const menuNav = sidebarTopWrapper.querySelector('nav.nav');

        if (menuHeading && menuNav) {
            const menuScroll = document.createElement('div');
            menuScroll.className = 'menu-scroll';
            menuHeading.parentNode.insertBefore(menuScroll, menuHeading);
            menuScroll.appendChild(menuHeading);
            menuScroll.appendChild(menuNav);
        }
    }

    const legacyBlocks = topNavbar.querySelectorAll(':scope > div.d-flex.align-items-center.text-white');
    legacyBlocks.forEach(function (block) {
        if (block.querySelector('#headerAvatar') || block.querySelector('.dropdown')) {
            block.classList.add('d-none');
        }
    });

    if (pageTitle) {
        pageTitle.classList.remove('m-0');
        pageTitle.classList.add('text-white', 'fw-bold', 'd-flex', 'align-items-center');
    }

    let toggleButton = document.getElementById('sidebarToggleTeacher');
    let leftGroup = topNavbar.querySelector('.menu-header-left');

    if (!leftGroup) {
        if (!pageTitle) {
            return;
        }

        leftGroup = document.createElement('div');
        leftGroup.className = 'd-flex align-items-center menu-header-left';

        topNavbar.prepend(leftGroup);
        leftGroup.appendChild(pageTitle);
    }

    if (!toggleButton) {
        toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.id = 'sidebarToggleTeacher';
        toggleButton.className = 'menu-toggle btn btn-outline-light me-3 border-0';
        toggleButton.setAttribute('aria-label', 'Toggle sidebar menu');
        toggleButton.innerHTML = '<i class="bi bi-list fs-3"></i>';

        leftGroup.prepend(toggleButton);
    }

    if (!topNavbar.querySelector('.menu-header-right')) {
        const rightGroup = document.createElement('div');
        rightGroup.className = 'd-flex align-items-center text-white menu-header-right';

        const bell = document.createElement('a');
        bell.href = 'approve-evidences.html';
        bell.className = 'menu-header-notify';
        bell.title = 'Xem thong bao va minh chung cho duyet';
        bell.innerHTML = '<i class="bi bi-bell fs-5 text-white position-relative">' +
            '<span class="menu-header-notify-badge d-none" id="menuHeaderNotifyBadge">0</span>' +
            '</i>';

        rightGroup.appendChild(bell);
        topNavbar.appendChild(rightGroup);
    }

    function parseUnreadCount(value) {
        const count = Number.parseInt(String(value || '0').trim(), 10);
        return Number.isFinite(count) && count > 0 ? count : 0;
    }

    function getSidebarUnreadCount() {
        const sidebarBadge = sidebar.querySelector('a[href="approve-evidences.html"] .badge');
        if (!sidebarBadge) {
            return 0;
        }

        return parseUnreadCount(sidebarBadge.textContent);
    }

    function renderUnreadCount(unreadCount) {
        const badge = topNavbar.querySelector('#menuHeaderNotifyBadge');
        const bell = topNavbar.querySelector('.menu-header-notify');

        if (!badge || !bell) {
            return;
        }

        const count = unreadCount > 0 ? unreadCount : 0;

        if (count === 0) {
            badge.classList.add('d-none');
            bell.removeAttribute('data-unread');
            return;
        }

        badge.classList.remove('d-none');
        badge.textContent = count > 10 ? '10+' : String(count);
        bell.setAttribute('data-unread', String(count));
    }

    async function updateUnreadCount() {
        // Fallback to sidebar count if API is not available yet.
        let unreadCount = getSidebarUnreadCount();

        try {
            const response = await fetch('/api/notifications/unread-count', {
                headers: {
                    Accept: 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                unreadCount = parseUnreadCount(data && data.unreadCount);
            }
        } catch (error) {
            // Keep fallback count when request fails.
        }

        renderUnreadCount(unreadCount);
    }

    async function hydrateTeacherProfile() {
        try {
            const response = await fetch('/api/me', {
                headers: { Accept: 'application/json' }
            });

            if (!response.ok) {
                return;
            }

            const me = await response.json();
            const safeName = me.full_name || profileName;
            const avatarUrl = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(safeName)}&background=0dcaf0&color=fff`;

            if (profileNameNode) {
                profileNameNode.textContent = safeName;
            }

            if (topAvatar) {
                topAvatar.src = avatarUrl;
            }

            const menuProfileName = sidebar.querySelector('.menu-profile-name');
            if (menuProfileName) {
                menuProfileName.textContent = safeName;
            }

            const menuProfileRole = sidebar.querySelector('.menu-profile-role');
            if (menuProfileRole) {
                menuProfileRole.textContent = me.role === 'teacher' ? 'Giang vien' : (me.role || 'Nguoi dung');
            }

            const menuProfileBadge = sidebar.querySelector('.menu-profile-badge');
            if (menuProfileBadge) {
                const badgeText = me.department_name || me.class_name || '';
                menuProfileBadge.textContent = badgeText;
                if (badgeText) {
                    menuProfileBadge.classList.remove('d-none');
                } else {
                    menuProfileBadge.classList.add('d-none');
                }
            }

            const menuAvatar = sidebar.querySelector('.menu-profile-trigger img');
            if (menuAvatar) {
                menuAvatar.src = avatarUrl;
            }

            const leafNodes = document.querySelectorAll('span, b, strong, p, h4, h5, td, option');
            leafNodes.forEach(function (node) {
                if (node.children.length > 0) {
                    return;
                }

                const text = String(node.textContent || '');
                let next = text;

                if (me.class_name) {
                    next = next.replace(/\b25TH01\b/g, me.class_name);
                }
                if (me.department_name) {
                    next = next.replace(/\bKHOA\s*CNTT\b/gi, me.department_name);
                    next = next.replace(/\bKhoa\s*CNTT\b/g, me.department_name);
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
                    let next = raw;

                    if (me.class_name) {
                        next = next.replace(/\b25TH01\b/g, me.class_name);
                    }
                    if (me.department_name) {
                        next = next.replace(/\bKHOA\s*CNTT\b/gi, me.department_name);
                        next = next.replace(/\bKhoa\s*CNTT\b/g, me.department_name);
                    }

                    if (next !== raw) {
                        node.setAttribute(attr, next);
                    }
                });
            });
        } catch (error) {
            console.error('Teacher shared data hydration error:', error);
        }
    }

    hydrateTeacherProfile();
    updateUnreadCount();

    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            toggleElement: toggleButton,
            sidebarElement: sidebar,
            mainContentElement: mainContent,
            topNavbarElement: topNavbar
        });
    }
});

function resetTeacherLayoutPlaceholders() {
    const profileNameNode = document.querySelector('.top-navbar-blue .text-end.me-3 b');
    if (profileNameNode) {
        profileNameNode.textContent = 'Đang tải...';
    }

    const topAvatar = document.getElementById('headerAvatar');
    if (topAvatar) {
        topAvatar.src = 'https://ui-avatars.com/api/?name=Teacher&background=6c757d&color=fff';
    }

    const sidebarBadge = document.querySelector('a[href="approve-evidences.html"] .badge');
    if (sidebarBadge) {
        sidebarBadge.textContent = '0';
        sidebarBadge.classList.add('d-none');
    }

    // Reset demo placeholders shown in teacher pages/modals before API data is fetched.
    const textFallbacks = [
        ['#modalStudentName', 'Đang tải...'],
        ['#modalStudentId', 'MSSV: --'],
        ['#modalClass', '--'],
        ['#modalDate', '--/--/----'],
        ['#gradeStudentCountBadge', '0 students'],
        ['#lblTime', '--']
    ];

    textFallbacks.forEach(function (entry) {
        const node = document.querySelector(entry[0]);
        if (node) {
            node.textContent = entry[1];
        }
    });

    const inputFallbacks = [
        ['#teacherFullNameInput', ''],
        ['#teacherEmailInput', ''],
        ['#teacherPhoneInput', '']
    ];

    inputFallbacks.forEach(function (entry) {
        const input = document.querySelector(entry[0]);
        if (input) {
            input.value = entry[1];
        }
    });
}
