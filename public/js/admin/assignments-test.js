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

const rooms = ['PLAB', 'PM3', 'A201', 'B102', 'C301'];

const allOfferingsData = [
    {
        id: '25TH01-CSDL',
        name: 'An ninh Cơ sở dữ liệu',
        year: '2025',
        semester: '1',
        isOpen: true,
        credits: 3,
        sections: [
            { code: 'N1', teacherMain: 'GV01', teacherSub: '', day: '2', start: 6, end: 10, room: 'PLAB', note: 'Nhóm chiều' },
            { code: 'N2', teacherMain: 'GV02', teacherSub: '', day: '2', start: 6, end: 10, room: 'PM3', note: 'Trùng giờ, khác phòng' }
        ]
    },
    {
        id: '25TH01-WEB',
        name: 'Lập trình Web nâng cao',
        year: '2025',
        semester: '1',
        isOpen: true,
        credits: 4,
        sections: [
            { code: 'N1', teacherMain: 'GV01', teacherSub: 'GV04', day: '5', start: 1, end: 5, room: 'PM3', note: 'Đồng giảng dạy' },
            { code: 'N2', teacherMain: 'GV03', teacherSub: '', day: '5', start: 1, end: 5, room: 'A201', note: 'Nhóm sáng' }
        ]
    },
    {
        id: '25TH01-JAVA',
        name: 'Lập trình Java nâng cao',
        year: '2025',
        semester: '2',
        isOpen: false,
        credits: 3,
        sections: [
            { code: 'N1', teacherMain: 'GV02', teacherSub: '', day: '3', start: 2, end: 5, room: 'A201', note: '' }
        ]
    },
    {
        id: '24TH02-AI',
        name: 'Trí tuệ nhân tạo',
        year: '2024',
        semester: '2',
        isOpen: true,
        credits: 3,
        sections: [
            { code: 'N1', teacherMain: 'GV03', teacherSub: '', day: '4', start: 1, end: 4, room: 'C301', note: '' }
        ]
    },
    {
        id: '26TH01-IOT',
        name: 'Hệ thống IoT',
        year: '2026',
        semester: '1',
        isOpen: false,
        credits: 3,
        sections: [
            { code: 'N1', teacherMain: 'GV04', teacherSub: '', day: '6', start: 6, end: 9, room: 'PLAB', note: '' }
        ]
    }
];

const sampleStudentsByOffering = {
    '25TH01-CSDL': [
        { mssv: '2050001', name: 'Nguyễn Văn A', dob: '2005-01-01', email: 'a.nguyen@student.bdu.edu.vn', section: 'N1' },
        { mssv: '2050002', name: 'Trần Thị B', dob: '2005-02-02', email: 'b.tran@student.bdu.edu.vn', section: 'N1' },
        { mssv: '2050003', name: 'Lê Minh C', dob: '2005-03-03', email: 'c.le@student.bdu.edu.vn', section: 'N2' }
    ],
    '25TH01-WEB': [
        { mssv: '2050101', name: 'Phạm Quốc D', dob: '2005-04-01', email: 'd.pham@student.bdu.edu.vn', section: 'N1' },
        { mssv: '2050102', name: 'Võ Thảo E', dob: '2005-05-12', email: 'e.vo@student.bdu.edu.vn', section: 'N2' }
    ],
    '25TH01-JAVA': [
        { mssv: '2050201', name: 'Bùi Gia F', dob: '2005-08-08', email: 'f.bui@student.bdu.edu.vn', section: 'N1' }
    ],
    '24TH02-AI': [
        { mssv: '1950301', name: 'Ngô Minh G', dob: '2004-11-10', email: 'g.ngo@student.bdu.edu.vn', section: 'N1' }
    ]
};

let currentFilter = { year: '2025', semester: '1' };
let offerings = [];
let editing = { offeringId: '', sectionCode: '' };
let sectionModal;
let pendingUploadOfferingId = '';

document.addEventListener('DOMContentLoaded', function () {
    sectionModal = new bootstrap.Modal(document.getElementById('sectionModal'));

    populateSelect('fTeacherMain', teachers.map(function (t) { return { value: t.id, label: t.name }; }));
    populateSelect('fTeacherSub', [{ value: '', label: '-- Không có --' }].concat(teachers.map(function (t) { return { value: t.id, label: t.name }; })));
    populateSelect('fDay', days.map(function (d) { return { value: d.value, label: d.label }; }));
    populateSelect('fStart', buildPeriodOptions());
    populateSelect('fEnd', buildPeriodOptions());
    populateSelect('fRoom', rooms.map(function (r) { return { value: r, label: r }; }));

    document.getElementById('btnSaveSection').addEventListener('click', saveSection);
    document.getElementById('btnExpandAll').addEventListener('click', function () { toggleAll(true); });
    document.getElementById('btnCollapseAll').addEventListener('click', function () { toggleAll(false); });
    document.getElementById('filterYear').addEventListener('change', applyFilter);
    document.getElementById('filterSemester').addEventListener('change', applyFilter);
    document.getElementById('studentUploadInput').addEventListener('change', handleStudentUploadChange);

    applyFilter();
});

function applyFilter() {
    const year = document.getElementById('filterYear').value;
    const semester = document.getElementById('filterSemester').value;

    currentFilter = { year: year, semester: semester };
    offerings = allOfferingsData.filter(function (o) {
        const yearMatched = year === 'all' || o.year === year;
        const semesterMatched = semester === 'all' || o.semester === semester;
        return yearMatched && semesterMatched;
    });

    const statusEl = document.getElementById('filterStatus');
    const statusText = document.getElementById('filterStatusText');

    statusEl.classList.remove('d-none');
    const openCount = offerings.filter(function (o) { return o.isOpen; }).length;
    const closedCount = offerings.length - openCount;
    statusText.textContent = 'Tổng ' + offerings.length + ' môn | Đang mở: ' + openCount + ' | Đã đóng: ' + closedCount + '.';

    render();
}

function uploadStudentList(offeringId) {
    const offering = offerings.find(function (o) { return o.id === offeringId; });
    if (!offering) {
        alert('Không tìm thấy môn học trong bộ lọc hiện tại.');
        return;
    }

    if (!offering.isOpen) {
        alert('Môn học đang ở trạng thái Đã đóng, không thể tải lên danh sách sinh viên.');
        return;
    }

    pendingUploadOfferingId = offeringId;
    const uploadInput = document.getElementById('studentUploadInput');
    uploadInput.value = '';
    uploadInput.click();
}

function parseCsvData(content) {
    const lines = content.replace(/\r/g, '').split('\n').filter(function (line) {
        return line.trim().length > 0;
    });

    if (lines.length < 2) {
        return [];
    }

    const students = [];
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

        students.push({
            mssv: mssv,
            name: name,
            dob: dob,
            email: email,
            section: section
        });
    }

    return students;
}

function parseXlsxData(arrayBuffer) {
    if (typeof XLSX === 'undefined') {
        return [];
    }

    const workbook = XLSX.read(arrayBuffer, { type: 'array' });
    if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
        return [];
    }

    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });
    if (!rows || rows.length < 2) {
        return [];
    }

    const students = [];
    for (let i = 1; i < rows.length; i += 1) {
        const row = rows[i].map(function (cell) {
            return String(cell).trim();
        });

        const mssv = row[1] || '';
        const name = row[2] || '';
        const dob = row[3] || '';
        const email = row[4] || '';
        const section = row[6] || row[5] || 'N1';

        if (!mssv || !name) {
            continue;
        }

        students.push({
            mssv: mssv,
            name: name,
            dob: dob,
            email: email,
            section: section
        });
    }

    return students;
}

function handleStudentUploadChange(event) {
    const file = event.target.files && event.target.files[0];
    if (!file || !pendingUploadOfferingId) {
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
        let parsedStudents = [];
        if (isCsv) {
            const text = typeof e.target.result === 'string' ? e.target.result : '';
            parsedStudents = parseCsvData(text);
        } else {
            const buffer = e.target.result;
            parsedStudents = parseXlsxData(buffer);
        }

        if (parsedStudents.length === 0) {
            alert('Không đọc được dữ liệu sinh viên. Kiểm tra lại định dạng cột trong file.');
            return;
        }

        sampleStudentsByOffering[pendingUploadOfferingId] = parsedStudents;
        alert('Tải lên thành công ' + parsedStudents.length + ' sinh viên cho môn ' + pendingUploadOfferingId + '.');
        pendingUploadOfferingId = '';
        render();
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

function downloadStudentList(offeringId) {
    const offering = offerings.find(function (o) { return o.id === offeringId; });
    if (!offering) {
        alert('Không tìm thấy môn học trong bộ lọc hiện tại.');
        return;
    }

    const rows = sampleStudentsByOffering[offeringId] || [];
    if (rows.length === 0) {
        return;
    }

    const csvLines = [];
    csvLines.push(['STT', 'MSSV', 'Họ và tên', 'Ngày sinh', 'Email', 'Mã môn', 'Nhóm học'].join(','));
    rows.forEach(function (student, idx) {
        csvLines.push([
            String(idx + 1),
            student.mssv,
            student.name,
            student.dob,
            student.email,
            offering.id,
            student.section
        ].join(','));
    });

    const element = document.createElement('a');
    element.setAttribute('href', 'data:text/csv;charset=utf-8,%EF%BB%BF' + encodeURIComponent(csvLines.join('\n')));
    element.setAttribute('download', offering.id + '_DanhSachSV_' + currentFilter.year + '_HK' + currentFilter.semester + '.csv');
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
}

function buildPeriodOptions() {
    const out = [];
    for (let i = 1; i <= 14; i += 1) {
        out.push({ value: String(i), label: 'Tiết ' + i });
    }
    return out;
}

function populateSelect(id, options) {
    const select = document.getElementById(id);
    select.innerHTML = '';
    options.forEach(function (option) {
        const el = document.createElement('option');
        el.value = option.value;
        el.textContent = option.label;
        select.appendChild(el);
    });
}

function getTeacherName(id) {
    const found = teachers.find(function (t) { return t.id === id; });
    return found ? found.name : '--';
}

function getDayLabel(day) {
    const found = days.find(function (d) { return d.value === day; });
    return found ? found.label : '--';
}

function render() {
    const conflicts = computeConflicts();
    const container = document.getElementById('offeringContainer');
    container.innerHTML = '';

    if (offerings.length === 0) {
        container.innerHTML = '<div class="alert alert-light border">Không có môn học nào trong bộ lọc đã chọn.</div>';
        renderConflictBanner({});
        return;
    }

    offerings.forEach(function (offering) {
        const card = document.createElement('div');
        card.className = 'offering-card';

        const head = document.createElement('div');
        head.className = 'offering-head d-flex justify-content-between align-items-center';

        const titleDiv = document.createElement('div');
        titleDiv.innerHTML = '<div class="offering-title">' + offering.id + ' - ' + offering.name + '</div><div class="small text-muted">Quản lý theo nhóm học phần | ' + offering.credits + ' tín chỉ</div>';
        head.appendChild(titleDiv);
        head.appendChild(attachActions(offering.id));
        card.appendChild(head);

        offering.sections.forEach(function (section) {
            const key = offering.id + '::' + section.code;
            const conflictLabel = conflicts[key] || '';
            const courseStatus = offering.isOpen ? 'Đang mở' : 'Đã đóng';
            const row = document.createElement('div');
            row.className = 'section-row';
            row.innerHTML =
                '<div><span class="section-chip ' + (conflictLabel ? 'section-conflict' : '') + '">' + section.code + '</span></div>' +
                '<div><div class="section-label">Giảng viên</div><div class="section-value">' + getTeacherName(section.teacherMain) + (section.teacherSub ? ' + ' + getTeacherName(section.teacherSub) : '') + '</div></div>' +
                '<div><div class="section-label">Lịch học</div><div class="section-value">' + getDayLabel(section.day) + ' | Tiết ' + section.start + '-' + section.end + '</div></div>' +
                '<div><div class="section-label">Phòng</div><div class="section-value">' + section.room + '</div></div>' +
                '<div><div class="section-label">Trạng thái</div><div class="section-value">' + courseStatus + (conflictLabel ? ' | ' + conflictLabel : '') + '</div></div>' +
                '<div class="section-actions"><button class="btn btn-sm btn-outline-secondary" data-edit="' + offering.id + '::' + section.code + '"><i class="bi bi-pencil-square"></i></button></div>';
            card.appendChild(row);
        });

        const foot = document.createElement('div');
        foot.className = 'offering-foot small text-muted';
        foot.textContent = 'Số nhóm: ' + offering.sections.length + ' | Có thể cho trùng lịch nếu khác giảng viên/khác phòng';
        card.appendChild(foot);

        container.appendChild(card);
    });

    bindRowEvents();
    renderConflictBanner(conflicts);
}

function attachActions(offeringId) {
    const btnDiv = document.createElement('div');
    btnDiv.className = 'd-flex gap-2';
    const rows = sampleStudentsByOffering[offeringId] || [];
    const canDownload = rows.length > 0;

    const btnUpload = document.createElement('button');
    btnUpload.className = 'btn btn-sm btn-outline-dark';
    btnUpload.innerHTML = '<i class="bi bi-upload me-1"></i>Tải lên SV';
    btnUpload.addEventListener('click', function () { uploadStudentList(offeringId); });

    const btnDownload = document.createElement('button');
    btnDownload.className = 'btn btn-sm btn-outline-success' + (canDownload ? '' : ' disabled');
    btnDownload.innerHTML = '<i class="bi bi-download me-1"></i>Tải xuống SV';
    if (canDownload) {
        btnDownload.addEventListener('click', function () { downloadStudentList(offeringId); });
    } else {
        btnDownload.setAttribute('aria-disabled', 'true');
        btnDownload.setAttribute('title', 'Môn học chưa có danh sách sinh viên để tải xuống');
    }

    const btnAdd = document.createElement('button');
    btnAdd.className = 'btn btn-sm btn-outline-primary';
    btnAdd.setAttribute('data-offering', offeringId);
    btnAdd.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Thêm nhóm';

    btnDiv.appendChild(btnUpload);
    btnDiv.appendChild(btnDownload);
    btnDiv.appendChild(btnAdd);
    return btnDiv;
}

function computeConflicts() {
    const conflicts = {};
    const bucket = [];

    offerings.forEach(function (offering) {
        offering.sections.forEach(function (section) {
            bucket.push({ offeringId: offering.id, section: section });
        });
    });

    for (let i = 0; i < bucket.length; i += 1) {
        for (let j = i + 1; j < bucket.length; j += 1) {
            const a = bucket[i];
            const b = bucket[j];
            if (a.section.day !== b.section.day) {
                continue;
            }

            const overlap = !(a.section.end < b.section.start || b.section.end < a.section.start);
            if (!overlap) {
                continue;
            }

            const sameTeacher =
                (a.section.teacherMain && (a.section.teacherMain === b.section.teacherMain || a.section.teacherMain === b.section.teacherSub)) ||
                (a.section.teacherSub && (a.section.teacherSub === b.section.teacherMain || a.section.teacherSub === b.section.teacherSub));
            const sameRoom = a.section.room === b.section.room;

            if (sameTeacher || sameRoom) {
                const aKey = a.offeringId + '::' + a.section.code;
                const bKey = b.offeringId + '::' + b.section.code;
                const reason = sameTeacher && sameRoom ? 'Trùng GV + phòng' : (sameTeacher ? 'Trùng giảng viên' : 'Trùng phòng');
                conflicts[aKey] = reason;
                conflicts[bKey] = reason;
            }
        }
    }

    return conflicts;
}

function renderConflictBanner(conflicts) {
    const banner = document.getElementById('conflictBanner');
    const total = Object.keys(conflicts).length;
    if (total === 0) {
        banner.classList.add('d-none');
        return;
    }

    banner.classList.remove('d-none');
    banner.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Đang có ' + total + ' nhóm xung đột lịch. Bạn cần đổi GV hoặc phòng.';
}

function bindRowEvents() {
    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const parts = btn.dataset.edit.split('::');
            openSectionModal(parts[0], parts[1]);
        });
    });

    document.querySelectorAll('[data-offering]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            addSection(btn.dataset.offering);
        });
    });
}

function findSection(offeringId, sectionCode) {
    const offering = offerings.find(function (o) { return o.id === offeringId; });
    if (!offering) {
        return null;
    }
    return offering.sections.find(function (s) { return s.code === sectionCode; }) || null;
}

function openSectionModal(offeringId, sectionCode) {
    const section = findSection(offeringId, sectionCode);
    if (!section) {
        return;
    }

    editing = { offeringId: offeringId, sectionCode: sectionCode };

    document.getElementById('sectionModalTitle').textContent = 'Cập nhật nhóm - ' + offeringId + ' / ' + sectionCode;
    document.getElementById('fSectionCode').value = sectionCode;
    document.getElementById('fTeacherMain').value = section.teacherMain || '';
    document.getElementById('fTeacherSub').value = section.teacherSub || '';
    document.getElementById('fDay').value = section.day || '2';
    document.getElementById('fStart').value = String(section.start || 1);
    document.getElementById('fEnd').value = String(section.end || 2);
    document.getElementById('fRoom').value = section.room || 'PLAB';
    document.getElementById('fNote').value = section.note || '';

    sectionModal.show();
}

function addSection(offeringId) {
    const offering = offerings.find(function (o) { return o.id === offeringId; });
    if (!offering) {
        return;
    }

    const nextIndex = offering.sections.length + 1;
    const code = 'N' + nextIndex;
    offering.sections.push({
        code: code,
        teacherMain: 'GV01',
        teacherSub: '',
        day: '2',
        start: 1,
        end: 3,
        room: 'A201',
        note: 'Nhóm mới'
    });

    render();
    openSectionModal(offeringId, code);
}

function saveSection() {
    const section = findSection(editing.offeringId, editing.sectionCode);
    if (!section) {
        return;
    }

    const start = Number.parseInt(document.getElementById('fStart').value, 10);
    const end = Number.parseInt(document.getElementById('fEnd').value, 10);
    if (start >= end) {
        alert('Tiết bắt đầu phải nhỏ hơn tiết kết thúc.');
        return;
    }

    section.teacherMain = document.getElementById('fTeacherMain').value;
    section.teacherSub = document.getElementById('fTeacherSub').value;
    section.day = document.getElementById('fDay').value;
    section.start = start;
    section.end = end;
    section.room = document.getElementById('fRoom').value;
    section.note = document.getElementById('fNote').value.trim();

    sectionModal.hide();
    render();
}

function toggleAll(expand) {
    const cards = document.querySelectorAll('.offering-card');
    cards.forEach(function (card) {
        const rows = card.querySelectorAll('.section-row');
        rows.forEach(function (row) {
            row.style.display = expand ? 'grid' : 'none';
        });
    });
}
