<<<<<<< HEAD
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
=======
let gradesData = [];
let attendanceData = { records: [] };

function gradeClass(letter) {
    const ch = String(letter || '').toUpperCase().charAt(0);
    if (ch === 'A') return 'grade-a';
    if (ch === 'B') return 'grade-b';
    if (ch === 'C') return 'grade-c';
    if (ch === 'D' || ch === 'F') return 'grade-d';
    return 'text-muted';
}

function esc(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function fetchGradesData() {
    const [gradesRes, attendanceRes] = await Promise.all([
        fetch('/api/student/grades'),
        fetch('/api/student/attendance')
    ]);

    if (gradesRes.status === 401 || attendanceRes.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    if (!gradesRes.ok) {
        throw new Error('Không thể tải điểm số');
    }

    gradesData = await gradesRes.json();
    attendanceData = attendanceRes.ok ? await attendanceRes.json() : { records: [] };
}

function renderGradesTable() {
    const tbody = document.querySelector('.table-grades tbody');
    if (!tbody) return;

    if (!gradesData.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu điểm.</td></tr>';
        return;
    }

    tbody.innerHTML = gradesData.map((g) => `
        <tr>
            <td class="ps-4 fw-bold text-dark">${esc(g.subject_name)} (${esc(g.subject_code)})</td>
            <td class="text-center text-muted">${g.assignment_score ?? '-'}</td>
            <td class="text-center text-muted">${g.midterm_score ?? '-'}</td>
            <td class="text-center text-muted">${g.final_score ?? '-'}</td>
            <td class="text-center fw-bold score-box text-primary">${g.total_score ?? '-'}</td>
            <td class="text-center fw-bold ${gradeClass(g.grade_letter)}">${esc(g.grade_letter || '--')}</td>
            <td class="text-center pe-4">
                <button class="btn btn-outline-primary btn-detail px-3" onclick="openDetailModal(${Number(g.id)})">
                    <i class="bi bi-eye-fill me-1"></i> Xem
                </button>
            </td>
        </tr>
    `).join('');
}

function getAbsenceRate(subjectName) {
    const rows = (attendanceData.records || []).filter((r) => r.subject_name === subjectName);
    const absent = rows.filter((r) => Number(r.status) !== 1).length;
    return `${absent}/${rows.length || 0}`;
}

function openDetailModal(gradeId) {
    const grade = gradesData.find((x) => Number(x.id) === Number(gradeId));
    if (!grade) return;

    document.getElementById('subjectDetailModalLabel').innerText = `${grade.subject_name} (${grade.subject_code})`;
    document.getElementById('modalAbsence').innerText = getAbsenceRate(grade.subject_name);

    const listContainer = document.getElementById('modalBTList');
    listContainer.innerHTML = `
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
            <span class="text-dark fw-bold small">Điểm bài tập</span>
            <span class="badge bg-primary rounded-pill px-3 py-2">${grade.assignment_score ?? '-'}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
            <span class="text-dark fw-bold small">Điểm giữa kỳ</span>
            <span class="badge bg-primary rounded-pill px-3 py-2">${grade.midterm_score ?? '-'}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
            <span class="text-dark fw-bold small">Điểm cuối kỳ</span>
            <span class="badge bg-primary rounded-pill px-3 py-2">${grade.final_score ?? '-'}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
            <span class="text-dark fw-bold small">Điểm tổng kết</span>
            <span class="badge bg-success rounded-pill px-3 py-2">${grade.total_score ?? '-'}</span>
        </li>
    `;

    const modal = new bootstrap.Modal(document.getElementById('subjectDetailModal'));
    modal.show();
}

async function initGrades() {
    try {
        await fetchGradesData();
        renderGradesTable();
    } catch (error) {
        console.error(error);
        alert('Không thể tải dữ liệu điểm từ database.');
    }
}

document.addEventListener('DOMContentLoaded', initGrades);
window.openDetailModal = openDetailModal;
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
