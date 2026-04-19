<<<<<<< HEAD
        function goToAttendance(subjectKey, groupKey, date) {
            const triggerEl = document.querySelector('#attendance-tab');
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
            
            document.getElementById('subjectSelect').value = subjectKey;
            onSubjectChange(); 
            document.getElementById('groupSelect').value = groupKey;
            onGroupChange();   
            document.getElementById('attendanceDate').value = date;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        const mockDB = {
            "CSDL": {
                name: "An ninh Cơ sở dữ liệu",
                groups: {
                    "01": { 
                        start: "16/01/2026", end: "05/05/2026", session: "Sáng",
                        students: [
                            { mssv: "22050001", name: "Nguyễn Văn A", dob: "15/04/2004", cls: "25TH01", status: "1", note: "" },
                            { mssv: "22050022", name: "Trần Vũ Gia Huy", dob: "22/08/2004", cls: "25TH01", status: "2", note: "Đã nộp phép" },
                            { mssv: "22050089", name: "Phan Đình Luyến", dob: "10/11/2004", cls: "25TH01", status: "3", note: "" },
                            { mssv: "22050111", name: "Nguyễn Thị C", dob: "01/01/2004", cls: "25TH01", status: "1", note: "" }
                        ]
                    }
                }
            },
            "CTDL": {
                name: "Cấu trúc dữ liệu & Giải thuật",
                groups: {
                    "01": { 
                        start: "18/01/2026", end: "10/05/2026", session: "Chiều",
                        students: [
                            { mssv: "22050201", name: "Hoàng Thanh D", dob: "12/12/2004", cls: "24CNTT02", status: "1", note: "" },
                            { mssv: "22050333", name: "Lê Văn C", dob: "20/05/2004", cls: "24CNTT02", status: "3", note: "Nghỉ không phép" }
                        ]
                    }
                }
            }
        };

        let currentDefaultSession = "Sáng";

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

            // Cập nhật thời gian
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
                    <td class="text-center stt-col text-muted">${index + 1}</td>
                    <td class="fw-bold text-dark mssv-cell">${sv.mssv}</td>
                    <td class="fw-bold ${sv.status === '3' ? 'text-danger' : 'text-dark'} student-name name-cell">${sv.name}</td>
                    <td>${sv.dob}</td>
                    <td>${sv.cls}</td>
                    <td>
                        <select class="form-select form-select-sm status-select val-${sv.status}" onchange="updateColor(this)">
                            <option value="1" ${sv.status === '1' ? 'selected' : ''}>Có mặt</option>
                            <option value="2" ${sv.status === '2' ? 'selected' : ''}>Vắng có phép</option>
                            <option value="3" ${sv.status === '3' ? 'selected' : ''}>Vắng không phép</option>
                        </select>
                    </td>
                    <td>${badgeHtml}</td>
                `;
                tbody.appendChild(tr);
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
            recalculateAttendance();
        }

        function recalculateAttendance() {
            const selects = document.querySelectorAll('.status-select');
            let present = 0; let absent = 0;
            selects.forEach(select => { 
                const row = select.closest('tr');
                if (row.style.display !== "none") {
                    if(select.value === '1') present++; else absent++; 
                }
            });
            document.getElementById('totalCount').innerText = present + absent;
            document.getElementById('presentCount').innerText = present;
            document.getElementById('absentCount').innerText = absent;
        }

        function filterTable() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            const rows = document.querySelectorAll("#attendanceTable tbody .student-row");
            rows.forEach(row => {
                const mssv = row.querySelector(".mssv-cell").innerText.toLowerCase();
                const name = row.querySelector(".name-cell").innerText.toLowerCase();
                if(mssv.includes(input) || name.includes(input)) row.style.display = "";
                else row.style.display = "none";
            });
            recalculateAttendance();
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
                <td class="text-center stt-col text-muted">${rowCount}</td>
                <td class="fw-bold text-dark mssv-cell">${mssv}</td>
                <td class="fw-bold text-primary student-name name-cell">${name} <span class="badge bg-primary ms-1 small">Mới</span></td>
                <td>${formattedDob}</td>
                <td>${cls}</td>
                <td>
                    <select class="form-select form-select-sm status-select val-1" onchange="updateColor(this)">
                        <option value="1" selected>Có mặt</option>
                        <option value="2">Vắng có phép</option>
                        <option value="3">Vắng không phép</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..."></td>
            `;

            tbody.appendChild(tr);
            bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
            document.getElementById('addStudentForm').reset();
            
            document.getElementById("searchInput").value = "";
            filterTable();
        }

        function exportToExcel() {
            const subjName = document.getElementById('subjectSelect').options[document.getElementById('subjectSelect').selectedIndex].text;
            const group = document.getElementById('groupSelect').value;
            const date = document.getElementById('attendanceDate').value;
            
            alert(`📥 Đã xuất file Excel thành công!\n\nThông tin Header file:\n- Môn học: ${subjName}\n- Mã môn học: [Từ CSDL]\n- Nhóm: ${group}\n- Ngày học: ${date}\n- Cột: STT | MSSV | Họ và tên | Ngày sinh | Lớp | Trạng thái`);
        }
=======
const attendanceState = {
    groups: [],
    subjectMap: new Map(),
    selectedGroupId: 0,
    roster: []
};

function formatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function buildSubjectKey(group) {
    return String(group.class_subject_id);
}

function parseStatus(value) {
    const n = Number(value);
    return [1, 2, 3].includes(n) ? n : 1;
}

function renderSchedule(groups) {
    const tbody = document.getElementById('teacherScheduleBody');
    if (!tbody) return;

    if (!groups.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No teaching schedule found.</td></tr>';
        return;
    }

    const byDay = new Map();
    for (let i = 2; i <= 8; i += 1) byDay.set(i, []);
    groups.forEach((g) => {
        const day = Number(g.day_of_week || 2);
        if (!byDay.has(day)) byDay.set(day, []);
        byDay.get(day).push(g);
    });

    const cells = [];
    for (let day = 2; day <= 8; day += 1) {
        const items = byDay.get(day) || [];
        if (!items.length) {
            cells.push('<td></td>');
            continue;
        }

        const cards = items.map((g) => {
            const title = `${g.class_name} - ${g.subject_name}`;
            const period = `Period ${g.start_period || '?'}-${g.end_period || '?'}`;
            const room = g.room || 'N/A';
            const groupCode = g.group_code || 'N/A';
            const dateValue = document.getElementById('attendanceDate')?.value || new Date().toISOString().slice(0, 10);
            return `
                <div class="class-card bg-primary bg-opacity-10 border border-primary rounded p-2 text-start h-100 shadow-sm mb-2" onclick="window.goToAttendanceByGroup(${Number(g.group_id)}, '${dateValue}')">
                    <div class="fw-bold text-primary mb-1">${g.class_name}</div>
                    <div class="small fw-bold text-dark lh-sm mb-2">${g.subject_name} (Group ${groupCode})</div>
                    <div class="small text-muted mb-1"><i class="bi bi-clock me-1"></i>${period}</div>
                    <div class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i>${room}</div>
                </div>`;
        }).join('');

        cells.push(`<td class="bg-light">${cards}</td>`);
    }

    tbody.innerHTML = `<tr>${cells.join('')}</tr>`;
}

function populateSelectors(groups) {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    if (!subjectSelect || !groupSelect) return;

    attendanceState.subjectMap.clear();
    groups.forEach((g) => {
        const key = buildSubjectKey(g);
        if (!attendanceState.subjectMap.has(key)) {
            attendanceState.subjectMap.set(key, {
                label: `${g.subject_name} - ${g.class_name}`,
                groups: []
            });
        }
        attendanceState.subjectMap.get(key).groups.push(g);
    });

    subjectSelect.innerHTML = Array.from(attendanceState.subjectMap.entries()).map(([key, value]) => {
        return `<option value="${key}">${value.label}</option>`;
    }).join('');

    if (!subjectSelect.value && subjectSelect.options.length) {
        subjectSelect.value = subjectSelect.options[0].value;
    }

    window.onSubjectChange();
}

function renderRoster(students) {
    const tbody = document.getElementById('studentTableBody');
    if (!tbody) return;

    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No students found in this group.</td></tr>';
        document.getElementById('totalCount').textContent = '0';
        document.getElementById('presentCount').textContent = '0';
        document.getElementById('absentCount').textContent = '0';
        return;
    }

    tbody.innerHTML = students.map((sv, idx) => {
        const birth = sv.birth_date ? formatDate(sv.birth_date) : '';
        const isAbsent = Number(sv.status) !== 1;
        const rowClass = Number(sv.status) === 2 ? 'bg-warning bg-opacity-10' : (Number(sv.status) === 3 ? 'bg-danger bg-opacity-10' : '');
        return `
            <tr class="student-row ${rowClass}" data-student-id="${sv.student_id}">
                <td class="text-center stt-col text-muted">${idx + 1}</td>
                <td class="fw-bold text-dark mssv-cell">${sv.username || ''}</td>
                <td class="fw-bold ${isAbsent ? 'text-danger' : 'text-dark'} student-name name-cell">${sv.full_name || ''}</td>
                <td>${birth}</td>
                <td>${sv.class_name || ''}</td>
                <td>
                    <select class="form-select form-select-sm status-select" onchange="window.updateColor(this)">
                        <option value="1" ${Number(sv.status) === 1 ? 'selected' : ''}>Present</option>
                        <option value="2" ${Number(sv.status) === 2 ? 'selected' : ''}>Absent Excused</option>
                        <option value="3" ${Number(sv.status) === 3 ? 'selected' : ''}>Absent Unexcused</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Note..."></td>
            </tr>`;
    }).join('');

    window.recalculateAttendance();
}

async function loadRoster() {
    if (!attendanceState.selectedGroupId) return;
    const date = document.getElementById('attendanceDate')?.value;
    if (!date) return;

    const res = await fetch(`/api/teacher/attendance/roster?groupId=${attendanceState.selectedGroupId}&date=${encodeURIComponent(date)}`, {
        headers: { Accept: 'application/json' }
    });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot load attendance roster.');
        return;
    }

    attendanceState.roster = Array.isArray(data.students) ? data.students : [];
    renderRoster(attendanceState.roster);
}

window.onSubjectChange = function () {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    if (!subjectSelect || !groupSelect) return;

    const subjectKey = subjectSelect.value;
    const item = attendanceState.subjectMap.get(subjectKey);
    const groups = item ? item.groups : [];

    groupSelect.innerHTML = groups.map((g) => {
        return `<option value="${g.group_id}">Group ${g.group_code || ''}</option>`;
    }).join('');

    if (groups.length) {
        groupSelect.value = String(groups[0].group_id);
    }

    window.onGroupChange();
};

window.onGroupChange = function () {
    const groupId = Number(document.getElementById('groupSelect')?.value || 0);
    attendanceState.selectedGroupId = groupId;

    const group = attendanceState.groups.find((g) => Number(g.group_id) === groupId);
    if (group) {
        const lblTime = document.getElementById('lblTime');
        if (lblTime) {
            lblTime.textContent = `${formatDate(group.start_date)} - ${formatDate(group.end_date)} | Period ${group.start_period || '?'}-${group.end_period || '?'} | Room ${group.room || 'N/A'}`;
        }

        const sessionSelect = document.getElementById('attendanceSession');
        if (sessionSelect && group.study_session) {
            sessionSelect.value = group.study_session;
        }
    }

    loadRoster();
};

window.goToAttendanceByGroup = function (groupId, dateValue) {
    const triggerEl = document.querySelector('#attendance-tab');
    if (triggerEl) {
        const tab = new bootstrap.Tab(triggerEl);
        tab.show();
    }

    const matched = attendanceState.groups.find((g) => Number(g.group_id) === Number(groupId));
    if (!matched) return;

    const subjectKey = buildSubjectKey(matched);
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    const dateInput = document.getElementById('attendanceDate');

    if (subjectSelect) subjectSelect.value = subjectKey;
    window.onSubjectChange();
    if (groupSelect) groupSelect.value = String(groupId);
    if (dateInput && dateValue) dateInput.value = dateValue;
    window.onGroupChange();
};

window.updateColor = function (selectElement) {
    const tr = selectElement.closest('tr');
    if (!tr) return;
    tr.classList.remove('bg-warning', 'bg-danger', 'bg-opacity-10');
    tr.querySelector('.student-name')?.classList.remove('text-danger');
    tr.querySelector('.student-name')?.classList.add('text-dark');

    const status = parseStatus(selectElement.value);
    if (status === 2) {
        tr.classList.add('bg-warning', 'bg-opacity-10');
    }
    if (status === 3) {
        tr.classList.add('bg-danger', 'bg-opacity-10');
        tr.querySelector('.student-name')?.classList.remove('text-dark');
        tr.querySelector('.student-name')?.classList.add('text-danger');
    }

    window.recalculateAttendance();
};

window.recalculateAttendance = function () {
    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    let present = 0;
    let absent = 0;
    rows.forEach((row) => {
        if (row.style.display === 'none') return;
        const status = parseStatus(row.querySelector('.status-select')?.value);
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
    if (sessionDiv) {
        sessionDiv.classList.add('invisible');
    }
};

window.exportToExcel = function () {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    const dateValue = document.getElementById('attendanceDate')?.value || '';

    const subjectLabel = subjectSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Unknown Subject';
    const groupLabel = groupSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Group';
    const safeDate = dateValue || new Date().toISOString().slice(0, 10);

    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    if (!rows.length) {
        alert('No data to export.');
        return;
    }

    const csvRows = [
        ['MSSV', 'Ho ten', 'Ngay sinh', 'Lop', 'Trang thai', 'Ghi chu'].join(','),
        ...rows.map((row) => {
            const mssv = row.querySelector('.mssv-cell')?.textContent?.trim() || '';
            const name = row.querySelector('.name-cell')?.textContent?.trim() || '';
            const dob = row.children[3]?.textContent?.trim() || '';
            const cls = row.children[4]?.textContent?.trim() || '';
            const status = row.querySelector('.status-select')?.selectedOptions?.[0]?.textContent?.trim() || '';
            const note = row.querySelector('input[type="text"]')?.value?.trim() || '';

            const escapeCsv = (value) => `"${String(value).replace(/"/g, '""')}"`;
            return [mssv, name, dob, cls, status, note].map(escapeCsv).join(',');
        })
    ];

    const header = [
        `Subject,${JSON.stringify(subjectLabel)}`,
        `Group,${JSON.stringify(groupLabel)}`,
        `Date,${safeDate}`,
        ''
    ];

    const csvContent = `${header.join('\n')}\n${csvRows.join('\n')}`;
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const fileName = `attendance_${safeDate}_${attendanceState.selectedGroupId || 'group'}.csv`;

    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

window.addStudentToTable = async function () {
    if (!attendanceState.selectedGroupId) {
        alert('Please select a group first.');
        return;
    }

    const username = (document.getElementById('newMssv')?.value || '').trim();
    const className = (document.getElementById('newClass')?.value || '').trim();
    if (!username || !className) {
        alert('Please enter MSSV and class.');
        return;
    }

    const res = await fetch(`/api/teacher/groups/${attendanceState.selectedGroupId}/students`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            username,
            className,
            fullName: document.getElementById('newFullName')?.value || '',
            birthDate: document.getElementById('newDob')?.value || null
        })
    });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot add student to group.');
        return;
    }

    await loadRoster();

    const modalEl = document.getElementById('addStudentModal');
    const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
    modal?.hide();
    document.getElementById('addStudentForm')?.reset();
    alert('Student has been added to this group.');
};

async function saveAttendance() {
    const date = document.getElementById('attendanceDate')?.value;
    if (!attendanceState.selectedGroupId || !date) {
        alert('Please select group and date first.');
        return;
    }

    const records = Array.from(document.querySelectorAll('#studentTableBody tr.student-row')).map((row) => {
        return {
            studentId: Number(row.getAttribute('data-student-id')),
            status: parseStatus(row.querySelector('.status-select')?.value)
        };
    });

    const res = await fetch('/api/teacher/attendance/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            groupId: attendanceState.selectedGroupId,
            attendanceDate: date,
            session: document.getElementById('attendanceSession')?.value || '',
            records
        })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot save attendance.');
        return;
    }

    alert('Attendance saved successfully.');
}

async function loadTeacherAttendanceData() {
    const res = await fetch('/api/teacher/groups', { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (res.status === 403) {
        return;
    }
    if (!res.ok) {
        alert('Cannot load teacher groups.');
        return;
    }

    const groups = await res.json();
    attendanceState.groups = Array.isArray(groups) ? groups : [];
    renderSchedule(attendanceState.groups);
    populateSelectors(attendanceState.groups);

    const dateInput = document.getElementById('attendanceDate');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }

    dateInput?.addEventListener('change', loadRoster);
    document.getElementById('teacherSaveAttendanceBtn')?.addEventListener('click', saveAttendance);
}

document.addEventListener('DOMContentLoaded', loadTeacherAttendanceData);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
