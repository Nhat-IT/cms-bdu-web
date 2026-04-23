(function () {
    function adminDisplayDate(value) {
        if (!value) return '';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    function applyProfile(me) {
        const displayName = me.full_name || me.username || 'Admin';
        const avatar = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0d6efd&color=fff`;

        const mainAvatar = document.getElementById('mainProfileAvatar');
        const headerAvatar = document.getElementById('headerAvatar');
        if (mainAvatar) mainAvatar.src = avatar;
        if (headerAvatar) headerAvatar.src = avatar;

        document.querySelectorAll('.admin-operator-name, h5.fw-bold.text-dark.mb-1').forEach(function (el) {
            el.textContent = displayName;
        });

        const usernameNode = document.getElementById('adminUsernameText');
        if (usernameNode) usernameNode.textContent = me.username || '';

        const createdNode = document.getElementById('adminCreatedAtText');
        if (createdNode && me.created_at) {
            createdNode.textContent = adminDisplayDate(me.created_at);
        }

        const fullNameInput = document.getElementById('adminFullNameInput');
        const emailInput = document.getElementById('adminEmailInput');
        if (fullNameInput) fullNameInput.value = displayName;
        if (emailInput) emailInput.value = me.email || '';
    }

    async function loadProfile() {
        const res = await fetch('/api/me', { headers: { Accept: 'application/json' } });
        if (res.status === 401) {
            window.location.href = '/login.html';
            return;
        }
        if (!res.ok) {
            return;
        }

        const me = await res.json();
        applyProfile(me);
    }

    window.handleUpdateProfile = async function (e) {
        e.preventDefault();

        const fullName = document.getElementById('adminFullNameInput')?.value?.trim() || '';
        const email = document.getElementById('adminEmailInput')?.value?.trim() || '';

        if (!fullName || !email) {
            alert('Vui lòng nhập đầy đủ tên hiển thị và email.');
            return false;
        }

        const res = await fetch('/api/me', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ fullName, email })
        });

        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            alert(data.error || 'Không thể cập nhật thông tin.');
            return false;
        }

        alert('Đã cập nhật thông tin quản trị viên.');
        loadProfile();
        return false;
    };

    window.handleChangePassword = async function (e) {
        e.preventDefault();

        const oldPassword = document.getElementById('oldPassword')?.value || '';
        const newPassword = document.getElementById('newPassword')?.value || '';
        const confirmPassword = document.getElementById('confirmPassword')?.value || '';

        if (newPassword !== confirmPassword) {
            document.getElementById('confirmPassword')?.classList.add('is-invalid');
            return false;
        }

        const res = await fetch('/api/me/password', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ oldPassword, newPassword })
        });

        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            alert(data.error || 'Không thể đổi mật khẩu.');
            return false;
        }

        alert('Đổi mật khẩu thành công. Hệ thống sẽ đăng xuất.');
        window.location.href = '/auth/logout';
        return false;
    };

    const confirmInput = document.getElementById('confirmPassword');
    if (confirmInput) {
        confirmInput.addEventListener('input', function () {
            confirmInput.classList.remove('is-invalid');
        });
    }

    const avatarInput = document.getElementById('avatarUploadInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                alert('Dung lượng ảnh vượt quá 2MB.');
                avatarInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = e.target.result;
                const mainAvatar = document.getElementById('mainProfileAvatar');
                const headerAvatar = document.getElementById('headerAvatar');
                if (mainAvatar) mainAvatar.src = img;
                if (headerAvatar) headerAvatar.src = img;
            };
            reader.readAsDataURL(file);
        });
    }

    document.addEventListener('DOMContentLoaded', loadProfile);
})();
