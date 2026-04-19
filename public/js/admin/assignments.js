document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
            }
        });
    }

    populatePeriodSelects();

    const singleStatus = document.getElementById('singleStatus');
    if (singleStatus) {
        singleStatus.addEventListener('change', toggleCancelReason);
    }

    initAssignmentEnhancements();

    toggleCancelReason();
});

const assignmentStudentsByClass = {
    '25TH01-CSDL': [
        { mssv: '2050001', name: 'Nguyễn Văn A', dob: '2005-01-01', email: 'a.nguyen@student.bdu.edu.vn', section: 'N1' },
        { mssv: '2050002', name: 'Trần Thị B', dob: '2005-02-02', email: 'b.tran@student.bdu.edu.vn', section: 'N2' }
    ]
};

const allAssignmentCourses = [
    {
        id: '25TH01-WEB',
        classCode: '25TH01',
        name: 'Lập trình Web nâng cao',
        year: '2025',
        semester: '1',
        isOpen: true,
        credits: 4,
        openWindow: '01/09/2025 - 31/12/2025',
        hasSchedule: false,
        groups: [
            { code: 'N1', teacherMain: 'GV01', teacherSub: 'GV04', day: '', start: '', end: '', room: '' },
            { code: 'N2', teacherMain: 'GV03', teacherSub: '', day: '5', start: 1, end: 5, room: 'A201' }
        ]
    },
    {
        id: '25TH01-CSDL',
        classCode: '25TH01',
        name: 'An ninh Cơ sở dữ liệu',
        year: '2025',
        semester: '1',
        isOpen: true,
        credits: 3,
        openWindow: '01/09/2025 - 31/12/2025',
        hasSchedule: true,
        groups: [
            { code: 'N1', teacherMain: 'GV01', teacherSub: '', day: '2', start: 6, end: 10, room: 'PLAB' },
            { code: 'N2', teacherMain: 'GV02', teacherSub: '', day: '2', start: 6, end: 10, room: 'PM3' }
        ]
    },
    {
        id: '25TH01-JAVA',
        classCode: '25TH01',
        name: 'Lập trình Java nâng cao',
        year: '2025',
        semester: '2',
        isOpen: false,
        credits: 3,
        openWindow: '15/01/2026 - 30/04/2026',
        hasSchedule: false,
        groups: [
            { code: 'N1', teacherMain: 'GV02', teacherSub: '', day: '3', start: 2, end: 5, room: 'A201' }
        ]
    }
];

const teachers = [
    { id: 'GV01', name: 'ThS. Dương Quang Sinh' },
    { id: 'GV02', name: 'ThS. Nguyễn Hồ Hải' },
    { id: 'GV03', name: 'TS. Lê Anh Tuấn' },
    { id: 'GV04', name: 'ThS. Hồ Ngọc Giàu' }
];

const days = [
    { value: '2', label: 'Thứ 2' },
    { value: '3', label: 'Thứ 3' },
    { value: '4', label: 'Thứ 4' },
    { value: '5', label: 'Thứ 5' },
    { value: '6', label: 'Thứ 6' },
    { value: '7', label: 'Thứ 7' }
];

let assignmentOfferingsFiltered = [];

let pendingAssignmentUploadClass = '';
let currentSessionCourse = null;
let currentSessionGroupCode = null;

function getTeacherName(teacherId) {
    const teacher = teachers.find(function (t) { return t.id === teacherId; });
    return teacher ? teacher.name : '—';
}

function getDayLabel(day) {
    const dayObj = days.find(function (d) { return d.value === day; });
    return dayObj ? dayObj.label : '—';
}

function getGroupLabel(groupCode) {
    const match = groupCode.match(/N(\d+)/);
    if (match) {
        return 'Nhóm ' + match[1];
    }
    return groupCode;
}

function initAssignmentEnhancements() {
    const filterYear = document.getElementById('assignFilterYear');
    const filterSemester = document.getElementById('assignFilterSemester');
    const filterStatus = document.getElementById('assignFilterOpenStatus');
    const uploadInput = document.getElementById('assignmentStudentUploadInput');

    if (!filterYear || !filterSemester || !filterStatus || !uploadInput) {
        return;
    }

    filterYear.addEventListener('change', applyAssignmentFilters);
    filterSemester.addEventListener('change', applyAssignmentFilters);
    filterStatus.addEventListener('change', applyAssignmentFilters);
    uploadInput.addEventListener('change', handleAssignmentUploadChange);

    applyAssignmentFilters();
}

function applyAssignmentFilters() {
    const filterYear = document.getElementById('assignFilterYear');
    const filterSemester = document.getElementById('assignFilterSemester');
    const filterStatus = document.getElementById('assignFilterOpenStatus');

    if (!filterYear || !filterSemester || !filterStatus) {
        return;
    }

    const year = filterYear.value;
    const semester = filterSemester.value;
    const status = filterStatus.value;

    assignmentOfferingsFiltered = allAssignmentCourses.filter(function (course) {
        const matchYear = year === 'all' || course.year === year;
        const matchSemester = semester === 'all' || course.semester === semester;
        const matchStatus = status === 'all' || (status === 'open' ? course.isOpen : !course.isOpen);
        return matchYear && matchSemester && matchStatus;
    });

    renderAssignmentOfferings();
    updateAssignmentDownloadButtons();
}

function renderAssignmentOfferings() {
    const container = document.getElementById('assignmentOfferingContainer');
    if (!container) return;
    
    container.innerHTML = '';

    if (assignmentOfferingsFiltered.length === 0) {
        container.innerHTML = '<div class="alert alert-light border">Không có môn học nào trong bộ lọc đã chọn.</div>';
        return;
    }

    assignmentOfferingsFiltered.forEach(function (course) {
        const card = document.createElement('div');
        card.className = 'offering-card';
        card.dataset.courseId = course.id;

        const head = document.createElement('div');
        head.className = 'offering-head';
        
        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = '<div class="offering-title">' + course.id + ' - ' + course.name + '</div>' +
            '<div class="offering-subtitle">' + course.credits + ' tín chỉ | Mã lớp: ' + course.classCode + '</div>';
        head.appendChild(titleDiv);
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'd-flex gap-2 flex-wrap';
        
        const btnUpload = document.createElement('button');
        btnUpload.className = 'btn btn-sm btn-outline-dark';
        btnUpload.innerHTML = '<i class="bi bi-upload me-1"></i>Tải lên SV';
        btnUpload.onclick = function () { uploadAssignmentStudentList(course.id); };
        
        const btnDownload = document.createElement('button');
        const hasStudents = assignmentStudentsByClass[course.id] && assignmentStudentsByClass[course.id].length > 0;
        btnDownload.className = 'btn btn-sm btn-outline-success' + (hasStudents ? '' : ' disabled');
        btnDownload.innerHTML = '<i class="bi bi-download me-1"></i>Tải xuống SV';
        if (hasStudents) {
            btnDownload.onclick = function () { downloadAssignmentStudentList(course.id); };
        } else {
            btnDownload.setAttribute('aria-disabled', 'true');
            btnDownload.setAttribute('title', 'Chưa có danh sách sinh viên');
        }
        
        const btnAdd = document.createElement('button');
        btnAdd.className = 'btn btn-sm btn-outline-primary';
        btnAdd.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm nhóm';
        btnAdd.onclick = function () { addGroupToClass(course.id, course.name); };
        
        actionsDiv.appendChild(btnUpload);
        actionsDiv.appendChild(btnDownload);
        actionsDiv.appendChild(btnAdd);
        
        head.appendChild(actionsDiv);
        
        card.appendChild(head);

        course.groups.forEach(function (group) {
            const row = document.createElement('div');
            row.className = 'section-row';
            const hasGroupSchedule = Boolean(group.day && group.start && group.end && group.room);
            
            const chipDiv = document.createElement('div');
            chipDiv.innerHTML = '<span class="section-chip">' + getGroupLabel(group.code) + '</span>';
            
            const teacherDiv = document.createElement('div');
            teacherDiv.innerHTML = '<div class="section-label">Giảng viên</div>' +
                '<div class="section-value">' + getTeacherName(group.teacherMain) +
                (group.teacherSub ? ' + ' + getTeacherName(group.teacherSub) : '') + '</div>';
            
            const scheduleDiv = document.createElement('div');
            scheduleDiv.innerHTML = '<div class="section-label">Lịch học</div>' +
                '<div class="section-value">' + (hasGroupSchedule ? (getDayLabel(group.day) + ' | Tiết ' + group.start + '-' + group.end) : 'Chưa xếp lịch') + '</div>';
            
            const roomDiv = document.createElement('div');
            roomDiv.innerHTML = '<div class="section-label">Phòng</div>' +
                '<div class="section-value">' + (hasGroupSchedule ? group.room : '--') + '</div>';
            
            const statusDiv = document.createElement('div');
            statusDiv.className = 'section-status';
            const statusText = course.isOpen ? 'Đang mở' : 'Đã đóng';
            statusDiv.innerHTML = '<div class="section-label">Trạng thái</div>' +
                '<div class="section-value">' + statusText + '</div>';
            
            const actionsRowDiv = document.createElement('div');
            actionsRowDiv.className = 'section-actions';
            
            const btnEdit = document.createElement('button');
            btnEdit.className = 'btn btn-sm ' + (hasGroupSchedule ? 'btn-outline-primary' : 'btn-primary') + (course.isOpen ? '' : ' disabled');
            btnEdit.innerHTML = '<i class="bi ' + (hasGroupSchedule ? 'bi-calendar3' : 'bi-calendar-plus') + ' me-1"></i>' + (hasGroupSchedule ? 'Quản lý lịch' : 'Xếp lịch ngay');
            if (course.isOpen) {
                btnEdit.onclick = function () {
                    if (hasGroupSchedule) {
                        openSessionManager(course.id, course.name, getTeacherName(group.teacherMain), group.code);
                        return;
                    }

                    openGroupScheduleModal(course.id, course.name, group.code, group.teacherMain, group.teacherSub, group.day, '', '', '');
                };
            } else {
                btnEdit.setAttribute('aria-disabled', 'true');
                btnEdit.setAttribute('title', 'Môn đã đóng');
            }
            
            actionsRowDiv.appendChild(btnEdit);
            
            row.appendChild(chipDiv);
            row.appendChild(teacherDiv);
            row.appendChild(scheduleDiv);
            row.appendChild(roomDiv);
            row.appendChild(statusDiv);
            row.appendChild(actionsRowDiv);
            
            card.appendChild(row);
        });

        const foot = document.createElement('div');
        foot.className = 'offering-foot';
        const scheduledGroups = course.groups.filter(function (group) {
            return Boolean(group.day && group.start && group.end && group.room);
        });
        let scheduleStatus = '<span class="badge bg-secondary">Chưa xếp lịch</span>';
        if (scheduledGroups.length === course.groups.length && course.groups.length > 0) {
            scheduleStatus = '<span class="badge bg-success">Đã xếp lịch</span>';
        } else if (scheduledGroups.length > 0) {
            const unscheduledGroups = course.groups.filter(function (group) {
                return !(group.day && group.start && group.end && group.room);
            }).map(function (group) {
                return getGroupLabel(group.code);
            });
            scheduleStatus = '<span class="badge bg-warning text-dark">Chưa xếp lịch nhóm ' + unscheduledGroups.join(', ') + '</span>';
        }
        foot.innerHTML = 'Số nhóm: ' + course.groups.length + ' | Thời gian mở: ' + course.openWindow + ' | ' + scheduleStatus;
        card.appendChild(foot);

        container.appendChild(card);
    });
}


function uploadAssignmentStudentList(courseId) {
    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    if (!course) {
        alert('Không tìm thấy môn học.');
        return;
    }

    if (!course.isOpen) {
        alert('Môn học đã đóng, không thể tải lên danh sách sinh viên.');
        return;
    }

    pendingAssignmentUploadClass = courseId;
    const uploadInput = document.getElementById('assignmentStudentUploadInput');
    uploadInput.value = '';
    uploadInput.click();
}

function parseAssignmentCsv(content) {
    const lines = content.replace(/\r/g, '').split('\n').filter(function (line) {
        return line.trim().length > 0;
    });
    if (lines.length < 2) {
        return [];
    }

    const parsed = [];
    for (let i = 1; i < lines.length; i += 1) {
        const cols = lines[i].split(',').map(function (item) { return item.trim(); });
        if (cols.length < 5) {
            continue;
        }

        const mssv = cols[1] || '';
        const name = cols[2] || '';
        const dob = cols[3] || '';
        const email = cols[4] || '';
        const section = cols[6] || cols[5] || 'N1';
        if (!mssv || !name) {
            continue;
        }

        parsed.push({ mssv: mssv, name: name, dob: dob, email: email, section: section });
    }

    return parsed;
}

function parseAssignmentXlsx(buffer) {
    if (typeof XLSX === 'undefined') {
        return [];
    }

    const workbook = XLSX.read(buffer, { type: 'array' });
    if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
        return [];
    }

    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });
    if (!rows || rows.length < 2) {
        return [];
    }

    const parsed = [];
    for (let i = 1; i < rows.length; i += 1) {
        const row = rows[i].map(function (cell) { return String(cell).trim(); });
        const mssv = row[1] || '';
        const name = row[2] || '';
        const dob = row[3] || '';
        const email = row[4] || '';
        const section = row[6] || row[5] || 'N1';

        if (!mssv || !name) {
            continue;
        }
        parsed.push({ mssv: mssv, name: name, dob: dob, email: email, section: section });
    }

    return parsed;
}

function handleAssignmentUploadChange(event) {
    const file = event.target.files && event.target.files[0];
    if (!file || !pendingAssignmentUploadClass) {
        return;
    }

    const fileName = file.name.toLowerCase();
    const isCsv = fileName.endsWith('.csv');
    const isExcel = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
    if (!isCsv && !isExcel) {
        alert('Chỉ hỗ trợ file CSV, XLSX hoặc XLS.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        let parsed = [];
        if (isCsv) {
            const text = typeof e.target.result === 'string' ? e.target.result : '';
            parsed = parseAssignmentCsv(text);
        } else {
            parsed = parseAssignmentXlsx(e.target.result);
        }

        if (parsed.length === 0) {
            alert('Không đọc được dữ liệu sinh viên. Vui lòng kiểm tra lại file.');
            return;
        }

        assignmentStudentsByClass[pendingAssignmentUploadClass] = parsed;
        alert('Đã tải lên ' + parsed.length + ' sinh viên cho ' + pendingAssignmentUploadClass + '.');
        pendingAssignmentUploadClass = '';
        updateAssignmentDownloadButtons();
    };

    reader.onerror = function () {
        alert('Không thể đọc file. Vui lòng thử lại.');
    };

    if (isCsv) {
        reader.readAsText(file, 'utf-8');
    } else {
        reader.readAsArrayBuffer(file);
    }
}

function updateAssignmentDownloadButtons() {
    // Download button state is now handled in renderAssignmentOfferings
}


function downloadAssignmentStudentList(courseId) {
    const rows = assignmentStudentsByClass[courseId] || [];
    if (rows.length === 0) {
        return;
    }

    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    const classCode = course ? course.classCode : courseId;

    const csv = [];
    csv.push(['STT', 'MSSV', 'Họ và tên', 'Ngày sinh', 'Email', 'Mã lớp HP', 'Nhóm học'].join(','));
    rows.forEach(function (student, index) {
        csv.push([
            String(index + 1),
            student.mssv,
            student.name,
            student.dob,
            student.email,
            classCode,
            student.section
        ].join(','));
    });

    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,%EF%BB%BF' + encodeURIComponent(csv.join('\n'));
    link.download = classCode + '_DanhSachSV.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function populatePeriodSelects() {
    const periodSelectIds = ['initStartPeriod', 'initEndPeriod', 'singleStart', 'singleEnd'];

    periodSelectIds.forEach(function (selectId) {
        const select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        const existingValues = Array.from(select.options).map(function (option) {
            return option.value;
        });

        for (let period = 1; period <= 14; period += 1) {
            const value = String(period);
            if (existingValues.includes(value)) {
                continue;
            }

            const option = document.createElement('option');
            option.value = value;
            option.textContent = 'Tiết ' + period;
            select.appendChild(option);
        }
    });
}

function openInitialScheduleModal(mode, classCode, subjectName, teacher = '', startDate = '', endDate = '', dayOfWeek = '', startPeriod = '', endPeriod = '', room = '', assistant = '', group = 'ALL') {
    document.getElementById('initClassCode').innerText = classCode;
    document.getElementById('initSubjectName').innerText = subjectName;
    const groupLabel = document.getElementById('initGroupLabel');
    if (groupLabel) {
        groupLabel.innerText = group === 'ALL' ? 'Toàn bộ nhóm' : getGroupLabel(group);
    }

    document.getElementById('initTeacher').value = teacher;
    const assistantSelect = document.getElementById('initAssistantTeacher');
    if (assistantSelect) {
        assistantSelect.value = assistant || '';
    }
    document.getElementById('initDayOfWeek').value = dayOfWeek;
    document.getElementById('initStartPeriod').value = startPeriod;
    document.getElementById('initEndPeriod').value = endPeriod;
    document.getElementById('initRoom').value = room;

    const modalHeader = document.getElementById('initModalHeader');
    const modalTitle = document.getElementById('initModalTitle');
    const submitBtn = document.getElementById('initSubmitBtn');
    const warningAlert = document.getElementById('editBulkWarning');
    const infoAlert = document.getElementById('initClassInfoAlert');

    if (mode === 'add') {
        modalHeader.className = 'modal-header bg-primary text-white border-bottom-0 pb-3';
        modalTitle.innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Thiết Lập Lịch Giảng Dạy Mới';
        submitBtn.className = 'btn btn-primary fw-bold px-4';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Lưu Lịch & Phát Sinh';
        warningAlert.classList.add('d-none');
        infoAlert.className = 'alert alert-primary bg-primary bg-opacity-10 border-0 mb-4';
    } else {
        modalHeader.className = 'modal-header bg-warning text-dark border-bottom-0 pb-3';
        modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh Sửa Lịch Giảng Dạy (Hàng Loạt)';
        submitBtn.className = 'btn btn-warning fw-bold px-4 text-dark shadow-sm';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Cập nhật toàn bộ các tuần';
        warningAlert.classList.remove('d-none');
        infoAlert.className = 'alert alert-warning bg-warning bg-opacity-10 border-0 mb-4';

        const sessionModal = bootstrap.Modal.getInstance(document.getElementById('sessionManagerModal'));
        if (sessionModal) {
            sessionModal.hide();
        }
    }

    new bootstrap.Modal(document.getElementById('initialScheduleModal')).show();
}

function openGroupScheduleModal(courseId, subjectName, groupCode, teacherId, assistantId, dayOfWeek, startPeriod, endPeriod, room) {
    openInitialScheduleModal('edit', courseId, subjectName, teacherId, '', '', dayOfWeek, startPeriod, endPeriod, room, assistantId, groupCode);
}

function handleInitialScheduleSubmit(event) {
    event.preventDefault();

    const start = parseInt(document.getElementById('initStartPeriod').value, 10);
    const end = parseInt(document.getElementById('initEndPeriod').value, 10);
    if (start >= end) {
        alert('⚠️ Lỗi: Tiết bắt đầu phải diễn ra TRƯỚC tiết kết thúc!');
        return false;
    }

    const btnText = document.getElementById('initSubmitBtn').innerText;
    if (btnText.includes('Cập nhật')) {
        alert('✅ Đã cập nhật thành công cho tất cả các buổi học chưa diễn ra của lớp này!');
    } else {
        alert('✅ Đã thiết lập lịch thành công và tự động phát sinh các buổi học vào Database!');
    }

    bootstrap.Modal.getInstance(document.getElementById('initialScheduleModal')).hide();
    return false;
}

function openSessionManager(classCode, subjectName, teacherName, groupCode = null) {
    const groupText = groupCode ? ' | ' + getGroupLabel(groupCode) : '';
    document.getElementById('lblClassInfo').innerText = classCode + ' | ' + subjectName + groupText + ' | GV: ' + teacherName;

    const course = allAssignmentCourses.find(function (item) { return item.id === classCode; });
    currentSessionCourse = course || null;
    currentSessionGroupCode = groupCode || null;
    renderSessionManagerRows(course, currentSessionGroupCode);

    const editWholeScheduleBtn = document.getElementById('btnEditWholeSchedule');
    if (editWholeScheduleBtn) {
        editWholeScheduleBtn.onclick = function () {
            if (course && course.groups.length > 0) {
                const firstGroup = course.groups[0];
                openInitialScheduleModal(
                    'edit',
                    course.id,
                    course.name,
                    firstGroup.teacherMain || teacherName || '',
                    '',
                    '',
                    firstGroup.day || '',
                    firstGroup.start ? String(firstGroup.start) : '',
                    firstGroup.end ? String(firstGroup.end) : '',
                    firstGroup.room || '',
                    firstGroup.teacherSub || '',
                    'ALL'
                );
                return;
            }

            openInitialScheduleModal('add', classCode, subjectName, teacherName || '', '', '', '', '', '', '', '', 'ALL');
        };
    }

    const addSingleSessionBtn = document.getElementById('btnAddSingleSession');
    if (addSingleSessionBtn) {
        addSingleSessionBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Thêm buổi học bù cho lớp này';
        addSingleSessionBtn.onclick = function () {
            openAddSingleSessionFromManager();
        };
    }

    new bootstrap.Modal(document.getElementById('sessionManagerModal')).show();
}

function openAddSingleSessionFromManager() {
    const course = currentSessionCourse;
    if (!course) {
        alert('Không xác định được lớp đang mở trong Quản lý lịch.');
        return;
    }

    let targetGroup = null;
    if (currentSessionGroupCode) {
        targetGroup = course.groups.find(function (group) { return group.code === currentSessionGroupCode; }) || null;
    }
    if (!targetGroup) {
        targetGroup = course.groups && course.groups.length > 0 ? course.groups[0] : null;
    }

    openEditSingleSession(
        'add',
        '',
        '',
        targetGroup && targetGroup.day ? targetGroup.day : '',
        targetGroup && targetGroup.start ? String(targetGroup.start) : '',
        targetGroup && targetGroup.end ? String(targetGroup.end) : '',
        targetGroup && targetGroup.room ? targetGroup.room : '',
        'normal',
        course.id,
        course.name,
        targetGroup && targetGroup.teacherMain ? targetGroup.teacherMain : '',
        targetGroup && targetGroup.code ? targetGroup.code : '01'
    );
}

function renderSessionManagerRows(course, groupCode = null) {
    const tbody = document.getElementById('sessionManagerTbody');
    if (!tbody) {
        return;
    }

    if (!course || !course.groups || course.groups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted py-4">Chưa có buổi học nào để hiển thị.</td></tr>';
        return;
    }

    const displayedGroups = groupCode
        ? course.groups.filter(function (group) { return group.code === groupCode; })
        : course.groups;

    if (displayedGroups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted py-4">Không tìm thấy nhóm cần quản lý.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    displayedGroups.forEach(function (group, index) {
        const hasGroupSchedule = Boolean(group.day && group.start && group.end && group.room);
        const row = document.createElement('tr');
        const dateLabel = hasGroupSchedule ? '02/03/2026' : '--';
        const teacherId = group.teacherMain || '';

        const dayCell = document.createElement('td');
        dayCell.textContent = hasGroupSchedule ? getDayLabel(group.day) : 'Chưa xếp';

        const actionCell = document.createElement('td');
        const editButton = document.createElement('button');
        editButton.className = 'btn btn-sm btn-light text-primary border';
        editButton.title = 'Sửa từng ngày';
        editButton.innerHTML = '<i class="bi bi-pencil-square"></i>';
        editButton.onclick = function () {
            openEditSingleSession(
                hasGroupSchedule ? 'edit' : 'add',
                hasGroupSchedule ? dateLabel : '',
                hasGroupSchedule ? '2026-03-02' : '',
                hasGroupSchedule ? group.day : '',
                hasGroupSchedule ? String(group.start) : '',
                hasGroupSchedule ? String(group.end) : '',
                hasGroupSchedule ? group.room : '',
                'normal',
                course.id,
                course.name,
                teacherId,
                group.code
            );
        };
        actionCell.appendChild(editButton);

        row.innerHTML = '';
        row.appendChild(createTextCell(String(index + 1), 'fw-bold'));
        row.appendChild(createTextCell(getGroupLabel(group.code), 'fw-bold text-primary'));
        row.appendChild(createTextCell(dateLabel, 'fw-bold text-dark'));
        row.appendChild(dayCell);
        row.appendChild(createTextCell(hasGroupSchedule ? ('Tiết ' + group.start + ' - ' + group.end) : '--', ''));
        row.appendChild(createTextCell(hasGroupSchedule ? group.room : '--', 'fw-bold text-danger'));
        row.appendChild(createStatusCell(course.isOpen ? 'Đang mở' : 'Đã đóng', course.isOpen ? 'status-normal' : 'bg-secondary'));
        row.appendChild(actionCell);
        tbody.appendChild(row);
    });
}

function createTextCell(text, className) {
    const cell = document.createElement('td');
    if (className) {
        cell.className = className;
    }
    cell.textContent = text;
    return cell;
}

function createStatusCell(text, badgeClass) {
    const cell = document.createElement('td');
    cell.innerHTML = '<span class="badge ' + badgeClass + '">' + text + '</span>';
    return cell;
}

function openEditSingleSession(mode, dateStr, dateVal, day, start, end, room, status, classCode, subjectName, teacherId, group = '01') {
    document.getElementById('qsSubjectInfo').innerText = subjectName;
    document.getElementById('qsClassCode').innerHTML = '<i class="bi bi-tags-fill me-1 text-muted"></i>Lớp: ' + classCode;
    document.getElementById('qsGroup').innerText = 'Nhóm: ' + getGroupLabel(group);
    document.getElementById('singleDate').value = dateVal;
    document.getElementById('singleStart').value = start;
    document.getElementById('singleEnd').value = end;

    const teacherSelect = document.getElementById('singleTeacher');
    for (let i = 0; i < teacherSelect.options.length; i++) {
        if (teacherSelect.options[i].value === teacherId) {
            teacherSelect.selectedIndex = i;
        }
    }

    const roomSelect = document.getElementById('singleRoom');
    for (let i = 0; i < roomSelect.options.length; i++) {
        if (roomSelect.options[i].value === room) {
            roomSelect.selectedIndex = i;
        }
    }

    document.getElementById('singleStatus').value = status;
    toggleCancelReason();

    const btnDelete = document.getElementById('btnDeleteSingle');
    if (mode === 'add') {
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Thêm buổi học bù / đột xuất';
        btnDelete.classList.add('d-none');
    } else {
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Sửa lịch ngày ' + dateStr;
        btnDelete.classList.remove('d-none');
    }

    new bootstrap.Modal(document.getElementById('singleSessionModal')).show();
}

function toggleCancelReason() {
    const status = document.getElementById('singleStatus').value;
    const reasonDiv = document.getElementById('cancelReasonDiv');
    if (status === 'canceled') {
        reasonDiv.classList.remove('d-none');
    } else {
        reasonDiv.classList.add('d-none');
    }
}

function saveSingleSession() {
    const start = parseInt(document.getElementById('singleStart').value, 10);
    const end = parseInt(document.getElementById('singleEnd').value, 10);
    if (start >= end) {
        alert('⚠️ Lỗi: Tiết bắt đầu phải nhỏ hơn Tiết kết thúc!');
        return;
    }

    const status = document.getElementById('singleStatus').value;
    if (status === 'canceled' && document.getElementById('cancelReason').value.trim() === '') {
        alert('⚠️ Vui lòng nhập lý do báo hủy để hệ thống gửi thông báo cho sinh viên!');
        document.getElementById('cancelReason').focus();
        return;
    }

    alert('✅ Đã cập nhật thông tin buổi học thành công!');
    bootstrap.Modal.getInstance(document.getElementById('singleSessionModal')).hide();
}

function deleteSingleSession() {
    if (confirm('⚠️ NGUY HIỂM: Bạn có chắc chắn muốn xóa hẳn buổi học này ra khỏi cơ sở dữ liệu không?\nHành động này không thể hoàn tác!')) {
        alert('✅ Đã xóa buổi học thành công!');
        bootstrap.Modal.getInstance(document.getElementById('singleSessionModal')).hide();
    }
}

function addGroupToClass(courseId, subjectName) {
    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    if (!course) {
        alert('Không tìm thấy môn học.');
        return;
    }

    if (!course.isOpen) {
        alert('Môn học đã đóng, không thể thêm nhóm mới.');
        return;
    }

    let nextNumber = 1;
    if (course.groups.length > 0) {
        const maxNumber = Math.max.apply(null, course.groups.map(function (group) {
            return parseInt(group.code.substring(1), 10);
        }));
        nextNumber = maxNumber + 1;
    }

    const newGroupCode = 'N' + nextNumber;

    const confirmMsg = 'Tạo nhóm mới: ' + getGroupLabel(newGroupCode) + ' cho ' + subjectName + '?';
    if (!confirm(confirmMsg)) {
        return;
    }

    course.groups.push({
        code: newGroupCode,
        teacherMain: '',
        teacherSub: '',
        day: '',
        start: '',
        end: '',
        room: ''
    });

    renderAssignmentOfferings();
}