// Logic Mở rộng/Thu gọn Sidebar
document.getElementById('sidebarToggle').addEventListener('click', function() {
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.toggle('active');
    } else {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('mainContent').classList.toggle('expanded');
    }
});

// --- LOGIC UPLOAD AVATAR ---
document.getElementById('avatarUploadInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        // Kiểm tra dung lượng (Max 2MB)
        if(file.size > 2 * 1024 * 1024) {
            alert('⛔ Dung lượng ảnh vượt quá 2MB. Vui lòng chọn ảnh khác nhỏ hơn!');
            this.value = ''; 
            return;
        }

        // Preview ảnh
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgUrl = e.target.result;
            // Cập nhật ảnh to ở giữa
            document.getElementById('mainProfileAvatar').src = imgUrl;
            // Cập nhật luôn ảnh nhỏ trên thanh Sidebar
            document.getElementById('sidebarAvatar').src = imgUrl;
            
            setTimeout(() => {
                alert('✅ Đã cập nhật ảnh đại diện thành công!');
            }, 300);
        }
        reader.readAsDataURL(file);
    }
});

// Xử lý Cập nhật Profile
function handleUpdateProfile(e) {
    e.preventDefault();
    alert('✅ Đã cập nhật Thông tin liên hệ thành công!');
    return false;
}

// Xử lý Đổi Mật Khẩu kèm Validate
function handleChangePassword(e) {
    e.preventDefault();
    
    const newPw = document.getElementById('newPassword');
    const confirmPw = document.getElementById('confirmPassword');
    
    if (newPw.value !== confirmPw.value) {
        confirmPw.classList.add('is-invalid');
        return false;
    } else {
        confirmPw.classList.remove('is-invalid');
        if(confirm('⚠️ Bạn có chắc chắn muốn đổi Mật khẩu không? Hệ thống sẽ yêu cầu bạn đăng nhập lại.')) {
            alert('🔒 Đã đổi mật khẩu thành công! Hệ thống sẽ tự động đăng xuất.');
            window.location.href = '../login.html';
        }
    }
    return false;
}

// Xóa cảnh báo đỏ khi người dùng gõ lại mật khẩu
document.getElementById('confirmPassword').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});