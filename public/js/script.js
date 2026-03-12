// ==========================================
// DỮ LIỆU GIẢ LẬP TOÀN CỤC (GLOBAL STATE)
// Bổ sung thêm ngày bắt đầu và kết thúc
// ==========================================
let mockSubjects = [
    { id: 'subj_1', name: 'Cơ sở dữ liệu - Nhóm 01', semester: 'HK1', teacher: 'ThS. Nguyễn Văn X', startDate: '2025-09-05', endDate: '2025-11-15' },
    { id: 'subj_2', name: 'Lập trình Web - Nhóm 02', semester: 'HK2', teacher: 'TS. Trần Thị Y', startDate: '2026-01-10', endDate: '2026-03-20' },
    { id: 'subj_3', name: 'An ninh cơ sở dữ liệu', semester: 'HK2', teacher: 'ThS. Dương Quang Sinh', startDate: '2026-01-12', endDate: '2026-03-25' }
];

document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Logic ẩn/hiện mật khẩu
    const togglePasswordBtn = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("passwordInput");
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener("click", function () {
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            const icon = this.querySelector("i");
            if (type === "text") {
                icon.className = "bi bi-eye-fill text-primary";
            } else {
                icon.className = "bi bi-eye-slash-fill text-muted";
            }
        });
    }

    // 2. Bật/Tắt Sidebar Mobile
    const sidebarToggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("sidebar");
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener("click", () => sidebar.classList.toggle("show"));
    }

    // ==========================================
    // 3. LOGIC LỌC MÔN HỌC & HIỂN THỊ THÔNG TIN CHI TIẾT
    // ==========================================
    const filterSemester = document.getElementById("filterSemester");
    const subjectSelect = document.getElementById("subjectSelect");

    // Hàm chuyển đổi format ngày từ YYYY-MM-DD sang DD/MM/YYYY cho đẹp
    function formatDateDisplay(dateString) {
        if(!dateString) return '...';
        const parts = dateString.split('-');
        if(parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    }

    // Hàm cập nhật khung thông tin (Giảng viên, Thời gian) khi môn học được chọn
    function updateActiveSubjectInfo() {
        const val = subjectSelect.value;
        const infoBox = document.getElementById("activeSubjectInfo");
        if (!infoBox) return; // Nếu không ở trang attendance thì bỏ qua

        if (!val) {
            infoBox.classList.add("d-none"); // Ẩn đi nếu chưa chọn môn
            return;
        }

        const subj = mockSubjects.find(s => s.id === val);
        if (subj) {
            document.getElementById("lblSubjName").textContent = subj.name;
            document.getElementById("lblSubjTeacher").textContent = subj.teacher;
            document.getElementById("lblSubjTime").textContent = `${formatDateDisplay(subj.startDate)} đến ${formatDateDisplay(subj.endDate)}`;
            
            infoBox.classList.remove("d-none"); // Hiện khung thông tin lên
        }
    }

    // Lắng nghe sự kiện người dùng tự tay đổi Môn học trong Dropdown
    if (subjectSelect) {
        subjectSelect.addEventListener("change", updateActiveSubjectInfo);
    }

    // Hàm vẽ lại danh sách môn học dựa trên học kỳ
    function renderSubjects(selectedSubjectId = null) {
        if (!filterSemester || !subjectSelect) return;
        
        const currentSem = filterSemester.value; 
        subjectSelect.innerHTML = '<option value="">-- Chọn môn học --</option>'; 

        mockSubjects.forEach(subj => {
            if (currentSem === 'all' || subj.semester === currentSem) {
                const opt = new Option(subj.name, subj.id);
                if (subj.id === selectedSubjectId) opt.selected = true;
                subjectSelect.add(opt);
            }
        });

        // Mỗi khi vẽ lại danh sách, gọi cập nhật khung thông tin luôn
        updateActiveSubjectInfo();
    }

    if (filterSemester) {
        filterSemester.addEventListener('change', () => renderSubjects());
        renderSubjects();
    }

    // ==========================================
    // 4. LOGIC QUẢN LÝ CHUYÊN CẦN (Khóa/Mở File)
    // ==========================================
    const attendanceTableBody = document.querySelector('#attendanceTable tbody');
    function toggleFileUpload(selectElement) {
        const row = selectElement.closest('tr');
        const fileInput = row.querySelector('.file-upload');
        if (selectElement.value === "2") { 
            fileInput.disabled = false;
        } else {
            fileInput.disabled = true;
            fileInput.value = ""; 
        }
    }

    if(attendanceTableBody) {
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() { toggleFileUpload(this); });
            toggleFileUpload(select);
        });
    }

    // ==========================================
    // 5. LOGIC THÊM SINH VIÊN (Từ Sheet/File/Form)
    // ==========================================
    function createStudentRow(stt, mssv, name) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="stt-col">${stt}</td>
            <td class="fw-bold mssv-col">${mssv}</td>
            <td class="name-col">${name}</td>
            <td>
                <select class="form-select form-select-sm status-select">
                    <option value="1" selected>Có mặt</option>
                    <option value="2">Vắng có phép</option>
                    <option value="3">Vắng không phép</option>
                </select>
            </td>
            <td><input type="file" class="form-control form-control-sm file-upload" disabled></td>
        `;
        tr.querySelector('.status-select').addEventListener('change', function() { toggleFileUpload(this); });
        return tr;
    }

    function updateStudentCount() {
        if(!attendanceTableBody) return;
        const count = attendanceTableBody.querySelectorAll('tr').length;
        const countText = document.getElementById('studentCountText');
        if(countText) countText.textContent = `Đang hiển thị ${count} sinh viên`;
    }

    const btnLoadSheet = document.getElementById('btnLoadSheet');
    if (btnLoadSheet) {
        btnLoadSheet.addEventListener('click', function() {
            if (!document.getElementById('googleSheetLink').value.trim()) return alert('Vui lòng nhập link!');
            this.disabled = true; document.getElementById('loadingSpinnerSheet').classList.remove('d-none');
            setTimeout(() => {
                attendanceTableBody.innerHTML = ''; 
                attendanceTableBody.appendChild(createStudentRow(1, '20260002', 'Trần Thị B'));
                attendanceTableBody.appendChild(createStudentRow(2, '20260003', 'Lê Hoàng C'));
                document.getElementById('loadingSpinnerSheet').classList.add('d-none');
                this.disabled = false; updateStudentCount(); alert('Đồng bộ Google Sheet thành công!');
            }, 800);
        });
    }

    const btnSaveNewStudent = document.getElementById('btnSaveNewStudent');
    if (btnSaveNewStudent) {
        btnSaveNewStudent.addEventListener('click', function() {
            const mssv = document.getElementById('newMssv').value.trim();
            const name = document.getElementById('newFullName').value.trim();
            if (!mssv || !name) return alert('Vui lòng nhập đầy đủ!');
            const currentRows = attendanceTableBody.querySelectorAll('tr').length;
            attendanceTableBody.appendChild(createStudentRow(currentRows + 1, mssv, name));
            updateStudentCount();
            bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
            document.getElementById('addStudentForm').reset();
        });
    }

   // ==========================================
    // 6. LOGIC THÊM / SỬA MÔN HỌC
    // ==========================================
    const btnSaveSubject = document.getElementById('btnSaveSubject');
    if (btnSaveSubject) {
        btnSaveSubject.addEventListener('click', function() {
            const id = document.getElementById('subjectId').value;
            const name = document.getElementById('subjectName').value.trim();
            const teacher = document.getElementById('teacherName').value.trim();
            const sem = document.getElementById('subjectSemester').value; 
            
            // Lấy thêm Buổi và Tiết học
            const session = document.getElementById('defaultSession').value;
            const startP = document.getElementById('startPeriod').value;
            const endP = document.getElementById('endPeriod').value;
            
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!name || !teacher || !startDate || !endDate || !startP || !endP) {
                return alert('Vui lòng điền đầy đủ thông tin Môn học và Tiết học!');
            }

            if (id) {
                const subjIndex = mockSubjects.findIndex(s => s.id === id);
                if(subjIndex > -1) {
                    mockSubjects[subjIndex].name = name;
                    mockSubjects[subjIndex].semester = sem;
                    mockSubjects[subjIndex].teacher = teacher;
                    mockSubjects[subjIndex].startDate = startDate;
                    mockSubjects[subjIndex].endDate = endDate;
                    // Bạn có thể lưu thêm session, startP, endP vào mockSubjects nếu cần hiển thị sau này
                }
                alert('Cập nhật Môn học thành công!');
                document.getElementById('filterSemester').value = sem;
                renderSubjects(id);
            } else {
                const newId = 'subj_' + new Date().getTime(); 
                mockSubjects.push({ 
                    id: newId, name: name, semester: sem, 
                    teacher: teacher, startDate: startDate, endDate: endDate 
                });
                alert(`Thêm Môn học mới thành công!\n(Lịch học: Buổi ${session}, Tiết ${startP} - ${endP})`);
                document.getElementById('filterSemester').value = sem;
                renderSubjects(newId);
            }

            bootstrap.Modal.getInstance(document.getElementById('subjectModal')).hide();
        });
    }

    // (Cũng trong hàm openSubjectModal, bạn có thể xóa trắng 2 ô Tiết học khi bấm nút 'Thêm' mới)
    // document.getElementById('startPeriod').value = '';
    // document.getElementById('endPeriod').value = '';

    // ==========================================
    // 7. & 8. LOGIC DOCUMENTS VÀ FEEDBACK 
    // (Lưu giữ toàn bộ code Document và Feedback đã cung cấp ở các bước trước)
    // ==========================================
    const docCategorySelect = document.getElementById('docCategory');
    const subjectGroupForDoc = document.getElementById('subjectGroupForDoc');
    if (docCategorySelect && subjectGroupForDoc) {
        docCategorySelect.addEventListener('change', function() {
            if (this.value === 'HocLieu' || this.value === 'BaiTap') {
                subjectGroupForDoc.classList.remove('d-none');
            } else {
                subjectGroupForDoc.classList.add('d-none');
                document.getElementById('docSubject').value = ""; 
            }
        });
    }

    const btnSubmitUpload = document.getElementById('btnSubmitUpload');
    const documentsTableBody = document.querySelector('#documentsTable tbody');
    if (btnSubmitUpload && documentsTableBody) {
        btnSubmitUpload.addEventListener('click', function() {
            const title = document.getElementById('docTitle').value.trim();
            const cat = document.getElementById('docCategory').value;
            const file = document.getElementById('docFile').value;

            if (!title || !cat || !file) return alert('Vui lòng nhập đầy đủ Tiêu đề, Phân loại và chọn File!');

            let catName, badgeClass;
            if (cat === 'ThongBao') { catName = 'Thông báo'; badgeClass = 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25'; }
            else if (cat === 'BienBan') { catName = 'Biên bản'; badgeClass = 'bg-warning bg-opacity-10 text-warning border-warning border-opacity-50 text-dark'; }
            else { catName = 'Học liệu'; badgeClass = 'bg-primary bg-opacity-10 text-primary border-primary border-opacity-25'; }

            const today = new Date();
            const dateStr = today.getDate().toString().padStart(2, '0') + '/' + (today.getMonth()+1).toString().padStart(2, '0') + '/' + today.getFullYear();

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-4 py-3"><div class="d-flex align-items-center"><i class="bi bi-file-earmark-check-fill fs-3 text-success me-3"></i><div><h6 class="mb-0 fw-bold text-dark">${title} <span class="badge bg-success ms-1" style="font-size:0.6rem;">MỚI</span></h6><small class="text-muted">Vừa tải lên</small></div></div></td>
                <td><span class="badge ${badgeClass} border px-2 py-1">${catName}</span></td>
                <td>Ban Cán Sự</td><td>${dateStr}</td><td class="text-center text-muted small">-- KB</td>
                <td class="pe-4 text-end"><button class="btn btn-sm btn-light text-primary me-1"><i class="bi bi-download"></i></button><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></td>
            `;
            documentsTableBody.insertBefore(tr, documentsTableBody.firstChild);
            bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
            document.getElementById('uploadDocForm').reset();
            subjectGroupForDoc.classList.add('d-none'); 
            alert('Tải tài liệu lên hệ thống thành công!');
        });
    }

    window.viewFeedback = function(id) {
        if (!window.feedbackModalInstance) {
            window.feedbackModalInstance = new bootstrap.Modal(document.getElementById('feedbackModal'));
        }
        const fbStatusBadge = document.getElementById('fbStatusBadge');
        const fbAttachment = document.getElementById('fbAttachment');
        const btnMarkResolved = document.getElementById('btnMarkResolved');
        const fbReply = document.getElementById('fbReply');
        
        if (id === 1) { 
            document.getElementById('fbSenderName').textContent = "Nguyễn Thị C (22050111)";
            document.getElementById('fbSubject').textContent = "Thắc mắc số buổi vắng môn An ninh CSDL";
            document.getElementById('fbContent').textContent = "Chào Ban cán sự, mình thấy trên hệ thống báo mình vắng 2 buổi nhưng thực tế hôm đó mình có đi học và có điểm danh trên giấy. Nhờ BCS kiểm tra lại giúp mình nhé. Cảm ơn!";
            fbStatusBadge.className = "badge bg-warning text-dark"; fbStatusBadge.textContent = "Chờ xử lý";
            fbAttachment.classList.add('d-none'); fbReply.value = ""; btnMarkResolved.style.display = "inline-block"; 
        } else if (id === 2) { 
            document.getElementById('fbSenderName').textContent = "Lê Hoàng D (22050222)";
            document.getElementById('fbSubject').textContent = "Xin nộp bổ sung giấy khám bệnh";
            document.getElementById('fbContent').textContent = "Mình gửi kèm file ảnh giấy khám bệnh của tuần trước, nhờ BCS cập nhật lại trạng thái vắng có phép giúp mình nhé.";
            fbStatusBadge.className = "badge bg-success"; fbStatusBadge.textContent = "Đã giải quyết";
            fbAttachment.classList.remove('d-none'); fbReply.value = "Đã check và cập nhật lại điểm danh môn Chuyên đề 2 thành Có phép."; btnMarkResolved.style.display = "none"; 
        }
        window.feedbackModalInstance.show();
    };

    const btnMarkResolved = document.getElementById('btnMarkResolved');
    if (btnMarkResolved) {
        btnMarkResolved.addEventListener('click', function() {
            alert('Đã cập nhật trạng thái phản hồi thành "Đã giải quyết" và gửi thông báo đến sinh viên!');
            bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
        });
    }

}); // End DOMContentLoaded

let subjectModalInstance = null;
function openSubjectModal(mode) {
    if (!subjectModalInstance) subjectModalInstance = new bootstrap.Modal(document.getElementById('subjectModal'));
    const modalTitle = document.getElementById('subjectModalTitle');
    const form = document.getElementById('subjectForm');
    const subjectSelect = document.getElementById('subjectSelect');

    form.reset(); 

    if (mode === 'add') {
        modalTitle.textContent = 'Thêm môn học mới';
        document.getElementById('subjectId').value = ''; 
        const currentFilterSem = document.getElementById('filterSemester').value;
        if(currentFilterSem !== 'all') document.getElementById('subjectSemester').value = currentFilterSem;
        subjectModalInstance.show();
    } 
    else if (mode === 'edit') {
        const selectedValue = subjectSelect.value;
        if (!selectedValue) return alert('Vui lòng chọn một Môn học trong danh sách bên dưới để Sửa!');
        
        modalTitle.textContent = 'Sửa cấu hình Môn học';
        document.getElementById('subjectId').value = selectedValue;
        
        const subjInfo = mockSubjects.find(s => s.id === selectedValue);
        if(subjInfo) {
            document.getElementById('subjectName').value = subjInfo.name;
            document.getElementById('subjectSemester').value = subjInfo.semester;
            document.getElementById('teacherName').value = subjInfo.teacher;
            document.getElementById('startDate').value = subjInfo.startDate || '';
            document.getElementById('endDate').value = subjInfo.endDate || '';
        }
        subjectModalInstance.show();
    }
}

// ==========================================
    // 9. LOGIC KHU VỰC SINH VIÊN (STUDENT)
    // ==========================================
    
    // Hàm chuẩn bị dữ liệu khi SV bấm nút "Nộp minh chứng"
    window.prepareProofUpload = function(dateStr, sessionStr) {
        const infoSpan = document.getElementById('proofDateInfo');
        if (infoSpan) {
            infoSpan.textContent = `Ngày ${dateStr} - ${sessionStr}`;
        }
    };

    // Hàm giả lập Gửi minh chứng thành công
    const btnSubmitProof = document.getElementById('btnSubmitProof');
    if (btnSubmitProof) {
        btnSubmitProof.addEventListener('click', function() {
            const file = document.getElementById('proofFile').value;
            if (!file) {
                return alert('Vui lòng chọn ảnh/file minh chứng từ thiết bị của bạn!');
            }

            // Đóng modal và hiển thị thông báo
            bootstrap.Modal.getInstance(document.getElementById('uploadProofModal')).hide();
            document.getElementById('proofForm').reset();
            
            alert('Đã gửi minh chứng thành công! Ban cán sự sẽ nhận được thông báo và tiến hành xét duyệt.');
            
            // Note: Trong thực tế, sau khi alert, trang sẽ được reload hoặc dùng JS cập nhật thẻ <tr> từ màu đỏ sang màu vàng "Đang chờ duyệt".
        });
    }

// ==========================================
    // 10. LOGIC ĐỘNG DÀNH CHO TRANG SINH VIÊN (STUDENT)
    // ==========================================
    const studentSubjectSelect = document.getElementById('studentSubjectSelect');
    
    // Tận dụng hàm formatDateDisplay đã viết ở trên
    function formatDateDisplay(dateString) {
        if(!dateString) return '...';
        const parts = dateString.split('-');
        if(parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    }

    // Cập nhật thông tin khi sinh viên chọn Môn
    function updateStudentSubjectInfo() {
        if (!studentSubjectSelect) return;
        
        const val = studentSubjectSelect.value;
        const subj = mockSubjects.find(s => s.id === val);
        
        if (subj) {
            const lblTeacher = document.getElementById("lblStudentTeacher");
            const lblStart = document.getElementById("lblStudentStart");
            const lblEnd = document.getElementById("lblStudentEnd");
            
            if(lblTeacher) lblTeacher.textContent = subj.teacher;
            if(lblStart) lblStart.textContent = formatDateDisplay(subj.startDate);
            if(lblEnd) lblEnd.textContent = formatDateDisplay(subj.endDate);
        }
    }

    // Lắng nghe thay đổi dropdown
    if (studentSubjectSelect) {
        studentSubjectSelect.addEventListener('change', updateStudentSubjectInfo);
        // Chạy lần đầu tiên khi tải trang
        updateStudentSubjectInfo();
    }