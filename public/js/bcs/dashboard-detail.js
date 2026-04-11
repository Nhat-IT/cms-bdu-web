    function loadDashboardData() {
        const year = document.getElementById('filterYear').options[document.getElementById('filterYear').selectedIndex].text;
        const sem = document.getElementById('filterSemester').options[document.getElementById('filterSemester').selectedIndex].text;
        
        // Mô phỏng hiệu ứng tải dữ liệu
        const btn = event.currentTarget;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang tải...';
        btn.disabled = true;

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert(`✅ Đã tải dữ liệu Thống kê cho:\n- ${year}\n- ${sem}\n- Lớp 25TH01`);
        }, 800);
    }

    function exportDetailExcel() {
        alert('📥 Hệ thống đang trích xuất Báo cáo chi tiết ra file Excel. Quá trình này có thể mất vài giây...');
    }
