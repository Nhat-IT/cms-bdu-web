let adminAccountsCache = [];
let editAccountId = null;
let loadAccountsAbortController = null;
let loadAccountsRequestId = 0;

function getRoleLabel(role) {
    const map = {
        admin: 'Quản trị viên',
        support_admin: 'Giáo vụ khoa',
        staff: 'Giáo vụ khoa',
        teacher: 'Giảng viên',
        bcs: 'Ban Cán Sự',
        student: 'Sinh viên'
    };
    return map[role] || role || 'N/A';
}

function getRoleBadge(role) {
    if (role === 'admin') return '<span class="badge bg-danger text-white px-2 py-1">Quản trị viên</span>';
    if (role === 'support_admin' || role === 'staff') return '<span class="badge bg-secondary text-white px-2 py-1">Giáo vụ khoa</span>';
    if (role === 'teacher') return '<span class="badge bg-primary text-white px-2 py-1">Giảng viên</span>';
    if (role === 'bcs') return '<span class="badge bg-warning text-dark px-2 py-1">Ban Cán Sự</span>';
    return '<span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1">Sinh viên</span>';
}

function toggleClassFieldByRole(role) {
    const classInput = document.getElementById('modalClass');
    const classInputGroup = document.getElementById('classInputGroup');
    const needsClass = role === 'student' || role === 'bcs';

    if (classInputGroup) {
        classInputGroup.style.display = needsClass ? 'block' : 'none';
    }

    if (!classInput) {
        return;
    }

    if (needsClass) {
        classInput.setAttribute('required', 'true');
        return;
    }

    classInput.removeAttribute('required');
    classInput.value = '';
}

function openAccountModal(mode, account) {
    const modalEl = document.getElementById('accountModal');
    if (!modalEl) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const title = document.getElementById('accountModalTitle');
    const usernameInput = document.getElementById('modalCode');
    const fullNameInput = document.getElementById('modalFullName');
    const emailInput = document.getElementById('modalEmail');
    const classInput = document.getElementById('modalClass');
    const roleSelect = document.getElementById('modalRoleSingle');

    if (!title || !usernameInput || !fullNameInput || !emailInput || !classInput || !roleSelect) {
        return;
    }

    if (mode === 'add') {
        editAccountId = null;
        title.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Thêm Tài Khoản Mới';
        usernameInput.value = '';
        fullNameInput.value = '';
        emailInput.value = '';
        classInput.value = '';
        roleSelect.value = 'student';
    } else {
        editAccountId = account.id;
        title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập Nhật Thông Tin';
        usernameInput.value = account.username || '';
        fullNameInput.value = account.full_name || '';
        emailInput.value = account.email || '';
        classInput.value = account.class_name || '';
        roleSelect.value = account.role || 'student';
    }

    toggleClassFieldByRole(roleSelect.value);
    modal.show();
}

function renderAccounts(rows) {
    const tbody = document.getElementById('adminAccountsTableBody');
    if (!tbody) {
        return;
    }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Không có tài khoản phù hợp.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(function (row, index) {
        const avatar = row.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(row.full_name || row.username || 'U')}&background=0D8ABC&color=fff`;
        const classText = row.class_name ? `<div class="small fw-bold text-primary">Lớp: ${row.class_name}</div>` : '';

        return `
            <tr>
                <td class="ps-4 text-center text-muted">${index + 1}</td>
                <td><div class="fw-bold text-dark">${row.username || ''}</div>${classText}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${avatar}" class="table-avatar border me-3" alt="Avatar">
                        <div>
                            <div class="fw-bold text-dark">${row.full_name || ''}</div>
                            <div class="small text-muted">${row.email || ''}</div>
                        </div>
                    </div>
                </td>
                <td class="text-center">${getRoleBadge(row.role)}</td>
                <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Đang hoạt động</span></td>
                <td class="pe-4 text-end">
                    <button class="btn btn-light action-btn text-warning border me-1" data-action="reset" data-id="${row.id}" title="Khôi phục mật khẩu"><i class="bi bi-key-fill"></i></button>
                    <button class="btn btn-light action-btn text-primary border me-1" data-action="edit" data-id="${row.id}" title="Sửa thông tin"><i class="bi bi-pencil-square"></i></button>
                </td>
            </tr>`;
    }).join('');
}

function findAccountById(id) {
    return adminAccountsCache.find(function (item) {
        return Number(item.id) === Number(id);
    });
}

async function resetAccountPassword(id) {
    if (!confirm('Khôi phục mật khẩu khởi tạo theo cấu hình hệ thống cho tài khoản này?')) {
        return;
    }

    const res = await fetch(`/api/admin/accounts/${id}/reset-password`, {
        method: 'POST',
        headers: { Accept: 'application/json' }
    });

    if (res.ok) {
        alert('Đã reset mật khẩu thành công.');
        return;
    }

    const data = await res.json().catch(function () {
        return {};
    });
    alert(data.error || 'Không thể reset mật khẩu.');
}

function bindAccountsTableEvents() {
    const tbody = document.getElementById('adminAccountsTableBody');
    if (!tbody || tbody.dataset.accountsEventBound === '1') {
        return;
    }

    tbody.dataset.accountsEventBound = '1';
    tbody.addEventListener('click', async function (event) {
        const button = event.target.closest('button[data-action][data-id]');
        if (!button || !tbody.contains(button)) {
            return;
        }

        const id = Number(button.getAttribute('data-id'));
        if (!Number.isFinite(id)) {
            return;
        }

        const action = button.getAttribute('data-action');
        if (action === 'edit') {
            const account = findAccountById(id);
            if (account) {
                openAccountModal('edit', account);
            }
            return;
        }

        if (action === 'reset') {
            if (button.dataset.loading === '1') {
                return;
            }

            button.dataset.loading = '1';
            button.disabled = true;
            try {
                await resetAccountPassword(id);
            } finally {
                button.disabled = false;
                button.dataset.loading = '0';
            }
        }
    });
}

function buildAccountsQuery() {
    const keyword = document.getElementById('accountSearchInput')?.value?.trim() || '';
    const role = document.getElementById('accountRoleFilter')?.value || 'all';

    const query = new URLSearchParams();
    if (keyword) {
        query.set('keyword', keyword);
    }
    if (role) {
        query.set('role', role);
    }
    return query;
}

async function loadAccounts() {
    if (loadAccountsAbortController) {
        loadAccountsAbortController.abort();
    }

    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    loadAccountsAbortController = controller;
    const requestId = ++loadAccountsRequestId;

    try {
        const query = buildAccountsQuery();
        const fetchOptions = {
            headers: { Accept: 'application/json' }
        };

        if (controller) {
            fetchOptions.signal = controller.signal;
        }

        const res = await fetch(`/api/admin/accounts?${query.toString()}`, fetchOptions);
        if (res.status === 401) {
            window.location.href = '/login.html';
            return;
        }
        if (res.status === 403) {
            return;
        }
        if (!res.ok) {
            throw new Error('Không thể tải danh sách tài khoản');
        }

        const data = await res.json();
        if (requestId !== loadAccountsRequestId) {
            return;
        }

        adminAccountsCache = Array.isArray(data) ? data : [];
        renderAccounts(adminAccountsCache);
    } catch (error) {
        if (error && error.name === 'AbortError') {
            return;
        }
        throw error;
    } finally {
        if (requestId === loadAccountsRequestId) {
            loadAccountsAbortController = null;
        }
    }
}

function debounce(callback, wait) {
    let timeout = null;

    return function () {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function () {
            callback.apply(context, args);
        }, wait);
    };
}

async function submitAccountForm(event) {
    event.preventDefault();

    const username = document.getElementById('modalCode')?.value?.trim() || '';
    const fullName = document.getElementById('modalFullName')?.value?.trim() || '';
    const email = document.getElementById('modalEmail')?.value?.trim() || '';
    const role = document.getElementById('modalRoleSingle')?.value || 'student';
    const className = document.getElementById('modalClass')?.value?.trim() || '';

    if (!username || !fullName || !email || !role) {
        alert('Vui lòng nhập đầy đủ thông tin bắt buộc.');
        return false;
    }

    const payload = { username, fullName, email, role, className };
    const isEdit = Number.isFinite(editAccountId) && editAccountId !== null;
    const url = isEdit ? `/api/admin/accounts/${editAccountId}` : '/api/admin/accounts';
    const method = isEdit ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json().catch(function () {
        return {};
    });

    if (!res.ok) {
        alert(data.error || 'Không thể lưu tài khoản.');
        return false;
    }

    bootstrap.Modal.getInstance(document.getElementById('accountModal'))?.hide();
    await loadAccounts();
    alert('Đã lưu tài khoản thành công.');
    return false;
}

function bindEvents() {
    const searchInput = document.getElementById('accountSearchInput');
    const roleFilter = document.getElementById('accountRoleFilter');
    const addBtn = document.getElementById('adminAddAccountBtn');
    const form = document.getElementById('accountForm');
    const roleSelect = document.getElementById('modalRoleSingle');
    const importBtn = document.getElementById('accountImportBtn');
    const statusFilter = document.getElementById('accountStatusFilter');
    const debouncedLoadAccounts = debounce(function () {
        loadAccounts();
    }, 250);

    if (searchInput) {
        searchInput.addEventListener('input', debouncedLoadAccounts);
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', function () {
            loadAccounts();
        });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            loadAccounts();
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            openAccountModal('add', null);
        });
    }

    if (form) {
        form.addEventListener('submit', submitAccountForm);
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            toggleClassFieldByRole(roleSelect.value);
        });
    }

    if (importBtn) {
        importBtn.addEventListener('click', function () {
            const fileInput = document.getElementById('accountImportFile');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                alert('Vui lòng chọn file import.');
                return;
            }
            alert('Đã nhận file import. Chức năng import chi tiết sẽ được hoàn thiện ở bước tiếp theo.');
            bootstrap.Modal.getInstance(document.getElementById('importAccountModal'))?.hide();
        });
    }

    bindAccountsTableEvents();
}

document.addEventListener('DOMContentLoaded', async function () {
    try {
        bindEvents();
        await loadAccounts();
    } catch (error) {
        const tbody = document.getElementById('adminAccountsTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Không tải được danh sách tài khoản.</td></tr>';
        }
    }
});
