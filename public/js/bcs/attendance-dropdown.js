// Thêm hàm xử lý khi chọn dropdown trạng thái điểm danh
function setAttendanceDropdown(studentId, statusValue) {
    // Nếu muốn cập nhật giao diện hoặc lưu trạng thái, xử lý tại đây
    // Ví dụ: đổi màu dòng, cập nhật biến, hoặc gửi AJAX lưu trạng thái
    // Dưới đây là ví dụ đổi màu dòng
    var select = document.getElementById('status_' + studentId);
    var tr = select.closest('tr');
    tr.classList.remove('bg-warning', 'bg-danger', 'bg-opacity-10');
    var nameNode = tr.querySelector('.fw-bold');
    if (nameNode) nameNode.classList.remove('text-danger', 'text-dark');

    if (statusValue == '2') {
        tr.classList.add('bg-warning', 'bg-opacity-10');
        if (nameNode) nameNode.classList.add('text-dark');
    } else if (statusValue == '3') {
        tr.classList.add('bg-danger', 'bg-opacity-10');
        if (nameNode) nameNode.classList.add('text-danger');
    } else {
        if (nameNode) nameNode.classList.add('text-dark');
    }
    // Nếu cần lưu trạng thái về server, gọi AJAX tại đây
}
