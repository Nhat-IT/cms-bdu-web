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
	if (!scheduledClasses.length) {
		tbody.innerHTML = rows.join('') +
			'<tr><td class="text-center text-muted py-3" colspan="11">Bạn chưa có lịch học. Khi được phân công lớp, lịch sẽ hiển thị ở đây.</td></tr>';
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
		renderScheduleList(classes);
	} catch (error) {
		console.error(error);
		const tbody = document.getElementById('studentScheduleBody');
		if (tbody) tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="11">Không thể tải lịch học từ database.</td></tr>';
	}
}

document.addEventListener('DOMContentLoaded', initSchedulePage);
