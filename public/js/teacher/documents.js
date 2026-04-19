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

<<<<<<< HEAD
        function openDocModal(mode, title = '', note = '', category = 'Học liệu', classId = '25TH01') {
=======
        function openDocModal(mode, title = '', note = '', category = 'Học liệu', classId = '') {
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
            const modalTitle = document.getElementById('docModalTitle');
            const submitBtn = document.getElementById('docModalSubmitBtn');
            const fileContainer = document.getElementById('fileUploadContainer');
            
            const categorySelect = document.getElementById('docCategory');
            const customCategoryDiv = document.getElementById('customCategoryDiv');
            const customCategoryInput = document.getElementById('customCategoryInput');

            const defaultCategories = ['Học liệu', 'Tham khảo', 'Đề cương', 'Thông báo'];

            if(mode === 'add') {
                modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải tài liệu lên';
                submitBtn.innerText = 'Tải lên Hệ thống';
                fileContainer.classList.remove('d-none'); 
                
                document.getElementById('docTitle').value = '';
                document.getElementById('docNote').value = '';
                document.getElementById('docClass').value = classId;
                document.getElementById('docFile').value = '';
                
                categorySelect.value = 'Học liệu';
                customCategoryDiv.classList.add('d-none');
                customCategoryInput.value = '';
                
            } else {
                modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật thông tin Tài liệu';
                submitBtn.innerText = 'Lưu thay đổi';
                fileContainer.classList.add('d-none'); 
                
                document.getElementById('docTitle').value = title;
                document.getElementById('docNote').value = note;
                document.getElementById('docClass').value = classId;

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

        function saveDocument() {
            const categorySelect = document.getElementById('docCategory').value;
            let finalCategory = categorySelect;
            if (categorySelect === 'Khác') {
                finalCategory = document.getElementById('customCategoryInput').value;
            }

            alert(`✅ Đã lưu thông tin tài liệu thành công!\nDanh mục lưu vào CSDL: [${finalCategory}]`);
        }

        function confirmDeleteDoc(title) {
            if(confirm(`⚠️ CẢNH BÁO: Bạn có chắc chắn muốn xóa file [${title}] khỏi hệ thống và Google Drive?\nHành động này không thể hoàn tác!`)) {
                alert(`✅ Đã xóa file thành công!`);
            }
        }

        function handleFileView(event, fileName) {
            event.preventDefault(); 
            const ext = fileName.split('.').pop().toLowerCase();
            const downloadExts = ['zip', 'rar', 'exe', 'tar'];
            
            if (downloadExts.includes(ext)) {
                if(confirm(`📦 Tệp [${fileName}] là tệp nén.\nBấm OK để tải xuống máy tính.`)) {
                    alert('⬇️ Đang tải tệp xuống...'); 
                }
            } else {
                alert(`👀 Đang mở bản xem trước của tệp [${fileName}]...`);
            }
        }
