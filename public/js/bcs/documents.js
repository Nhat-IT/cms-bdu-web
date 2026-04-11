    // Xử lý Ẩn/Hiện ô nhập danh mục "Khác"
    function toggleCustomCategory() {
        const categorySelect = document.getElementById('docCategory');
        const customDiv = document.getElementById('customCategoryDiv');
        
        if (categorySelect.value === 'Khác') {
            customDiv.classList.remove('d-none');
            document.getElementById('customCategoryInput').focus();
        } else {
            customDiv.classList.add('d-none');
        }
    }

    // Modal Logic Thêm/Sửa (Đồng bộ với logic của Giảng viên)
    function openDocModal(mode, title = '', note = '', category = 'Thông báo') {
        const modalTitle = document.getElementById('docModalTitle');
        const submitBtn = document.getElementById('docModalSubmitBtn');
        const fileContainer = document.getElementById('fileUploadContainer');
        
        const categorySelect = document.getElementById('docCategory');
        const customCategoryDiv = document.getElementById('customCategoryDiv');
        const customCategoryInput = document.getElementById('customCategoryInput');

        const defaultCategories = ['Thông báo', 'Biên bản', 'Học liệu'];

        if(mode === 'add') {
            modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải Tài Liệu Lên';
            submitBtn.innerText = 'TẢI LÊN';
            fileContainer.classList.remove('d-none'); 
            
            document.getElementById('uploadDocForm').reset();
            categorySelect.value = 'Thông báo';
            customCategoryDiv.classList.add('d-none');
            customCategoryInput.value = '';
        } else {
            modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh Sửa Tài Liệu';
            submitBtn.innerText = 'LƯU THAY ĐỔI';
            fileContainer.classList.add('d-none'); // Ẩn ô chọn file khi Sửa
            
            document.getElementById('docTitle').value = title;
            document.getElementById('docNote').value = note;

            if (defaultCategories.includes(category)) {
                categorySelect.value = category;
                customCategoryDiv.classList.add('d-none');
                customCategoryInput.value = '';
            } else {
                categorySelect.value = 'Khác';
                customCategoryDiv.classList.remove('d-none');
                customCategoryInput.value = category; 
            }
        }
    }

    // Xử lý nút xác nhận
    function saveDocument() {
        alert("✅ Đã lưu thông tin tài liệu thành công vào hệ thống lớp!");
    }

    // Xử lý xem/tải trực tiếp khi bấm vào tên file
    function handleFileView(event, fileName) {
        event.preventDefault(); 
        const ext = fileName.split('.').pop().toLowerCase();
        const downloadExts = ['zip', 'rar', 'exe', 'tar'];
        
        if (downloadExts.includes(ext)) {
            if(confirm(`📦 Tệp [${fileName}] là tệp nén.\nBấm OK để tải xuống máy tính.`)) {
                alert('⬇️ Đang tải tệp xuống...'); 
            }
        } else {
            alert(`👀 Đang mở bản xem trước của tệp [${fileName}] trên Google Drive Viewer...`);
        }
    }
