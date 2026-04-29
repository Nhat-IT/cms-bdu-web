const ADMIN_REPLACEMENT_ATTRS = ['value', 'placeholder', 'title', 'onclick', 'data-source'];
const ADMIN_PLACEHOLDER_PATTERN = /(Admin\s+Khoa\s*CNTT|Gi[aá]o\s*v[uụ]\s*khoa\s*CNTT|\b25TH01\b|\bKHOA\s*CNTT\b|\bKhoa\s*CNTT\b)/i;

resetAdminLayoutPlaceholders();

document.addEventListener('DOMContentLoaded', function () {
    resetAdminLayoutPlaceholders();

    if (window.CMSMenu && typeof window.CMSMenu.init === 'function') {
        window.CMSMenu.init({
            sidebarSelector: '#sidebar',
            mainContentSelector: '#mainContent',
            topNavbarSelector: '.top-navbar-admin',
            toggleSelector: '#sidebarToggle'
        });
    }

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
    const headerAvatar = document.getElementById('headerAvatar');
    if (headerAvatar && !headerAvatar.getAttribute('src')) {
        headerAvatar.src = 'https://ui-avatars.com/api/?name=Admin&background=6c757d&color=fff';
    }
}

function applyAdminTextReplacements(raw, options) {
    const input = String(raw || '');
    if (!input || !ADMIN_PLACEHOLDER_PATTERN.test(input)) {
        return input;
    }

    let next = input
        .replace(/Admin\s+Khoa\s*CNTT/gi, options.displayName)
        .replace(/Gi[aá]o\s*v[uụ]\s*khoa\s*CNTT/gi, options.displayName)
        .replace(/\b25TH01\b/g, options.className);

    if (options.departmentName) {
        next = next.replace(/\bKHOA\s*CNTT\b/gi, options.departmentName);
        next = next.replace(/\bKhoa\s*CNTT\b/g, options.departmentName);
    }

    return next;
}

function hydrateTextNodes(options) {
    if (!document.body || typeof document.createTreeWalker !== 'function') {
        return;
    }

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
            const text = String(node.nodeValue || '');
            if (!text.trim()) {
                return NodeFilter.FILTER_REJECT;
            }
            return ADMIN_PLACEHOLDER_PATTERN.test(text) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
    });

    const textNodes = [];
    while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
    }

    textNodes.forEach(function (node) {
        const before = String(node.nodeValue || '');
        const after = applyAdminTextReplacements(before, options);
        if (after !== before) {
            node.nodeValue = after;
        }
    });
}

function hydrateAttributes(options) {
    const nodes = document.querySelectorAll('[value], [placeholder], [title], [onclick], [data-source]');
    nodes.forEach(function (node) {
        ADMIN_REPLACEMENT_ATTRS.forEach(function (attr) {
            if (!node.hasAttribute(attr)) {
                return;
            }

            const before = String(node.getAttribute(attr) || '');
            const after = applyAdminTextReplacements(before, options);
            if (after !== before) {
                node.setAttribute(attr, after);
            }
        });
    });
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
        const replacements = {
            displayName,
            className: me.class_name || '--',
            departmentName: me.department_name || ''
        };

        document.querySelectorAll('.admin-operator-name, .admin-display-name').forEach(function (node) {
            node.textContent = displayName;
        });

        const headerAvatar = document.getElementById('headerAvatar');
        if (headerAvatar) {
            headerAvatar.src = avatar;
        }

        hydrateTextNodes(replacements);
        hydrateAttributes(replacements);
    } catch (error) {
        console.error('Admin shared data hydration error:', error);
    }
}
