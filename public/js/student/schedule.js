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
	const wrapper = document.querySelector('.schedule-table-wrapper');
	if (!wrapper) return;

	if (!classes.length) {
		wrapper.innerHTML = '<div class="p-4 text-muted text-center">Bạn chưa có lịch học.</div>';
		return;
	}

	const scheduledClasses = classes.filter(hasValidSchedule);
	if (!scheduledClasses.length) {
		wrapper.innerHTML = '<div class="p-4 text-muted text-center">Bạn đã đăng ký môn học nhưng chưa được xếp lịch học.</div>';
		return;
	}

	const cards = scheduledClasses.map((item) => `
		<div class="subject-block mb-3">
			<div class="subject-title">${item.subject_name} (${item.subject_code})</div>
			<div>Nhóm: ${item.group_code || '--'} | Lớp: ${item.class_name || '--'}</div>
			<div>Phòng: ${item.room || '--'} | ${dayText(item.day_of_week)}</div>
			<div>Tiết: ${item.start_period || '--'} - ${item.end_period || '--'} | Buổi: ${item.study_session || '--'}</div>
			<div>GV: ${item.teacher_name || 'Chưa phân công'}</div>
			<div class="mt-1 fw-bold text-danger"><i class="bi bi-calendar-range"></i> ${formatDateDMY(item.start_date)} - ${formatDateDMY(item.end_date)}</div>
		</div>
	`).join('');

	wrapper.innerHTML = `<div class="p-3">${cards}</div>`;
}

async function initSchedulePage() {
	try {
		const wrapper = document.querySelector('.schedule-table-wrapper');
		if (wrapper) {
			wrapper.innerHTML = '<div class="p-4 text-muted text-center">Đang tải lịch học...</div>';
		}

		const classes = await fetchSchedule();
		renderScheduleList(classes);
	} catch (error) {
		console.error(error);
		alert('Không thể tải lịch học từ database.');
	}
}

document.addEventListener('DOMContentLoaded', initSchedulePage);

