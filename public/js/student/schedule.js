function formatDateDMY(dateValue) {
	const d = new Date(dateValue);
	if (Number.isNaN(d.getTime())) return '--/--/----';
	const dd = String(d.getDate()).padStart(2, '0');
	const mm = String(d.getMonth() + 1).padStart(2, '0');
	const yyyy = d.getFullYear();
	return `${dd}/${mm}/${yyyy}`;
}

function dayText(dayOfWeek) {
	if (!dayOfWeek) return 'Chưa cập nhật';
	if (Number(dayOfWeek) === 8) return 'Chủ nhật';
	return `Thứ ${dayOfWeek}`;
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

// Bien global luu du lieu lich de dung lai khi chuyen view
window.gScheduleClasses = [];

// Tra ve mang hoc ky {id, label} tu API
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
	const end = weekOption.dataset.endDate;
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

// Map tiet -> gio (co dinh theo quy uoc BDU)
const PERIOD_TIMES = [
	'',      // index 0
	'07:00', // tiet 1
	'07:50', // tiet 2
	'08:40', // tiet 3
	'09:30', // tiet 4
	'10:20', // tiet 5
	'11:10', // tiet 6
	'12:30', // tiet 7
	'13:20', // tiet 8
	'14:10', // tiet 9
	'15:00', // tiet 10
	'15:50', // tiet 11
	'16:40', // tiet 12
];

// Mang co dinh 12 tiet (dung khi chua co du lieu)
const ALL_PERIODS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

// Thong tin tuan hien tai trong dropdown
let gWeeksList = [];
let gCurrentView = 'week'; // 'week' hoặc 'day'
let gSelectedDay = null; // 2-8

// Cap nhat thong tin ngay hien tai trong header bar
function updateTodayInfo() {
    const now = new Date();
    const dayOfWeek = now.getDay(); // 0=CN, 1=T2
    const weekdays = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    const displayDay = dayOfWeek === 0 ? 8 : dayOfWeek + 1; // CN = 8

    const weekdayText = document.getElementById('currentWeekdayText');
    const dateText = document.getElementById('currentDateText');
    const weekText = document.getElementById('currentWeekText');

    if (weekdayText) weekdayText.textContent = weekdays[dayOfWeek];
    if (dateText) {
        const dd = String(now.getDate()).padStart(2, '0');
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        dateText.textContent = `${dd}/${mm}/${now.getFullYear()}`;
    }

    // Cap nhat chip ngay active
    const chips = document.querySelectorAll('.weekday-chip');
    chips.forEach(chip => {
        const chipDay = parseInt(chip.dataset.day || '0', 10);
        chip.classList.toggle('active', chipDay === displayDay);
    });

    // Lay tuan hien tai
    const weekSel = document.getElementById('weekSelect');
    if (weekSel && weekSel.value) {
        const opt = weekSel.options[weekSel.selectedIndex];
        if (weekText && opt) {
            weekText.textContent = opt.textContent.replace(/^Tuần\s*/i, '') || '--';
        }
    } else if (weekText) {
        weekText.textContent = '--';
    }

    return displayDay;
}

// Chuyen doi giua xem theo tuan / xem theo ngay
function switchScheduleView(view) {
    gCurrentView = view;
    const weekBtn = document.getElementById('viewByWeekBtn');
    const dayBtn = document.getElementById('viewByDayBtn');
    if (weekBtn) weekBtn.classList.toggle('active', view === 'week');
    if (dayBtn) dayBtn.classList.toggle('active', view === 'day');

    // Neu la xem theo ngay, auto chon ngay hien tai
    if (view === 'day' && !gSelectedDay) {
        const today = updateTodayInfo();
        gSelectedDay = today;
        highlightDayChip(today);
    }

    // Re-render schedule
    renderScheduleByView();
}

// Highlight chip ngay duoc chon
function highlightDayChip(day) {
    const chips = document.querySelectorAll('.weekday-chip');
    chips.forEach(chip => {
        const chipDay = parseInt(chip.dataset.day || '0', 10);
        chip.classList.toggle('active', chipDay === day);
    });
}

// Danh dau nhung ngay co lich hoc
function markDaysWithClasses(classes) {
    const daysWithClass = new Set();
    classes.forEach(item => {
        if (hasValidSchedule(item)) {
            daysWithClass.add(Number(item.day_of_week));
        }
    });

    const chips = document.querySelectorAll('.weekday-chip');
    chips.forEach(chip => {
        const chipDay = parseInt(chip.dataset.day || '0', 10);
        // CN = 8 trong HTML, nhung API tra ve 0 cho CN
        const apiDay = chipDay === 8 ? 0 : chipDay;
        chip.classList.toggle('has-class', daysWithClass.has(apiDay));
    });
}

// Render schedule dua tren view hien tai
function renderScheduleByView() {
    // Lay du lieu lich tu API (da load san trong gClasses)
    const classes = window.gScheduleClasses || [];
    if (gCurrentView === 'day' && gSelectedDay) {
        renderScheduleList(classes, gSelectedDay);
    } else {
        renderScheduleList(classes, null);
    }
}

// Xu ly click chip ngay
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const chip = e.target.closest('.weekday-chip');
        if (!chip) return;

        const day = parseInt(chip.dataset.day || '0', 10);
        if (day < 2 || day > 8) return;

        gSelectedDay = day;
        highlightDayChip(day);
        switchScheduleView('day');
    });
});

window.switchScheduleView = switchScheduleView;

function renderScheduleList(classes, filterDay) {
	const tbody = document.getElementById('studentScheduleBody');
	if (!tbody) return;

	const scheduledClasses = classes.filter(hasValidSchedule);

	// Neu co filter theo ngay, chi hien thi ngay do
	let filteredByDay = scheduledClasses;
	if (filterDay) {
		filteredByDay = scheduledClasses.filter(item => {
			const itemDay = Number(item.day_of_week || 0);
			// Chuyen doi: filterDay=8 (CN) can khop voi itemDay=0 (API tra ve 0 cho CN)
			if (filterDay === 8) return itemDay === 0;
			return itemDay === filterDay;
		});
	}

	// Map theo thu de tra cuu nhanh
	const dayMap = new Map();
	for (let day = 2; day <= 8; day += 1) {
		dayMap.set(day, []);
	}
	filteredByDay.forEach(function (item) {
		const day = Number(item.day_of_week || 0);
		if (dayMap.has(day)) {
			dayMap.get(day).push(item);
		} else if (day === 0) {
			// CN = 0 trong API
			dayMap.get(8).push(item);
		}
	});

	// Neu co du lieu: chi render cac tiet co mon hoc
	// Neu khong co du lieu: van render day du 12 tiet (khung TKB)
	let periodsToRender = ALL_PERIODS;
	if (scheduledClasses.length) {
		const allPeriods = scheduledClasses
			.map((i) => [Number(i.start_period || 0), Number(i.end_period || 0)])
			.flat()
			.filter((p) => p > 0);
		const minP = allPeriods.length ? Math.min(...allPeriods) : 1;
		const maxP = allPeriods.length ? Math.max(...allPeriods) : 12;
		periodsToRender = [];
		for (let p = minP; p <= maxP; p += 1) periodsToRender.push(p);
	}

	const rows = [];
	periodsToRender.forEach(function (period) {
		const dayCells = [];
		for (let day = 2; day <= 8; day += 1) {
			const items = dayMap.get(day) || [];
			const inPeriod = items.filter(
				(i) => Number(i.start_period) <= period && Number(i.end_period) >= period
			);

			if (!inPeriod.length) {
				dayCells.push('<td class="bg-white"></td>');
			} else {
				// Chi lay mon hoc dau tien o tiet bat dau
				const item = inPeriod[0];
				const isStart = Number(item.start_period) === period;
				const isEnd = Number(item.end_period) === period;

				let borderClass = '';
				if (isStart && isEnd) borderClass = 'period-start period-end';
				else if (isStart) borderClass = 'period-start';
				else if (isEnd) borderClass = 'period-end';

				dayCells.push(`
					<td class="${borderClass}">
						<div class="subject-block mb-1">
							<div class="subject-title">${item.subject_name || 'Môn'}</div>
							<div><i class="bi bi-geo-alt me-1"></i>${item.room || '--'}</div>
							<div><i class="bi bi-person me-1"></i>${item.teacher_name || 'Chưa phân công'}</div>
						</div>
					</td>
				`);
			}
		}

		rows.push(`
			<tr>
				<td class="bg-white"></td>
				<td class="text-center fw-bold text-dark bg-light">${period}</td>
				<td class="text-center text-muted small bg-light">${PERIOD_TIMES[period] || '--:--'}</td>
				${dayCells.join('')}
				<td class="bg-white"></td>
			</tr>
		`);
	});

	// Neu hoan toan khong co du lieu, hien thong bao
	if (!filteredByDay.length) {
		tbody.innerHTML = rows.join('') +
			`<tr><td class="text-center text-muted py-3" colspan="11">${filterDay ? 'Ngày này không có lịch học.' : 'Bạn chưa có lịch học. Khi được phân công lớp, lịch sẽ hiển thị ở đây.'}</td></tr>`;
	} else {
		tbody.innerHTML = rows.join('');
	}
}

async function initSchedulePage() {
	try {
		const tbody = document.getElementById('studentScheduleBody');
		if (tbody) {
			tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="11">Đang tải lịch học...</td></tr>';
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
			// Sau khi populate xong, auto-chon tuan 1
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
		window.gScheduleClasses = classes;
		updateTodayInfo();
		markDaysWithClasses(classes);
		renderScheduleByView();
	} catch (error) {
		console.error(error);
		const tbody = document.getElementById('studentScheduleBody');
		if (tbody) tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="11">Không thể tải lịch học từ database.</td></tr>';
	}
}

document.addEventListener('DOMContentLoaded', initSchedulePage);

