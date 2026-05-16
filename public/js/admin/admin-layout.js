// ─── Global session-expiry handler ───────────────────────────────────────────
// Intercepts every fetch() in the admin panel.
// - HTTP 401  → session hết hạn
// - JSON body với message 'login_required' / 'session_expired' / 'unauthorized'
//   → session hết hạn
// Khi phát hiện, hiển thị toast rồi redirect về trang đăng nhập sau 2 giây.
(function () {
    var LOGIN_URL = '/cms/login.php';
    var _sessionExpiredPending = false;

    function showSessionToast() {
        // Nếu đã có toast đang hiện thì không tạo thêm
        if (_sessionExpiredPending) return;
        _sessionExpiredPending = true;

        var toast = document.createElement('div');
        toast.setAttribute('role', 'alert');
        toast.style.cssText = [
            'position:fixed', 'top:20px', 'right:20px', 'z-index:99999',
            'background:#dc3545', 'color:#fff', 'padding:14px 20px',
            'border-radius:8px', 'box-shadow:0 4px 16px rgba(0,0,0,.25)',
            'font-size:14px', 'max-width:320px', 'line-height:1.5'
        ].join(';');
        toast.innerHTML =
            '<strong><i class="bi bi-shield-lock-fill me-2"></i>Phiên đăng nhập đã hết hạn</strong>' +
            '<div style="margin-top:4px;opacity:.9">Đang chuyển về trang đăng nhập…</div>';
        document.body.appendChild(toast);

        setTimeout(function () {
            window.location.href = LOGIN_URL;
        }, 2000);
    }

    // Expose so assignments.js (và các file CMS khác) có thể gọi cùng hàm
    window._triggerSessionExpired = showSessionToast;

    var SESSION_EXPIRED_MESSAGES = new Set([
        'login_required', 'session_expired', 'unauthorized', 'not_logged_in'
    ]);

    var _originalFetch = window.fetch;
    window.fetch = function (input, init) {
        return _originalFetch.call(this, input, init).then(function (response) {
            // 401 → hết phiên
            if (response.status === 401) {
                showSessionToast();
                // Trả về response gốc để caller không bị unhandled rejection
                return response;
            }

            // Clone để đọc body mà không tiêu thụ stream của caller
            var contentType = response.headers.get('content-type') || '';
            if (contentType.indexOf('application/json') !== -1) {
                var cloned = response.clone();
                cloned.json().then(function (data) {
                    if (data && data.ok === false &&
                        SESSION_EXPIRED_MESSAGES.has(String(data.message || ''))) {
                        showSessionToast();
                    }
                }).catch(function () { /* không phải JSON hợp lệ — bỏ qua */ });
            }

            return response;
        });
    };
})();

// ─── End global session-expiry handler ───────────────────────────────────────


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

function isInsideAdminTable(node) {
    let parent = node;
    for (let i = 0; i < 5; i++) {
        if (parent && parent.tagName === 'TABLE' && parent.classList && parent.classList.contains('table')) {
            return true;
        }
        parent = parent.parentElement;
        if (!parent) break;
    }
    return false;
}

function hydrateTextNodes(options) {
    if (!document.body || typeof document.createTreeWalker !== 'function') {
        return;
    }

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
            // Skip text nodes inside admin data tables
            if (isInsideAdminTable(node)) {
                return NodeFilter.FILTER_REJECT;
            }
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
        // Skip nodes inside admin data tables
        if (isInsideAdminTable(node)) {
            return;
        }
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
            return; // interceptor đã redirect
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
