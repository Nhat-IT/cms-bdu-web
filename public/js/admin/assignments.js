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
let currentSingleSessionContext = null;

function findAssignmentCourse(ref) {
    const refText = String(ref || '');
    let course = allAssignmentCourses.find(function (c) { return String(c.id) === refText; }) || null;
    if (course) return course;

    const refNum = parseInt(refText, 10);
    if (Number.isFinite(refNum) && refNum > 0) {
        course = allAssignmentCourses.find(function (c) { return Number(c.csId) === refNum; }) || null;
    }
    return course;
}

function isSubjectOpen(subjectId) {
    const sid = String(subjectId || '').trim();
    if (!sid) return true;
    const matched = allAssignmentCourses.find(function (c) { return String(c.subjectId || '') === sid; });
    if (!matched) return true;
    return !!matched.isOpen;
}

function triggerAssignmentFilePicker() {
    const uploadInput = document.getElementById('assignmentStudentUploadInput');
    if (!uploadInput) return;
    uploadInput.value = '';
    uploadInput.click();
}

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

function normalizeSemesterCode(value) {
    const text = String(value || '').trim().toUpperCase();
    if (!text) return '';
    const match = text.match(/(?:HK)?\s*([123])$/);
    return match ? ('HK' + match[1]) : text;
}

function getSemesterDisplay(value) {
    const code = normalizeSemesterCode(value);
    if (code === 'HK1') return 'Học kỳ 1';
    if (code === 'HK2') return 'Học kỳ 2';
    if (code === 'HK3') return 'Học kỳ 3 (Hè)';
    return value || '--';
}

function getNearestDateByDay(dayValue) {
    const day = parseInt(dayValue, 10);
    if (!Number.isFinite(day) || day < 2 || day > 8) return '';
    const today = new Date();
    const targetJsDay = day === 8 ? 0 : (day - 1);
    const diff = (targetJsDay - today.getDay() + 7) % 7;
    const target = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    target.setDate(target.getDate() + diff);
    return target.toISOString().slice(0, 10);
}

function getDayOfWeekFromDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    if (Number.isNaN(d.getTime())) return '';
    const jsDay = d.getDay(); // 0..6
    return jsDay === 0 ? '8' : String(jsDay + 1); // 2..8
}

function resolveSingleSessionCourse(context) {
    if (!context) return null;

    const directCsId = parseInt(context.csId, 10);
    if (Number.isFinite(directCsId) && directCsId > 0) {
        const direct = findAssignmentCourse(directCsId);
        if (direct) return direct;
    }

    const classCode = String(context.classCode || '').trim();
    const subjectName = String(context.subjectName || '').trim();
    return allAssignmentCourses.find(function (course) {
        return String(course.classCode || '').trim() === classCode
            && String(course.name || '').trim() === subjectName;
    }) || null;
}

function getTeacherDisplayNameById(teacherId) {
    const idText = String(teacherId || '').trim();
    if (!idText) return null;
    const list = window.teachers || teachers || [];
    const teacher = list.find(function (t) { return String(t.id) === idText; });
    if (!teacher) return null;
    return (teacher.title ? (teacher.title + '. ') : '') + teacher.name;
}

function upsertScheduleIntoClientData(payload, res) {
    const csId = parseInt((res && res.class_subject_id) || payload.class_subject_id, 10);
    if (!Number.isFinite(csId) || csId <= 0) return false;

    const classCode = String(payload.class_code || '').trim() || '--';
    const subjectIdText = String(payload.subject_id || '').trim();
    const groupCode = getGroupCode((res && res.group_code) || payload.group_code || 'N1');
    const teacherMain = String((res && res.teacher_main_id) || payload.teacher_main_id || '').trim();
    const teacherSubRaw = (res && Object.prototype.hasOwnProperty.call(res, 'teacher_sub_id')) ? res.teacher_sub_id : payload.teacher_sub_id;
    const teacherSub = teacherSubRaw === null || teacherSubRaw === undefined || teacherSubRaw === '' ? '' : String(teacherSubRaw);
    const day = String((res && res.day_of_week) || payload.day_of_week || '');
    const start = String((res && res.start_period) || payload.start_period || '');
    const end = String((res && res.end_period) || payload.end_period || '');
    const room = String((res && res.room) || payload.room || '');

    const teacherMainName = getTeacherDisplayNameById(teacherMain);
    const teacherSubName = getTeacherDisplayNameById(teacherSub);

    let course = allAssignmentCourses.find(function (c) { return parseInt(c.csId, 10) === csId; }) || null;
    let template = null;
    if (!course && subjectIdText) {
        (window.allClasses || []).some(function (cls) {
            template = (cls.assignments || []).find(function (asg) {
                return String(asg.subjectId || '') === subjectIdText;
            }) || null;
            return !!template;
        });
    }

    if (!course) {
        const subjectName = template ? (template.subjectName || '') : '';
        const subjectCode = template ? (template.subjectCode || '') : '';
        course = {
            id: (subjectCode || 'SUB') + '-' + csId,
            csId: csId,
            name: subjectName,
            credits: template ? (template.credits || 0) : 0,
            classCode: classCode,
            year: (payload.academic_year && payload.academic_year !== 'all') ? payload.academic_year : (template ? (template.year || '') : ''),
            semester: normalizeSemesterCode((payload.semester_name && payload.semester_name !== 'all') ? payload.semester_name : (template ? (template.semester || '') : '')),
            isOpen: template ? !!template.isOpen : true,
            openWindow: template ? (template.openWindow || 'Chưa xác định') : 'Chưa xác định',
            groups: [],
            subjectCode: subjectCode,
            subjectId: subjectIdText || (template ? (template.subjectId || null) : null)
        };
        allAssignmentCourses.push(course);
    }

    course.csId = csId;
    course.classCode = classCode;
    if (subjectIdText) course.subjectId = subjectIdText;
    course.semester = normalizeSemesterCode(course.semester || payload.semester_name || '');
    if (!Array.isArray(course.groups)) course.groups = [];

    let courseGroup = course.groups.find(function (g) { return getGroupCode(g.code) === groupCode; }) || null;
    if (!courseGroup) {
        courseGroup = { code: groupCode };
        course.groups.push(courseGroup);
    }
    courseGroup.code = groupCode;
    courseGroup.teacherMain = teacherMain || null;
    courseGroup.teacherMainName = teacherMainName;
    courseGroup.teacherSub = teacherSub || null;
    courseGroup.teacherSubName = teacherSubName;
    courseGroup.day = day || null;
    courseGroup.start = start || null;
    courseGroup.end = end || null;
    courseGroup.room = room || null;

    if (!Array.isArray(window.allClasses)) window.allClasses = [];
    let classEntry = window.allClasses.find(function (cls) { return String(cls.classCode || '') === classCode; }) || null;
    if (!classEntry) {
        classEntry = {
            id: 'class-' + classCode,
            classCode: classCode,
            name: 'Lớp ' + classCode,
            hasOpen: true,
            assignments: []
        };
        window.allClasses.push(classEntry);
    }
    if (!Array.isArray(classEntry.assignments)) classEntry.assignments = [];

    let asg = classEntry.assignments.find(function (a) { return parseInt(a.csId, 10) === csId; }) || null;
    if (!asg && subjectIdText) {
        asg = classEntry.assignments.find(function (a) { return String(a.subjectId || '') === subjectIdText; }) || null;
    }
    if (!asg) {
        asg = {
            id: course.id || ((course.subjectCode || 'SUB') + '-' + csId),
            csId: csId,
            subjectId: course.subjectId || null,
            subjectCode: course.subjectCode || '',
            subjectName: course.name || '',
            classCode: classCode,
            credits: course.credits || 0,
            isOpen: !!course.isOpen,
            year: course.year || '',
            semester: course.semester || '',
            openWindow: course.openWindow || 'Chưa xác định',
            computedStatus: '1',
            hasStudents: false,
            studentCount: 0,
            teacherMain: teacherMain || null,
            teacherMainName: teacherMainName,
            groups: []
        };
        classEntry.assignments.push(asg);
    }

    asg.id = (asg.subjectCode || course.subjectCode || 'SUB') + '-' + csId;
    asg.csId = csId;
    asg.classCode = classCode;
    asg.semester = normalizeSemesterCode(asg.semester || payload.semester_name || course.semester || '');
    asg.teacherMain = teacherMain || null;
    asg.teacherMainName = teacherMainName;
    if (!Array.isArray(asg.groups)) asg.groups = [];
    let asgGroup = asg.groups.find(function (g) { return getGroupCode(g.code) === groupCode; }) || null;
    if (!asgGroup) {
        asgGroup = { code: groupCode };
        asg.groups.push(asgGroup);
    }
    asgGroup.code = groupCode;
    asgGroup.teacherMain = teacherMain || null;
    asgGroup.teacherMainName = teacherMainName;
    asgGroup.teacherSub = teacherSub || null;
    asgGroup.teacherSubName = teacherSubName;
    asgGroup.day = day || null;
    asgGroup.start = start || null;
    asgGroup.end = end || null;
    asgGroup.room = room || null;

    if (subjectIdText) {
        const unassignedClass = window.allClasses.find(function (cls) { return String(cls.id || '') === 'class-unassigned'; });
        if (unassignedClass && Array.isArray(unassignedClass.assignments)) {
            unassignedClass.assignments = unassignedClass.assignments.filter(function (a) {
                return String(a.subjectId || '') !== subjectIdText;
            });
        }
    }

    if (Array.isArray(window.masterScheduleCourses)) {
        let masterCourse = window.masterScheduleCourses.find(function (c) { return parseInt(c.csId, 10) === csId; }) || null;
        if (!masterCourse) {
            masterCourse = {
                id: csId,
                csId: csId,
                name: ((course.subjectCode || '') ? (course.subjectCode + ' - ') : '') + (course.name || ''),
                classCode: classCode,
                year: course.year || '',
                semester: course.semester || '',
                groups: []
            };
            window.masterScheduleCourses.push(masterCourse);
        }
        masterCourse.classCode = classCode;
        masterCourse.semester = course.semester || masterCourse.semester;
        if (!Array.isArray(masterCourse.groups)) masterCourse.groups = [];
        let mg = masterCourse.groups.find(function (g) { return getGroupCode(g.code) === groupCode; }) || null;
        if (!mg) {
            mg = { code: groupCode };
            masterCourse.groups.push(mg);
        }
        mg.code = groupCode;
        mg.teacherMain = teacherMain || null;
        mg.day = day || null;
        mg.start = start || null;
        mg.end = end || null;
        mg.room = room || null;
    }

    return true;
}

function appendGroupToClassSubject(csId, groupCode) {
    const code = getGroupCode(groupCode || 'N1');
    const course = allAssignmentCourses.find(function (c) { return parseInt(c.csId, 10) === parseInt(csId, 10); }) || null;
    if (course) {
        if (!Array.isArray(course.groups)) course.groups = [];
        const exists = course.groups.some(function (g) { return getGroupCode(g.code) === code; });
        if (!exists) {
            course.groups.push({
                code: code,
                teacherMain: course.teacherMain || null,
                teacherMainName: course.teacherMainName || null,
                teacherSub: null,
                teacherSubName: null,
                day: null,
                start: null,
                end: null,
                room: null,
                roomName: null
            });
        }
    }

    (window.allClasses || []).forEach(function (cls) {
        (cls.assignments || []).forEach(function (asg) {
            if (parseInt(asg.csId, 10) !== parseInt(csId, 10)) return;
            if (!Array.isArray(asg.groups)) asg.groups = [];
            const exists = asg.groups.some(function (g) { return getGroupCode(g.code) === code; });
            if (!exists) {
                asg.groups.push({
                    code: code,
                    teacherMain: asg.teacherMain || null,
                    teacherMainName: asg.teacherMainName || null,
                    teacherSub: null,
                    teacherSubName: null,
                    day: null,
                    start: null,
                    end: null,
                    room: null,
                    roomName: null
                });
            }
        });
    });
}

function markStudentsImportedByCsId(csId, importedCount) {
    const imported = Math.max(0, parseInt(importedCount, 10) || 0);
    (window.allClasses || []).forEach(function (cls) {
        (cls.assignments || []).forEach(function (asg) {
            if (parseInt(asg.csId, 10) !== parseInt(csId, 10)) return;
            asg.hasStudents = true;
            const base = parseInt(asg.studentCount, 10) || 0;
            asg.studentCount = imported > 0 ? (base + imported) : Math.max(base, 1);
        });
    });
}

function removeGroupFromClassSubject(csId, groupCode) {
    const code = getGroupCode(groupCode || '');
    const course = allAssignmentCourses.find(function (c) { return parseInt(c.csId, 10) === parseInt(csId, 10); }) || null;
    if (course && Array.isArray(course.groups)) {
        course.groups = course.groups.filter(function (g) { return getGroupCode(g.code) !== code; });
    }

    (window.allClasses || []).forEach(function (cls) {
        (cls.assignments || []).forEach(function (asg) {
            if (parseInt(asg.csId, 10) !== parseInt(csId, 10) || !Array.isArray(asg.groups)) return;
            asg.groups = asg.groups.filter(function (g) { return getGroupCode(g.code) !== code; });
        });
    });

    if (Array.isArray(window.masterScheduleCourses)) {
        window.masterScheduleCourses.forEach(function (mc) {
            if (parseInt(mc.csId, 10) !== parseInt(csId, 10) || !Array.isArray(mc.groups)) return;
            mc.groups = mc.groups.filter(function (g) { return getGroupCode(g.code) !== code; });
        });
    }
}

function decrementStudentsByCsId(csId, removedCount) {
    const removed = Math.max(0, parseInt(removedCount, 10) || 0);
    if (removed <= 0) return;

    (window.allClasses || []).forEach(function (cls) {
        (cls.assignments || []).forEach(function (asg) {
            if (parseInt(asg.csId, 10) !== parseInt(csId, 10)) return;
            const current = Math.max(0, parseInt(asg.studentCount, 10) || 0);
            const next = Math.max(0, current - removed);
            asg.studentCount = next;
            asg.hasStudents = next > 0;
        });
    });
}

function initAssignmentEnhancements() {
    const filterYear = document.getElementById('assignFilterYear');
    const filterSemester = document.getElementById('assignFilterSemester');
    const filterStatus = document.getElementById('assignFilterOpenStatus');
    const searchInput = document.getElementById('searchAssignment');
    const uploadInput = document.getElementById('assignmentStudentUploadInput');
    const confirmUploadBtn = document.getElementById('confirmUploadBtn');
    const studentUploadGuideModal = document.getElementById('studentUploadGuideModal');

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
    if (confirmUploadBtn) {
        confirmUploadBtn.addEventListener('click', function() {
            if (!pendingAssignmentUploadClass) {
                if (window.showToast) window.showToast('Vui lòng chọn lớp/môn trước khi tải file.', 'warning');
                return;
            }
            if (studentUploadGuideModal && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(studentUploadGuideModal);
                modal.hide();
            }
            triggerAssignmentFilePicker();
        });
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
    const semester = normalizeSemesterCode(filterSemester.value);
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
        const matchSemester = semester === 'ALL' || normalizeSemesterCode(asg.semester) === semester;
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

    // Deduplicate by class + subject to avoid duplicated cards on UI.
    const seen = new Set();
    assignmentOfferingsFiltered = assignmentOfferingsFiltered.filter(function(asg) {
        const key = String(asg.classCode || '') + '|' + String(asg.subjectId || asg.subjectCode || '');
        if (!key || seen.has(key)) return false;
        seen.add(key);
        return true;
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

    const subjectMap = {};
    assignmentOfferingsFiltered.forEach(function(asg) {
        const key = String(asg.subjectId || asg.subjectCode || asg.id);
        if (!subjectMap[key]) {
            subjectMap[key] = {
                subjectId: asg.subjectId,
                subjectCode: asg.subjectCode,
                subjectName: asg.subjectName,
                credits: asg.credits,
                year: asg.year,
                semester: asg.semester,
                isOpen: asg.isOpen,
                assignments: []
            };
        }
        subjectMap[key].assignments.push(asg);
    });

    Object.keys(subjectMap).forEach(function(key) {
        const subj = subjectMap[key];
        let rows = [];
        subj.assignments.forEach(function(asg) {
            const groups = (asg.groups && asg.groups.length) ? asg.groups : [{ code: 'N1' }];
            groups.forEach(function(g, groupIndex) {
                rows.push({ asg: asg, g: g, groupIndex: groupIndex });
            });
        });

        const totalRows = rows.length;
        const rowsHtml = rows.map(function(row) {
            const asg = row.asg;
            const g = row.g;
            const groupIndex = row.groupIndex;

            function normalizeTeacherName(name) {
                const text = String(name || '').trim();
                if (!text || text === '—' || text === '--' || text === '-') return '';
                return text;
            }

            const mainTeacher = normalizeTeacherName(g.teacherMainName || getTeacherName(g.teacherMain));
            const subTeacher = normalizeTeacherName(g.teacherSubName || getTeacherName(g.teacherSub));
            const teacherDisplay = (mainTeacher && subTeacher) ? (mainTeacher + ' + ' + subTeacher) : (mainTeacher || subTeacher || '--');
            const teacherHtml = mainTeacher && subTeacher
                ? ('<span class="assignment-col-value assignment-teacher-main">' + mainTeacher + '</span><span class="assignment-teacher-sub">+ ' + subTeacher + '</span>')
                : ('<span class="assignment-col-value">' + teacherDisplay + '</span>');
            const hasSchedule = Boolean(g.day && g.start && g.end && g.room);
            const scheduleDisplay = hasSchedule
                ? (getDayLabel(String(g.day)) + ' | Tiết ' + g.start + '-' + g.end)
                : 'Chưa xếp lịch';
            const roomDisplay = hasSchedule
                ? (g.roomName || (typeof getRoomName === 'function' ? getRoomName(String(g.room)) : String(g.room)))
                : '--';
            const statusDisplay = subj.isOpen ? 'Đang mở' : 'Đã đóng';
            const groupCode = g.code || 'N1';
            const normalizedGroupCode = getGroupCode(groupCode);
            const isFirstGroup = groupIndex === 0 || normalizedGroupCode === 'N1';
            const classSubjectId = resolveClassSubjectId(asg);
            const hasClassSubject = Number.isFinite(classSubjectId) && classSubjectId > 0;
            const isClosedSubject = !subj.isOpen;
            const manageBtn = (hasClassSubject && !isClosedSubject)
                ? '<button class="btn btn-outline-primary" onclick="openSessionManager(\'' + escapeHtml(asg.id) + '\', \'' + escapeHtml(asg.subjectName) + '\', \'' + escapeHtml(mainTeacher || teacherDisplay) + '\', \'' + escapeHtml(groupCode) + '\')"><i class="bi bi-calendar-week me-1"></i>Quản lý lịch</button>'
                : (isClosedSubject
                    ? '<button class="btn btn-secondary" disabled title="Môn đã đóng, không thể xếp lịch"><i class="bi bi-lock me-1"></i>Môn đã đóng</button>'
                    : '<button class="btn btn-primary" onclick="addSubjectClass(\'' + escapeHtml(String(asg.subjectId || '')) + '\', \'' + escapeHtml(asg.subjectName || '') + '\')"><i class="bi bi-plus-square me-1"></i>Xếp lịch ngay</button>');
            const scheduleBtn = (hasClassSubject && !isClosedSubject)
                ? '<button class="btn btn-primary" onclick="openGroupScheduleModal(\'' + classSubjectId + '\', \'' + escapeHtml(asg.subjectName) + '\', \'' + escapeHtml(groupCode) + '\', \'' + escapeHtml(g.teacherMain || '') + '\', \'' + escapeHtml(g.teacherSub || '') + '\', \'' + escapeHtml(g.day || '') + '\', \'' + escapeHtml(g.start || '') + '\', \'' + escapeHtml(g.end || '') + '\', \'' + escapeHtml(g.room || '') + '\', \'' + escapeHtml(asg.classCode || '') + '\')"><i class="bi bi-plus-square me-1"></i>Xếp lịch ngay</button>'
                : (isClosedSubject
                    ? '<button class="btn btn-secondary" disabled title="Môn đã đóng, không thể xếp lịch"><i class="bi bi-lock me-1"></i>Môn đã đóng</button>'
                    : '<button class="btn btn-primary" onclick="addSubjectClass(\'' + escapeHtml(String(asg.subjectId || '')) + '\', \'' + escapeHtml(asg.subjectName || '') + '\')"><i class="bi bi-plus-square me-1"></i>Xếp lịch ngay</button>');
            const primaryActionBtn = hasClassSubject ? ((!hasSchedule && subj.isOpen) ? scheduleBtn : manageBtn) : manageBtn;
            const uploadIconBtn = hasClassSubject
                ? '<button class="btn btn-outline-dark btn-icon-only" title="Tải lên danh sách sinh viên" onclick="uploadAssignmentStudentList(\'' + escapeHtml(asg.id) + '\')"><i class="bi bi-upload"></i></button>'
                : '<button class="btn btn-outline-secondary btn-icon-only" title="Cần xếp lịch trước khi tải lên danh sách sinh viên" disabled><i class="bi bi-upload"></i></button>';
            const downloadIconBtn = asg.hasStudents
                ? (hasClassSubject
                    ? '<button class="btn btn-outline-success btn-icon-only" title="Tải xuống danh sách sinh viên" onclick="downloadAssignmentStudentList(\'' + escapeHtml(asg.id) + '\')"><i class="bi bi-download"></i></button>'
                    : '<button class="btn btn-outline-secondary btn-icon-only" title="Cần xếp lịch trước khi tải xuống danh sách sinh viên" disabled><i class="bi bi-download"></i></button>')
                : (hasClassSubject
                    ? '<button class="btn btn-outline-secondary btn-icon-only" title="Chưa có danh sách sinh viên trong DB" disabled><i class="bi bi-download"></i></button>'
                    : '<button class="btn btn-outline-secondary btn-icon-only" title="Cần xếp lịch trước khi tải xuống danh sách sinh viên" disabled><i class="bi bi-download"></i></button>');
            const deleteGroupBtn = (totalRows > 1 && hasClassSubject && !isFirstGroup)
                ? '<button class="btn btn-outline-danger btn-icon-only assignment-delete-group" title="Xóa nhóm ' + groupCode + ' (total:' + totalRows + ')" onclick="deleteGroupFromClass(\'' + escapeHtml(asg.id) + '\', \'' + escapeHtml(groupCode) + '\', \'' + escapeHtml(asg.subjectName || '') + '\', \'' + escapeHtml(String(asg.subjectId || '')) + '\')"><i class="bi bi-trash"></i></button>'
                : '';
            const actionButtons = primaryActionBtn + '<span class="assignment-action-icons">' + uploadIconBtn + downloadIconBtn + deleteGroupBtn + '</span>';

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

        const firstUnscheduled = rows.find(function(row) { return !(row.g.day && row.g.start && row.g.end && row.g.room); });
        const footerStatus = firstUnscheduled
            ? ('Chưa xếp nhóm ' + getClassGroupLabel(firstUnscheduled.asg.classCode, firstUnscheduled.g.code || 'N1'))
            : 'Đã xếp lịch';
        const totalGroups = rows.length;
        const firstAsg = subj.assignments.find(function(item) { return resolveClassSubjectId(item) > 0; }) || subj.assignments[0] || {};
        const hasClassSubjectInCard = subj.assignments.some(function(item) { return resolveClassSubjectId(item) > 0; });
        const actionDisabled = subj.isOpen ? '' : ' disabled';
        const actionTitle = '';
        const addGroupAction = hasClassSubjectInCard
            ? "addGroupToClass('" + escapeHtml(firstAsg.id || '') + "', '" + escapeHtml(subj.subjectName || '') + "')"
            : "addSubjectClass('" + escapeHtml(String(subj.subjectId || '')) + "', '" + escapeHtml(subj.subjectName || '') + "')";

        const card = document.createElement('div');
        card.className = 'assignment-offering-card';
        card.innerHTML = '' +
            '<div class="assignment-offering-head">' +
                '<div>' +
                    '<div class="assignment-offering-title">' + (subj.subjectCode || '--') + ' - ' + (subj.subjectName || '') + '</div>' +
                    '<div class="assignment-offering-subtitle">' + (subj.credits || 0) + ' tín chỉ | ' + getSemesterDisplay(subj.semester) + ' | Năm học: ' + (subj.year || '--') + '</div>' +
                '</div>' +
                '<div class="assignment-head-actions">' +
                    '<button class="btn btn-outline-primary" onclick="' + addGroupAction + '"' + actionDisabled + actionTitle + '><i class="bi bi-plus-circle me-1"></i>Thêm nhóm</button>' +
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
                '<span>Số nhóm: ' + totalGroups + ' | Thời gian mở: ' + (firstAsg.openWindow || '--') + ' | ' + footerStatus + '</span>' +
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

    const csId = resolveClassSubjectId(course);
    if (!csId) {
        if (window.showToast) window.showToast('Môn này chưa có lớp học phần. Vui lòng xếp lịch trước khi tải lên.', 'warning');
        else alert('Môn này chưa có lớp học phần. Vui lòng xếp lịch trước khi tải lên.');
        return;
    }

    pendingAssignmentUploadClass = courseId;
    const guideModalEl = document.getElementById('studentUploadGuideModal');
    if (guideModalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(guideModalEl).show();
        return;
    }
    triggerAssignmentFilePicker();
}

function parseAssignmentCsv(content) {
    const lines = content.replace(/\r/g, '').split('\n').filter(function (line) {
        return line.trim().length > 0;
    });
    if (lines.length < 1) {
        return [];
    }

    function normalizeCsvDate(value) {
        const raw = String(value || '').trim();
        if (raw === '') return '';
        if (/^\d+$/.test(raw)) {
            const serial = parseInt(raw, 10);
            if (serial > 20000) {
                const base = new Date(Date.UTC(1899, 11, 30));
                base.setUTCDate(base.getUTCDate() + serial);
                const dd = String(base.getUTCDate()).padStart(2, '0');
                const mm = String(base.getUTCMonth() + 1).padStart(2, '0');
                const yyyy = base.getUTCFullYear();
                return dd + '/' + mm + '/' + yyyy;
            }
        }
        return raw;
    }

    function parseByColumns(cols) {
        const c = cols.map(function (item) { return String(item || '').trim(); });
        const hasSttFirst = /^\d*$/.test(c[0] || '') && (c[1] || '') !== '';
        const mssv = hasSttFirst ? (c[1] || '') : (c[0] || '');
        const name = hasSttFirst ? (c[2] || '') : (c[1] || '');
        const dob = normalizeCsvDate(hasSttFirst ? (c[3] || '') : (c[2] || ''));
        const className = hasSttFirst ? (c[4] || '') : (c[3] || '');
        if (!mssv || !name) return null;
        return { mssv: mssv, name: name, dob: dob, className: className, section: 'N1' };
    }

    const parsed = [];
    let startIndex = 0;
    const first = lines[0].toLowerCase();
    if (first.includes('mssv') || first.includes('họ và tên') || first.includes('ho va ten')) {
        startIndex = 1;
    }

    for (let i = startIndex; i < lines.length; i += 1) {
        const cols = lines[i].split(',');
        const row = parseByColumns(cols);
        if (row) parsed.push(row);
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
    if (!rows || rows.length < 1) {
        return [];
    }

    function normalizeXlsxDate(value) {
        if (value === null || value === undefined || value === '') return '';
        if (typeof value === 'number' && value > 20000) {
            const date = XLSX.SSF.parse_date_code(value);
            if (date && date.d && date.m && date.y) {
                return String(date.d).padStart(2, '0') + '/' + String(date.m).padStart(2, '0') + '/' + String(date.y);
            }
        }
        return String(value).trim();
    }

    function parseByColumns(cols) {
        const c = cols.map(function (item) { return String(item || '').trim(); });
        const hasSttFirst = /^\d*$/.test(c[0] || '') && (c[1] || '') !== '';
        const mssv = hasSttFirst ? (c[1] || '') : (c[0] || '');
        const name = hasSttFirst ? (c[2] || '') : (c[1] || '');
        const dob = normalizeXlsxDate(hasSttFirst ? cols[3] : cols[2]);
        const className = hasSttFirst ? (c[4] || '') : (c[3] || '');
        if (!mssv || !name) return null;
        return { mssv: mssv, name: name, dob: dob, className: className, section: 'N1' };
    }

    const parsed = [];
    let startIndex = 0;
    if (rows[0] && rows[0].length) {
        const firstLine = rows[0].map(function (x) { return String(x || '').toLowerCase(); }).join(' ');
        if (firstLine.includes('mssv') || firstLine.includes('họ và tên') || firstLine.includes('ho va ten')) {
            startIndex = 1;
        }
    }
    for (let i = startIndex; i < rows.length; i += 1) {
        const parsedRow = parseByColumns(rows[i] || []);
        if (parsedRow) parsed.push(parsedRow);
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

        const course = allAssignmentCourses.find(function (c) { return c.id === pendingAssignmentUploadClass; });
        if (!course) {
            alert('Không xác định được lớp học phần để import.');
            return;
        }

        const csId = resolveClassSubjectId(course);
        if (!csId) {
            alert('Không xác định được class_subject_id để import.');
            return;
        }

        if (!window.callApi) {
            alert('Không thể kết nối API import.');
            return;
        }

        window.callApi('import_group_students', {
            class_subject_id: csId,
            group_code: 'N1',
            students: JSON.stringify(parsed)
        })
            .then(function(res) {
                if (res && res.ok) {
                    if (window.showToast) {
                        const imported = typeof res.imported === 'number' ? res.imported : parsed.length;
                        const skipped = typeof res.skipped === 'number' ? res.skipped : 0;
                        window.showToast('Import thành công: ' + imported + ' SV, bỏ qua: ' + skipped + '.', 'success');
                    } else {
                        alert('Đã import danh sách sinh viên thành công.');
                    }
                    pendingAssignmentUploadClass = '';
                    markStudentsImportedByCsId(csId, res ? res.imported : parsed.length);
                    if (typeof applyAssignmentFilters === 'function') {
                        applyAssignmentFilters();
                    }
                    return;
                }
                alert('Import thất bại. Vui lòng thử lại.');
            })
            .catch(function() {
                alert('Lỗi kết nối server khi import danh sách sinh viên.');
            });
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
    const course = allAssignmentCourses.find(function (c) { return c.id === courseId; });
    if (!course) {
        alert('Không tìm thấy lớp học phần.');
        return;
    }

    const csId = resolveClassSubjectId(course);
    if (!csId) {
        if (window.showToast) window.showToast('Môn này chưa có lớp học phần. Vui lòng xếp lịch trước khi tải xuống.', 'warning');
        else alert('Môn này chưa có lớp học phần. Vui lòng xếp lịch trước khi tải xuống.');
        return;
    }

    fetch('/cms/controllers/admin/classSubjectController.php?action=export_group_students&class_subject_id=' + encodeURIComponent(csId), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            const students = (data && data.ok && Array.isArray(data.students)) ? data.students : [];
            if (!students.length) {
                if (window.showToast) window.showToast('Chưa có danh sách sinh viên trong DB để tải xuống.', 'warning');
                else alert('Chưa có danh sách sinh viên trong DB để tải xuống.');
                return;
            }

            const classCode = course.classCode || courseId;
            const csv = [];
            csv.push(['STT', 'MSSV', 'Họ và tên', 'Ngày sinh', 'Lớp'].join(','));
            students.forEach(function(student, index) {
                const mssv = student.username || student.mssv || '';
                const name = student.full_name || '';
                const dob = student.birth_date || '';
                const className = student.class_name || '';
                csv.push([
                    String(index + 1),
                    mssv,
                    name,
                    dob,
                    className
                ].join(','));
            });

            const link = document.createElement('a');
            link.href = 'data:text/csv;charset=utf-8,%EF%BB%BF' + encodeURIComponent(csv.join('\n'));
            link.download = classCode + '_DanhSachSV.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        })
        .catch(function() {
            if (window.showToast) window.showToast('Lỗi khi tải danh sách sinh viên từ DB.', 'error');
            else alert('Lỗi khi tải danh sách sinh viên từ DB.');
        });
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

function openInitialScheduleModal(mode, csId, classCode, subjectName, teacher = '', startDate = '', endDate = '', dayOfWeek = '', startPeriod = '', endPeriod = '', room = '', assistant = '', group = 'N1', subjectId = '') {
    window._currentCsId = csId;
    window._currentSubjectId = subjectId || '';
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
    const course = findAssignmentCourse(parsedCsId);
    if (course && !course.isOpen) {
        if (window.showToast) window.showToast('Môn học đã đóng, không thể xếp lịch.', 'warning');
        else alert('Môn học đã đóng, không thể xếp lịch.');
        return;
    }
    openInitialScheduleModal('edit', parsedCsId, classCode || parsedCsId, subjectName, teacherId, '', '', dayOfWeek, startPeriod, endPeriod, room, assistantId, groupCode);
}

function handleInitialScheduleSubmit(event) {
    event.preventDefault();

    const currentCsId = parseInt(window._currentCsId, 10);
    const currentSubjectId = window._currentSubjectId || '';
    if ((Number.isFinite(currentCsId) && currentCsId > 0 && (findAssignmentCourse(currentCsId)?.isOpen === false))
        || (currentSubjectId && !isSubjectOpen(currentSubjectId))) {
        if (window.showToast) window.showToast('Môn học đã đóng, không thể xếp lịch.', 'warning');
        else alert('Môn học đã đóng, không thể xếp lịch.');
        return false;
    }

    const start = parseInt(document.getElementById('initStartPeriod').value, 10);
    const end = parseInt(document.getElementById('initEndPeriod').value, 10);

    const initTeacherEl = document.getElementById('initTeacher');
    const teacherMainId = String((initTeacherEl && initTeacherEl.value) || '').trim();
    const teacherExists = (window.teachers || teachers || []).some(function (t) {
        return String(t.id) === teacherMainId;
    });
    if (!teacherMainId || !teacherExists) {
        if (initTeacherEl) {
            initTeacherEl.focus();
        }
        if (window.showToast) window.showToast('Vui lòng chọn Giảng viên chính.', 'warning');
        else alert('Vui lòng chọn Giảng viên chính.');
        return false;
    }

    if (start >= end) {
        if (window.showToast) window.showToast('Lỗi: Tiết bắt đầu phải diễn ra TRƯỚC tiết kết thúc!', 'error');
        else alert('Lỗi: Tiết bắt đầu phải diễn ra TRƯỚC tiết kết thúc!');
        return false;
    }

    const payload = {
        class_subject_id: window._currentCsId,
        subject_id: window._currentSubjectId || '',
        class_code: document.getElementById('initClassCode') ? document.getElementById('initClassCode').value : '',
        semester_name: document.getElementById('assignFilterSemester') ? document.getElementById('assignFilterSemester').value : 'all',
        academic_year: document.getElementById('assignFilterYear') ? document.getElementById('assignFilterYear').value : 'all',
        group_code: window._currentGroupCode || 'N1',
        teacher_main_id: teacherMainId,
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
                    upsertScheduleIntoClientData(payload, res || {});
                    if (res.class_subject_id) {
                        window._currentCsId = String(res.class_subject_id);
                    }
                    window.showToast('Đã lưu lịch giảng dạy thành công!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('initialScheduleModal')).hide();
                    if (typeof applyAssignmentFilters === 'function') {
                        applyAssignmentFilters();
                    }
                    if (typeof renderMasterSchedule === 'function') {
                        renderMasterSchedule();
                    }
                } else {
                    let msg = 'Có lỗi xảy ra khi lưu lịch.';
                    if (res.message === 'conflict_room') msg = res.detail || 'Phòng học đã bị trùng trong thời gian này.';
                    else if (res.message === 'conflict_teacher') msg = res.detail || 'Giảng viên đã bị trùng lịch trong thời gian này.';
                    else if (res.message === 'subject_closed') msg = 'Môn học đã đóng, không thể xếp lịch.';
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
        targetGroup && targetGroup.code ? targetGroup.code : '01',
        course.csId || ''
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
                group.code,
                course.csId || ''
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

function openEditSingleSession(mode, dateStr, dateVal, day, start, end, room, status, classCode, subjectName, teacherId, group = '01', csId = '') {
    const normalizedGroup = getGroupCode(group || 'N1');
    const normalizedDate = dateVal || ((mode === 'add') ? getNearestDateByDay(day) : '');
    currentSingleSessionContext = {
        mode: mode || 'edit',
        classCode: classCode || '',
        subjectName: subjectName || '',
        groupCode: normalizedGroup,
        csId: csId || '',
        originalDay: day ? String(day) : ''
    };

    document.getElementById('qsSubjectInfo').innerText = subjectName;
    document.getElementById('qsClassCode').innerHTML = '<i class="bi bi-tags-fill me-1 text-muted"></i>Lớp: ' + classCode;
    document.getElementById('qsGroup').innerText = 'Lớp - Nhóm: ' + getClassGroupLabel(classCode, normalizedGroup);
    document.getElementById('singleDate').value = normalizedDate;
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
        if (btnDelete) btnDelete.classList.add('d-none');
    } else if (mode === 'edit_master') {
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Sửa lịch ngày ' + dateStr;
        if (btnDelete) btnDelete.classList.add('d-none');
    } else {
        document.getElementById('singleSessionTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Sửa lịch ngày ' + dateStr;
        if (btnDelete) btnDelete.classList.remove('d-none');
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
    const context = currentSingleSessionContext || {};
    const course = resolveSingleSessionCourse(context);
    const classSubjectId = parseInt((course && course.csId) || context.csId, 10);
    if (!Number.isFinite(classSubjectId) || classSubjectId <= 0) {
        if (window.showToast) window.showToast('Không xác định được lớp học phần để lưu lịch.', 'error');
        else alert('Không xác định được lớp học phần để lưu lịch.');
        return;
    }

    if (course && course.isOpen === false) {
        if (window.showToast) window.showToast('Môn học đã đóng, không thể cập nhật lịch.', 'warning');
        else alert('Môn học đã đóng, không thể cập nhật lịch.');
        return;
    }

    const dateValue = (document.getElementById('singleDate').value || '').trim();
    let dayOfWeek = getDayOfWeekFromDate(dateValue);
    if (!dayOfWeek && context.originalDay) {
        dayOfWeek = String(context.originalDay).trim();
    }
    if (!dayOfWeek) {
        if (window.showToast) window.showToast('Ngày học không hợp lệ. Vui lòng chọn lại ngày học.', 'error');
        else alert('Ngày học không hợp lệ. Vui lòng chọn lại ngày học.');
        return;
    }

    const start = parseInt(document.getElementById('singleStart').value, 10);
    const end = parseInt(document.getElementById('singleEnd').value, 10);
    if (!Number.isFinite(start) || !Number.isFinite(end) || start <= 0 || end <= 0) {
        if (window.showToast) window.showToast('Vui lòng chọn đầy đủ tiết bắt đầu và tiết kết thúc.', 'warning');
        else alert('Vui lòng chọn đầy đủ tiết bắt đầu và tiết kết thúc.');
        return;
    }
    if (start >= end) {
        if (window.showToast) window.showToast('Tiết bắt đầu phải nhỏ hơn tiết kết thúc.', 'warning');
        else alert('Tiết bắt đầu phải nhỏ hơn tiết kết thúc.');
        return;
    }

    const teacherMainId = (document.getElementById('singleTeacher').value || '').trim();
    if (!teacherMainId) {
        if (window.showToast) window.showToast('Vui lòng chọn giảng viên phụ trách.', 'warning');
        else alert('Vui lòng chọn giảng viên phụ trách.');
        return;
    }

    const room = (document.getElementById('singleRoom').value || '').trim();
    if (!room) {
        if (window.showToast) window.showToast('Vui lòng chọn phòng học.', 'warning');
        else alert('Vui lòng chọn phòng học.');
        return;
    }

    const status = document.getElementById('singleStatus').value;
    if (status === 'canceled' && document.getElementById('cancelReason').value.trim() === '') {
        if (window.showToast) window.showToast('Vui lòng nhập lý do báo hủy.', 'warning');
        else alert('Vui lòng nhập lý do báo hủy.');
        document.getElementById('cancelReason').focus();
        return;
    }

    const groupCode = context.groupCode || currentSessionGroupCode || 'N1';
    let teacherSubId = '';
    if (course && Array.isArray(course.groups)) {
        const group = course.groups.find(function (g) { return getGroupCode(g.code) === getGroupCode(groupCode); });
        if (group && group.teacherSub) {
            teacherSubId = String(group.teacherSub);
        }
    }

    const payload = {
        class_subject_id: classSubjectId,
        group_code: groupCode,
        teacher_main_id: teacherMainId,
        teacher_sub_id: teacherSubId,
        day_of_week: dayOfWeek,
        start_period: String(start),
        end_period: String(end),
        room: room
    };

    if (!window.callApi) {
        if (window.showToast) window.showToast('Không thể kết nối API lưu lịch.', 'error');
        else alert('Không thể kết nối API lưu lịch.');
        return;
    }

    window.callApi('save_group_schedule', payload)
        .then(function (res) {
            if (!res || !res.ok) {
                let msg = 'Không thể lưu thay đổi lịch học.';
                if (res && res.message === 'conflict_room') msg = res.detail || 'Phòng học bị trùng lịch.';
                else if (res && res.message === 'conflict_teacher') msg = res.detail || 'Giảng viên bị trùng lịch.';
                else if (res && res.message === 'subject_closed') msg = 'Môn học đã đóng, không thể cập nhật lịch.';
                else if (res && res.message === 'invalid_schedule_data') msg = 'Dữ liệu lịch chưa hợp lệ. Vui lòng kiểm tra lại ngày, tiết và phòng.';
                if (window.showToast) window.showToast(msg, 'error');
                else alert(msg);
                return;
            }

            if (course && Array.isArray(course.groups)) {
                const target = course.groups.find(function (g) { return getGroupCode(g.code) === getGroupCode(groupCode); });
                if (target) {
                    target.day = String(dayOfWeek);
                    target.start = String(start);
                    target.end = String(end);
                    target.room = room;
                    target.teacherMain = String(teacherMainId);
                }
            }
            if (Array.isArray(window.masterScheduleCourses)) {
                const masterCourse = window.masterScheduleCourses.find(function (c) { return parseInt(c.csId, 10) === classSubjectId; });
                if (masterCourse && Array.isArray(masterCourse.groups)) {
                    const masterGroup = masterCourse.groups.find(function (g) { return getGroupCode(g.code) === getGroupCode(groupCode); });
                    if (masterGroup) {
                        masterGroup.day = String(dayOfWeek);
                        masterGroup.start = String(start);
                        masterGroup.end = String(end);
                        masterGroup.room = room;
                        masterGroup.teacherMain = String(teacherMainId);
                    }
                }
            }

            if (window.showToast) window.showToast('Đã lưu thay đổi lịch học vào hệ thống.', 'success');

            const singleModal = bootstrap.Modal.getInstance(document.getElementById('singleSessionModal'));
            if (singleModal) singleModal.hide();

            if (currentSessionCourse) {
                renderSessionManagerRows(currentSessionCourse, currentSessionGroupCode);
            }
            if (typeof renderMasterSchedule === 'function') {
                renderMasterSchedule();
            }
            if (typeof applyAssignmentFilters === 'function') {
                applyAssignmentFilters();
            }
        })
        .catch(function () {
            if (window.showToast) window.showToast('Lỗi kết nối đến server khi lưu lịch.', 'error');
            else alert('Lỗi kết nối đến server khi lưu lịch.');
        });
}

function deleteSingleSession() {
    const context = currentSingleSessionContext || {};
    if (context.mode === 'edit_master') {
        if (window.showToast) window.showToast('Ở Thời khóa biểu tổng chỉ được sửa lịch ngày, không được xóa.', 'warning');
        else alert('Ở Thời khóa biểu tổng chỉ được sửa lịch ngày, không được xóa.');
        return;
    }

    if (confirm('⚠️ NGUY HIỂM: Bạn có chắc chắn muốn xóa hẳn buổi học này ra khỏi cơ sở dữ liệu không?\nHành động này không thể hoàn tác!')) {
        alert('✅ Đã xóa buổi học thành công!');
        bootstrap.Modal.getInstance(document.getElementById('singleSessionModal')).hide();
    }
}

function addGroupToClass(courseId, subjectName) {
    let course = findAssignmentCourse(courseId);
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
                const maxNum = (course.groups || []).reduce(function(mx, g) {
                    const m = String(g.code || '').match(/^N(\d+)$/i);
                    const n = m ? parseInt(m[1], 10) : 0;
                    return n > mx ? n : mx;
                }, 0);
                const newGroupCode = (res && res.group_code) ? getGroupCode(res.group_code) : ('N' + (maxNum + 1));
                appendGroupToClassSubject(csId, newGroupCode);
                if (typeof applyAssignmentFilters === 'function') {
                    applyAssignmentFilters();
                }
                return;
            }
            alert('Không thể thêm nhóm. Vui lòng thử lại.');
        })
        .catch(function() {
            alert('Lỗi kết nối server khi thêm nhóm.');
        });
}

function deleteGroupFromClass(courseId, groupCode, subjectName, subjectId) {
    let course = allAssignmentCourses.find(function(c) { return c.id === courseId; }) || null;
    if (!course) {
        alert('Không tìm thấy môn học.');
        return;
    }

    // Tổng số nhóm của toàn bộ cùng môn (gộp nhiều lớp), phải giữ lại ít nhất 1.
    const totalGroupsInSubject = allAssignmentCourses.reduce(function(sum, c) {
        const sameSubject = String(c.subjectId || '') === String(subjectId || '');
        if (!sameSubject) return sum;
        return sum + ((c.groups && c.groups.length) ? c.groups.length : 0);
    }, 0);
    if (totalGroupsInSubject <= 1) {
        alert('Mỗi môn học phải có ít nhất 1 nhóm, không thể xóa nhóm cuối cùng của môn.');
        return;
    }

    // Không cho phép xóa nhóm mặc định N1
    const normalizedCode = getGroupCode(groupCode);
    if (normalizedCode === 'N1') {
        alert('Không thể xóa nhóm mặc định đầu tiên (N1). Vui lòng thêm nhóm mới trước khi xóa nhóm này.');
        return;
    }

    const csId = resolveClassSubjectId(course);
    if (!csId) {
        alert('Không xác định được lớp học phần để xóa nhóm.');
        return;
    }

    const confirmMsg = 'Xóa nhóm ' + getGroupCode(groupCode) + ' của môn ' + (subjectName || '') + '?';
    if (!confirm(confirmMsg)) {
        return;
    }

    if (!window.callApi) {
        alert('Không thể kết nối API xóa nhóm.');
        return;
    }

    window.callApi('delete_group', {
        class_subject_id: csId,
        group_code: getGroupCode(groupCode)
    })
        .then(function(res) {
            if (res && res.ok) {
                const removedStudentCount = Math.max(0, parseInt((res && res.deleted_student_count) || 0, 10) || 0);
                if (window.showToast) {
                    const msg = removedStudentCount > 0
                        ? ('Đã xóa nhóm thành công. Đã xóa ' + removedStudentCount + ' sinh viên thuộc nhóm trong DB.')
                        : 'Đã xóa nhóm thành công.';
                    window.showToast(msg, 'success');
                }
                removeGroupFromClassSubject(csId, (res && res.group_code) || groupCode);
                decrementStudentsByCsId(csId, removedStudentCount);
                if (typeof applyAssignmentFilters === 'function') {
                    applyAssignmentFilters();
                }
                if (typeof renderMasterSchedule === 'function') {
                    renderMasterSchedule();
                }
                return;
            }
            let msg = 'Không thể xóa nhóm. Vui lòng thử lại.';
            if (res && res.message === 'cannot_delete_default_group') {
                msg = 'Không thể xóa nhóm mặc định đầu tiên (N1). Vui lòng thêm nhóm mới trước khi xóa.';
            }
            if (window.showToast) window.showToast(msg, 'error');
            else alert(msg);
        })
        .catch(function() {
            if (window.showToast) window.showToast('Lỗi kết nối server khi xóa nhóm.', 'error');
            else alert('Lỗi kết nối server khi xóa nhóm.');
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
    if (!isSubjectOpen(subjectId)) {
        alert('Môn học đã đóng, không thể thêm lớp HP mới.');
        return;
    }
    // Open modal to create class_subject + first schedule/group for this subject
    openInitialScheduleModal('add', '', '', subjectName, '', '', '', '', '', '', '', '', 'N1', subjectId);
}
