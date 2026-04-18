// ========== Hàm lấy dữ liệu từ API ==========
async function fetchDashboardData() {
	try {
		const response = await fetch('/api/student/dashboard');
		if (!response.ok) {
			console.error('Response status:', response.status);
			if (response.status === 401) {
				// Chưa đăng nhập, chuyển hướng về login
				window.location.href = '/login.html';
				return;
			}
			throw new Error('Lỗi lấy dữ liệu');
		}
		return await response.json();
	} catch (error) {
		console.error('Error fetching dashboard:', error);
		// Hiển thị thông báo lỗi
		showErrorMessage('Không thể tải dữ liệu. Vui lòng tải lại trang.');
		return null;
	}
}

function formatDateDMY(dateValue) {
	const d = new Date(dateValue);
	if (Number.isNaN(d.getTime())) return '--/--/----';
	const dd = String(d.getDate()).padStart(2, '0');
	const mm = String(d.getMonth() + 1).padStart(2, '0');
	const yyyy = d.getFullYear();
	return `${dd}/${mm}/${yyyy}`;
}

// ========== Hàm hiển thị dữ liệu hồ sơ ==========
function displayUserProfile(profile) {
	if (!profile) return;

	// Cập nhật hình ảnh đại diện
	const avatarImages = document.querySelectorAll('img[alt="Avatar"]');
	avatarImages.forEach(img => {
		img.src = profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.full_name)}&background=0d6efd&color=fff`;
	});

	// Cập nhật tên người dùng
	const nameElements = document.querySelectorAll('.profile-name, .user-full-name');
	nameElements.forEach(el => {
		el.textContent = profile.full_name || 'Chưa cập nhật';
	});

	// Cập nhật username (MSSV)
	const usernameElements = document.querySelectorAll('.profile-mssv, .user-username');
	usernameElements.forEach(el => {
		el.textContent = `MSSV: ${profile.username || 'N/A'}`;
	});

	// Cập nhật email
	const emailElements = document.querySelectorAll('.profile-email, .user-email');
	emailElements.forEach(el => {
		el.textContent = profile.email || 'N/A';
	});
}

function displayTopCards(data) {
	const classCountEl = document.querySelector('[data-classes-count]');
	const warningCountEl = document.querySelector('[data-warning-count]');
	const absentCountEl = document.querySelector('[data-absent-count]');
	const resolvedCountEl = document.querySelector('[data-resolved-count]');

	if (classCountEl) {
		classCountEl.textContent = String(data?.classes?.count || 0);
	}

	const absentTotal = Number(data?.attendance?.excused_absent || 0) + Number(data?.attendance?.unexcused_absent || 0);
	if (absentCountEl) {
		absentCountEl.textContent = String(absentTotal);
	}

	// Tạm xem "môn cảnh báo" là số môn có tổng điểm dưới 5.
	const warningCount = (data?.grades?.recent || []).filter((g) => Number(g.total_score || 0) < 5).length;
	if (warningCountEl) {
		warningCountEl.textContent = String(warningCount);
	}

	// Tạm dùng số phản hồi đã xử lý = 0 trên dashboard cho đến khi có endpoint tổng hợp.
	if (resolvedCountEl) {
		resolvedCountEl.textContent = '0';
	}
}

// ========== Hàm hiển thị thống kê điểm danh ==========
function displayAttendanceStats(attendance) {
	if (!attendance) return;

	// Cập nhật số liệu điểm danh
	const statsContainer = document.querySelector('.attendance-stats');
	if (statsContainer) {
		statsContainer.innerHTML = `
			<div class="stat-item">
				<div class="stat-number">${attendance.total_sessions || 0}</div>
				<div class="stat-label">Tổng buổi học</div>
			</div>
			<div class="stat-item">
				<div class="stat-number text-success">${attendance.present || 0}</div>
				<div class="stat-label">Có mặt</div>
			</div>
			<div class="stat-item">
				<div class="stat-number text-warning">${attendance.excused_absent || 0}</div>
				<div class="stat-label">Vắng có phép</div>
			</div>
			<div class="stat-item">
				<div class="stat-number text-danger">${attendance.unexcused_absent || 0}</div>
				<div class="stat-label">Vắng không phép</div>
			</div>
		`;
	}

	// Cập nhật biểu đồ nếu có
	const attendanceRate = attendance.total_sessions > 0 
		? Math.round((attendance.present / attendance.total_sessions) * 100)
		: 0;
    
	const rateElements = document.querySelectorAll('.attendance-rate');
	rateElements.forEach(el => {
		el.textContent = `${attendanceRate}%`;
	});
}

// ========== Hàm hiển thị các lớp học ==========
function displayClasses(classes) {
	const classesContainer = document.querySelector('.classes-list, [data-classes-container]');
    
	if (!classesContainer) return;

	if (!classes || classes.length === 0) {
		classesContainer.innerHTML = '<p class="text-muted text-center">Bạn chưa đăng ký môn học nào</p>';
		return;
	}

	let html = '';
	classes.forEach(cls => {
		html += `
			<div class="class-card card mb-3 border-0 shadow-sm" data-class-id="${cls.class_subject_id}">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-start mb-2">
						<div>
							<h6 class="card-title fw-bold mb-1">${cls.subject_name}</h6>
							<small class="text-muted">${cls.subject_code}</small>
						</div>
						<span class="badge bg-primary">${cls.credits} tín</span>
					</div>
					<p class="text-muted small mb-2">
						<i class="bi bi-person-fill"></i> ${cls.teacher_name || 'Chưa phân công'}
					</p>
					<p class="text-muted small mb-0">
						<i class="bi bi-calendar"></i> 
						${formatDateDMY(cls.start_date)} - ${formatDateDMY(cls.end_date)}
					</p>
				</div>
			</div>
		`;
	});
    
	classesContainer.innerHTML = html;
}

function displayTodaySchedule(classes) {
	const scheduleContainer = document.querySelector('.col-lg-5 .card .card-body');
	if (!scheduleContainer) return;

	if (!classes || !classes.length) {
		scheduleContainer.innerHTML = `
			<div class="p-4 text-center border rounded border-dashed bg-white mt-1">
				<i class="bi bi-calendar2-week text-muted fs-1 mb-2"></i>
				<h6 class="text-muted fw-bold">Chua co lich hoc</h6>
				<p class="small text-muted mb-0">Khi duoc phan lop, lich hoc se hien thi o day.</p>
			</div>
		`;
		return;
	}

	const items = classes.slice(0, 2);
	scheduleContainer.innerHTML = items.map((cls) => `
		<div class="p-3 mb-3 bg-light rounded border-start border-4 border-primary shadow-sm">
			<div class="d-flex justify-content-between mb-2">
				<span class="badge bg-primary px-2 py-1"><i class="bi bi-calendar3 me-1"></i>${formatDateDMY(cls.start_date)} - ${formatDateDMY(cls.end_date)}</span>
				<span class="text-muted small fw-bold"><i class="bi bi-geo-alt-fill me-1 text-danger"></i>${cls.room || 'Chua cap nhat phong'}</span>
			</div>
			<h6 class="fw-bold text-dark mb-1">${cls.subject_name || 'Mon hoc'}</h6>
			<p class="text-muted small mb-0"><i class="bi bi-person-badge text-primary me-1"></i>GV: ${cls.teacher_name || 'Chua phan cong'}</p>
		</div>
	`).join('');
}

function displayAttendanceWarnings(classes, attendance) {
	const attendanceCard = document.querySelector('.col-lg-7 .card .card-body');
	if (!attendanceCard) return;

	if (!classes || !classes.length) {
		attendanceCard.innerHTML = `
			<div class="p-4 text-center border rounded border-dashed bg-white mt-1">
				<i class="bi bi-clipboard2-pulse text-muted fs-1 mb-2"></i>
				<h6 class="text-muted fw-bold">Chua co du lieu diem danh</h6>
				<p class="small text-muted mb-0">Sau buoi hoc dau tien, he thong se cap nhat tai day.</p>
			</div>
		`;
		return;
	}

	const totalSessions = Number(attendance?.total_sessions || 0);
	const totalAbsent = Number(attendance?.excused_absent || 0) + Number(attendance?.unexcused_absent || 0);
	if (!totalSessions) {
		attendanceCard.innerHTML = `
			<div class="p-4 text-center border rounded border-dashed bg-white mt-1">
				<i class="bi bi-clipboard2-pulse text-muted fs-1 mb-2"></i>
				<h6 class="text-muted fw-bold">Chua duoc diem danh</h6>
				<p class="small text-muted mb-0">Hien tai ban chua co buoi diem danh nao.</p>
			</div>
		`;
		return;
	}
	const ratio = totalSessions > 0 ? Math.min(100, Math.round((totalAbsent / totalSessions) * 100)) : 0;

	const firstClass = classes[0];
	const statusColor = ratio >= 20 ? 'danger' : ratio >= 10 ? 'warning' : 'success';

	attendanceCard.innerHTML = `
		<div class="mb-2">
			<div class="d-flex justify-content-between align-items-end mb-1">
				<div>
					<h6 class="fw-bold text-${statusColor} mb-0">${firstClass.subject_name}</h6>
					<small class="text-muted">Tổng buổi học ghi nhận: ${totalSessions}</small>
				</div>
				<span class="fw-bold text-${statusColor}">Đã vắng ${totalAbsent}/${totalSessions || 0}</span>
			</div>
			<div class="progress" style="height: 10px;">
				<div class="progress-bar bg-${statusColor}" role="progressbar" style="width: ${ratio}%;"></div>
			</div>
			<small class="text-${statusColor} fw-bold mt-1 d-block">
				<i class="bi bi-exclamation-circle me-1"></i>
				${ratio >= 20 ? 'Cảnh báo: Tỉ lệ vắng cao, cần theo dõi ngay.' : 'Tình trạng chuyên cần đang ổn định.'}
			</small>
		</div>
	`;
}

// ========== Hàm hiển thị điểm số ==========
function displayGrades(gradesData) {
	const gradesContainer = document.querySelector('.grades-list, [data-grades-container]');
    
	if (!gradesContainer) return;

	if (!gradesData || gradesData.recent.length === 0) {
		gradesContainer.innerHTML = '<p class="text-muted text-center">Chưa có điểm số</p>';
		return;
	}

	let html = '<table class="table table-hover">';
	html += `
		<thead class="table-light">
			<tr>
				<th>Môn học</th>
				<th class="text-center">QT</th>
				<th class="text-center">Giữa kỳ</th>
				<th class="text-center">Cuối kỳ</th>
				<th class="text-center">Tổng</th>
				<th class="text-center">Xếp loại</th>
			</tr>
		</thead>
		<tbody>
	`;

	gradesData.recent.forEach(grade => {
		const letterClass = getGradeClass(grade.grade_letter);
		html += `
			<tr>
				<td>${grade.subject_name}</td>
				<td class="text-center">${grade.assignment_score ? grade.assignment_score.toFixed(1) : '-'}</td>
				<td class="text-center">${grade.midterm_score ? grade.midterm_score.toFixed(1) : '-'}</td>
				<td class="text-center">${grade.final_score ? grade.final_score.toFixed(1) : '-'}</td>
				<td class="text-center fw-bold">${grade.total_score ? grade.total_score.toFixed(1) : '-'}</td>
				<td class="text-center"><span class="badge bg-${letterClass}">${grade.grade_letter || 'N/A'}</span></td>
			</tr>
		`;
	});

	html += '</tbody></table>';
	gradesContainer.innerHTML = html;

	// Cập nhật điểm trung bình
	const avgGradeElements = document.querySelectorAll('.avg-grade, .average-score');
	avgGradeElements.forEach(el => {
		el.textContent = gradesData.averageScore;
	});
}

// ========== Hàm lấy class CSS cho grade ==========
function getGradeClass(grade) {
	if (!grade) return 'secondary';
	const firstChar = grade.toUpperCase().charAt(0);
	switch (firstChar) {
		case 'A': return 'success';
		case 'B': return 'info';
		case 'C': return 'warning';
		case 'D': return 'danger';
		case 'F': return 'danger';
		default: return 'secondary';
	}
}

// ========== Hàm hiển thị thông báo chưa đọc ==========
function displayNotifications(notifications) {
	const notificationBadges = document.querySelectorAll('.notification-badge, [data-notification-count]');
	notificationBadges.forEach(badge => {
		badge.textContent = notifications.unread;
		if (notifications.unread > 0) {
			badge.style.display = 'inline-block';
		}
	});
}

// ========== Hàm hiển thị thông báo lỗi ==========
function showErrorMessage(message) {
	const alertDiv = document.createElement('div');
	alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
	alertDiv.style.zIndex = '9999';
	alertDiv.innerHTML = `
		${message}
		<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
	`;
	document.body.appendChild(alertDiv);

	// Tự động ẩn sau 5 giây
	setTimeout(() => {
		alertDiv.remove();
	}, 5000);
}

// ========== Hàm khởi tạo trang ==========
async function initDashboard() {
	// Hiển thị loading spinner nếu có
	const spinner = document.querySelector('.spinner, [data-loading]');
	if (spinner) spinner.style.display = 'block';

	const data = await fetchDashboardData();
    
	if (spinner) spinner.style.display = 'none';

	if (data) {
		// Hiển thị từng phần dữ liệu
		displayUserProfile(data.profile);
		displayTopCards(data);
		displayAttendanceStats(data.attendance);
		displayClasses(data.classes.list);
		displayTodaySchedule(data.classes.list);
		displayAttendanceWarnings(data.classes.list, data.attendance);
		displayGrades(data.grades);
		displayNotifications(data.notifications);
	}
}

// ========== Chạy khi DOM sẵn sàng ==========
document.addEventListener('DOMContentLoaded', initDashboard);

// ========== Cập nhật dữ liệu mỗi 30 giây ==========
setInterval(initDashboard, 30000);

