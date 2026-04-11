(function () {
    const MOBILE_BREAKPOINT = 768;

    function getElements(options) {
        const sidebar = options.sidebarElement || document.querySelector(options.sidebarSelector || '#sidebar');
        const mainContent = options.mainContentElement || document.querySelector(options.mainContentSelector || '#mainContent, .main-content');
        const topNavbar = options.topNavbarElement || document.querySelector(options.topNavbarSelector || '.top-navbar-admin, .top-navbar-blue, .top-navbar-teacher');
        const toggle = options.toggleElement || document.querySelector(options.toggleSelector || '#sidebarToggle, #sidebarToggleTeacher');

        return { sidebar, mainContent, topNavbar, toggle };
    }

    function applyDesktopPosition(mainContent, topNavbar) {
        if (!mainContent || !topNavbar || window.innerWidth <= MOBILE_BREAKPOINT) {
            return;
        }

        const left = getComputedStyle(mainContent).marginLeft;
        topNavbar.style.left = left;
    }

    function initMenu(options = {}) {
        const { sidebar, mainContent, topNavbar, toggle } = getElements(options);

        if (!sidebar || !toggle) {
            return false;
        }

        if (toggle.dataset.cmsMenuBound === '1') {
            return true;
        }

        toggle.dataset.cmsMenuBound = '1';
        toggle.classList.remove('d-md-none');

        toggle.addEventListener('click', function () {
            if (window.innerWidth <= MOBILE_BREAKPOINT) {
                sidebar.classList.toggle('active');
                return;
            }

            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
            applyDesktopPosition(mainContent, topNavbar);
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth > MOBILE_BREAKPOINT) {
                return;
            }

            const isToggle = event.target.closest('#sidebarToggle, #sidebarToggleTeacher');
            const isInsideSidebar = event.target.closest('#sidebar');

            if (!isToggle && !isInsideSidebar) {
                sidebar.classList.remove('active');
            }
        });

        window.addEventListener('resize', function () {
            applyDesktopPosition(mainContent, topNavbar);
        });

        applyDesktopPosition(mainContent, topNavbar);
        return true;
    }

    function initLoginPasswordToggle() {
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');

        if (!togglePasswordBtn || !passwordInput || togglePasswordBtn.dataset.cmsPwdBound === '1') {
            return;
        }

        togglePasswordBtn.dataset.cmsPwdBound = '1';

        togglePasswordBtn.addEventListener('click', function () {
            const nextType = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', nextType);

            const icon = this.querySelector('i');
            if (!icon) {
                return;
            }

            icon.className = nextType === 'text' ? 'bi bi-eye-fill text-primary' : 'bi bi-eye-slash-fill text-muted';
        });
    }

    window.CMSMenu = {
        init: initMenu
    };

    document.addEventListener('DOMContentLoaded', function () {
        initLoginPasswordToggle();
        initMenu();
    });
})();
