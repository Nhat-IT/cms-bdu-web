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
