        // JS Xử lý truyền dữ liệu vào Modal
        function openEvidenceModal(name, id, className, date, imageUrl, status) {
            // Gán thông tin
            document.getElementById('modalStudentName').innerText = name;
            document.getElementById('modalStudentId').innerText = 'MSSV: ' + id;
            document.getElementById('modalClass').innerText = className;
            document.getElementById('modalDate').innerText = date;
            
            // Gán ảnh giả lập (Thực tế sẽ là link Cloudinary trong bảng attendance_evidences)
            document.getElementById('modalEvidenceImage').src = imageUrl;
            
            // Xử lý ẩn/hiện nút bấm dựa vào trạng thái (status)
            const actionDiv = document.getElementById('actionButtonsDiv');
            const rejectDiv = document.getElementById('rejectReasonDiv');
            const messageDiv = document.getElementById('statusMessageDiv');
            
            if(status === 'pending') {
                actionDiv.classList.remove('d-none'); // Hiện 2 nút Duyệt/Từ chối
                rejectDiv.classList.remove('d-none'); // Hiện ô nhập lý do từ chối
                messageDiv.classList.add('d-none');   // Ẩn thông báo đã xử lý
            } else {
                actionDiv.classList.add('d-none');    // Ẩn 2 nút vì đã xử lý rồi
                rejectDiv.classList.add('d-none');    // Ẩn ô nhập
                messageDiv.classList.remove('d-none'); // Hiện thông báo "Đã xử lý"
            }

            // Mở Modal
            var evidenceModal = new bootstrap.Modal(document.getElementById('evidenceModal'));
            evidenceModal.show();
        }

        // JS Giả lập xử lý Duyệt/Từ chối
        function processEvidence(actionType) {
            if(actionType === 'Approved') {
                alert("✅ Đã DUYỆT minh chứng! Trạng thái sinh viên trong bảng điểm danh sẽ được chuyển thành: Vắng CÓ PHÉP.");
            } else {
                alert("❌ Đã TỪ CHỐI minh chứng! Trạng thái sinh viên trong bảng điểm danh sẽ bị đánh dấu: Vắng KHÔNG PHÉP.");
            }
            // Đóng Modal sau khi xử lý
            var evidenceModal = bootstrap.Modal.getInstance(document.getElementById('evidenceModal'));
            evidenceModal.hide();
        }
