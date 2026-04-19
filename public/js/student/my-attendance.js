<<<<<<< HEAD
function prepareProofUpload(date, session) {
        document.getElementById('proofDateInfo').innerText = `${date} (${session})`;
    }

    function submitProof() {
        alert("✅ Đã tải file minh chứng lên thành công!\nVui lòng đợi Ban Cán Sự hoặc Giảng viên xét duyệt.");
        bootstrap.Modal.getInstance(document.getElementById('uploadProofModal')).hide();
    }
=======
let attendancePayload = { stats: {}, records: [] };
let classes = [];
let selectedProofRecordId = null;

function fmtDate(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '--/--/----';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function statusBadge(status) {
    if (Number(status) === 1) return '<span class="badge bg-success px-3 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>Có mặt</span>';
    if (Number(status) === 2) return '<span class="badge bg-warning text-dark px-3 py-1 rounded-pill"><i class="bi bi-exclamation-circle me-1"></i>Vắng có phép</span>';
    return '<span class="badge bg-danger px-3 py-1 rounded-pill"><i class="bi bi-x-circle me-1"></i>Vắng không phép</span>';
}

async function fetchAttendanceData() {
    const [attendanceRes, classesRes] = await Promise.all([
        fetch('/api/student/attendance'),
        fetch('/api/student/classes')
    ]);

    if (attendanceRes.status === 401 || classesRes.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    if (!attendanceRes.ok) {
        throw new Error('Không thể tải điểm danh');
    }

    attendancePayload = await attendanceRes.json();
    classes = classesRes.ok ? await classesRes.json() : [];
}

function populateSubjectSelect() {
    const subjectSelect = document.getElementById('studentSubjectSelect');
    if (!subjectSelect) return;

    const uniqueSubjects = [];
    const seen = new Set();
    classes.forEach((c) => {
        const key = `${c.class_subject_group_id}-${c.subject_name}`;
        if (!seen.has(key)) {
            seen.add(key);
            uniqueSubjects.push(c);
        }
    });

    subjectSelect.innerHTML = uniqueSubjects.map((item) =>
        `<option value="${item.class_subject_group_id}">${item.subject_name} - Nhóm ${item.group_code || '--'}</option>`
    ).join('');

    subjectSelect.addEventListener('change', renderAttendanceBySelection);
}

function renderSummary(records) {
    const badges = document.querySelectorAll('.attendance-summary-badge');
    if (badges.length < 3) return;

    const total = records.length;
    const present = records.filter((r) => Number(r.status) === 1).length;
    const absent = records.filter((r) => Number(r.status) !== 1).length;

    badges[0].textContent = `Tổng buổi: ${total}`;
    badges[1].textContent = `Có mặt: ${present}`;
    badges[2].textContent = `Đã vắng: ${absent}`;
}

function renderSubjectInfo(selectedClass) {
    const box = document.getElementById('studentSubjectInfo');
    if (!box || !selectedClass) return;

    box.innerHTML = `
        <div class="row w-100 text-dark small m-0">
            <div class="col-md-5 p-0"><i class="bi bi-person-badge text-primary me-2"></i>Giảng viên: <strong>${selectedClass.teacher_name || 'Chưa cập nhật'}</strong></div>
            <div class="col-md-7 p-0"><i class="bi bi-calendar2-range text-primary me-2"></i>Thời gian: <strong>${fmtDate(selectedClass.start_date)} - ${fmtDate(selectedClass.end_date)}</strong></div>
        </div>
    `;
}

function renderWarning(records) {
    const warningText = document.querySelector('.attendance-warning-text');
    if (!warningText) return;

    const absent = records.filter((r) => Number(r.status) !== 1).length;
    if (absent >= 3) {
        warningText.innerHTML = `<strong>Cảnh báo:</strong> Bạn đã vắng ${absent} buổi. Vượt quá 20% sẽ bị cấm thi!`;
    } else {
        warningText.innerHTML = `<strong>Thông báo:</strong> Bạn đã vắng ${absent} buổi. Tiếp tục duy trì chuyên cần.`;
    }
}

function renderAttendanceBySelection() {
    const subjectSelect = document.getElementById('studentSubjectSelect');
    const selectedGroupId = Number(subjectSelect?.value || 0);

    const selectedClass = classes.find((c) => Number(c.class_subject_group_id) === selectedGroupId) || classes[0];
    const records = (attendancePayload.records || []).filter((r) => Number(r.class_subject_group_id) === Number(selectedClass?.class_subject_group_id));

    renderSummary(records);
    renderSubjectInfo(selectedClass);
    renderWarning(records);

    const tbody = document.querySelector('.attendance-detail-table tbody');
    if (!tbody) return;

    if (!records.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu điểm danh cho môn này.</td></tr>';
        return;
    }

    tbody.innerHTML = records.map((row, index) => {
        const evidenceCell = row.drive_link
            ? `<a href="${row.drive_link}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light text-primary border"><i class="bi bi-link-45deg me-1"></i>Xem minh chứng</a>`
            : '<span class="text-muted small">--</span>';

        const reviewCell = row.evidence_status
            ? `<span class="badge bg-light border text-dark">${row.evidence_status}</span>`
            : '<span class="text-muted">--</span>';

        const actionCell = row.drive_link
            ? reviewCell
            : (Number(row.status) === 1
                ? '<span class="text-muted">--</span>'
                : `<button class="btn btn-sm btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadProofModal" onclick="prepareProofUpload('${fmtDate(row.attendance_date)}', '${selectedClass?.study_session || '--'}', ${row.id}, '${(selectedClass?.subject_name || '').replaceAll("'", "\\'")}')"><i class="bi bi-cloud-upload me-1"></i> Nộp bổ sung</button>`);

        return `
            <tr>
                <td class="ps-4 py-3 text-center attendance-col-stt">${index + 1}</td>
                <td class="attendance-col-date text-dark">${fmtDate(row.attendance_date)}</td>
                <td class="text-dark">${selectedClass?.study_session || '--'}</td>
                <td>${statusBadge(row.status)}</td>
                <td>${evidenceCell}</td>
                <td class="pe-4 text-center">${actionCell}</td>
            </tr>
        `;
    }).join('');
}

function prepareProofUpload(date, session, recordId, subjectName) {
    selectedProofRecordId = Number(recordId);
    document.getElementById('proofDateInfo').innerText = `${date} (${session})`;
    const subjectTextTarget = document.querySelector('#uploadProofModal .alert.alert-warning span.fw-bold');
    if (subjectTextTarget && subjectName) {
        subjectTextTarget.innerText = subjectName;
    }
}

async function uploadProofFileToDrive(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('/api/upload-to-drive', {
        method: 'POST',
        body: formData
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || payload.error || 'Upload file thất bại');
    }

    return response.json();
}

async function submitProof() {
    try {
        if (!selectedProofRecordId) {
            alert('Thiếu thông tin buổi điểm danh cần nộp minh chứng.');
            return;
        }

        const fileInput = document.getElementById('proofFile');
        const file = fileInput?.files?.[0];
        if (!file) {
            alert('Vui lòng chọn file minh chứng.');
            return;
        }

        const uploadResult = await uploadProofFileToDrive(file);
        const response = await fetch('/api/student/attendance/evidences', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attendanceRecordId: selectedProofRecordId,
                driveLink: uploadResult.link,
                driveFileId: uploadResult.fileId
            })
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || 'Không thể lưu minh chứng');
        }

        alert('Đã nộp minh chứng thành công.');
        bootstrap.Modal.getInstance(document.getElementById('uploadProofModal'))?.hide();
        document.getElementById('proofForm')?.reset();
        selectedProofRecordId = null;

        await fetchAttendanceData();
        renderAttendanceBySelection();
    } catch (error) {
        console.error(error);
        alert(error.message || 'Nộp minh chứng thất bại.');
    }
}

async function initAttendance() {
    try {
        await fetchAttendanceData();
        populateSubjectSelect();
        renderAttendanceBySelection();
    } catch (error) {
        console.error(error);
        alert('Không thể tải dữ liệu điểm danh từ database.');
    }
}

document.addEventListener('DOMContentLoaded', initAttendance);
window.prepareProofUpload = prepareProofUpload;
window.submitProof = submitProof;
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
