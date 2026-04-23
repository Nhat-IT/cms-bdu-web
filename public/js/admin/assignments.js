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

    // Filter the nested structure: each subject shows assignments matching filters
    assignmentOfferingsFiltered = (window.allSubjects || []).map(function(subj) {
        const filteredAssignments = subj.assignments.filter(function(asg) {
            const matchYear = year === 'all' || asg.year === year;
            const matchSemester = semester === 'all' || asg.semester === semester;
            const matchStatus = status === 'all' || (status === 'open' ? subj.isOpen : !subj.isOpen);
            return matchYear && matchSemester && matchStatus;
        });
        return Object.assign({}, subj, { assignments: filteredAssignments });
    }).filter(function(subj) {
        return subj.assignments.length > 0;
    });

    renderAssignmentOfferings();
}

function renderAssignmentOfferings() {
    const container = document.getElementById('assignmentOfferingContainer');
    if (!container) return;

    container.innerHTML = '';

    if (assignmentOfferingsFiltered.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1"></i><br>Không có môn học nào trong bộ lọc đã chọn.</div>';
        return;
    }

    // Inject custom hover style once
    if (!document.getElementById('custom-group-hover-style')) {
        const style = document.createElement('style');
        style.id = 'custom-group-hover-style';
        style.innerHTML = '.group-row:hover { background-color: #f8f9fa !important; }';
        document.head.appendChild(style);
    }

    assignmentOfferingsFiltered.forEach(function(subj) {
        const card = document.createElement('div');
        card.className = 'card mb-4 bg-white shadow-sm';
        card.style.border = '1px solid #dee2e6';
        card.style.borderRadius = '8px';
        card.style.overflow = 'hidden';

        // ─── CARD HEADER ───
        const subjectCodeStr = subj.subjectCode || '';
        const totalGroups = subj.assignments.reduce(function(s, a) { return s + a.groups.length; }, 0);
        const scheduledGroups = subj.assignments.reduce(function(s, a) {
            return s + a.groups.filter(function(g) { return g.day && g.start && g.end && g.room; }).length;
        }, 0);
        const statusBadge = subj.isOpen
            ? '<span class="badge me-1" style="background:#d1fae5;color:#0f766e;border:1px solid #10b981">Đang mở</span>'
            : '<span class="badge me-1" style="background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db">Đã đóng</span>';

        let headerBadge = '';
        if (scheduledGroups === 0 && totalGroups > 0) {
            headerBadge = '<span class="badge ms-2" style="background:#ffc107;color:#000;border:1px solid #f59e0b">Chưa xếp lịch</span>';
        } else if (scheduledGroups >= totalGroups && totalGroups > 0) {
            headerBadge = '<span class="badge ms-2" style="background:#d1fae5;color:#0f766e;border:1px solid #10b981">Đã xếp lịch</span>';
        } else {
            headerBadge = '<span class="badge ms-2" style="background:#dbeafe;color:#1d4ed8;border:1px solid #93c5fd">' + scheduledGroups + '/' + totalGroups + ' nhóm</span>';
        }

        const headHtml =
            '<div class="d-flex justify-content-between align-items-center p-4 pb-3">' +
                '<div>' +
                    '<h5 class="fw-bold mb-1 text-dark"><i class="bi bi-book me-2 text-primary"></i>' + subjectCodeStr + ' - ' + subj.name + '</h5>' +
                    '<div class="small text-muted">' + subj.credits + ' tín chỉ' + headerBadge + '</div>' +
                '</div>' +
                '<div class="d-flex gap-2 flex-wrap">' +
                    '<button class="btn btn-sm btn-outline-secondary" onclick="uploadAllSubjectsStudentList(\'' + subj.subjectId + '\')"><i class="bi bi-upload me-1"></i> Tải lên SV</button>' +
                    '<button class="btn btn-sm btn-outline-success" onclick="downloadAllSubjectsStudentList(\'' + subj.subjectId + '\')"><i class="bi bi-download me-1"></i> Tải xuống SV</button>' +
                    '<button class="btn btn-sm btn-primary" onclick="addSubjectClass(\'' + subj.subjectId + '\', \'' + subj.name + '\')"><i class="bi bi-plus-circle me-1"></i> Thêm lớp HP</button>' +
                '</div>' +
            '</div>';

        // ─── TABLE HEADER ROW ───
        const tableHeaderHtml =
            '<div class="row align-items-center py-2 mx-0" style="background:#f1f5f9;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;font-size:0.75rem;font-weight:700;text-transform:uppercase;color:#64748b;letter-spacing:0.04em">' +
                '<div class="col-md-2">Lớp-Nhóm</div>' +
                '<div class="col-md-2">Học kỳ</div>' +
                '<div class="col-md-2">Giảng viên</div>' +
                '<div class="col-md-2">Lịch học</div>' +
                '<div class="col-md-2 text-center">Trạng thái</div>' +
                '<div class="col-md-2 text-center">Hành động</div>' +
            '</div>';

        // ─── ASSIGNMENT ROWS ───
        let rowsHtml = '';
        subj.assignments.forEach(function(asg, idx) {
            const scheduledInAsg = asg.groups.filter(function(g) { return g.day && g.start && g.end && g.room; }).length;

            // Primary teacher (use group-level if main not set)
            const primaryGroup = asg.groups[0] || {};
            const teacherDisplay = primaryGroup.teacherMainName || primaryGroup.teacherSubName || getTeacherName(primaryGroup.teacherMain) || '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Chưa PC</span>';

            // Schedule summary: show first scheduled group or "Chưa xếp"
            const scheduledGroup = asg.groups.find(function(g) { return g.day && g.start && g.end && g.room; });
            let scheduleDisplay = 'Chưa xếp';
            let scheduleClass = 'text-danger';
            if (scheduledGroup) {
                scheduleDisplay = getDayLabel(scheduledGroup.day) + ' | T' + scheduledGroup.start + '-' + scheduledGroup.end;
                scheduleClass = 'text-success';
            }

            // Room
            let roomDisplay = scheduledGroup ? scheduledGroup.room : '--';
            if (asg.groups.length > 1 && scheduledGroup) {
                roomDisplay = scheduledGroup.room;
            }

            // Status from computed_status (same logic as classes-subjects.php)
            let statusHtml = '';
            if (asg.groups.length === 0) {
                statusHtml = '<span class="badge" style="background:#f3f4f6;color:#6b7280">Chưa có nhóm</span>';
            } else if (asg.computedStatus === '0') {
                statusHtml = '<span class="badge" style="background:#f3f4f6;color:#6b7280">Đã đóng</span>';
            } else if (scheduledInAsg === 0) {
                statusHtml = '<span class="badge" style="background:#fee2e2;color:#b91c1c">Chưa xếp lịch</span>';
            } else if (scheduledInAsg === asg.groups.length) {
                statusHtml = '<span class="badge" style="background:#d1fae5;color:#0f766e">Đã xếp lịch</span>';
            } else {
                statusHtml = '<span class="badge" style="background:#fef3c7;color:#b45309">' + scheduledInAsg + '/' + asg.groups.length + ' nhóm</span>';
            }

            // Action buttons
            const openBtnDisabled = !subj.isOpen ? ' disabled title="Môn đã đóng"' : '';
            const hasAnySchedule = asg.groups.some(function(g) { return g.day && g.start && g.end && g.room; });
            let actionBtn = '';
            if (subj.isOpen) {
                if (hasAnySchedule) {
                    actionBtn = '<button class="btn btn-sm btn-outline-primary px-2"' + openBtnDisabled + ' onclick="openSessionManager(\'' + (asg.csId || asg.id) + '\', \'' + subj.name + '\', \'' + teacherDisplay.replace(/'/g, "\\'") + '\')"><i class="bi bi-calendar3 me-1"></i>QL lịch</button>';
                } else {
                    actionBtn = '<button class="btn btn-sm btn-primary px-2"' + openBtnDisabled + ' onclick="openGroupScheduleModal(\'' + (asg.csId || asg.id) + '\', \'' + subj.name + '\', \'' + (asg.groups[0] ? asg.groups[0].code : 'N1') + '\', \'' + (asg.groups[0] ? asg.groups[0].teacherMain : '') + '\', \'' + (asg.groups[0] ? asg.groups[0].teacherSub : '') + '\', \'' + (asg.groups[0] ? asg.groups[0].day : '') + '\', \'\', \'\', \'\', \'' + asg.classCode + '\')"><i class="bi bi-calendar-plus me-1"></i>Xếp lịch</button>';
                }
            }

            const rowClass = (idx % 2 === 0) ? '' : 'style="background:#fafafa"';
            rowsHtml +=
                '<div class="row align-items-center py-3 border-bottom mx-0 group-row" ' + rowClass + '>' +
                    '<div class="col-md-2 fw-bold text-primary">' + asg.classCode + '</div>' +
                    '<div class="col-md-2 small">' + (asg.semester ? 'HK' + asg.semester.replace('HK','') : '--') + ' ' + asg.year + '</div>' +
                    '<div class="col-md-2 small">' + teacherDisplay + '</div>' +
                    '<div class="col-md-2 small ' + scheduleClass + ' fw-bold">' + scheduleDisplay + '</div>' +
                    '<div class="col-md-2 text-center">' + statusHtml + '</div>' +
                    '<div class="col-md-2 text-center">' + actionBtn + '</div>' +
                '</div>';
        });

        // ─── FOOTER ───
        const openWindow = subj.assignments[0] ? subj.assignments[0].openWindow : '--';
        const footClass = scheduledGroups === totalGroups && totalGroups > 0
            ? 'style="background:#d1fae5;color:#0f766e;border-top:1px solid #10b981"'
            : (totalGroups > 0 && scheduledGroups === 0
                ? 'style="background:#fff8e1;color:#b45309;border-top:1px solid #f59e0b"'
                : 'style="background:#f8fafc;color:#64748b;border-top:1px solid #eef2f7"');
        const footStatus = totalGroups === 0
            ? 'Chưa có nhóm'
            : (scheduledGroups === totalGroups ? 'Đã xếp lịch' : 'Chưa xếp lịch');

        const footHtml =
            '<div class="px-4 py-2 small fw-bold" ' + footClass + '>' +
                'Số nhóm: ' + totalGroups + ' | Thời gian mở: ' + openWindow + ' | ' + footStatus +
            '</div>';

        card.innerHTML = headHtml + tableHeaderHtml + rowsHtml + footHtml;
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

function openInitialScheduleModal(mode, csId, classCode, subjectName, teacher = '', startDate = '', endDate = '', dayOfWeek = '', startPeriod = '', endPeriod = '', room = '', assistant = '', group = 'ALL') {
    window._currentCsId = csId;
    window._currentGroupCode = group === 'ALL' ? 'N1' : group;

    const classCodeSelect = document.getElementById('initClassCode');
    if (classCodeSelect) {
        classCodeSelect.value = classCode;
    }
    
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

function openGroupScheduleModal(csId, subjectName, groupCode, teacherId, assistantId, dayOfWeek, startPeriod, endPeriod, room, classCode) {
    openInitialScheduleModal('edit', csId, classCode || csId, subjectName, teacherId, '', '', dayOfWeek, startPeriod, endPeriod, room, assistantId, groupCode);
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
                    course.csId || course.id,
                    course.classCode || course.id,
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

            openInitialScheduleModal('add', course.csId || course.id, classCode, subjectName, teacherName || '', '', '', '', '', '', '', 'ALL');
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

    let nextNumber = 1;
    if (course.groups.length > 0) {
        const maxNumber = Math.max.apply(null, course.groups.map(function (group) {
            return parseInt(String(group.code).replace('N', ''), 10) || 1;
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
