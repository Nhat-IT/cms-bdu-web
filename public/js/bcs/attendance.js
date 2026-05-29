const bcsAttendanceState = {
    groups: [],
    subjectMap: new Map(),
    selectedGroupId: 0,
    _initialized: false
};

let _autoSaveTimer = null;

function bcsShowSaveStatus(state) {
    const el = document.getElementById('autoSaveStatus');
    if (!el) return;
    if (state === 'saving') {
        el.innerHTML = '<span class="text-warning"><i class="bi bi-arrow-repeat bcs-spin me-1"></i>Đang lưu...</span>';
    } else if (state === 'saved') {
        el.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Đã lưu</span>';
        setTimeout(() => { if (el) el.innerHTML = ''; }, 2500);
    } else if (state === 'error') {
        el.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Lỗi lưu — thử lại</span>';
    } else {
        el.innerHTML = '';
    }
}

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

function bcsNormalizeDayOfWeek(value) {
    const map = { '2': 'Thứ 2', '3': 'Thứ 3', '4': 'Thứ 4', '5': 'Thứ 5', '6': 'Thứ 6', '7': 'Thứ 7', '8': 'CN', 'Monday': 'Thứ 2', 'Tuesday': 'Thứ 3', 'Wednesday': 'Thứ 4', 'Thursday': 'Thứ 5', 'Friday': 'Thứ 6', 'Saturday': 'Thứ 7', 'Sunday': 'CN' };
    return map[String(value || '').trim()] || '';
}

function bcsBuildSubjectKey(group) {
    return String(group.class_subject_id || '');
}

function bcsGetGroupLabel(group) {
    const raw = String(group.group_code || '').trim();
    if (!raw) return 'Nhóm';
    const match = raw.match(/N\s*(\d+)/i);
    return match ? `Nhóm ${match[1]}` : `Nhóm ${raw}`;
}

function bcsClearSubjectInfo() {
    const lblTeacher = document.getElementById('lblTeacher');
    const lblTime = document.getElementById('lblTime');
    const lblRoom = document.getElementById('lblRoom');
    if (lblTeacher) lblTeacher.textContent = '...';
    if (lblTime) lblTime.textContent = '...';
    if (lblRoom) lblRoom.textContent = '...';
}

function bcsSetAttendanceAvailability(_canTakeAttendance) {
    // Auto-save mode: no manual save button to enable/disable
}

function bcsRenderRoster(students) {
    const tbody = document.getElementById('studentTableBody');
    if (!tbody) {
        console.error('[BCS] studentTableBody not found');
        return;
    }

    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Chưa có sinh viên đăng ký nhóm này.</td></tr>';
        document.getElementById('totalCount').textContent = '0';
        document.getElementById('presentCount').textContent = '0';
        document.getElementById('absentCount').textContent = '0';
        bcsSetAttendanceAvailability(false);
        return;
    }

    tbody.innerHTML = students.map((sv, idx) => {
        const birth = sv.birth_date ? bcsFormatDate(sv.birth_date) : '';
        const rawBirth = sv.birth_date ? String(sv.birth_date).slice(0, 10) : '';
        const statusValue = Number(sv.status);
        const rowClass = statusValue === 2
            ? 'bg-warning bg-opacity-10'
            : (statusValue === 3 ? 'bg-danger bg-opacity-10' : '');
        const isUnexcused = statusValue === 3;
        const savedNote = (sv.note || '').replace(/"/g, '&quot;');
        const registrationId = Number(sv.registration_id || 0);
        const escAttr = v => String(v || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');

        return `
            <tr class="student-row ${rowClass}"
                data-student-id="${sv.student_id}"
                data-registration-id="${registrationId}"
                data-mssv="${escAttr(sv.username)}"
                data-full-name="${escAttr(sv.full_name)}"
                data-birth="${rawBirth}"
                data-class="${escAttr(sv.class_name)}">
                <td class="text-center stt-col text-muted">${idx + 1}</td>
                <td class="fw-bold text-dark mssv-cell">${sv.username || ''}</td>
                <td class="fw-bold ${isUnexcused ? 'text-danger' : 'text-dark'} student-name name-cell">
                    ${sv.full_name || ''}
                    <button class="btn btn-link p-0 ms-1 edit-sv-btn" title="Chỉnh sửa thông tin sinh viên" onclick="window.openEditStudentModal(this)">
                        <i class="bi bi-pencil-fill" style="font-size:0.7rem;color:#0d6efd;"></i>
                    </button>
                </td>
                <td>${birth}</td>
                <td>${sv.class_name || ''}</td>
                <td>
                    <select class="form-select form-select-sm status-select" onchange="window.updateColor(this)">
                        <option value="1" ${statusValue === 1 ? 'selected' : ''}>Có mặt</option>
                        <option value="2" ${statusValue === 2 ? 'selected' : ''}>Vắng có phép</option>
                        <option value="3" ${statusValue === 3 ? 'selected' : ''}>Vắng không phép</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0 note-input" placeholder="Ghi chú..." value="${savedNote}"></td>
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

    const isFirstLoad = !bcsAttendanceState._initialized;

    if (currentSubject && bcsAttendanceState.subjectMap.has(currentSubject)) {
        subjectSelect.value = currentSubject;
    } else if (isFirstLoad && subjectSelect.options.length > 1) {
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
    bcsAttendanceState._initialized = true;
}

async function bcsLoadRoster() {
    if (!bcsAttendanceState.selectedGroupId) {
        bcsRenderRoster([]);
        bcsSetAttendanceAvailability(false);
        return;
    }

    const date = document.getElementById('attendanceDate')?.value;
    if (!date) return;

    const url = bcsToUrl(`/api/bcs/attendance/roster?groupId=${bcsAttendanceState.selectedGroupId}&date=${encodeURIComponent(date)}`);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });

    if (res.status === 401) {
        window.location.href = bcsToUrl('/login.php');
        return;
    }

    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        const text = await res.text().catch(() => '');
        console.error('[BCS] roster API returned non-JSON:', res.status, text.substring(0, 200));
        bcsRenderRoster([]);
        return;
    }
    const data = await res.json();

    if (!res.ok) {
        console.error('[BCS] roster API error', res.status, data);
        alert(data.error || 'Không thể tải danh sách điểm danh.');
        bcsRenderRoster([]);
        return;
    }

    const students = Array.isArray(data.students) ? data.students : [];
    bcsRenderRoster(students);

    if (data.studySession) {
        const sessionSelect = document.getElementById('attendanceSession');
        if (sessionSelect && Array.from(sessionSelect.options).some(o => o.value === data.studySession)) {
            sessionSelect.value = data.studySession;
        }
    }
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
        .map((g) => `<option value="${g.group_id}">${bcsGetGroupLabel(g)}</option>`)
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
        const dayLabel = bcsNormalizeDayOfWeek(group.day_of_week);
        const dateRange = `${bcsFormatDate(group.start_date)} - ${bcsFormatDate(group.end_date)}`;
        const timeDisplay = dayLabel ? `${dayLabel} | ${dateRange}` : dateRange;

        if (lblTeacher) lblTeacher.textContent = group.teacher_name || 'Chưa cập nhật';
        if (lblTime) lblTime.textContent = timeDisplay;
        if (lblRoom) lblRoom.textContent = roomDisplay;
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
    clearTimeout(_autoSaveTimer);
    _autoSaveTimer = setTimeout(saveBcsAttendanceSilent, 400);
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
        const name = (row.getAttribute('data-full-name') || '').toLowerCase();
        row.style.display = (!keyword || mssv.includes(keyword) || name.includes(keyword)) ? '' : 'none';
    });
    window.recalculateAttendance();
};

window.checkSessionReason = function () {
    const sessionDiv = document.getElementById('sessionReasonDiv');
    if (sessionDiv) sessionDiv.classList.add('invisible');
};

window.addStudentToTable = async function () {
    const groupId = bcsAttendanceState.selectedGroupId;
    if (!groupId) {
        document.getElementById('addStudentError').textContent = 'Vui lòng chọn nhóm trước khi thêm sinh viên.';
        document.getElementById('addStudentError').classList.remove('d-none');
        return;
    }

    const mssv      = (document.getElementById('newMssv')?.value     || '').trim();
    const fullName  = (document.getElementById('newFullName')?.value  || '').trim();
    const birthDate = (document.getElementById('newDob')?.value       || '');
    const className = (document.getElementById('newClass')?.value     || '').trim();

    const errEl = document.getElementById('addStudentError');
    errEl?.classList.add('d-none');

    if (!mssv || !fullName || !className) {
        if (errEl) { errEl.textContent = 'Vui lòng điền đầy đủ MSSV, Họ tên và Lớp.'; errEl.classList.remove('d-none'); }
        return;
    }

    const addBtn = document.querySelector('#addStudentModal .modal-footer .btn-primary');
    if (addBtn) { addBtn.disabled = true; addBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang thêm...'; }

    try {
        const res = await fetch(bcsToUrl('/api/bcs/attendance/add-student'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ groupId, mssv, fullName, birthDate, className })
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            if (errEl) { errEl.textContent = data.error || 'Không thể thêm sinh viên.'; errEl.classList.remove('d-none'); }
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('addStudentModal'))?.hide();
        document.getElementById('addStudentForm')?.reset();
        const classInput = document.getElementById('newClass');
        if (classInput) classInput.value = window.CLASS_NAME || '';
        bcsLoadRoster();

    } catch (e) {
        if (errEl) { errEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.'; errEl.classList.remove('d-none'); }
    } finally {
        if (addBtn) { addBtn.disabled = false; addBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm vào danh sách'; }
    }
};

window.exportToExcel = async function () {
    const subject   = document.getElementById('subjectSelect')?.selectedOptions?.[0]?.textContent?.trim() || 'Môn học';
    const group     = document.getElementById('groupSelect')?.selectedOptions?.[0]?.textContent?.trim()   || 'Nhóm';
    const dateValue = document.getElementById('attendanceDate')?.value || new Date().toISOString().slice(0, 10);
    const session   = document.getElementById('attendanceSession')?.value || '';
    const className = window.CLASS_NAME || '';

    const rows = Array.from(document.querySelectorAll('#studentTableBody tr.student-row'));
    if (!rows.length) {
        alert('Không có dữ liệu để xuất.');
        return;
    }

    const dp = dateValue.split('-');
    const dateDisp = dp.length === 3 ? `${dp[2]}/${dp[1]}/${dp[0]}` : dateValue;

    const data = rows.map((row, i) => ({
        stt:       i + 1,
        mssv:      row.querySelector('.mssv-cell')?.textContent?.trim() || '',
        name:      (row.getAttribute('data-full-name') || '').trim(),
        dob:       row.children[3]?.textContent?.trim() || '',
        cls:       row.children[4]?.textContent?.trim() || '',
        statusVal: Number(row.querySelector('.status-select')?.value || 1),
        statusTxt: row.querySelector('.status-select')?.selectedOptions?.[0]?.textContent?.trim() || '',
        note:      row.querySelector('.note-input')?.value?.trim() || ''
    }));

    if (typeof ExcelJS === 'undefined') {
        alert('Thư viện xuất Excel chưa tải. Vui lòng kiểm tra kết nối internet và thử lại.');
        return;
    }

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Điểm Danh');

    ws.columns = [
        { width: 6  },
        { width: 14 },
        { width: 28 },
        { width: 13 },
        { width: 11 },
        { width: 16 },
        { width: 25 },
    ];

    const TNR = (size, bold = false, italic = false, argb = null) => {
        const f = { name: 'Times New Roman', size };
        if (bold)   f.bold   = true;
        if (italic) f.italic = true;
        if (argb)   f.color  = { argb };
        return f;
    };
    const BORDER_H = { top: { style:'thin', color:{argb:'FFFFFFFF'} }, bottom: { style:'thin', color:{argb:'FFFFFFFF'} }, left: { style:'thin', color:{argb:'FFFFFFFF'} }, right: { style:'thin', color:{argb:'FFFFFFFF'} } };
    const BORDER_D = { top: { style:'thin', color:{argb:'FFd1d5db'} }, bottom: { style:'thin', color:{argb:'FFd1d5db'} }, left: { style:'thin', color:{argb:'FFd1d5db'} }, right: { style:'thin', color:{argb:'FFd1d5db'} } };

    let r = 1;

    // Tiêu đề
    ws.mergeCells(`A${r}:G${r}`);
    const titleC = ws.getCell(`A${r}`);
    titleC.value     = 'BẢNG ĐIỂM DANH';
    titleC.font      = TNR(14, true);
    titleC.alignment = { horizontal: 'center', vertical: 'middle' };
    ws.getRow(r++).height = 30;

    // Thông tin
    ws.mergeCells(`A${r}:G${r}`);
    const infoC = ws.getCell(`A${r}`);
    infoC.value     = `Môn học: ${subject} | ${group} | Ngày: ${dateDisp}${session ? ' | Buổi: ' + session : ''}`;
    infoC.font      = TNR(12, false, true);
    infoC.alignment = { vertical: 'middle' };
    ws.getRow(r++).height = 18;

    // Lớp
    if (className) {
        ws.mergeCells(`A${r}:G${r}`);
        const clsC = ws.getCell(`A${r}`);
        clsC.value     = `Lớp: ${className}`;
        clsC.font      = TNR(12, false, true);
        clsC.alignment = { vertical: 'middle' };
        ws.getRow(r++).height = 18;
    }

    // Khoảng trống
    ws.getRow(r++).height = 6;

    // Header
    const headers = ['STT', 'MSSV', 'HỌ VÀ TÊN', 'NGÀY SINH', 'LỚP', 'TRẠNG THÁI', 'GHI CHÚ'];
    const hRow = ws.getRow(r++);
    hRow.height = 28;
    headers.forEach((val, i) => {
        const c = hRow.getCell(i + 1);
        c.value     = val;
        c.font      = TNR(12, true, false, 'FFFFFFFF');
        c.fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1a56db' } };
        c.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        c.border    = BORDER_H;
    });

    // Dữ liệu
    const STATUS = {
        1: { bg: 'FFdcfce7', text: 'FF166534' },
        2: { bg: 'FFfefce8', text: 'FF854d0e' },
        3: { bg: 'FFfee2e2', text: 'FF991b1b' }
    };
    const CENTER_COLS = new Set([1, 4, 5, 6]);

    data.forEach(sv => {
        const row = ws.addRow([sv.stt, sv.mssv, sv.name, sv.dob, sv.cls, sv.statusTxt, sv.note]);
        row.height = 20;
        const sc = STATUS[sv.statusVal] || STATUS[1];
        row.eachCell({ includeEmpty: true }, (cell, col) => {
            cell.font      = TNR(12);
            cell.alignment = { vertical: 'middle', horizontal: CENTER_COLS.has(col) ? 'center' : 'left' };
            cell.border    = BORDER_D;
            if (col === 6) {
                cell.font = TNR(12, true, false, sc.text);
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: sc.bg } };
            }
        });
    });

    const buffer = await wb.xlsx.writeBuffer();
    const blob   = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url    = URL.createObjectURL(blob);
    const a      = document.createElement('a');
    const groupNum = (group.match(/\d+/) || [''])[0];
    a.href     = url;
    a.download = `diemdanh_${dateValue}_nhom${groupNum || group.replace(/\s+/g, '_')}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
};

async function saveBcsAttendanceSilent() {
    const date = document.getElementById('attendanceDate')?.value;
    if (!bcsAttendanceState.selectedGroupId || !date) return;

    const records = Array.from(document.querySelectorAll('#studentTableBody tr.student-row')).map((row) => ({
        studentId: Number(row.getAttribute('data-student-id')),
        registrationId: Number(row.getAttribute('data-registration-id') || 0),
        status: bcsParseStatus(row.querySelector('.status-select')?.value),
        note: (row.querySelector('.note-input')?.value || '').trim()
    }));

    if (!records.length) return;

    bcsShowSaveStatus('saving');
    try {
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
        bcsShowSaveStatus(res.ok ? 'saved' : 'error');
    } catch (e) {
        bcsShowSaveStatus('error');
    }
}

async function saveBcsAttendance() {
    const date = document.getElementById('attendanceDate')?.value;
    if (!bcsAttendanceState.selectedGroupId || !date) {
        alert('Vui lòng chọn nhóm và ngày học trước khi lưu.');
        return;
    }

    const records = Array.from(document.querySelectorAll('#studentTableBody tr.student-row')).map((row) => ({
        studentId: Number(row.getAttribute('data-student-id')),
        registrationId: Number(row.getAttribute('data-registration-id') || 0),
        status: bcsParseStatus(row.querySelector('.status-select')?.value),
        note: (row.querySelector('.note-input')?.value || '').trim()
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
    bcsLoadRoster();
}

window.saveAttendance = saveBcsAttendance;

async function loadBcsAttendanceData() {
    const res = await fetch(bcsToUrl('/api/bcs/groups'), { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = bcsToUrl('/login.php');
        return;
    }
    if (!res.ok) {
        const errText = await res.text();
        console.error('[BCS Attendance] groups API error:', res.status, errText);
        return;
    }

    const groups = await res.json();
    if (!Array.isArray(groups)) {
        console.error('[BCS Attendance] groups API returned non-array:', groups);
        return;
    }
    bcsAttendanceState.groups = groups;

    const semesterSelect = document.getElementById('filterSemester');
    const dateInput = document.getElementById('attendanceDate');

    bcsApplyFilters();
    bcsSetAttendanceAvailability(false);

    semesterSelect?.addEventListener('change', bcsApplyFilters);

    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
    dateInput?.addEventListener('change', bcsLoadRoster);

    document.getElementById('addStudentModal')?.addEventListener('show.bs.modal', function () {
        document.getElementById('addStudentError')?.classList.add('d-none');
    });
}

window.openEditStudentModal = function (btn) {
    const row = btn.closest('tr');
    const studentId = Number(row.getAttribute('data-student-id') || 0);

    document.getElementById('editRegistrationId').value = row.getAttribute('data-registration-id') || '';
    document.getElementById('editStudentIdLinked').value = studentId;
    document.getElementById('editMssv').value = row.getAttribute('data-mssv') || '';
    document.getElementById('editFullName').value = row.getAttribute('data-full-name') || '';
    document.getElementById('editDob').value = row.getAttribute('data-birth') || '';
    document.getElementById('editClass').value = row.getAttribute('data-class') || '';

    // MSSV của tài khoản có sẵn là tên đăng nhập — không cho sửa
    const mssvInput = document.getElementById('editMssv');
    mssvInput.readOnly = studentId > 0;
    mssvInput.classList.toggle('bg-light', studentId > 0);
    const mssvNote = document.getElementById('editMssvNote');
    if (mssvNote) mssvNote.style.display = studentId > 0 ? '' : 'none';

    document.getElementById('editStudentError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
};

window.saveEditStudent = async function () {
    const registrationId = Number(document.getElementById('editRegistrationId').value);
    const studentId      = Number(document.getElementById('editStudentIdLinked').value);
    const mssv      = (document.getElementById('editMssv').value      || '').trim();
    const fullName  = (document.getElementById('editFullName').value  || '').trim();
    const birthDate =  document.getElementById('editDob').value       || '';
    const className = (document.getElementById('editClass').value     || '').trim();

    const errEl = document.getElementById('editStudentError');
    errEl.classList.add('d-none');

    if (!mssv || !fullName || !className) {
        errEl.textContent = 'Vui lòng điền đầy đủ MSSV, Họ tên và Lớp.';
        errEl.classList.remove('d-none');
        return;
    }

    const saveBtn = document.getElementById('editStudentSaveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang lưu...';

    try {
        const res = await fetch(bcsToUrl('/api/bcs/attendance/update-student'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ registrationId, studentId, mssv, fullName, birthDate, className })
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            errEl.textContent = data.error || 'Không thể cập nhật thông tin.';
            errEl.classList.remove('d-none');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('editStudentModal'))?.hide();
        bcsLoadRoster();
    } catch (e) {
        errEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
        errEl.classList.remove('d-none');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Lưu thay đổi';
    }
};

document.addEventListener('DOMContentLoaded', loadBcsAttendanceData);
