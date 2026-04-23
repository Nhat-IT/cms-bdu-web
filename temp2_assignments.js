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

    loadAssignmentCoursesFromApi().finally(function () {
        initAssignmentEnhancements();
    });

    toggleCancelReason();
});

const assignmentStudentsByClass = {};

const allAssignmentCourses = [];

const teachers = [];

const days = [
    { value: '2', label: 'Thß╗⌐ 2' },
    { value: '3', label: 'Thß╗⌐ 3' },
    { value: '4', label: 'Thß╗⌐ 4' },
    { value: '5', label: 'Thß╗⌐ 5' },
    { value: '6', label: 'Thß╗⌐ 6' },
    { value: '7', label: 'Thß╗⌐ 7' }
];

let assignmentOfferingsFiltered = [];

let pendingAssignmentUploadClass = '';
let currentSessionCourse = null;
let currentSessionGroupCode = null;

function getTeacherName(teacherId) {
    const teacher = teachers.find(function (t) { return t.id === teacherId; });
    return teacher ? teacher.name : 'ΓÇö';
}

function getDayLabel(day) {
    const dayObj = days.find(function (d) { return d.value === day; });
    return dayObj ? dayObj.label : 'ΓÇö';
}

function getGroupLabel(groupCode) {
    const match = groupCode.match(/N(\d+)/);
    if (match) {
        return 'Nh├│m ' + match[1];
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

async function loadAssignmentCoursesFromApi() {
    try {
        const res = await fetch('/api/admin/teaching-assignments', { headers: { Accept: 'application/json' } });
        if (!res.ok) {
            allAssignmentCourses.splice(0, allAssignmentCourses.length);
            teachers.splice(0, teachers.length);
            return;
        }

        const rows = await res.json();
        if (!Array.isArray(rows)) {
            allAssignmentCourses.splice(0, allAssignmentCourses.length);
            teachers.splice(0, teachers.length);
            return;
        }

        allAssignmentCourses.splice(0, allAssignmentCourses.length, ...rows.map(function (item) {
            return {
                id: item.id,
                classCode: item.classCode,
                name: item.name,
                year: item.year || '2025',
                semester: item.semester || '1',
                isOpen: Boolean(item.isOpen),
                credits: Number(item.credits || 0),
                openWindow: item.openWindow || '--',
                hasSchedule: Boolean(item.hasSchedule),
                groups: Array.isArray(item.groups) ? item.groups : []
            };
        }));

        teachers.splice(0, teachers.length);
        const teacherMap = new Map();
        allAssignmentCourses.forEach(function (course) {
            course.groups.forEach(function (group) {
                if (group.teacherMain && group.teacherMainName && !teacherMap.has(group.teacherMain)) {
                    teacherMap.set(group.teacherMain, group.teacherMainName);
                }
                if (group.teacherSub && group.teacherSubName && !teacherMap.has(group.teacherSub)) {
                    teacherMap.set(group.teacherSub, group.teacherSubName);
                }
            });
        });

        Array.from(teacherMap.entries()).forEach(function (entry) {
            teachers.push({ id: entry[0], name: entry[1] });
        });
    } catch (error) {
        allAssignmentCourses.splice(0, allAssignmentCourses.length);
        teachers.splice(0, teachers.length);
        console.error('Kh├┤ng tß║úi ─æ╞░ß╗úc dß╗» liß╗çu ph├ón c├┤ng tß╗½ API:', error);
    }
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
        container.innerHTML = '<div class="alert alert-light border">Kh├┤ng c├│ m├┤n hß╗ìc n├áo trong bß╗Ö lß╗ìc ─æ├ú chß╗ìn.</div>';
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
            '<div class="offering-subtitle">' + course.credits + ' t├¡n chß╗ë | M├ú lß╗¢p: ' + course.classCode + '</div>';
        head.appendChild(titleDiv);
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'd-flex gap-2 flex-wrap';
        
        const btnUpload = document.createElement('button');
        btnUpload.className = 'btn btn-sm btn-outline-dark';
        btnUpload.innerHTML = '<i class="bi bi-upload me-1"></i>Tß║úi l├¬n SV';
        btnUpload.onclick = function () { uploadAssignmentStudentList(course.id); };
        
        const btnDownload = document.createElement('button');
        const hasStudents = assignmentStudentsByClass[course.id] && assignmentStudentsByClass[course.id].length > 0;
        btnDownload.className = 'btn btn-sm btn-outline-success' + (hasStudents ? '' : ' disabled');
        btnDownload.innerHTML = '<i class="bi bi-download me-1"></i>Tß║úi xuß╗æng SV';
        if (hasStudents) {
            btnDownload.onclick = function () { downloadAssignmentStudentList(course.id); };
        } else {
            btnDownload.setAttribute('aria-disabled', 'true');
            btnDownload.setAttribute('title', 'Ch╞░a c├│ danh s├ích sinh vi├¬n');
        }
        
        const btnAdd = document.createElement('button');
        btnAdd.className = 'btn btn-sm btn-outline-primary';
        btnAdd.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Th├¬m nh├│m';
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
            const teacherMainName = group.teacherMainName || getTeacherName(group.teacherMain);
            const teacherSubName = group.teacherSubName || getTeacherName(group.teacherSub);
            teacherDiv.innerHTML = '<div class="section-label">Giß║úng vi├¬n</div>' +
                '<div class="section-value">' + teacherMainName +
                (group.teacherSub ? ' + ' + teacherSubName : '') + '</div>';
            
            const scheduleDiv = document.createElement('div');
            scheduleDiv.innerHTML = '<div class="section-label">Lß╗ïch hß╗ìc</div>' +
                '<div class="section-value">' + (hasGroupSchedule ? (getDayLabel(group.day) + ' | Tiß║┐t ' + group.start + '-' + group.end) : 'Ch╞░a xß║┐p lß╗ïch') + '</div>';
            
            const roomDiv = document.createElement('div');
            roomDiv.innerHTML = '<div class="section-label">Ph├▓ng</div>' +
                '<div class="section-value">' + (hasGroupSchedule ? group.room : '--') + '</div>';
            
            const statusDiv = document.createElement('div');
            statusDiv.className = 'section-status';
            const statusText = course.isOpen ? '─Éang mß╗ƒ' : '─É├ú ─æ├│ng';
            statusDiv.innerHTML = '<div class="section-label">Trß║íng th├íi</div>' +
                '<div class="section-value">' + statusText + '</div>';
            
            const actionsRowDiv = document.createElement('div');
            actionsRowDiv.className = 'section-actions';
            
            const btnEdit = document.createElement('button');
            btnEdit.className = 'btn btn-sm ' + (hasGroupSchedule ? 'btn-outline-primary' : 'btn-primary') + (course.isOpen ? '' : ' disabled');
            btnEdit.innerHTML = '<i class="bi ' + (hasGroupSchedule ? 'bi-calendar3' : 'bi-calendar-plus') + ' me-1"></i>' + (hasGroupSchedule ? 'Quß║ún l├╜ lß╗ïch' : 'Xß║┐p lß╗ïch ngay');
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
                btnEdit.setAttribute('title', 'M├┤n ─æ├ú ─æ├│ng');
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
        let scheduleStatus = '<span class="badge bg-secondary">Ch╞░a xß║┐p lß╗ïch</span>';
        if (scheduledGroups.length === course.groups.length && course.groups.length > 0) {
            scheduleStatus = '<span class="badge bg-success">─É├ú xß║┐p lß╗ïch</span>';
        } else if (scheduledGroups.length > 0) {
            const unscheduledGroups = course.groups.filter(function (group) {
                return !(group.day && group.start && group.end && group.room);
            }).map(function (group) {
                return getGroupLabel(group.code);
            });
            scheduleStatus = '<span class="badge bg-warning text-dark">Ch╞░a xß║┐p lß╗ïch nh├│m ' + unscheduledGroups.join(', ') + '</span>';
        }
        foot.innerHTML = 'Sß╗æ nh├│m: ' + course.groups.length + ' | Thß╗¥i gian mß╗ƒ: ' + course.openWindow + ' | ' + scheduleStatus;
        card.appendChild(foot);

        container.appendChild(card);
    });
}


function uploadAssignmentStudentList(courseId) {
    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    if (!course) {
        alert('Kh├┤ng t├¼m thß║Ñy m├┤n hß╗ìc.');
        return;
    }

    if (!course.isOpen) {
        alert('M├┤n hß╗ìc ─æ├ú ─æ├│ng, kh├┤ng thß╗â tß║úi l├¬n danh s├ích sinh vi├¬n.');
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
        alert('Chß╗ë hß╗ù trß╗ú file CSV, XLSX hoß║╖c XLS.');
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
            alert('Kh├┤ng ─æß╗ìc ─æ╞░ß╗úc dß╗» liß╗çu sinh vi├¬n. Vui l├▓ng kiß╗âm tra lß║íi file.');
            return;
        }

        assignmentStudentsByClass[pendingAssignmentUploadClass] = parsed;
        alert('─É├ú tß║úi l├¬n ' + parsed.length + ' sinh vi├¬n cho ' + pendingAssignmentUploadClass + '.');
        pendingAssignmentUploadClass = '';
        updateAssignmentDownloadButtons();
    };

    reader.onerror = function () {
        alert('Kh├┤ng thß╗â ─æß╗ìc file. Vui l├▓ng thß╗¡ lß║íi.');
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
    csv.push(['STT', 'MSSV', 'Hß╗ì v├á t├¬n', 'Ng├áy sinh', 'Email', 'M├ú lß╗¢p HP', 'Nh├│m hß╗ìc'].join(','));
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
            option.textContent = 'Tiß║┐t ' + period;
            select.appendChild(option);
        }
    });
}

function openInitialScheduleModal(mode, classCode, subjectName, teacher = '', startDate = '', endDate = '', dayOfWeek = '', startPeriod = '', endPeriod = '', room = '', assistant = '', group = 'ALL') {
    document.getElementById('initClassCode').innerText = classCode;
    document.getElementById('initSubjectName').innerText = subjectName;
    const groupLabel = document.getElementById('initGroupLabel');
    if (groupLabel) {
        groupLabel.innerText = group === 'ALL' ? 'To├án bß╗Ö nh├│m' : getGroupLabel(group);
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
        modalTitle.innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Thiß║┐t Lß║¡p Lß╗ïch Giß║úng Dß║íy Mß╗¢i';
        submitBtn.className = 'btn btn-primary fw-bold px-4';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>L╞░u Lß╗ïch & Ph├ít Sinh';
        warningAlert.classList.add('d-none');
        infoAlert.className = 'alert alert-primary bg-primary bg-opacity-10 border-0 mb-4';
    } else {
        modalHeader.className = 'modal-header bg-warning text-dark border-bottom-0 pb-3';
        modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chß╗ënh Sß╗¡a Lß╗ïch Giß║úng Dß║íy (H├áng Loß║ít)';
        submitBtn.className = 'btn btn-warning fw-bold px-4 text-dark shadow-sm';
        submitBtn.innerHTML = '<i class="bi bi-shield-check me-2"></i>Cß║¡p nhß║¡t to├án bß╗Ö c├íc tuß║ºn';
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
        alert('ΓÜá∩╕Å Lß╗ùi: Tiß║┐t bß║»t ─æß║ºu phß║úi diß╗àn ra TR╞»ß╗ÜC tiß║┐t kß║┐t th├║c!');
        return false;
    }

    const btnText = document.getElementById('initSubmitBtn').innerText;
    if (btnText.includes('Cß║¡p nhß║¡t')) {
        alert('Γ£à ─É├ú cß║¡p nhß║¡t th├ánh c├┤ng cho tß║Ñt cß║ú c├íc buß╗òi hß╗ìc ch╞░a diß╗àn ra cß╗ºa lß╗¢p n├áy!');
    } else {
        alert('Γ£à ─É├ú thiß║┐t lß║¡p lß╗ïch th├ánh c├┤ng v├á tß╗▒ ─æß╗Öng ph├ít sinh c├íc buß╗òi hß╗ìc v├áo Database!');
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
        addSingleSessionBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Th├¬m buß╗òi hß╗ìc b├╣ cho lß╗¢p n├áy';
        addSingleSessionBtn.onclick = function () {
            openAddSingleSessionFromManager();
        };
    }

    new bootstrap.Modal(document.getElementById('sessionManagerModal')).show();
}

function openAddSingleSessionFromManager() {
    const course = currentSessionCourse;
    if (!course) {
        alert('Kh├┤ng x├íc ─æß╗ïnh ─æ╞░ß╗úc lß╗¢p ─æang mß╗ƒ trong Quß║ún l├╜ lß╗ïch.');
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
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted py-4">Ch╞░a c├│ buß╗òi hß╗ìc n├áo ─æß╗â hiß╗ân thß╗ï.</td></tr>';
        return;
    }

    const displayedGroups = groupCode
        ? course.groups.filter(function (group) { return group.code === groupCode; })
        : course.groups;

    if (displayedGroups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted py-4">Kh├┤ng t├¼m thß║Ñy nh├│m cß║ºn quß║ún l├╜.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    displayedGroups.forEach(function (group, index) {
        const hasGroupSchedule = Boolean(group.day && group.start && group.end && group.room);
        const row = document.createElement('tr');
        const dateLabel = '--';
        const teacherId = group.teacherMain || '';

        const dayCell = document.createElement('td');
        dayCell.textContent = hasGroupSchedule ? getDayLabel(group.day) : 'Ch╞░a xß║┐p';

        const actionCell = document.createElement('td');
        const editButton = document.createElement('button');
        editButton.className = 'btn btn-sm btn-light text-primary border';
        editButton.title = 'Sß╗¡a tß╗½ng ng├áy';
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
        row.appendChild(createTextCell(hasGroupSchedule ? ('Tiß║┐t ' + group.start + ' - ' + group.end) : '--', ''));
        row.appendChild(createTextCell(hasGroupSchedule ? group.room : '--', 'fw-bold text-danger'));
        row.appendChild(createStatusCell(course.isOpen ? '─Éang mß╗ƒ' : '─É├ú ─æ├│ng', course.isOpen ? 'status-normal' : 'bg-secondary'));
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
    document.getElementById('qsClassCode').innerHTML = '<i class="bi bi-tags-fill me-1 text-muted"></i>Lß╗¢p: ' + classCode;
    document.getElementById('qsGroup').innerText = 'Nh├│m: ' + getGroupLabel(group);
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
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Th├¬m buß╗òi hß╗ìc b├╣ / ─æß╗Öt xuß║Ñt';
        btnDelete.classList.add('d-none');
    } else {
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Sß╗¡a lß╗ïch ng├áy ' + dateStr;
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
        alert('ΓÜá∩╕Å Lß╗ùi: Tiß║┐t bß║»t ─æß║ºu phß║úi nhß╗Å h╞ín Tiß║┐t kß║┐t th├║c!');
        return;
    }

    const status = document.getElementById('singleStatus').value;
    if (status === 'canceled' && document.getElementById('cancelReason').value.trim() === '') {
        alert('ΓÜá∩╕Å Vui l├▓ng nhß║¡p l├╜ do b├ío hß╗ºy ─æß╗â hß╗ç thß╗æng gß╗¡i th├┤ng b├ío cho sinh vi├¬n!');
        document.getElementById('cancelReason').focus();
        return;
    }

    alert('Γ£à ─É├ú cß║¡p nhß║¡t th├┤ng tin buß╗òi hß╗ìc th├ánh c├┤ng!');
    bootstrap.Modal.getInstance(document.getElementById('singleSessionModal')).hide();
}

function deleteSingleSession() {
    if (confirm('ΓÜá∩╕Å NGUY HIß╗éM: Bß║ín c├│ chß║»c chß║»n muß╗æn x├│a hß║│n buß╗òi hß╗ìc n├áy ra khß╗Åi c╞í sß╗ƒ dß╗» liß╗çu kh├┤ng?\nH├ánh ─æß╗Öng n├áy kh├┤ng thß╗â ho├án t├íc!')) {
        alert('Γ£à ─É├ú x├│a buß╗òi hß╗ìc th├ánh c├┤ng!');
        bootstrap.Modal.getInstance(document.getElementById('singleSessionModal')).hide();
    }
}

function addGroupToClass(courseId, subjectName) {
    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    if (!course) {
        alert('Kh├┤ng t├¼m thß║Ñy m├┤n hß╗ìc.');
        return;
    }

    if (!course.isOpen) {
        alert('M├┤n hß╗ìc ─æ├ú ─æ├│ng, kh├┤ng thß╗â th├¬m nh├│m mß╗¢i.');
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

    const confirmMsg = 'Tß║ío nh├│m mß╗¢i: ' + getGroupLabel(newGroupCode) + ' cho ' + subjectName + '?';
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
