// --- LOGIC TÌM KIẾM MÔN HỌC ---
    function filterSubjects() {
        const input = document.getElementById("subjectFilter").value.toLowerCase();
        const subjectGroups = document.querySelectorAll(".subject-group");
        
        subjectGroups.forEach(group => {
            const title = group.querySelector(".subject-title").innerText.toLowerCase();
            if(title.includes(input)) {
                group.style.display = ""; 
            } else {
                group.style.display = "none"; 
            }
        });
    }

    const listView = document.getElementById('assignmentListView');
    const detailView = document.getElementById('assignmentDetailView');
    const uploadedFilesContainer = document.getElementById('uploadedFilesContainer');
    const btnAddFile = document.getElementById('addDropdownContainer');
    const btnTurnIn = document.getElementById('btnTurnIn');
    const btnUnsubmit = document.getElementById('btnUnsubmit');
    const workStatusText = document.getElementById('workStatusText');
    const teacherFeedbackDiv = document.getElementById('teacherFeedbackDiv');
    const autoGrowTextareas = document.querySelectorAll('.auto-grow-textarea');

    let submittedItems = [];
    let currentMode = 'pending'; 

    function autoGrowTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
    }

    autoGrowTextareas.forEach((textarea) => {
        autoGrowTextarea(textarea);
        textarea.addEventListener('input', function () {
            autoGrowTextarea(textarea);
        });
    });

    function openAssignmentDetail(status) {
        currentMode = status;
        submittedItems = []; 

        if(status === 'pending') {
            document.getElementById('detailTitle').innerText = 'Báo cáo giữa kỳ - Chương 1,2';
            document.getElementById('detailAuthor').innerText = 'ThS. Dương Quang Sinh • 2 ngày trước';
            document.getElementById('detailScore').innerText = '10 điểm';
            document.getElementById('detailDeadline').innerText = 'Đến hạn: 23:59, 30/03/2026';
            document.getElementById('detailDesc').style.display = 'block';
            document.getElementById('detailMaterial').style.display = 'flex';
            
            workStatusText.innerText = 'Đã giao';
            workStatusText.className = 'fw-bold text-success';
            
            btnAddFile.classList.remove('d-none');
            btnTurnIn.classList.remove('d-none');
            btnUnsubmit.classList.add('d-none');
            teacherFeedbackDiv.classList.add('d-none');

            renderItems();
        } 
        else if(status === 'graded') {
            document.getElementById('detailTitle').innerText = 'Bài tập thực hành Lab 01';
            document.getElementById('detailAuthor').innerText = 'ThS. Nguyễn Hồ Hải • 10/03/2026';
            document.getElementById('detailScore').innerText = '9/10';
            document.getElementById('detailDeadline').innerText = 'Không có hạn nộp';
            document.getElementById('detailDesc').style.display = 'none'; 
            document.getElementById('detailMaterial').style.display = 'none'; 
            
            workStatusText.innerText = 'Đã chấm';
            workStatusText.className = 'fw-bold text-dark';
            
            btnAddFile.classList.add('d-none');
            btnTurnIn.classList.add('d-none');
            btnUnsubmit.classList.add('d-none');
            teacherFeedbackDiv.classList.remove('d-none');

            submittedItems.push({ type: 'link', name: 'Source Code Github', extra: 'github.com/nhaty/lab01' });
            renderItems(true); 
        }

        listView.classList.add('d-none');
        detailView.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function closeAssignmentDetail() {
        detailView.classList.add('d-none');
        listView.classList.remove('d-none');
    }

    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            const ext = file.name.split('.').pop().toUpperCase();
            submittedItems.push({ type: 'file', name: file.name, extra: ext });
            document.getElementById('fileInput').value = ''; 
            renderItems();
        }
    }

    function addLinkItem() {
        const url = document.getElementById('linkInput').value;
        if(url.trim() === '') return;
        
        let domain = (new URL(url)).hostname.replace('www.','');
        
        submittedItems.push({ type: 'link', name: `Liên kết: ${domain}`, extra: url });
        document.getElementById('linkInput').value = '';
        
        bootstrap.Modal.getInstance(document.getElementById('addLinkModal')).hide();
        renderItems();
    }

    function removeItem(index) {
        submittedItems.splice(index, 1);
        renderItems();
    }

    function renderItems(isReadOnly = false) {
        uploadedFilesContainer.innerHTML = '';
        
        if(submittedItems.length > 0) {
            btnTurnIn.innerText = "Nộp bài";
            btnTurnIn.classList.add('btn-primary');
            btnTurnIn.classList.remove('btn-light', 'text-dark', 'border');

            submittedItems.forEach((item, index) => {
                let iconHTML = '';
                if(item.type === 'file') {
                    iconHTML = `<i class="bi bi-file-earmark-text-fill text-danger fs-4 me-3"></i>`;
                } else {
                    iconHTML = `<i class="bi bi-link-45deg text-success fs-3 me-2"></i>`;
                }

                let removeBtnHTML = isReadOnly ? '' : `<button class="btn btn-link text-muted p-0 ms-2" onclick="removeItem(${index})"><i class="bi bi-x-lg"></i></button>`;

                const html = `
                    <div class="file-upload-item shadow-sm">
                        <div class="d-flex align-items-center overflow-hidden">
                            ${iconHTML}
                            <div class="text-truncate">
                                <div class="fw-bold small text-dark mb-0 text-truncate">${item.name}</div>
                                <div class="text-muted text-truncate" style="font-size: 0.7rem;">${item.extra}</div>
                            </div>
                        </div>
                        ${removeBtnHTML}
                    </div>
                `;
                uploadedFilesContainer.insertAdjacentHTML('beforeend', html);
            });
        } else {
            btnTurnIn.innerText = "Đánh dấu là đã hoàn thành";
        }
    }

    function turnInWork() {
        if (submittedItems.length === 0) {
            if(!confirm("Bạn chưa đính kèm tệp hay liên kết nào. Thầy/cô sẽ chỉ thấy rằng bạn đã đánh dấu hoàn thành. Tiếp tục?")) return;
        }

        workStatusText.innerText = "Đã nộp";
        workStatusText.className = "fw-bold text-muted";
        
        btnAddFile.classList.add('d-none');
        btnTurnIn.classList.add('d-none');
        btnUnsubmit.classList.remove('d-none');
        
        renderItems(true); 
    }

    function unsubmitWork() {
        if(confirm("Hủy nộp bài để thêm hoặc thay đổi bài làm. Đừng quên nộp lại khi làm xong nhé!")) {
            workStatusText.innerText = "Đã giao";
            workStatusText.className = "fw-bold text-success";
            
            btnAddFile.classList.remove('d-none');
            btnTurnIn.classList.remove('d-none');
            btnUnsubmit.classList.add('d-none');
            
            renderItems(false); 
        }
    }
