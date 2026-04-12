// Trang hồ sơ BCS: xử lý ảnh đại diện, cập nhật hồ sơ và đổi mật khẩu.
(function () {
    const avatarInput = document.getElementById('avatarUploadInput');
    const mainAvatar = document.getElementById('mainProfileAvatar');
    const sidebarAvatar = document.getElementById('sidebarAvatar');
    const roleInput = document.getElementById('roleInput');
    const profileRoleText = document.getElementById('profileRoleText');
    const sidebarRoleText = document.getElementById('sidebarRoleText');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    if (avatarInput) {
        avatarInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (!file) {
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('⛔ Dung lượng ảnh vượt quá 2MB. Vui lòng chọn ảnh khác nhỏ hơn!');
                avatarInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                const imgUrl = e.target.result;
                if (mainAvatar) {
                    mainAvatar.src = imgUrl;
                }
                if (sidebarAvatar) {
                    sidebarAvatar.src = imgUrl;
                }
                setTimeout(function () {
                    alert('✅ Đã cập nhật ảnh đại diện thành công!');
                }, 250);
            };
            reader.readAsDataURL(file);
        });
    }

    window.handleUpdateProfile = function (e) {
        e.preventDefault();

        const roleValue = roleInput ? roleInput.value.trim() : '';
        if (!roleValue) {
            alert('⛔ Vui lòng nhập chức vụ BCS.');
            return false;
        }

        if (profileRoleText) {
            profileRoleText.textContent = roleValue;
        }
        if (sidebarRoleText) {
            sidebarRoleText.textContent = roleValue;
        }

        alert('✅ Đã cập nhật Thông tin liên hệ & chức vụ BCS thành công!');
        return false;
    };

    window.handleChangePassword = function (e) {
        e.preventDefault();

        const newPw = document.getElementById('newPassword');
        const confirmPw = document.getElementById('confirmPassword');

        if (!newPw || !confirmPw) {
            return false;
        }

        if (newPw.value !== confirmPw.value) {
            confirmPw.classList.add('is-invalid');
            return false;
        }

        confirmPw.classList.remove('is-invalid');
        if (confirm('⚠️ Bạn có chắc chắn muốn đổi Mật khẩu không? Hệ thống sẽ yêu cầu bạn đăng nhập lại.')) {
            alert('🔒 Đã đổi mật khẩu thành công! Hệ thống sẽ tự động đăng xuất.');
            window.location.href = '../login.html';
        }
        return false;
    };

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            confirmPasswordInput.classList.remove('is-invalid');
        });
    }
})();
