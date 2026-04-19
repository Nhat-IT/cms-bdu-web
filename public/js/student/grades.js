    // Mở rộng/Thu gọn Sidebar
    // Xử lý Modal hiển thị chi tiết (Cập nhật để render tên bài tập cụ thể)
    function openDetailModal(subjectName, absenceRate, assignments) {
        // Cập nhật tiêu đề Modal
        document.getElementById('subjectDetailModalLabel').innerText = subjectName;
        
        // Cập nhật chuyên cần
        document.getElementById('modalAbsence').innerText = absenceRate;

        // Cập nhật list điểm bài tập
        const listContainer = document.getElementById('modalBTList');
        listContainer.innerHTML = ''; // Reset list

        // Duyệt qua mảng object để lấy Tên và Điểm
        assignments.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent';
            li.innerHTML = `
                <span class="text-dark fw-bold small">${item.name}</span>
                <span class="badge bg-primary rounded-pill px-3 py-2">${item.score.toFixed(1)}</span>
            `;
            listContainer.appendChild(li);
        });

        // Hiển thị modal
        var myModal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));
        myModal.show();
    }
