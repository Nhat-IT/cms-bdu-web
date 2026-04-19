// Trang hồ sơ BCS: dùng API thật để tải/cập nhật hồ sơ và đổi mật khẩu.
(function () {
    const avatarInput = document.getElementById('avatarUploadInput');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    function getDisplayName(raw) {
        return String(raw || '').trim() || 'BCS';
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
        const displayName = getDisplayName(me.full_name);
        const avatar = me.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0d6efd&color=fff&size=200`;

        const mainAvatar = document.getElementById('mainProfileAvatar');
        const sidebarAvatar = document.getElementById('sidebarAvatar');
        if (mainAvatar) mainAvatar.src = avatar;
        if (sidebarAvatar) sidebarAvatar.src = avatar;

        const titleName = document.querySelector('h5.fw-bold.text-dark.mb-1');
        if (titleName) titleName.textContent = displayName;

        const fullNameInput = document.getElementById('bcsFullNameInput');
        const birthDateInput = document.getElementById('bcsBirthDateInput');
        const emailInput = document.getElementById('bcsEmailInput');
        const phoneInput = document.getElementById('bcsPhoneInput');
        const codeInput = document.getElementById('bcsCodeInput');
        const addressInput = document.getElementById('bcsAddressInput');

        if (fullNameInput) fullNameInput.value = displayName;
        if (birthDateInput) birthDateInput.value = me.birth_date ? String(me.birth_date).slice(0, 10) : '';
        if (emailInput) emailInput.value = me.email || '';
        if (phoneInput) phoneInput.value = me.phone_number || '';
        if (codeInput) codeInput.value = me.username || '';
        if (addressInput) addressInput.value = me.address || '';
    }

    window.handleUpdateProfile = async function (e) {
        e.preventDefault();

        const fullName = document.getElementById('bcsFullNameInput')?.value?.trim() || '';
        const email = document.getElementById('bcsEmailInput')?.value?.trim() || '';
        const birthDate = document.getElementById('bcsBirthDateInput')?.value || null;
        const phoneNumber = document.getElementById('bcsPhoneInput')?.value?.trim() || '';
        const address = document.getElementById('bcsAddressInput')?.value?.trim() || '';

        if (!fullName || !email) {
            alert('Vui lòng nhập đầy đủ họ tên và email.');
            return false;
        }

        const res = await fetch('/api/me', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ fullName, email, birthDate, phoneNumber, address })
        });

        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            alert(data.error || 'Không thể cập nhật hồ sơ.');
            return false;
        }

        alert('Đã cập nhật hồ sơ BCS.');
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

    if (avatarInput) {
        avatarInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) {
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Dung lượng ảnh vượt quá 2MB.');
                avatarInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                const imgUrl = e.target.result;
                const mainAvatar = document.getElementById('mainProfileAvatar');
                const sidebarAvatar = document.getElementById('sidebarAvatar');
                if (mainAvatar) mainAvatar.src = imgUrl;
                if (sidebarAvatar) sidebarAvatar.src = imgUrl;
            };
            reader.readAsDataURL(file);
        });
    }

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            confirmPasswordInput.classList.remove('is-invalid');
        });
    }

    document.addEventListener('DOMContentLoaded', loadProfile);
})();
