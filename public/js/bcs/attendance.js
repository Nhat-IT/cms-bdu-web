const bcsAttendanceState = {
    groups: [],
    subjectMap: new Map(),
    selectedGroupId: 0
};

const bcsToUrl = window.cmsUrl || function (path) { return path; };

function bcsFormatDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) return raw;

    const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (ymd) {
        return `${ymd[3]}/${ymd[2]}/${ymd[1]}`;
    }

    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return raw;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function bcsParseStatus(value) {
    const n = Number(value);
    return [1, 2, 3].includes(n) ? n : 1;
}

function bcsNormalizeSemester(value) {
    const text = String(value || '').trim().toUpperCase();
    if (!text || text === 'ALL') return 'ALL';
    const m = text.match(/(?:HK)?\s*([123])$/);
    return m ? `HK${m[1]}` : text;
}

function bcsGetSessionByPeriods(startPeriod) {
    const start = Number(startPeriod);
    if (start >= 1 && start <= 5) return 'Sáng';
    if (start >= 6 && start <= 10) return 'Chiều';
    if (start >= 11 && start <= 14) return 'Tối';
    return '';
}

function bcsBuildSubjectKey(group) {
    return String(group.class_subject_id || '');
}

function bcsGetGroupLabel(groupCode) {
    const raw = String(groupCode || '').trim();
    if (!raw) return 'Nhóm';
    const match = raw.match(/N\s*(\d+)/i);
    if (match) return `Nhóm ${match[1]}`;
    return `Nhóm ${raw}`;
}

function bcsClearSubjectInfo() {
    const lblTeacher = document.getElementById('lblTeacher');
    const lblTime = document.getElementById('lblTime');
    const lblRoom = document.getElementById('lblRoom');
    if (lblTeacher) lblTeacher.textContent = '...';
    if (lblTime) lblTime.textContent = '...';
    if (lblRoom) lblRoom.textContent = '...';
}

function bcsSetAttendanceAvailability(canTakeAttendance) {
    const saveBtn = document.getElementById('bcsSaveAttendanceBtn');
    if (saveBtn) {
        saveBtn.disabled = !canTakeAttendance;
        saveBtn.title = canTakeAttendance ? '' : 'Chưa có dữ liệu điểm danh để lưu.';
    }
}

function bcsRenderRoster(students) {
    const tbody = document.getElementById('studentTableBody');
    if (!tbody) return;

    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu điểm danh.</td></tr>';
        document.getElementById('totalCount').textContent = '0';
        document.getElementById('presentCount').textContent = '0';
        document.getElementById('absentCount').textContent = '0';
        bcsSetAttendanceAvailability(false);
        return;
    }

    tbody.innerHTML = students.map((sv, idx) => {
        const birth = sv.birth_date ? bcsFormatDate(sv.birth_date) : '';
        const statusValue = Number(sv.status);
        const rowClass = statusValue === 2
            ? 'bg-warning bg-opacity-10'
            : (statusValue === 3 ? 'bg-danger bg-opacity-10' : '');
        const isUnexcused = statusValue === 3;

        return `
            <tr class="student-row ${rowClass}" data-student-id="${sv.student_id}">
                <td class="text-center stt-col text-muted">${idx + 1}</td>
                <td class="fw-bold text-dark mssv-cell">${sv.username || ''}</td>
                <td class="fw-bold ${isUnexcused ? 'text-danger' : 'text-dark'} student-name name-cell">${sv.full_name || ''}</td>
                <td>${birth}</td>
                <td>${sv.class_name || ''}</td>
                <td>
                    <select class="form-select form-select-sm status-select" onchange="window.updateColor(this)">
                        <option value="1" ${statusValue === 1 ? 'selected' : ''}>Có mặt</option>
                        <option value="2" ${statusValue === 2 ? 'selected' : ''}>Vắng có phép</option>
                        <option value="3" ${statusValue === 3 ? 'selected' : ''}>Vắng không phép</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..."></td>
            </tr>`;
    }).join('');

    bcsSetAttendanceAvailability(true);
    window.recalculateAttendance();
}

function bcsGetSelectedSemesterFilter() {
    const semesterSelect = document.getElementById('filterSemester');
    if (!semesterSelect) return { semester: 'ALL', year: 'all' };

    const semester = bcsNormalizeSemester(semesterSelect.value || 'all');
    const selectedYear = String(semesterSelect.selectedOptions?.[0]?.getAttribute('data-year') || 'all').trim();
    return {
        semester,
        year: selectedYear || 'all'
    };
}

function bcsFilterGroupsBySemester(groups) {
    const selected = bcsGetSelectedSemesterFilter();

    return groups.filter((g) => {
        if (selected.semester === 'ALL') return true;
        const groupSemester = bcsNormalizeSemester(g.semester_name || '');
        const groupYear = String(g.academic_year || '').trim();
        return groupSemester === selected.semester && groupYear === selected.year;
    });
}

function bcsPopulateSelectors(groups) {
    const subjectSelect = document.getElementById('subjectSelect');
    const groupSelect = document.getElementById('groupSelect');
    if (!subjectSelect || !groupSelect) return;

    bcsAttendanceState.subjectMap.clear();
    groups.forEach((g) => {
        const key = bcsBuildSubjectKey(g);
        if (!key) return;
        if (!bcsAttendanceState.subjectMap.has(key)) {
            const code = String(g.subject_code || '').trim();
            const name = String(g.subject_name || '').trim();
            bcsAttendanceState.subjectMap.set(key, {
                label: code ? `${code} - ${name}` : name,
                groups: []
            });
        }
        bcsAttendanceState.subjectMap.get(key).groups.push(g);
    });

    const currentSubject = subjectSelect.value;
    const options = ['<option value="">-- Chọn môn học --</option>'];
    Array.from(bcsAttendanceState.subjectMap.entries()).forEach(([key, value]) => {
        options.push(`<option value="${key}">${value.label}</option>`);
    });
    subjectSelect.innerHTML = options.join('');

    if (currentSubject && bcsAttendanceState.subjectMap.has(currentSubject)) {
        subjectSelect.value = currentSubject;
    } else if (subjectSelect.options.length > 1) {
        subjectSelect.value = subjectSelect.options[1].value;
    }

    if (!subjectSelect.value) {
        groupSelect.innerHTML = '<option value="">-- Chọn nhóm --</option>';
        bcsAttendanceState.selectedGroupId = 0;
        bcsClearSubjectInfo();
        bcsRenderRoster([]);
        bcsSetAttendanceAvailability(false);
        return;
    }

    window.onSubjectChange();
}

function bcsApplyFilters() {
    const filteredGroups = bcsFilterGroupsBySemester(bcsAttendanceState.groups);
    bcsPopulateSelectors(filteredGroups);
}

async function bcsLoadRoster() {
    if (!bcsAttendanceState.selectedGroupId) {
        bcsRenderRoster([]);
        bcsSetAttendanceAvailability(false);
        return;
    }

    const date = document.getElementById('attendanceDate')?.value;
    if (!date) return;

    const res = await fetch(bcsToUrl(`/api/bcs/attendance/roster?groupId=${bcsAttendanceState.selectedGroupId}&date=${encodeURIComponent(date)}`), {
        headers: { Accept: 'application/json' }
    });

    if (res.status === 401) {
        window.location.href = bcsToUrl('/login.php');
        return;
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Không thể tải danh sách điểm danh.');
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

    if (!groups.length) {
        groupSelect.innerHTML = '<option value="">-- Chọn nhóm --</option>';
        bcsAttendanceState.selectedGroupId = 0;
        bcsClearSubjectInfo();
        bcsRenderRoster([]);
        bcsSetAttendanceAvailability(false);
        return;
    }

    groupSelect.innerHTML = groups
        .map((g) => `<option value="${g.group_id}">${bcsGetGroupLabel(g.group_code)}</option>`)
        .join('');

    groupSelect.value = String(groups[0].group_id);
    window.onGroupChange();
};

window.onGroupChange = function () {
    const groupId = Number(document.getElementById('groupSelect')?.value || 0);
    bcsAttendanceState.selectedGroupId = groupId;

    const group = bcsAttendanceState.groups.find((g) => Number(g.group_id) === groupId);
    if (group) {
        const lblTeacher = document.getElementById('lblTeacher');
        const lblTime = document.getElementById('lblTime');
        const lblRoom = document.getElementById('lblRoom');
        const sessionSelect = document.getElementById('attendanceSession');

        const roomDisplay = group.room_name || group.room || 'N/A';
        const sessionByPeriod = bcsGetSessionByPeriods(group.start_period);
        const sessionValue = sessionByPeriod || String(group.study_session || '').trim();

        if (lblTeacher) lblTeacher.textContent = group.teacher_name || 'Chưa cập nhật';
        if (lblTime) {
            lblTime.textContent = `${bcsFormatDate(group.start_date)} - ${bcsFormatDate(group.end_date)}`;
        }
        if (lblRoom) {
            lblRoom.textContent = roomDisplay;
        }
        if (sessionSelect && sessionValue) {
            sessionSelect.value = sessionValue;
        }
    } else {
        bcsClearSubjectInfo();
        bcsSetAttendanceAvailability(false);
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
    alert('Bạn cần thêm sinh viên tại trang phân công lớp học.');
};

window.exportToExcel = function () {
    const subject = document.getElementById('subjectSelect')?.selectedOptions?.[0]?.textContent?.trim() || 'Môn học';
    const group = document.getElementById('groupSelect')?.selectedOptions?.[0]?.textContent?.trim() || 'Nhóm';
    const dateValue = document.getElementById('attendanceDate')?.value || new Date().toISOString().slice(0, 10);

    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    if (!rows.length) {
        alert('Không có dữ liệu để xuất.');
        return;
    }

    const csvRows = [
        ['MSSV', 'Họ tên', 'Ngày sinh', 'Lớp', 'Trạng thái'].join(','),
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
        alert('Vui lòng chọn nhóm và ngày học trước khi lưu.');
        return;
    }

    const records = Array.from(document.querySelectorAll('#studentTableBody tr.student-row')).map((row) => ({
        studentId: Number(row.getAttribute('data-student-id')),
        status: bcsParseStatus(row.querySelector('.status-select')?.value)
    }));

    if (!records.length) {
        alert('Chưa có dữ liệu điểm danh nên không thể lưu.');
        return;
    }

    const res = await fetch(bcsToUrl('/api/bcs/attendance/save'), {
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
        alert(data.error || 'Không thể lưu điểm danh.');
        return;
    }

    alert('Đã lưu dữ liệu điểm danh thành công.');
}

window.saveAttendance = saveBcsAttendance;

async function loadBcsAttendanceData() {
    const res = await fetch(bcsToUrl('/api/bcs/groups'), { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = bcsToUrl('/login.php');
        return;
    }
    if (!res.ok) {
        return;
    }

    const groups = await res.json();
    bcsAttendanceState.groups = Array.isArray(groups) ? groups : [];

    const semesterSelect = document.getElementById('filterSemester');
    const dateInput = document.getElementById('attendanceDate');

    bcsApplyFilters();
    bcsSetAttendanceAvailability(false);

    semesterSelect?.addEventListener('change', bcsApplyFilters);

    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
    dateInput?.addEventListener('change', bcsLoadRoster);
    document.getElementById('bcsSaveAttendanceBtn')?.addEventListener('click', saveBcsAttendance);
}

document.addEventListener('DOMContentLoaded', loadBcsAttendanceData);
