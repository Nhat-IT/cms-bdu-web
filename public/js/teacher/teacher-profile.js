<<<<<<< HEAD
        // --- LOGIC UPLOAD AVATAR ---
        document.getElementById('avatarUploadInput').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                if(file.size > 2 * 1024 * 1024) {
                    alert('⛔ Dung lượng ảnh vượt quá 2MB. Vui lòng chọn ảnh khác nhỏ hơn!');
                    this.value = ''; 
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgUrl = e.target.result;
                    document.getElementById('mainProfileAvatar').src = imgUrl;
                    document.getElementById('headerAvatar').src = imgUrl;
                    
                    setTimeout(() => {
                        alert('✅ Đã cập nhật ảnh đại diện thành công!');
                    }, 300);
                }
                reader.readAsDataURL(file);
            }
        });

        function handleUpdateProfile(e) {
            e.preventDefault();
            alert('✅ Đã cập nhật Thông tin cá nhân thành công!');
            return false;
        }

        function handleChangePassword(e) {
            e.preventDefault();
            
            const newPw = document.getElementById('newPassword');
            const confirmPw = document.getElementById('confirmPassword');
            
            if (newPw.value !== confirmPw.value) {
                confirmPw.classList.add('is-invalid');
                return false;
            } else {
                confirmPw.classList.remove('is-invalid');
                if(confirm('⚠️ Bạn có chắc chắn muốn đổi Mật khẩu không? Hệ thống sẽ yêu cầu đăng nhập lại.')) {
                    alert('🔒 Đã đổi mật khẩu thành công! Hệ thống sẽ tự động đăng xuất.');
                    window.location.href = '../login.html';
                }
            }
            return false;
        }

        document.getElementById('confirmPassword').addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
=======
(function () {
    function getDisplayName(raw) {
        return String(raw || '').trim() || 'Giảng viên';
    }

    function getAvatar(displayName, avatar) {
        return avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=0dcaf0&color=fff&size=200`;
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
        const avatarUrl = getAvatar(displayName, me.avatar);

        const mainAvatar = document.getElementById('mainProfileAvatar');
        const headerAvatar = document.getElementById('headerAvatar');
        if (mainAvatar) mainAvatar.src = avatarUrl;
        if (headerAvatar) headerAvatar.src = avatarUrl;

        const nameNodes = document.querySelectorAll('h5.fw-bold.text-dark.mb-1, .text-end.me-3 b');
        nameNodes.forEach(function (node) {
            node.textContent = displayName;
        });

        const fullNameInput = document.getElementById('teacherFullNameInput');
        const emailInput = document.getElementById('teacherEmailInput');
        const phoneInput = document.getElementById('teacherPhoneInput');

        if (fullNameInput) fullNameInput.value = displayName;
        if (emailInput) emailInput.value = me.email || '';
        if (phoneInput) phoneInput.value = me.phone_number || '';
    }

    async function updateProfile(event) {
        event.preventDefault();

        const fullName = document.getElementById('teacherFullNameInput')?.value?.trim() || '';
        const email = document.getElementById('teacherEmailInput')?.value?.trim() || '';
        const phoneNumber = document.getElementById('teacherPhoneInput')?.value?.trim() || '';

        if (!fullName || !email) {
            alert('Vui lòng nhập đầy đủ họ tên và email.');
            return false;
        }

        const res = await fetch('/api/me', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ fullName, email, phoneNumber })
        });

        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            alert(data.error || 'Không thể cập nhật hồ sơ.');
            return false;
        }

        alert('Đã cập nhật thông tin cá nhân.');
        loadProfile();
        return false;
    }

    async function changePassword(event) {
        event.preventDefault();
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
    }

    const avatarInput = document.getElementById('avatarUploadInput');
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
                const headerAvatar = document.getElementById('headerAvatar');
                if (mainAvatar) mainAvatar.src = imgUrl;
                if (headerAvatar) headerAvatar.src = imgUrl;
            };
            reader.readAsDataURL(file);
        });
    }

    const confirmPw = document.getElementById('confirmPassword');
    if (confirmPw) {
        confirmPw.addEventListener('input', function () {
            confirmPw.classList.remove('is-invalid');
        });
    }

    window.handleUpdateProfile = updateProfile;
    window.handleChangePassword = changePassword;

    document.addEventListener('DOMContentLoaded', loadProfile);
})();
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
