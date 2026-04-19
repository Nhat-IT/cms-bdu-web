<<<<<<< HEAD

=======
function formatDateDMY(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '--/--/----';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

// Kiem tra lich co hop le de hien thi
function hasValidSchedule(item) {
    if (!item) return false;
    const day = Number(item.day_of_week || 0);
    const start = Number(item.start_period || 0);
    const end = Number(item.end_period || 0);
    return day >= 2 && day <= 8 && start > 0 && end >= start;
}

// Lay danh sach lop hoc phan cua sinh vien
async function fetchSchedule() {
    const response = await fetch('/api/student/classes');
    if (response.status === 401) {
        window.location.href = '/login.html';
        return [];
    }
    if (!response.ok) {
        throw new Error('Không thể tải thời khóa biểu');
    }
    return response.json();
}

// Tra ve mang hoc ky {id, label} tu API
async function fetchSemesters() {
    const response = await fetch('/api/student/semesters');
    if (!response.ok) return [];
    return response.json().catch(() => []);
}

// Tra ve mang tuan {id, label, start_date, end_date} tu API
async function fetchWeeks(semesterId) {
    const url = semesterId ? `/api/student/weeks?semester_id=${semesterId}` : '/api/student/weeks';
    const response = await fetch(url);
    if (!response.ok) return [];
    return response.json().catch(() => []);
}

// Populate dropdown hoc ky
async function populateSemesterSelect() {
    const sel = document.getElementById('semesterSelect');
    if (!sel) return;
    const semesters = await fetchSemesters();
    if (!semesters.length) {
        sel.innerHTML = '<option value="">-- Chưa có học kỳ --</option>';
        return;
    }
    sel.innerHTML = '<option value="">-- Chọn học kỳ --</option>';
    semesters.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.label || s.name || s.semester_name || s.id;
        sel.appendChild(opt);
    });
    // Tu dong chon hoc ky hien tai
    const currentOpt = semesters.find((s) => s.is_current || s.is_current === 1);
    if (currentOpt) sel.value = currentOpt.id;
}

// Populate dropdown tuan dua tren hoc ky duoc chon
async function populateWeekSelect(semesterId) {
    const sel = document.getElementById('weekSelect');
    if (!sel) return;
    const weeks = await fetchWeeks(semesterId);
    if (!weeks.length) {
        sel.innerHTML = '<option value="">-- Chưa có tuần --</option>';
        return;
    }
    sel.innerHTML = '<option value="">-- Chọn tuần --</option>';
    weeks.forEach((w) => {
        const opt = document.createElement('option');
        opt.value = w.id;
        opt.textContent = w.label || w.name || `Tuần ${w.week_number}`;
        opt.dataset.startDate = w.start_date || '';
        opt.dataset.endDate = w.end_date || '';
        sel.appendChild(opt);
    });
}

// Cap nhat ngay trong header theo tuan duoc chon
function updateHeaderDates(weekOption) {
    if (!weekOption) return;
    const start = weekOption.dataset.startDate;
    const dateSpans = document.querySelectorAll('.week-date');
    // startDate dang YYYY-MM-DD, can tao ngay cho thu 2 -> chu nhat
    const baseDate = start ? new Date(start) : null;
    if (!baseDate || Number.isNaN(baseDate.getTime())) return;
    const dayOfWeek = baseDate.getDay(); // 0=CN, 1=thu 2
    const monday = new Date(baseDate);
    monday.setDate(monday.getDate() - ((dayOfWeek + 6) % 7));

    dateSpans.forEach((span) => {
        const dayNum = parseInt(span.dataset.day || '0', 10);
        if (dayNum < 2 || dayNum > 8) return;
        const targetDate = new Date(monday);
        targetDate.setDate(monday.getDate() + dayNum - 2);
        const dd = String(targetDate.getDate()).padStart(2, '0');
        const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
        span.textContent = `${dd}/${mm}`;
    });
}

// Gio bat dau moi tiet (co dinh theo quy uoc BDU)
const PERIOD_START_TIMES = {
    1: '07:00', 2: '07:45', 3: '08:30', 4: '09:15', 5: '10:00',
    6: '10:45', 7: '11:30', 8: '12:15', 9: '13:00', 10: '13:45',
    11: '14:30', 12: '15:15', 13: '16:00', 14: '16:45', 15: '17:30'
};

// Render lịch học theo mẫu mới
function renderScheduleList(classes) {
    const tbody = document.getElementById('studentScheduleBody');
    if (!tbody) return;

    const scheduledClasses = classes.filter(hasValidSchedule);

    // Map theo thu de tra cuu nhanh
    const dayMap = new Map();
    for (let day = 2; day <= 8; day += 1) {
        dayMap.set(day, []);
    }
    scheduledClasses.forEach(function (item) {
        const day = Number(item.day_of_week || 0);
        if (dayMap.has(day)) {
            dayMap.get(day).push(item);
        }
    });

    // Tìm tiết bắt đầu sớm nhất và kết thúc muộn nhất
    let minPeriod = 1;
    let maxPeriod = 15;
    if (scheduledClasses.length) {
        const allPeriods = scheduledClasses
            .map((i) => [Number(i.start_period || 0), Number(i.end_period || 0)])
            .flat()
            .filter((p) => p > 0);
        if (allPeriods.length) {
            minPeriod = Math.min(...allPeriods);
            maxPeriod = Math.max(...allPeriods);
        }
    }

    const rows = [];
    
    // Render từng tiết
    for (let period = minPeriod; period <= maxPeriod; period++) {
        const periodRow = [];
        
        // Cột nút tuần trước (chỉ hiện ở tiết đầu tiên)
        if (period === minPeriod) {
            periodRow.push('<td class="edge-col-header" rowspan="' + (maxPeriod - minPeriod + 1) + '"><i class="bi bi-arrow-left"></i></td>');
        }
        
        // Cột số tiết
        periodRow.push('<td class="fw-bold text-dark" style="background: #fff3f3; text-align: center;">Tiết ' + period + '</td>');
        
        // Cột giờ bắt đầu (chỉ hiện ở tiết đầu tiên)
        if (period === minPeriod) {
            periodRow.push('<td class="text-muted" style="background: #fff3f3; text-align: center; font-size: 0.8rem;" rowspan="' + (maxPeriod - minPeriod + 1) + '">' + (PERIOD_START_TIMES[period] || '--:--') + '</td>');
        }
        
        // 7 cột ngày trong tuần (Thứ 2 -> CN)
        for (let day = 2; day <= 8; day++) {
            const items = dayMap.get(day) || [];
            const inPeriod = items.filter((i) => Number(i.start_period) <= period && Number(i.end_period) >= period);
            
            if (!inPeriod.length) {
                periodRow.push('<td></td>');
            } else {
                const item = inPeriod[0];
                const isStart = Number(item.start_period) === period;
                const isEnd = Number(item.end_period) === period;
                
                // Chỉ hiện nội dung ở tiết bắt đầu
                if (isStart) {
                    const rowspan = Number(item.end_period) - Number(item.start_period) + 1;
                    const timeStart = PERIOD_START_TIMES[Number(item.start_period)] || '--:--';
                    const timeEnd = PERIOD_START_TIMES[Number(item.end_period) + 1] || '--:--';
                    
                    periodRow.push(`
                        <td rowspan="${rowspan}" class="has-subject">
                            <div class="subject-block" title="${item.subject_name || 'Môn học'}">
                                <div class="subject-title">${item.subject_name || 'Môn học'} (${item.subject_code || ''})</div>
                                <div>Nhóm: ${item.group_code || '--'}</div>
                                <div>Phòng: ${item.room || '--'}</div>
                                <div>GV: ${item.teacher_name || 'Chưa phân công'}</div>
                                <div class="mt-1 fw-bold text-danger"><i class="bi bi-clock"></i> ${timeStart} - ${timeEnd}</div>
                            </div>
                        </td>
                    `);
                }
                // Nếu không phải tiết bắt đầu, không render gì (cell sẽ bị occupied bởi rowspan)
            }
        }
        
        // Cột nút tuần tiếp (chỉ hiện ở tiết đầu tiên)
        if (period === minPeriod) {
            periodRow.push('<td class="edge-col-header" rowspan="' + (maxPeriod - minPeriod + 1) + '"><i class="bi bi-arrow-right"></i></td>');
        }
        
        rows.push('<tr>' + periodRow.join('') + '</tr>');
    }

    // Nếu hoàn toàn không có dữ liệu, hiện thông báo
    if (!scheduledClasses.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>Bạn chưa có lịch học. Khi được phân công lớp, lịch sẽ hiển thị ở đây.</td></tr>';
    } else {
        tbody.innerHTML = rows.join('');
    }
}

async function initSchedulePage() {
    try {
        const tbody = document.getElementById('studentScheduleBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Đang tải lịch học...</td></tr>';
        }

        // Populate dropdown hoc ky
        await populateSemesterSelect();

        const semesterSel = document.getElementById('semesterSelect');
        const weekSel = document.getElementById('weekSelect');

        if (semesterSel?.value) {
            await populateWeekSelect(semesterSel.value);
        }

        // Su kien: doi hoc ky -> load tuan
        semesterSel?.addEventListener('change', async function () {
            const semId = this.value;
            await populateWeekSelect(semId || null);
            const weekOpt = weekSel?.options[weekSel.selectedIndex] || null;
            updateHeaderDates(weekOpt);
        });

        // Su kien: doi tuan -> cap nhat header dates
        weekSel?.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            updateHeaderDates(opt);
        });

        // Nut chuyen tuan (mui ten trai/phai trong header)
        document.getElementById('weekNavPrev')?.addEventListener('click', function () {
            if (!weekSel || weekSel.options.length <= 1) return;
            const current = weekSel.selectedIndex;
            if (current > 1) {
                weekSel.selectedIndex = current - 1;
                weekSel.dispatchEvent(new Event('change'));
            }
        });

        document.getElementById('weekNavNext')?.addEventListener('click', function () {
            if (!weekSel || weekSel.options.length <= 1) return;
            const current = weekSel.selectedIndex;
            if (current < weekSel.options.length - 1) {
                weekSel.selectedIndex = current + 1;
                weekSel.dispatchEvent(new Event('change'));
            }
        });

        // Lay lich hoc tu API
        const classes = await fetchSchedule();
        renderScheduleList(classes);
    } catch (error) {
        console.error(error);
        const tbody = document.getElementById('studentScheduleBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Không thể tải lịch học từ database.</td></tr>';
    }
}

document.addEventListener('DOMContentLoaded', initSchedulePage);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
