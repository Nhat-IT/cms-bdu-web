let adminAccountsCache = [];
let editAccountId = null;

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

function openAccountModal(mode, account) {
    const modalEl = document.getElementById('accountModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const title = document.getElementById('accountModalTitle');
    const usernameInput = document.getElementById('modalCode');
    const fullNameInput = document.getElementById('modalFullName');
    const emailInput = document.getElementById('modalEmail');
    const classInput = document.getElementById('modalClass');
    const classInputGroup = document.getElementById('classInputGroup');
    const roleSelect = document.getElementById('modalRoleSingle');

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

    const needsClass = roleSelect.value === 'student' || roleSelect.value === 'bcs';
    if (classInputGroup) {
        classInputGroup.style.display = needsClass ? 'block' : 'none';
    }
    if (classInput) {
        if (needsClass) {
            classInput.setAttribute('required', 'true');
        } else {
            classInput.removeAttribute('required');
            classInput.value = '';
        }
    }

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

    tbody.querySelectorAll('button[data-action="edit"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = Number(btn.getAttribute('data-id'));
            const account = adminAccountsCache.find(function (item) { return Number(item.id) === id; });
            if (account) {
                openAccountModal('edit', account);
            }
        });
    });

    tbody.querySelectorAll('button[data-action="reset"]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = Number(btn.getAttribute('data-id'));
            if (!confirm('Khôi phục mật khẩu khởi tạo theo cấu hình hệ thống cho tài khoản này?')) {
                return;
            }

            const res = await fetch(`/api/admin/accounts/${id}/reset-password`, { method: 'POST', headers: { Accept: 'application/json' } });
            if (res.ok) {
                alert('Đã reset mật khẩu thành công.');
            } else {
                const data = await res.json().catch(function () { return {}; });
                alert(data.error || 'Không thể reset mật khẩu.');
            }
        });
    });
}

async function loadAccounts() {
    const keyword = document.getElementById('accountSearchInput')?.value?.trim() || '';
    const role = document.getElementById('accountRoleFilter')?.value || 'all';

    const query = new URLSearchParams();
    if (keyword) query.set('keyword', keyword);
    if (role) query.set('role', role);

    const res = await fetch(`/api/admin/accounts?${query.toString()}`, { headers: { Accept: 'application/json' } });
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

    adminAccountsCache = await res.json();
    renderAccounts(adminAccountsCache);
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

    const data = await res.json().catch(function () { return {}; });
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

    if (searchInput) searchInput.addEventListener('input', function () { loadAccounts(); });
    if (roleFilter) roleFilter.addEventListener('change', function () { loadAccounts(); });
    const statusFilter = document.getElementById('accountStatusFilter');
    if (statusFilter) statusFilter.addEventListener('change', function () { loadAccounts(); });
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
            const classInput = document.getElementById('modalClass');
            const classInputGroup = document.getElementById('classInputGroup');
            const needsClass = roleSelect.value === 'student' || roleSelect.value === 'bcs';
            if (classInputGroup) {
                classInputGroup.style.display = needsClass ? 'block' : 'none';
            }
            if (classInput) {
                if (needsClass) {
                    classInput.setAttribute('required', 'true');
                } else {
                    classInput.removeAttribute('required');
                    classInput.value = '';
                }
            }
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
