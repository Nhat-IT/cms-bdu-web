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
