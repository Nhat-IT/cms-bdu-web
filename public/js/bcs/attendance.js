<<<<<<< HEAD
    const mockDB = {
        "CSDL": {
            name: "Cơ sở dữ liệu",
            groups: {
                "01": { 
                    teacher: "ThS. Nguyễn Văn X", start: "12/01/2026", end: "05/05/2026", session: "Sáng",
                    students: [
                        { mssv: "22050001", name: "Nguyễn Văn A", dob: "15/04/2004", cls: "25TH01", status: "1", note: "" },
                        { mssv: "22050022", name: "Trần Vũ Gia Huy", dob: "22/08/2004", cls: "25TH01", status: "2", note: "Đã nộp phép" },
                        { mssv: "22050089", name: "Phan Đình Luyến", dob: "10/11/2004", cls: "25TH01", status: "3", note: "" }
                    ]
                },
                "02": { 
                    teacher: "ThS. Lê Hoàng Z", start: "13/01/2026", end: "06/05/2026", session: "Chiều",
                    students: [
                        { mssv: "22050100", name: "Lê Tuấn B", dob: "20/05/2004", cls: "25TH02", status: "1", note: "" },
                        { mssv: "22050105", name: "Đỗ Minh C", dob: "05/09/2004", cls: "25TH02", status: "1", note: "" }
                    ]
                }
            }
        },
        "WEB": {
            name: "Lập trình Web",
            groups: {
                "01": { 
                    teacher: "ThS. Trần Thị Y", start: "15/01/2026", end: "10/05/2026", session: "Chiều",
                    students: [
                        { mssv: "22050201", name: "Hoàng Thanh D", dob: "12/12/2004", cls: "25TH01", status: "1", note: "" }
                    ]
                }
            }
        }
    };

    let currentDefaultSession = "Sáng";

    function applyStatusSelectStyle(selectElement) {
        if (!selectElement) {
            return;
        }

        const value = selectElement.value;
        if (value === '1') {
            selectElement.style.color = '#198754';
            selectElement.style.backgroundColor = '#ecfdf3';
            selectElement.style.borderColor = '#86efac';
        } else if (value === '2') {
            selectElement.style.color = '#b58105';
            selectElement.style.backgroundColor = '#fffbeb';
            selectElement.style.borderColor = '#fcd34d';
        } else {
            selectElement.style.color = '#dc3545';
            selectElement.style.backgroundColor = '#fff1f2';
            selectElement.style.borderColor = '#fda4af';
        }
        selectElement.style.fontWeight = '700';
    }

    function initDropdowns() {
        const subjectSelect = document.getElementById('subjectSelect');
        subjectSelect.innerHTML = '';
        for (const subjKey in mockDB) {
            const option = document.createElement('option');
            option.value = subjKey;
            option.text = mockDB[subjKey].name;
            subjectSelect.appendChild(option);
        }
        onSubjectChange(); 
    }

    function onSubjectChange() {
        const subjKey = document.getElementById('subjectSelect').value;
        const groupSelect = document.getElementById('groupSelect');
        groupSelect.innerHTML = '';
        
        const groups = mockDB[subjKey].groups;
        for (const groupKey in groups) {
            const option = document.createElement('option');
            option.value = groupKey;
            option.text = "Nhóm " + groupKey;
            groupSelect.appendChild(option);
        }
        onGroupChange();
    }

    function onGroupChange() {
        const subjKey = document.getElementById('subjectSelect').value;
        const groupKey = document.getElementById('groupSelect').value;
        const groupData = mockDB[subjKey].groups[groupKey];

        document.getElementById('lblTeacher').innerText = groupData.teacher;
        document.getElementById('lblTime').innerText = groupData.start + " - " + groupData.end;
        
        currentDefaultSession = groupData.session;
        document.getElementById('attendanceSession').value = currentDefaultSession;
        checkSessionReason();

        renderStudentList(groupData.students);
    }

    function renderStudentList(students) {
        const tbody = document.getElementById('studentTableBody');
        tbody.innerHTML = '';
        
        students.forEach((sv, index) => {
            const tr = document.createElement('tr');
            tr.className = `student-row ${sv.status === '2' ? 'bg-warning bg-opacity-10' : sv.status === '3' ? 'bg-danger bg-opacity-10' : ''}`;
            
            const badgeHtml = sv.status === '2' ? `<span class="badge bg-warning text-dark border border-warning"><i class="bi bi-file-medical-fill me-1"></i>${sv.note}</span>` 
                            : `<input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..." value="${sv.note}">`;

            tr.innerHTML = `
                <td class="text-center stt-col">${index + 1}</td>
                <td class="fw-bold text-dark">${sv.mssv}</td>
                <td class="fw-bold ${sv.status === '3' ? 'text-danger' : 'text-dark'} student-name">${sv.name}</td>
                <td>${sv.dob}</td>
                <td>${sv.cls}</td>
                <td>
                    <select class="form-select form-select-sm status-select val-${sv.status}" onchange="updateColor(this)">
                        <option value="1" style="color:#198754;" ${sv.status === '1' ? 'selected' : ''}>Có mặt</option>
                        <option value="2" style="color:#b58105;" ${sv.status === '2' ? 'selected' : ''}>Vắng có phép</option>
                        <option value="3" style="color:#dc3545;" ${sv.status === '3' ? 'selected' : ''}>Vắng không phép</option>
                    </select>
                </td>
                <td>${badgeHtml}</td>
            `;
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('.status-select').forEach(function (select) {
            applyStatusSelectStyle(select);
        });
        recalculateAttendance();
    }

    document.addEventListener('DOMContentLoaded', initDropdowns);

    function checkSessionReason() {
        const selectedSession = document.getElementById('attendanceSession').value;
        const reasonDiv = document.getElementById('sessionReasonDiv');
        if(selectedSession !== currentDefaultSession) {
            reasonDiv.classList.remove('invisible');
            document.getElementById('sessionReason').required = true;
        } else {
            reasonDiv.classList.add('invisible');
            document.getElementById('sessionReason').required = false;
        }
    }

    function updateColor(selectElement) {
        const tr = selectElement.closest('tr');
        selectElement.classList.remove('val-1', 'val-2', 'val-3');
        tr.classList.remove('bg-warning', 'bg-danger', 'bg-opacity-10');
        tr.querySelector('.student-name').classList.remove('text-danger');
        tr.querySelector('.student-name').classList.add('text-dark');

        if (selectElement.value === '1') {
            selectElement.classList.add('val-1');
        } else if (selectElement.value === '2') {
            selectElement.classList.add('val-2');
            tr.classList.add('bg-warning', 'bg-opacity-10');
        } else if (selectElement.value === '3') {
            selectElement.classList.add('val-3');
            tr.classList.add('bg-danger', 'bg-opacity-10');
            tr.querySelector('.student-name').classList.remove('text-dark');
            tr.querySelector('.student-name').classList.add('text-danger');
        }
        applyStatusSelectStyle(selectElement);
        recalculateAttendance();
    }

    function recalculateAttendance() {
        const selects = document.querySelectorAll('.status-select');
        let present = 0; let absent = 0;
        selects.forEach(select => { if(select.value === '1') present++; else absent++; });
        document.getElementById('totalCount').innerText = present + absent;
        document.getElementById('presentCount').innerText = present;
        document.getElementById('absentCount').innerText = absent;
    }

    function filterTable() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#attendanceTable tbody .student-row");
        rows.forEach(row => {
            const mssv = row.cells[1].innerText.toLowerCase();
            const name = row.cells[2].innerText.toLowerCase();
            if(mssv.includes(input) || name.includes(input)) row.style.display = "";
            else row.style.display = "none";
        });
    }

    function addStudentToTable() {
        const mssv = document.getElementById('newMssv').value;
        const name = document.getElementById('newFullName').value;
        const dob = document.getElementById('newDob').value || "Chưa cập nhật";
        const cls = document.getElementById('newClass').value;

        if(!mssv || !name || !cls) { alert("Vui lòng nhập MSSV, Họ Tên và Lớp học!"); return; }

        const tbody = document.querySelector('#attendanceTable tbody');
        const rowCount = tbody.querySelectorAll('tr').length + 1;
        
        let formattedDob = dob;
        if(dob !== "Chưa cập nhật") {
            const parts = dob.split('-');
            formattedDob = `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        const tr = document.createElement('tr');
        tr.className = "student-row";
        tr.innerHTML = `
            <td class="text-center stt-col">${rowCount}</td>
            <td class="fw-bold text-dark">${mssv}</td>
            <td class="fw-bold text-primary student-name">${name} <span class="badge bg-primary ms-1 small">Mới</span></td>
            <td>${formattedDob}</td>
            <td>${cls}</td>
            <td>
                <select class="form-select form-select-sm status-select val-1" onchange="updateColor(this)">
                    <option value="1" style="color:#198754;" selected>Có mặt</option>
                    <option value="2" style="color:#b58105;">Vắng có phép</option>
                    <option value="3" style="color:#dc3545;">Vắng không phép</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..."></td>
        `;

        tbody.appendChild(tr);
        applyStatusSelectStyle(tr.querySelector('.status-select'));
        bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
        document.getElementById('addStudentForm').reset();
        recalculateAttendance();
    }

    function exportToExcel() {
        const subjName = document.getElementById('subjectSelect').options[document.getElementById('subjectSelect').selectedIndex].text;
        const group = document.getElementById('groupSelect').value;
        const date = document.getElementById('attendanceDate').value;
        
        alert(`📥 Đã xuất file Excel thành công!\n\nThông tin Header file:\n- Môn học: ${subjName}\n- Mã môn học: [Từ CSDL]\n- Nhóm: ${group}\n- Ngày học: ${date}\n- Cột: STT | MSSV | Họ và tên | Ngày sinh | Lớp | Trạng thái`);
    }
=======
const bcsAttendanceState = {
    groups: [],
    subjectMap: new Map(),
    selectedGroupId: 0
};

function bcsFormatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function bcsParseStatus(value) {
    const n = Number(value);
    return [1, 2, 3].includes(n) ? n : 1;
}

function bcsBuildSubjectKey(group) {
    return String(group.class_subject_id);
}

function bcsPopulateSelectors(groups) {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    if (!subjectSelect || !groupSelect) return;

    bcsAttendanceState.subjectMap.clear();
    groups.forEach((g) => {
        const key = bcsBuildSubjectKey(g);
        if (!bcsAttendanceState.subjectMap.has(key)) {
            bcsAttendanceState.subjectMap.set(key, {
                label: `${g.subject_name} - ${g.class_name}`,
                groups: []
            });
        }
        bcsAttendanceState.subjectMap.get(key).groups.push(g);
    });

    subjectSelect.innerHTML = Array.from(bcsAttendanceState.subjectMap.entries())
        .map(([key, value]) => `<option value="${key}">${value.label}</option>`)
        .join('');

    if (!subjectSelect.value && subjectSelect.options.length) {
        subjectSelect.value = subjectSelect.options[0].value;
    }

    window.onSubjectChange();
}

function bcsRenderRoster(students) {
    const tbody = document.getElementById('studentTableBody');
    if (!tbody) return;

    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Chua co sinh vien trong nhom nay.</td></tr>';
        document.getElementById('totalCount').textContent = '0';
        document.getElementById('presentCount').textContent = '0';
        document.getElementById('absentCount').textContent = '0';
        return;
    }

    tbody.innerHTML = students.map((sv, idx) => {
        const birth = sv.birth_date ? bcsFormatDate(sv.birth_date) : '';
        const rowClass = Number(sv.status) === 2
            ? 'bg-warning bg-opacity-10'
            : (Number(sv.status) === 3 ? 'bg-danger bg-opacity-10' : '');
        const isUnexcused = Number(sv.status) === 3;

        return `
            <tr class="student-row ${rowClass}" data-student-id="${sv.student_id}">
                <td class="text-center stt-col text-muted">${idx + 1}</td>
                <td class="fw-bold text-dark mssv-cell">${sv.username || ''}</td>
                <td class="fw-bold ${isUnexcused ? 'text-danger' : 'text-dark'} student-name name-cell">${sv.full_name || ''}</td>
                <td>${birth}</td>
                <td>${sv.class_name || ''}</td>
                <td>
                    <select class="form-select form-select-sm status-select" onchange="window.updateColor(this)">
                        <option value="1" ${Number(sv.status) === 1 ? 'selected' : ''}>Co mat</option>
                        <option value="2" ${Number(sv.status) === 2 ? 'selected' : ''}>Vang co phep</option>
                        <option value="3" ${Number(sv.status) === 3 ? 'selected' : ''}>Vang khong phep</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chu..."></td>
            </tr>`;
    }).join('');

    window.recalculateAttendance();
}

async function bcsLoadRoster() {
    if (!bcsAttendanceState.selectedGroupId) return;
    const date = document.getElementById('attendanceDate')?.value;
    if (!date) return;

    const res = await fetch(`/api/bcs/attendance/roster?groupId=${bcsAttendanceState.selectedGroupId}&date=${encodeURIComponent(date)}`, {
        headers: { Accept: 'application/json' }
    });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the tai danh sach diem danh.');
        return;
    }

    const students = Array.isArray(data.students) ? data.students : [];
    bcsRenderRoster(students);
}

window.onSubjectChange = function () {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    if (!subjectSelect || !groupSelect) return;

    const subjectKey = subjectSelect.value;
    const item = bcsAttendanceState.subjectMap.get(subjectKey);
    const groups = item ? item.groups : [];

    groupSelect.innerHTML = groups.map((g) => `<option value="${g.group_id}">Nhom ${g.group_code || ''}</option>`).join('');
    if (groups.length) {
        groupSelect.value = String(groups[0].group_id);
    }

    window.onGroupChange();
};

window.onGroupChange = function () {
    const groupId = Number(document.getElementById('groupSelect')?.value || 0);
    bcsAttendanceState.selectedGroupId = groupId;

    const group = bcsAttendanceState.groups.find((g) => Number(g.group_id) === groupId);
    if (group) {
        const lblTeacher = document.getElementById('lblTeacher');
        const lblTime = document.getElementById('lblTime');
        const sessionSelect = document.getElementById('attendanceSession');

        if (lblTeacher) lblTeacher.textContent = group.teacher_name || 'Chua cap nhat';
        if (lblTime) {
            lblTime.textContent = `${bcsFormatDate(group.start_date)} - ${bcsFormatDate(group.end_date)} | Tiet ${group.start_period || '?'}-${group.end_period || '?'} | Phong ${group.room || 'N/A'}`;
        }
        if (sessionSelect && group.study_session) {
            sessionSelect.value = group.study_session;
        }
    }

    bcsLoadRoster();
};

window.updateColor = function (selectElement) {
    const tr = selectElement.closest('tr');
    if (!tr) return;

    tr.classList.remove('bg-warning', 'bg-danger', 'bg-opacity-10');
    const nameNode = tr.querySelector('.student-name');
    nameNode?.classList.remove('text-danger');
    nameNode?.classList.add('text-dark');

    const status = bcsParseStatus(selectElement.value);
    if (status === 2) tr.classList.add('bg-warning', 'bg-opacity-10');
    if (status === 3) {
        tr.classList.add('bg-danger', 'bg-opacity-10');
        nameNode?.classList.remove('text-dark');
        nameNode?.classList.add('text-danger');
    }

    window.recalculateAttendance();
};

window.recalculateAttendance = function () {
    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    let present = 0;
    let absent = 0;

    rows.forEach((row) => {
        if (row.style.display === 'none') return;
        const status = bcsParseStatus(row.querySelector('.status-select')?.value);
        if (status === 1) present += 1;
        else absent += 1;
    });

    document.getElementById('totalCount').textContent = String(present + absent);
    document.getElementById('presentCount').textContent = String(present);
    document.getElementById('absentCount').textContent = String(absent);
};

window.filterTable = function () {
    const keyword = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    rows.forEach((row) => {
        const mssv = (row.querySelector('.mssv-cell')?.textContent || '').toLowerCase();
        const name = (row.querySelector('.name-cell')?.textContent || '').toLowerCase();
        row.style.display = (!keyword || mssv.includes(keyword) || name.includes(keyword)) ? '' : 'none';
    });
    window.recalculateAttendance();
};

window.checkSessionReason = function () {
    const sessionDiv = document.getElementById('sessionReasonDiv');
    if (sessionDiv) sessionDiv.classList.add('invisible');
};

window.addStudentToTable = function () {
    alert('Ban can them sinh vien tai trang phan cong lop hoc.');
};

window.exportToExcel = function () {
    const subject = document.getElementById('subjectSelect')?.selectedOptions?.[0]?.textContent?.trim() || 'Mon hoc';
    const group = document.getElementById('groupSelect')?.selectedOptions?.[0]?.textContent?.trim() || 'Nhom';
    const dateValue = document.getElementById('attendanceDate')?.value || new Date().toISOString().slice(0, 10);

    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    if (!rows.length) {
        alert('Khong co du lieu de xuat.');
        return;
    }

    const csvRows = [
        ['MSSV', 'Ho ten', 'Ngay sinh', 'Lop', 'Trang thai'].join(','),
        ...rows.map((row) => {
            const mssv = row.querySelector('.mssv-cell')?.textContent?.trim() || '';
            const name = row.querySelector('.name-cell')?.textContent?.trim() || '';
            const dob = row.children[3]?.textContent?.trim() || '';
            const cls = row.children[4]?.textContent?.trim() || '';
            const status = row.querySelector('.status-select')?.selectedOptions?.[0]?.textContent?.trim() || '';
            const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
            return [mssv, name, dob, cls, status].map(esc).join(',');
        })
    ];

    const content = `Subject,${JSON.stringify(subject)}\nGroup,${JSON.stringify(group)}\nDate,${dateValue}\n\n${csvRows.join('\n')}`;
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = `bcs_attendance_${dateValue}_${bcsAttendanceState.selectedGroupId || 'group'}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
};

async function saveBcsAttendance() {
    const date = document.getElementById('attendanceDate')?.value;
    if (!bcsAttendanceState.selectedGroupId || !date) {
        alert('Vui long chon nhom va ngay hoc truoc khi luu.');
        return;
    }

    const records = Array.from(document.querySelectorAll('#studentTableBody tr.student-row')).map((row) => ({
        studentId: Number(row.getAttribute('data-student-id')),
        status: bcsParseStatus(row.querySelector('.status-select')?.value)
    }));

    const res = await fetch('/api/bcs/attendance/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            groupId: bcsAttendanceState.selectedGroupId,
            attendanceDate: date,
            session: document.getElementById('attendanceSession')?.value || '',
            records
        })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the luu diem danh.');
        return;
    }

    alert('Da luu du lieu diem danh thanh cong.');
}

async function loadBcsAttendanceData() {
    const res = await fetch('/api/bcs/groups', { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (!res.ok) {
        alert('Khong the tai danh sach nhom hoc phan.');
        return;
    }

    const groups = await res.json();
    bcsAttendanceState.groups = Array.isArray(groups) ? groups : [];
    bcsPopulateSelectors(bcsAttendanceState.groups);

    const dateInput = document.getElementById('attendanceDate');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }

    dateInput?.addEventListener('change', bcsLoadRoster);
    document.getElementById('bcsSaveAttendanceBtn')?.addEventListener('click', saveBcsAttendance);
}

document.addEventListener('DOMContentLoaded', loadBcsAttendanceData);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
