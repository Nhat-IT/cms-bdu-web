<<<<<<< HEAD
// Mô tả: Xử lý bộ lọc và modal chi tiết cho trang thông báo của sinh viên.
(function () {
    const searchInput = document.getElementById('notifySearchInput');
    const sourceFilter = document.getElementById('notifySourceFilter');
    const readFilter = document.getElementById('notifyReadFilter');
    const clearBtn = document.getElementById('clearNotifyFilterBtn');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const notifyItems = Array.from(document.querySelectorAll('#notifyList .notify-item'));
    const detailModal = new bootstrap.Modal(document.getElementById('notifyDetailModal'));
    const detailTitle = document.getElementById('notifyDetailTitle');
    const detailSource = document.getElementById('notifyDetailSource');
    const detailTime = document.getElementById('notifyDetailTime');
    const detailContent = document.getElementById('notifyDetailContent');
    const unreadCounter = document.querySelector('.top-navbar-blue .badge.rounded-pill.bg-danger');

    function updateUnreadCounter() {
        if (!unreadCounter) {
            return;
        }

        const unreadCount = notifyItems.filter(function (item) {
            return item.dataset.read !== 'true';
        }).length;

        unreadCounter.textContent = String(unreadCount);
    }

    function applyFilters() {
        const searchValue = (searchInput.value || '').trim().toLowerCase();
        const sourceValue = sourceFilter.value;
        const readValue = readFilter.value;

        notifyItems.forEach(function (item) {
            const text = item.textContent.toLowerCase();
            const source = item.dataset.source || '';
            const read = item.dataset.read === 'true' ? 'read' : 'unread';

            const matchSearch = !searchValue || text.includes(searchValue);
            const matchSource = sourceValue === 'all' || source === sourceValue;
            const matchRead = readValue === 'all' || read === readValue;

            item.classList.toggle('d-none', !(matchSearch && matchSource && matchRead));
        });
    }

    function markAllAsRead() {
        notifyItems.forEach(function (item) {
            item.dataset.read = 'true';
        });

        readFilter.value = 'read';
        updateUnreadCounter();
        applyFilters();
    }

    function openNotifyDetail(item) {
        if (item.dataset.read !== 'true') {
            item.dataset.read = 'true';
            updateUnreadCounter();
            applyFilters();
        }

        const source = item.dataset.source || 'Thông báo';
        const title = (item.querySelector('h6') || {}).textContent || 'Chi tiết thông báo';
        const content = (item.querySelector('p') || {}).textContent || '';
        const time = (item.querySelector('small') || {}).textContent || '';

        detailTitle.textContent = title.trim();
        detailContent.textContent = content.trim();
        detailTime.textContent = time.trim();

        detailSource.textContent = source;
        detailSource.className = 'badge source-badge';
        if (source === 'BAN CÁN SỰ') {
            detailSource.classList.add('source-badge-bcs');
        } else if (source === 'GIẢNG VIÊN') {
            detailSource.classList.add('source-badge-lecturer');
        } else if (source === 'KHOA CNTT') {
            detailSource.classList.add('bg-warning', 'text-dark');
        }

        detailModal.show();
    }

    searchInput.addEventListener('input', applyFilters);
    sourceFilter.addEventListener('change', applyFilters);
    readFilter.addEventListener('change', applyFilters);

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        sourceFilter.value = 'all';
        readFilter.value = 'all';
        applyFilters();
    });

    markAllReadBtn.addEventListener('click', markAllAsRead);
    notifyItems.forEach(function (item) {
        item.addEventListener('click', function () {
            openNotifyDetail(item);
        });
    });

    updateUnreadCounter();
    applyFilters();
})();
=======
let notifications = [];

const searchInput = document.getElementById('notifySearchInput');
const sourceFilter = document.getElementById('notifySourceFilter');
const readFilter = document.getElementById('notifyReadFilter');
const clearBtn = document.getElementById('clearNotifyFilterBtn');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const notifyList = document.getElementById('notifyList');
const detailModal = new bootstrap.Modal(document.getElementById('notifyDetailModal'));
const detailTitle = document.getElementById('notifyDetailTitle');
const detailSource = document.getElementById('notifyDetailSource');
const detailTime = document.getElementById('notifyDetailTime');
const detailContent = document.getElementById('notifyDetailContent');
const unreadCounter = document.querySelector('.top-navbar-blue .badge.rounded-pill.bg-danger');

function formatDateTime(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '--/--/---- --:--';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
}

function inferSource(title = '', message = '') {
    const text = `${title} ${message}`.toLowerCase();
    if (text.includes('bcs') || text.includes('ban cán sự')) return 'BAN CÁN SỰ';
    if (text.includes('giảng viên') || text.includes('gv')) return 'GIẢNG VIÊN';
    if (text.includes('khoa') || text.includes('cntt')) return 'KHOA CNTT';
    return 'BAN CÁN SỰ';
}

function sourceBadgeClass(source) {
    if (source === 'BAN CÁN SỰ') return 'source-badge source-badge-bcs';
    if (source === 'GIẢNG VIÊN') return 'source-badge source-badge-lecturer';
    return 'source-badge bg-warning text-dark';
}

function esc(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function fetchNotifications() {
    const response = await fetch('/api/notifications');
    if (response.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (!response.ok) {
        throw new Error('Không thể tải thông báo');
    }

    const data = await response.json();
    notifications = data.map((n) => ({
        ...n,
        source: inferSource(n.title, n.message),
        read: Number(n.is_read) === 1
    }));
}

function updateUnreadCounter() {
    if (!unreadCounter) return;
    unreadCounter.textContent = String(notifications.filter((n) => !n.read).length);
}

function getFilteredNotifications() {
    const searchValue = (searchInput.value || '').trim().toLowerCase();
    const sourceValue = sourceFilter.value;
    const readValue = readFilter.value;

    return notifications.filter((item) => {
        const text = `${item.title || ''} ${item.message || ''}`.toLowerCase();
        const read = item.read ? 'read' : 'unread';

        const matchSearch = !searchValue || text.includes(searchValue);
        const matchSource = sourceValue === 'all' || item.source === sourceValue;
        const matchRead = readValue === 'all' || read === readValue;
        return matchSearch && matchSource && matchRead;
    });
}

function renderNotifications() {
    const list = getFilteredNotifications();
    if (!list.length) {
        notifyList.innerHTML = '<div class="list-group-item py-4 text-center text-muted">Không có thông báo phù hợp.</div>';
        return;
    }

    notifyList.innerHTML = list.map((item) => `
        <div class="list-group-item notify-item py-3 ${item.read ? '' : 'bg-light'}" data-id="${item.id}" title="Nhấp để xem chi tiết">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <div class="mb-1"><span class="badge ${sourceBadgeClass(item.source)}">${item.source}</span></div>
                    <h6 class="fw-bold mb-1">${esc(item.title || 'Thông báo')}</h6>
                    <p class="text-muted mb-0">${esc(item.message || '')}</p>
                </div>
                <small class="text-muted">${formatDateTime(item.created_at)}</small>
            </div>
        </div>
    `).join('');

    notifyList.querySelectorAll('.notify-item').forEach((row) => {
        row.addEventListener('click', () => {
            const item = notifications.find((n) => Number(n.id) === Number(row.dataset.id));
            if (!item) return;
            item.read = true;
            detailTitle.textContent = item.title || 'Chi tiết thông báo';
            detailSource.textContent = item.source;
            detailSource.className = `badge ${sourceBadgeClass(item.source)}`;
            detailTime.textContent = formatDateTime(item.created_at);
            detailContent.textContent = item.message || '';
            updateUnreadCounter();
            renderNotifications();
            detailModal.show();
        });
    });
}

async function markAllAsRead() {
    try {
        const response = await fetch('/api/notifications/mark-read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error('Không thể đánh dấu đã đọc');
        notifications = notifications.map((n) => ({ ...n, read: true, is_read: 1 }));
        readFilter.value = 'read';
        updateUnreadCounter();
        renderNotifications();
    } catch (error) {
        console.error(error);
        alert('Không thể đánh dấu tất cả thông báo là đã đọc.');
    }
}

function resetFilters() {
    searchInput.value = '';
    sourceFilter.value = 'all';
    readFilter.value = 'all';
    renderNotifications();
}

async function initNotificationsPage() {
    try {
        await fetchNotifications();
        updateUnreadCounter();
        renderNotifications();
        searchInput.addEventListener('input', renderNotifications);
        sourceFilter.addEventListener('change', renderNotifications);
        readFilter.addEventListener('change', renderNotifications);
        clearBtn.addEventListener('click', resetFilters);
        markAllReadBtn.addEventListener('click', markAllAsRead);
    } catch (error) {
        console.error(error);
        alert('Không thể tải danh sách thông báo từ database.');
    }
}

document.addEventListener('DOMContentLoaded', initNotificationsPage);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
