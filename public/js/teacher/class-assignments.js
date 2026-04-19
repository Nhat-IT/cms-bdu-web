        // XỬ LÝ SỰ KIỆN CLICK VÀO FILE
        function handleFileClick(event, fileName, fileUrl) {
            event.preventDefault(); // Ngăn chặn chuyển trang mặc định của thẻ <a>
            
            // Lấy đuôi file (extension)
            const ext = fileName.split('.').pop().toLowerCase();
            
            // Danh sách các định dạng file nén hoặc file chạy -> Bắt buộc tải xuống
            const downloadExts = ['zip', 'rar', '7z', 'tar', 'gz', 'exe'];
            
            if (downloadExts.includes(ext)) {
                // Hành vi 1: Tải xuống trực tiếp
                if(confirm(`📦 Tệp [${fileName}] là tệp nén.\nBấm OK để tải xuống thiết bị của bạn.`)) {
                    // Mô phỏng tải xuống. Thực tế sẽ dùng window.location.href = fileUrl;
                    alert('⬇️ Đang tải tệp xuống...'); 
                }
            } else {
                // Hành vi 2: Xem trực tiếp bằng Google Drive Viewer
                if(confirm(`👀 Mở tệp [${fileName}] trong trình xem trước?`)) {
                    // Nếu là PDF thì trình duyệt đọc được ngay. 
                    // Nếu là DOCX, XLSX thì dùng Google Viewer đọc giùm.
                    let viewerUrl = fileUrl;
                    if(ext === 'doc' || ext === 'docx' || ext === 'xls' || ext === 'xlsx' || ext === 'ppt' || ext === 'pptx') {
                        viewerUrl = `https://drive.google.com/viewerng/viewer?url=${encodeURIComponent(fileUrl)}`;
                    }
                    window.open(viewerUrl, '_blank');
                }
            }
        }

        // JS Xử lý Modal Thêm/Sửa Bài Tập
        function openAssignmentModal(mode, title = '', classId = '', deadline = '', desc = '') {
            const modalTitle = document.getElementById('assignmentModalTitle');
            const submitBtn = document.getElementById('assignmentModalSubmitBtn');

            if(mode === 'add') {
                modalTitle.innerHTML = '<i class="bi bi-journal-plus me-2"></i>Tạo Bài tập mới';
                submitBtn.innerText = 'Giao bài tập';
                document.getElementById('modalAssignTitle').value = '';
                document.getElementById('modalAssignClass').value = '';
                document.getElementById('modalAssignDeadline').value = '';
                document.getElementById('modalAssignDesc').value = '';
                document.getElementById('assignmentFile').value = '';
            } else {
                modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Bài tập';
                submitBtn.innerText = 'Lưu thay đổi';
                document.getElementById('modalAssignTitle').value = title;
                document.getElementById('modalAssignClass').value = classId;
                document.getElementById('modalAssignDeadline').value = deadline;
                document.getElementById('modalAssignDesc').value = desc;
            }
        }

        function confirmDeleteAssignment(title) {
            if(confirm(`⚠️ CẢNH BÁO: Bạn có chắc chắn muốn xóa bài tập [${title}]?\nTOÀN BỘ bài nộp của sinh viên cho bài tập này cũng sẽ bị xóa vĩnh viễn!`)) {
                alert(`✅ Đã xóa bài tập thành công!`);
            }
        }

        function openGradeModal(studentId, studentName, score, feedback) {
            document.getElementById('modalStudentId').innerText = 'MSSV: ' + studentId;
            document.getElementById('modalStudentName').innerText = studentName;
            document.getElementById('modalScoreInput').value = score;
            document.getElementById('modalFeedbackInput').value = feedback;
        }
