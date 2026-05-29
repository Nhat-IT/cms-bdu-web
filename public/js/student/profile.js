(function () {
    const avatarInput = document.getElementById('avatarUploadInput');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    // ── Avatar upload ───────────────────────────────────────────────────────────
    function setAvatarMsg(html) {
        const el = document.getElementById('avatarUploadMsg');
        if (el) el.innerHTML = html;
    }

    if (avatarInput) {
        avatarInput.addEventListener('change', async function () {
            const file = this.files && this.files[0];
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                alert('Dung lượng ảnh vượt quá 2MB.');
                this.value = '';
                return;
            }

            setAvatarMsg('<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Đang tải lên...</span>');

            const formData = new FormData();
            formData.append('avatar', file);

            try {
                const res = await fetch('/cms/api/me/avatar', { method: 'POST', body: formData });
                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    setAvatarMsg('<span class="text-danger">' + (data.error || 'Tải lên thất bại.') + '</span>');
                    return;
                }

                const newSrc = data.avatar;
                const mainAvatar = document.getElementById('mainProfileAvatar');
                const sidebarAvatar = document.getElementById('sidebarAvatar');
                if (mainAvatar) mainAvatar.src = newSrc;
                if (sidebarAvatar) sidebarAvatar.src = newSrc;

                setAvatarMsg('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Cập nhật ảnh thành công!</span>');
                setTimeout(() => setAvatarMsg(''), 3000);
            } catch {
                setAvatarMsg('<span class="text-danger">Lỗi kết nối. Thử lại.</span>');
            }

            this.value = '';
        });
    }

    // ── Cập nhật thông tin liên hệ ─────────────────────────────────────────────
    window.handleUpdateProfile = async function (e) {
        e.preventDefault();

        const payload = {
            birthDate:   document.getElementById('profileBirthDate')?.value || null,
            phoneNumber: document.getElementById('profilePhoneNumber')?.value.trim() || null,
            address:     document.getElementById('profileAddress')?.value.trim() || null,
        };

        try {
            const res = await fetch('/cms/api/me', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || 'Không thể cập nhật hồ sơ.');
                return false;
            }

            alert('Đã cập nhật thông tin liên hệ thành công.');
        } catch {
            alert('Lỗi kết nối. Thử lại.');
        }

        return false;
    };

    // ── Đổi mật khẩu ──────────────────────────────────────────────────────────
    window.handleChangePassword = async function (e) {
        e.preventDefault();

        const newPw      = document.getElementById('newPassword');
        const confirmPw  = document.getElementById('confirmPassword');
        const errorEl    = document.getElementById('confirmPasswordError');

        if (newPw.value !== confirmPw.value) {
            confirmPw.classList.add('is-invalid');
            if (errorEl) { errorEl.textContent = 'Mật khẩu xác nhận không khớp!'; errorEl.style.display = ''; }
            return false;
        }
        confirmPw.classList.remove('is-invalid');
        if (errorEl) errorEl.style.display = 'none';

        if (newPw.value.length < 6) {
            alert('Mật khẩu mới phải có ít nhất 6 ký tự.');
            return false;
        }

        try {
            const res = await fetch('/cms/api/me/password', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    oldPassword: document.getElementById('oldPassword')?.value || '',
                    newPassword: newPw.value,
                }),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || 'Không thể đổi mật khẩu.');
                return false;
            }

            alert('Đổi mật khẩu thành công! Vui lòng đăng nhập lại.');
            window.location.href = '/cms/views/logout.php';
        } catch {
            alert('Lỗi kết nối. Thử lại.');
        }

        return false;
    };

    // ── Hiện/ẩn mật khẩu ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toggle-password').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                const icon = this.querySelector('i');
                if (icon) icon.className = show ? 'bi bi-eye-slash-fill text-muted' : 'bi bi-eye-fill text-muted';
            });
        });
    });

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            this.classList.remove('is-invalid');
            const errorEl = document.getElementById('confirmPasswordError');
            if (errorEl) errorEl.style.display = 'none';
        });
    }
})();
