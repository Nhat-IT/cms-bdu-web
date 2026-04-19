// Shared Teacher layout: collapse/expand sidebar like student pages.
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
        badge.textContent = 'KHOA CNTT';
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
