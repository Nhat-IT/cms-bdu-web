(function () {
    const MOBILE_BREAKPOINT = 768;

    function ensureSidebarBackdrop() {
        let backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            return backdrop;
        }

        backdrop = document.createElement('div');
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function setMobileSidebarState(sidebar, isOpen) {
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('active', isOpen);
        document.body.classList.toggle('cms-sidebar-open', isOpen);
    }

    function wrapStandaloneTables() {
        const tables = document.querySelectorAll('table');
        tables.forEach(function (table) {
            if (table.closest('.table-responsive') || table.closest('.schedule-wrapper')) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive cms-auto-table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }

    function getElements(options) {
        const sidebar = options.sidebarElement || document.querySelector(options.sidebarSelector || '#sidebar');
        const mainContent = options.mainContentElement || document.querySelector(options.mainContentSelector || '#mainContent, .main-content');
        const topNavbar = options.topNavbarElement || document.querySelector(options.topNavbarSelector || '.top-navbar-admin, .top-navbar-blue');
        const toggle = options.toggleElement || document.querySelector(options.toggleSelector || '#sidebarToggle, #sidebarToggleTeacher');

        return { sidebar, mainContent, topNavbar, toggle };
    }

    function applyDesktopPosition(sidebar, mainContent, topNavbar) {
        if (!topNavbar) {
            return;
        }

        if (window.innerWidth <= MOBILE_BREAKPOINT) {
            topNavbar.style.left = '0px';
            return;
        }

        const rootStyles = getComputedStyle(document.documentElement);
        const expandedLeft = rootStyles.getPropertyValue('--cms-sidebar-width').trim() || '260px';
        const collapsedLeft = rootStyles.getPropertyValue('--cms-sidebar-collapsed-width').trim() || '86px';

        if (sidebar) {
            topNavbar.style.left = sidebar.classList.contains('collapsed') ? collapsedLeft : expandedLeft;
            return;
        }

        if (mainContent) {
            topNavbar.style.left = getComputedStyle(mainContent).marginLeft;
            return;
        }

        topNavbar.style.left = expandedLeft;
    }

    function initMenu(options = {}) {
        const { sidebar, mainContent, topNavbar, toggle } = getElements(options);

        if (!sidebar || !toggle) {
            return false;
        }

        const backdrop = ensureSidebarBackdrop();

        if (toggle.dataset.cmsMenuBound === '1') {
            return true;
        }

        toggle.dataset.cmsMenuBound = '1';
        toggle.classList.remove('d-md-none');

        toggle.addEventListener('click', function () {
            if (window.innerWidth <= MOBILE_BREAKPOINT) {
                const willOpen = !sidebar.classList.contains('active');
                setMobileSidebarState(sidebar, willOpen);
                return;
            }

            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
            applyDesktopPosition(sidebar, mainContent, topNavbar);
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth > MOBILE_BREAKPOINT) {
                return;
            }

            const isToggle = event.target.closest('#sidebarToggle, #sidebarToggleTeacher');
            const isInsideSidebar = event.target.closest('#sidebar');

            if (!isToggle && !isInsideSidebar) {
                setMobileSidebarState(sidebar, false);
            }
        });

        backdrop.addEventListener('click', function () {
            setMobileSidebarState(sidebar, false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMobileSidebarState(sidebar, false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > MOBILE_BREAKPOINT) {
                setMobileSidebarState(sidebar, false);
            }
            applyDesktopPosition(sidebar, mainContent, topNavbar);
        });

        applyDesktopPosition(sidebar, mainContent, topNavbar);
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
        wrapStandaloneTables();
        initLoginPasswordToggle();
        initMenu();
    });
})();
