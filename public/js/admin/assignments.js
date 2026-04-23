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

    if (window.allAssignmentCourses) {
        allAssignmentCourses.push(...window.allAssignmentCourses);
    }
    if (window.teachers) {
        teachers.push(...window.teachers);
    }
    
    window.showToast = function(message, type) {
        type = type || 'info';
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(container);
        }
        let colors = { success: '#198754', error: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
        let icons  = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
        let toast  = document.createElement('div');
        toast.style.cssText = 'background:#fff;border-left:4px solid ' + (colors[type]||colors.info) + ';box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:8px;padding:12px 16px;display:flex;align-items:flex-start;gap:10px;max-width:360px;font-size:0.9rem;';
        toast.innerHTML = '<i class="bi ' + (icons[type]||icons.info) + '" style="color:' + (colors[type]||colors.info) + ';font-size:1.1rem;margin-top:1px;flex-shrink:0"></i><span style="flex:1;white-space:pre-wrap">' + message + '</span>';
        container.appendChild(toast);
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 4000);
    };

    window.callApi = function(action, params) {
        let fd = new FormData();
        fd.append('action', action);
        fd.append('format', 'json');
        Object.keys(params).forEach(function(k) { fd.append(k, params[k]); });
        return fetch('/cms/controllers/admin/classSubjectController.php', {
            method: 'POST',
            body: fd
        }).then(function(res) { return res.json(); });
    };

    initAssignmentEnhancements();

    toggleCancelReason();
});

const assignmentStudentsByClass = {};

const allAssignmentCourses = [];

const teachers = [];

const days = [
    { value: '2', label: 'Thứ 2' },
    { value: '3', label: 'Thứ 3' },
    { value: '4', label: 'Thứ 4' },
    { value: '5', label: 'Thứ 5' },
    { value: '6', label: 'Thứ 6' },
    { value: '7', label: 'Thứ 7' },
    { value: '8', label: 'Chủ Nhật' }
];

let assignmentOfferingsFiltered = [];

let pendingAssignmentUploadClass = '';
let currentSessionCourse = null;
let currentSessionGroupCode = null;

function resolveClassSubjectId(asg) {
    const direct = parseInt(asg && asg.csId, 10);
    if (Number.isFinite(direct) && direct > 0) return direct;
    const idText = String((asg && asg.id) || '');
    const m = idText.match(/-(\d+)$/);
    return m ? parseInt(m[1], 10) : 0;
}

function getTeacherName(teacherId) {
    const list = window.teachers || teachers || [];
    const teacher = list.find(function (t) { return t.id === teacherId; });
    return teacher ? teacher.name : '—';
}

function getDayLabel(day) {
    const dayObj = days.find(function (d) { return d.value === day; });
    return dayObj ? dayObj.label : '—';
}

function getGroupLabel(groupCode) {
    if (!groupCode) return 'Nhóm 1';
    const match = String(groupCode).match(/N(\d+)/);
    if (match) return 'Nhóm ' + match[1];
    return String(groupCode);
}

function getGroupCode(groupCode) {
    if (!groupCode) return 'N1';
    const match = String(groupCode).match(/N(\d+)/i);
    if (match) return 'N' + match[1];
    return String(groupCode);
}

function getClassGroupLabel(classCode, groupCode) {
    const code = (classCode && String(classCode).trim()) ? String(classCode).trim() : '--';
    return code + ' - ' + getGroupCode(groupCode);
}

function initAssignmentEnhancements() {
    const filterYear = document.getElementById('assignFilterYear');
    const filterSemester = document.getElementById('assignFilterSemester');
    const filterStatus = document.getElementById('assignFilterOpenStatus');
    const searchInput = document.getElementById('searchAssignment');
    const uploadInput = document.getElementById('assignmentStudentUploadInput');

    if (!filterYear || !filterSemester || !filterStatus) {
        return;
    }

    filterYear.addEventListener('change', applyAssignmentFilters);
    filterSemester.addEventListener('change', applyAssignmentFilters);
    filterStatus.addEventListener('change', applyAssignmentFilters);
    if (searchInput) {
        searchInput.addEventListener('input', debounce(applyAssignmentFilters, 300));
    }
    if (uploadInput) {
        uploadInput.addEventListener('change', handleAssignmentUploadChange);
    }

    applyAssignmentFilters();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}



function applyAssignmentFilters() {
    const filterYear = document.getElementById('assignFilterYear');
    const filterSemester = document.getElementById('assignFilterSemester');
    const filterStatus = document.getElementById('assignFilterOpenStatus');
    const searchInput = document.getElementById('searchAssignment');

    if (!filterYear || !filterSemester || !filterStatus) {
        return;
    }

    const year = filterYear.value;
    const semester = filterSemester.value;
    const status = filterStatus.value;
    const search = searchInput ? searchInput.value.toLowerCase().trim() : '';

    // Flatten all assignments from all classes
    let flatAssignments = [];
    (window.allClasses || []).forEach(function(cls) {
        (cls.assignments || []).forEach(function(asg) {
            flatAssignments.push(Object.assign({}, asg, { classCode: cls.classCode, className: cls.name }));
        });
    });

    // Filter
    assignmentOfferingsFiltered = flatAssignments.filter(function(asg) {
        const matchYear = year === 'all' || asg.year === year;
        const matchSemester = semester === 'all' || asg.semester === semester;
        const matchStatus = status === 'all' || (status === 'open' ? asg.isOpen : !asg.isOpen);
        
        // Search filter
        let matchSearch = true;
        if (search) {
            const searchText = search;
            const classCode = (asg.classCode || '').toLowerCase();
            const subjectName = (asg.subjectName || '').toLowerCase();
            const subjectCode = (asg.subjectCode || '').toLowerCase();
            const teacherName = (asg.teacherMainName || '').toLowerCase();
            matchSearch = classCode.includes(searchText) || subjectName.includes(searchText) || 
                         subjectCode.includes(searchText) || teacherName.includes(searchText);
        }
        
        return matchYear && matchSemester && matchStatus && matchSearch;
    });

    renderAssignmentOfferingsTable();
}

function renderAssignmentOfferingsTable() {
    const container = document.getElementById('assignmentOfferingContainer');
    if (!container) return;
    container.innerHTML = '';

    if (assignmentOfferingsFiltered.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Không có dữ liệu phù hợp</div>';
        return;
    }

    assignmentOfferingsFiltered.forEach(function(asg) {
        const groups = (asg.groups && asg.groups.length) ? asg.groups : [{ code: 'N1' }];
        const firstUnscheduled = groups.find(function(g) { return !(g.day && g.start && g.end && g.room); });

        const rowsHtml = groups.map(function(g) {
            const mainTeacher = g.teacherMainName || getTeacherName(g.teacherMain) || '';
            const subTeacher = g.teacherSubName || getTeacherName(g.teacherSub) || '';
            const teacherDisplay = (mainTeacher && subTeacher) ? (mainTeacher + ' + ' + subTeacher) : (mainTeacher || subTeacher || '--');
            const teacherHtml = mainTeacher && subTeacher
                ? ('<span class="assignment-col-value assignment-teacher-main">' + mainTeacher + '</span><span class="assignment-teacher-sub">+ ' + subTeacher + '</span>')
                : ('<span class="assignment-col-value">' + teacherDisplay + '</span>');
            const hasSchedule = Boolean(g.day && g.start && g.end && g.room);
            const scheduleDisplay = hasSchedule
                ? (getDayLabel(String(g.day)) + ' | Tiết ' + g.start + '-' + g.end)
                : 'Chưa xếp lịch';
            const roomDisplay = hasSchedule ? String(g.room) : '--';
            let statusDisplay = 'Chưa xếp lịch';
            if (asg.computedStatus === '0') {
                statusDisplay = 'Đã đóng';
            } else if (hasSchedule) {
                statusDisplay = 'Đang mở';
            }
            const groupCode = g.code || 'N1';
            const manageBtn = '<button class="btn btn-outline-primary" onclick="openSessionManager(\'' + escapeHtml(asg.id) + '\', \'' + escapeHtml(asg.subjectName) + '\', \'' + escapeHtml(mainTeacher || teacherDisplay) + '\', \'' + escapeHtml(groupCode) + '\')"><i class="bi bi-calendar-week me-1"></i>Quản lý lịch</button>';
            const classSubjectId = resolveClassSubjectId(asg);
            const scheduleBtn = '<button class="btn btn-primary" onclick="openGroupScheduleModal(\'' + classSubjectId + '\', \'' + escapeHtml(asg.subjectName) + '\', \'' + escapeHtml(groupCode) + '\', \'' + escapeHtml(g.teacherMain || '') + '\', \'' + escapeHtml(g.teacherSub || '') + '\', \'' + escapeHtml(g.day || '') + '\', \'' + escapeHtml(g.start || '') + '\', \'' + escapeHtml(g.end || '') + '\', \'' + escapeHtml(g.room || '') + '\', \'' + escapeHtml(asg.classCode || '') + '\')"><i class="bi bi-plus-square me-1"></i>Xếp lịch ngay</button>';
            const actionButtons = (!hasSchedule && asg.isOpen) ? scheduleBtn : manageBtn;

            return '' +
                '<div class="assignment-group-row">' +
                    '<div><span class="assignment-group-pill">' + getClassGroupLabel(asg.classCode, groupCode) + '</span></div>' +
                    '<div>' + teacherHtml + '</div>' +
                    '<div><span class="assignment-col-value ' + (hasSchedule ? '' : 'text-muted-soft') + '">' + scheduleDisplay + '</span></div>' +
                    '<div><span class="assignment-col-value">' + roomDisplay + '</span></div>' +
                    '<div><span class="assignment-col-value">' + statusDisplay + '</span></div>' +
                    '<div class="assignment-action">' + actionButtons + '</div>' +
                '</div>';
        }).join('');

        const warningText = firstUnscheduled ? ('Chưa xếp lịch ' + getClassGroupLabel(asg.classCode, firstUnscheduled.code || 'N1')) : '';
        const actionDisabled = asg.isOpen ? '' : ' disabled';
        const card = document.createElement('div');
        card.className = 'assignment-offering-card';
        card.innerHTML = '' +
            '<div class="assignment-offering-head">' +
                '<div>' +
                    '<div class="assignment-offering-title">' + (asg.subjectCode || '--') + ' - ' + (asg.subjectName || '') + '</div>' +
                    '<div class="assignment-offering-subtitle">' + (asg.credits || 0) + ' tín chỉ | Năm học: ' + (asg.year || '--') + '</div>' +
                '</div>' +
                '<div class="assignment-head-actions">' +
                    '<button class="btn btn-outline-dark" onclick="uploadAssignmentStudentList(\'' + escapeHtml(asg.id) + '\')"' + actionDisabled + '><i class="bi bi-upload me-1"></i>Tải lên SV</button>' +
                    '<button class="btn btn-outline-success" onclick="downloadAssignmentStudentList(\'' + escapeHtml(asg.id) + '\')"><i class="bi bi-download me-1"></i>Tải xuống SV</button>' +
                    '<button class="btn btn-outline-primary" onclick="addGroupToClass(\'' + escapeHtml(asg.id) + '\', \'' + escapeHtml(asg.subjectName) + '\')"' + actionDisabled + '><i class="bi bi-plus-circle me-1"></i>Thêm nhóm</button>' +
                '</div>' +
            '</div>' +
            '<div class="assignment-grid-head">' +
                '<div class="assignment-grid-head-item">NHÓM</div>' +
                '<div class="assignment-grid-head-item">GIẢNG VIÊN</div>' +
                '<div class="assignment-grid-head-item">LỊCH HỌC</div>' +
                '<div class="assignment-grid-head-item">PHÒNG</div>' +
                '<div class="assignment-grid-head-item">TRẠNG THÁI</div>' +
                '<div class="assignment-grid-head-item assignment-grid-head-action">Hành động</div>' +
            '</div>' +
            rowsHtml +
            '<div class="assignment-offering-foot">' +
                '<span>Số nhóm: ' + groups.length + ' | Thời gian mở: ' + (asg.openWindow || '--') + '</span>' +
                (warningText ? '<span class="assignment-warning-chip">' + warningText + '</span>' : '') +
            '</div>';
        container.appendChild(card);
    });
}

function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function renderAssignmentOfferings() {
    renderAssignmentOfferingsTable();
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

function openInitialScheduleModal(mode, csId, classCode, subjectName, teacher = '', startDate = '', endDate = '', dayOfWeek = '', startPeriod = '', endPeriod = '', room = '', assistant = '', group = 'N1') {
    window._currentCsId = csId;
    const normalizedGroup = (group && group !== 'ALL') ? group : (currentSessionGroupCode || 'N1');
    window._currentGroupCode = normalizedGroup;

    const classCodeSelect = document.getElementById('initClassCode');
    if (classCodeSelect) {
        classCodeSelect.value = classCode;
    }
    
    document.getElementById('initSubjectName').innerText = subjectName;
    const groupLabel = document.getElementById('initGroupLabel');
    if (groupLabel) {
        groupLabel.innerText = getClassGroupLabel(classCode, normalizedGroup);
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
    const infoAlert = document.getElementById('initClassInfoAlert');

    if (mode === 'add') {
        modalHeader.className = 'modal-header bg-primary text-white border-bottom-0 pb-3';
        modalTitle.innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Thiết Lập Lịch Giảng Dạy Mới';
        submitBtn.className = 'btn btn-primary fw-bold px-4';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Lưu Lịch & Phát Sinh';
        infoAlert.className = 'alert alert-primary bg-primary bg-opacity-10 border-0 mb-4';
    } else {
        modalHeader.className = 'modal-header bg-warning text-dark border-bottom-0 pb-3';
        modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Xếp lịch môn ' + (subjectName || '');
        submitBtn.className = 'btn btn-warning fw-bold px-4 text-dark shadow-sm';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Cập nhật toàn bộ các tuần';
        infoAlert.className = 'alert alert-warning bg-warning bg-opacity-10 border-0 mb-4';

        const sessionModal = bootstrap.Modal.getInstance(document.getElementById('sessionManagerModal'));
        if (sessionModal) {
            sessionModal.hide();
        }
    }

    new bootstrap.Modal(document.getElementById('initialScheduleModal')).show();
}

function openGroupScheduleModal(csId, subjectName, groupCode, teacherId, assistantId, dayOfWeek, startPeriod, endPeriod, room, classCode) {
    const parsedCsId = parseInt(csId, 10);
    if (!Number.isFinite(parsedCsId) || parsedCsId <= 0) {
        if (window.showToast) window.showToast('Không xác định được lớp học phần để xếp lịch.', 'error');
        else alert('Không xác định được lớp học phần để xếp lịch.');
        return;
    }
    openInitialScheduleModal('edit', parsedCsId, classCode || parsedCsId, subjectName, teacherId, '', '', dayOfWeek, startPeriod, endPeriod, room, assistantId, groupCode);
}

function handleInitialScheduleSubmit(event) {
    event.preventDefault();

    const start = parseInt(document.getElementById('initStartPeriod').value, 10);
    const end = parseInt(document.getElementById('initEndPeriod').value, 10);
    if (start >= end) {
        if (window.showToast) window.showToast('Lỗi: Tiết bắt đầu phải diễn ra TRƯỚC tiết kết thúc!', 'error');
        else alert('Lỗi: Tiết bắt đầu phải diễn ra TRƯỚC tiết kết thúc!');
        return false;
    }

    const payload = {
        class_subject_id: window._currentCsId,
        group_code: window._currentGroupCode || 'N1',
        teacher_main_id: document.getElementById('initTeacher').value,
        teacher_sub_id: document.getElementById('initAssistantTeacher') ? document.getElementById('initAssistantTeacher').value : '',
        day_of_week: document.getElementById('initDayOfWeek').value,
        start_period: document.getElementById('initStartPeriod').value,
        end_period: document.getElementById('initEndPeriod').value,
        room: document.getElementById('initRoom').value
    };

    const submitBtn = document.getElementById('initSubmitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang lưu...';

    if (window.callApi) {
        window.callApi('save_group_schedule', payload)
            .then(function(res) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                if (res && res.ok) {
                    window.showToast('Đã lưu lịch giảng dạy thành công!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('initialScheduleModal')).hide();
                    window.location.reload(); 
                } else {
                    let msg = 'Có lỗi xảy ra khi lưu lịch.';
                    if (res.message === 'conflict_room') msg = res.detail || 'Phòng học đã bị trùng trong thời gian này.';
                    else if (res.message === 'conflict_teacher') msg = res.detail || 'Giảng viên đã bị trùng lịch trong thời gian này.';
                    window.showToast(msg, 'error');
                }
            })
            .catch(function(err) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                window.showToast('Lỗi kết nối đến server.', 'error');
            });
    }

    return false;
}

function openSessionManager(courseId, subjectName, teacherName, groupCode = null) {
    const course = allAssignmentCourses.find(function (item) { return item.id === courseId; });
    if (!course) {
        if (window.showToast) window.showToast('Không tìm thấy dữ liệu lớp học phần để quản lý lịch.', 'error');
        else alert('Không tìm thấy dữ liệu lớp học phần để quản lý lịch.');
        return;
    }
    const displayClassCode = course && course.classCode ? course.classCode : courseId;
    const groupText = groupCode ? ' | ' + getClassGroupLabel(displayClassCode, groupCode) : '';
    document.getElementById('lblClassInfo').innerText = displayClassCode + ' | ' + subjectName + groupText + ' | GV: ' + teacherName;

    currentSessionCourse = course || null;
    currentSessionGroupCode = groupCode || null;
    renderSessionManagerRows(course, currentSessionGroupCode);

    const editWholeScheduleBtn = document.getElementById('btnEditWholeSchedule');
    if (editWholeScheduleBtn) {
        editWholeScheduleBtn.onclick = function () {
            if (course && course.groups.length > 0) {
                const targetGroup = currentSessionGroupCode
                    ? (course.groups.find(function(g){ return g.code === currentSessionGroupCode; }) || course.groups[0])
                    : course.groups[0];
                openInitialScheduleModal(
                    'edit',
                    course.csId || course.id,
                    course.classCode || course.id,
                    course.name,
                    targetGroup.teacherMain || teacherName || '',
                    '',
                    '',
                    targetGroup.day || '',
                    targetGroup.start ? String(targetGroup.start) : '',
                    targetGroup.end ? String(targetGroup.end) : '',
                    targetGroup.room || '',
                    targetGroup.teacherSub || '',
                    targetGroup.code || 'N1'
                );
                return;
            }

            openInitialScheduleModal('add', course.csId || course.id, displayClassCode, subjectName, teacherName || '', '', '', '', '', '', '', currentSessionGroupCode || 'N1');
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
        course.classCode || course.id,
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
        const dateLabel = '--';
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
                '',
                '',
                hasGroupSchedule ? group.day : '',
                hasGroupSchedule ? String(group.start) : '',
                hasGroupSchedule ? String(group.end) : '',
                hasGroupSchedule ? group.room : '',
                'normal',
                course.classCode || course.id,
                course.name,
                teacherId,
                group.code
            );
        };
        actionCell.appendChild(editButton);

        row.innerHTML = '';
        row.appendChild(createTextCell(String(index + 1), 'fw-bold'));
        row.appendChild(createTextCell(getClassGroupLabel(course.classCode || '', group.code), 'fw-bold text-primary'));
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
    document.getElementById('qsGroup').innerText = 'Lớp - Nhóm: ' + getClassGroupLabel(classCode, group);
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
    let course = null;
    // Try flat list first
    course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    // Try nested allSubjects
    if (!course && window.allSubjects) {
        window.allSubjects.forEach(function(subj) {
            (subj.assignments || []).forEach(function(asg) {
                if (asg.id === courseId || asg.csId == courseId) {
                    course = asg;
                }
            });
        });
    }
    if (!course) {
        alert('Không tìm thấy môn học.');
        return;
    }

    if (!course.isOpen) {
        alert('Môn học đã đóng, không thể thêm nhóm mới.');
        return;
    }

    const csIdFromCourse = parseInt(course.csId, 10);
    const csId = Number.isFinite(csIdFromCourse) && csIdFromCourse > 0
        ? csIdFromCourse
        : parseInt(String(course.id || '').split('-').pop(), 10);

    if (!csId || csId <= 0) {
        alert('Không xác định được lớp học phần để thêm nhóm.');
        return;
    }

    const confirmMsg = 'Tạo nhóm mới cho ' + subjectName + '?';
    if (!confirm(confirmMsg)) {
        return;
    }

    if (!window.callApi) {
        alert('Không thể kết nối API thêm nhóm.');
        return;
    }

    window.callApi('add_group', { class_subject_id: csId })
        .then(function(res) {
            if (res && res.ok) {
                if (window.showToast) window.showToast('Đã thêm nhóm mới thành công.', 'success');
                window.location.reload();
                return;
            }
            alert('Không thể thêm nhóm. Vui lòng thử lại.');
        })
        .catch(function() {
            alert('Lỗi kết nối server khi thêm nhóm.');
        });
}

// ─── NEW: Per-subject helpers for the new 1-subject-per-card layout ─────────────────

// Upload SV cho tất cả lớp HP của 1 môn
function uploadAllSubjectsStudentList(subjectId) {
    const subj = (window.allSubjects || []).find(function(s) { return String(s.subjectId) === String(subjectId); });
    if (!subj) {
        alert('Không tìm thấy môn học.');
        return;
    }
    // Use first assignment's csId for upload
    if (subj.assignments && subj.assignments.length > 0) {
        uploadAssignmentStudentList(subj.assignments[0].csId || subj.assignments[0].id);
    } else {
        alert('Môn học chưa có lớp HP nào. Hãy thêm lớp HP trước.');
    }
}

// Download SV cho tất cả lớp HP của 1 môn (download first)
function downloadAllSubjectsStudentList(subjectId) {
    const subj = (window.allSubjects || []).find(function(s) { return String(s.subjectId) === String(subjectId); });
    if (!subj || !subj.assignments || subj.assignments.length === 0) {
        alert('Không có dữ liệu sinh viên để tải xuống.');
        return;
    }
    downloadAssignmentStudentList(subj.assignments[0].csId || subj.assignments[0].id);
}

// Thêm lớp HP mới cho 1 môn (gọi modal phân công mới)
function addSubjectClass(subjectId, subjectName) {
    // Find the subject to check if it's open
    const subj = (window.allSubjects || []).find(function(s) { return String(s.subjectId) === String(subjectId); });
    if (subj && !subj.isOpen) {
        alert('Môn học đã đóng, không thể thêm lớp HP mới.');
        return;
    }
    // Open the initial schedule modal in "add" mode with empty values
    // The modal needs a csId, so we pass empty to create a new one
    openInitialScheduleModal('add', '', '', subjectName, '', '', '', '', '', '', '', '');
}
