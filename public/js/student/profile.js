async function loadProfile() {
    const response = await fetch('/api/student/profile');
    if (response.status === 401) {
        window.location.href = '/login.html';
        return null;
    }
    if (!response.ok) {
        throw new Error('Không thể tải hồ sơ');
    }
    return response.json();
}

function bindProfileData(profile) {
    if (!profile) return;

    const avatar = profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.full_name || 'User')}&background=0d6efd&color=fff&size=200`;

    const mainAvatar = document.getElementById('mainProfileAvatar');
    const sidebarAvatar = document.getElementById('sidebarAvatar');
    if (mainAvatar) mainAvatar.src = avatar;
    if (sidebarAvatar) sidebarAvatar.src = avatar;

    const sideName = document.querySelector('.profile-container .text-white.fw-bold.fs-6');
    if (sideName) sideName.textContent = profile.full_name || 'Chưa cập nhật';

    const sideMssv = document.querySelector('.profile-container .text-white-50.small.mb-1');
    if (sideMssv) sideMssv.textContent = `MSSV: ${profile.username || '--'}`;

    const mainName = document.querySelector('.card-body.text-center.pt-0 h5.fw-bold.text-dark.mb-1');
    if (mainName) mainName.textContent = profile.full_name || 'Chưa cập nhật';

    // Họ tên (readonly): lấy từ DB do admin cấp
    const profileFullNameInput = document.getElementById('profileFullName');
    if (profileFullNameInput) profileFullNameInput.value = profile.full_name || '';

    // Email: hiển thị FULL email (không cắt @)
    const profileEmailInput = document.getElementById('profileEmail');
    if (profileEmailInput) {
        profileEmailInput.value = profile.email || '';
    }

    const birthDateInput = document.getElementById('profileBirthDate');
    const phoneInput = document.getElementById('profilePhoneNumber');
    const addressInput = document.getElementById('profileAddress');

    if (birthDateInput) {
        birthDateInput.value = profile.birth_date ? String(profile.birth_date).slice(0, 10) : '';
    }
    if (phoneInput) {
        phoneInput.value = profile.phone_number || '';
    }
    if (addressInput) {
        addressInput.value = profile.address || '';
    }

    // MSSV: lấy phần số trước @ trong email (khi đăng nhập bằng Gmail)
    const profileMssvEl = document.getElementById('profileMssv');
    if (profileMssvEl) {
        // Ưu tiên username, nếu là email thì lấy phần trước @
        const rawUsername = profile.username || '';
        let mssv = rawUsername;
        // Nếu username chứa @ (đăng nhập bằng Gmail), trích phần số trước @
        if (mssv.includes('@')) {
            mssv = mssv.split('@')[0];
        }
        // Nếu vẫn trống, thử lấy từ email
        if (!mssv && profile.email) {
            mssv = profile.email.split('@')[0];
        }
        profileMssvEl.textContent = mssv || '--';
    }

    // Chức vụ: CHỈ hiện cho tài khoản BCS (Ban cán sự)
    const roleInput = document.getElementById('profileRoleInput');
    const roleContainer = roleInput ? roleInput.closest('.mb-3') : null;
    if (roleInput) {
        roleInput.value = profile.position || '';
    }
    if (roleContainer) {
        // Ẩn/hiện dựa trên role từ API /api/me
        const isBcs = profile.is_bcs || false;
        roleContainer.style.display = isBcs ? '' : 'none';
    }

    // Chuyên ngành
    const majorEl = document.getElementById('profileMajor');
    if (majorEl) majorEl.textContent = profile.major_name || profile.major || '--';

    // Niên khóa
    const cohortEl = document.getElementById('profileCohort');
    if (cohortEl) cohortEl.textContent = profile.cohort || profile.nien_khoa || '--';
}

document.getElementById('avatarUploadInput').addEventListener('change', function (event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('Dung lượng ảnh vượt quá 2MB.');
        this.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const imgUrl = e.target.result;
        document.getElementById('mainProfileAvatar').src = imgUrl;
        document.getElementById('sidebarAvatar').src = imgUrl;
        alert('Đã cập nhật ảnh đại diện trên giao diện.');
    };
    reader.readAsDataURL(file);
});

async function handleUpdateProfile(e) {
    e.preventDefault();

    try {
        const payload = {
            birthDate: document.getElementById('profileBirthDate')?.value || null,
            phoneNumber: document.getElementById('profilePhoneNumber')?.value.trim() || null,
            address: document.getElementById('profileAddress')?.value.trim() || null,
            position: document.getElementById('profileRoleInput')?.value.trim() || null
        };

        const response = await fetch('/api/student/profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (response.status === 401) {
            window.location.href = '/login.html';
            return false;
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.error || 'Không thể cập nhật hồ sơ');
        }

        const profile = await response.json();
        bindProfileData(profile);
        alert('Đã cập nhật thông tin liên hệ thành công.');
    } catch (error) {
        console.error(error);
        alert(error.message || 'Cập nhật hồ sơ thất bại.');
    }

    return false;
}

async function handleChangePassword(e) {
    e.preventDefault();

    const newPw = document.getElementById('newPassword');
    const confirmPw = document.getElementById('confirmPassword');
    if (newPw.value !== confirmPw.value) {
        confirmPw.classList.add('is-invalid');
        return false;
    }

    confirmPw.classList.remove('is-invalid');

    try {
        const response = await fetch('/api/student/password', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                oldPassword: document.getElementById('oldPassword')?.value || '',
                newPassword: newPw.value
            })
        });

        if (response.status === 401) {
            window.location.href = '/login.html';
            return false;
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.error || 'Không thể đổi mật khẩu');
        }

        alert('Đổi mật khẩu thành công. Vui lòng đăng nhập lại.');
        window.location.href = '/auth/logout';
    } catch (error) {
        console.error(error);
        alert(error.message || 'Đổi mật khẩu thất bại.');
    }

    return false;
}

document.getElementById('confirmPassword').addEventListener('input', function () {
    this.classList.remove('is-invalid');
});

// ==== Nút hiện/ẩn mật khẩu ====
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = this.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-fill text-muted' : 'bi bi-eye-slash-fill text-muted';
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const profile = await loadProfile();
        bindProfileData(profile);
    } catch (error) {
        console.error(error);
        alert('Không thể tải hồ sơ sinh viên từ database.');
    }
});

window.handleUpdateProfile = handleUpdateProfile;
window.handleChangePassword = handleChangePassword;
