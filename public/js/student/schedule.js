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

function hasValidSchedule(item) {
	if (!item) return false;
	const day = Number(item.day_of_week || 0);
	const start = Number(item.start_period || 0);
	const end = Number(item.end_period || 0);
	return day >= 2 && day <= 8 && start > 0 && end >= start;
}

function renderScheduleList(classes) {
	const tbody = document.getElementById('studentScheduleBody');
	if (!tbody) return;

	if (!classes.length) {
		tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="9">Bạn chưa có lịch học.</td></tr>';
		return;
	}

	const scheduledClasses = classes.filter(hasValidSchedule);
	if (!scheduledClasses.length) {
		tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="9">Bạn đã đăng ký môn học nhưng chưa được xếp lịch học.</td></tr>';
		return;
	}

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

	const dayCells = [];
	for (let day = 2; day <= 8; day += 1) {
		const items = dayMap.get(day) || [];
		if (!items.length) {
			dayCells.push('<td></td>');
			continue;
		}

		const cellHtml = items.map(function (item) {
			return `
				<div class="subject-block mb-2" title="${item.subject_name || 'Môn học'}">
					<div class="subject-title">${item.subject_name || 'Môn học'} (${item.subject_code || '--'})</div>
					<div>Nhóm: ${item.group_code || '--'} | Lớp: ${item.class_name || '--'}</div>
					<div>Phòng: ${item.room || '--'} | ${dayText(item.day_of_week)}</div>
					<div>Tiết: ${item.start_period || '--'} - ${item.end_period || '--'} | Buổi: ${item.study_session || '--'}</div>
					<div>GV: ${item.teacher_name || 'Chưa phân công'}</div>
					<div class="mt-1 fw-bold text-danger"><i class="bi bi-calendar-range"></i> ${formatDateDMY(item.start_date)} - ${formatDateDMY(item.end_date)}</div>
				</div>
			`;
		}).join('');
		dayCells.push(`<td>${cellHtml}</td>`);
	}

	tbody.innerHTML = `
		<tr>
			<td class="edge-col text-muted fw-bold">Lịch</td>
			${dayCells.join('')}
			<td class="edge-col text-muted">--:--</td>
		</tr>
	`;
}

async function initSchedulePage() {
	try {
		const tbody = document.getElementById('studentScheduleBody');
		if (tbody) {
			tbody.innerHTML = '<tr><td class="text-center text-muted py-4" colspan="9">Đang tải lịch học...</td></tr>';
		}

		const classes = await fetchSchedule();
		renderScheduleList(classes);
	} catch (error) {
		console.error(error);
		alert('Không thể tải lịch học từ database.');
	}
}

document.addEventListener('DOMContentLoaded', initSchedulePage);

