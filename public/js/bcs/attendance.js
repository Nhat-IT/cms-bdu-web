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
