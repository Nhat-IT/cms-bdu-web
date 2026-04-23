-- Tắt kiểm tra khóa ngoại để tạo bảng không bị lỗi thứ tự
SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================
-- 1. NHÓM QUẢN TRỊ & NGƯỜI DÙNG
-- ==========================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255),
    full_name VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
    google_id VARCHAR(255),
    role ENUM('student', 'bcs', 'teacher', 'support_admin', 'admin') DEFAULT 'student',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1: Hoạt động, 0: Bị khóa',
    position VARCHAR(100) DEFAULT NULL COMMENT 'Chức vụ (BCS): Lớp trưởng, Thư ký, BTV...',
    academic_title VARCHAR(50) DEFAULT NULL COMMENT 'Học hàm/học vị: GS, PGS, TS, ThS, CN...',
    avatar VARCHAR(255),
    birth_date DATE NULL,
    phone_number VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_email_domain CHECK (email LIKE '%@student.bdu.edu.vn' OR email LIKE '%@bdu.edu.vn')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm sẵn data cho danh mục quyền
INSERT INTO roles (id, role_name) VALUES 
(1, 'student'), 
(2, 'bcs'), 
(3, 'teacher'), 
(4, 'support_admin'), 
(5, 'admin');

-- TẠO SẴN TÀI KHOẢN ADMIN THEO YÊU CẦU
INSERT INTO users (username, password, full_name, email, role, is_active) 
VALUES ('admin', '123456@', 'Quản trị viên Hệ thống', 'admin@bdu.edu.vn', 'admin', 1);

CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    target_table VARCHAR(50),
    target_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 2. NHÓM ĐÀO TẠO & TỔ CHỨC LỚP HỌC
-- ==========================================

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(20) NULL COMMENT 'Mã ngành (VD: CNTT, KTPM)',
    department_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(20),
    academic_year VARCHAR(20),
    start_date DATE,
    end_date DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample semesters data
INSERT INTO semesters (semester_name, academic_year, start_date, end_date) VALUES
('HK1', '2025-2026', '2025-09-01', '2026-01-15'),
('HK2', '2025-2026', '2026-02-01', '2026-06-15'),
('HK1', '2026-2027', '2026-09-01', '2027-01-15'),
('HK2', '2026-2027', '2027-02-01', '2027-06-15');

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT,
    class_name VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(100) NOT NULL,
    credits INT NOT NULL,
    year_level INT DEFAULT NULL COMMENT 'Nam hoc: 1, 2, 3, 4...',
    semester VARCHAR(10) DEFAULT NULL COMMENT 'Hoc ky: HK1, HK2, HK3',
    prerequisite_id INT DEFAULT NULL COMMENT 'Mon hoc tien quyet (FK subjects.id)',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1: Hoat dong, 0: Khong hoat dong',
    open_date DATE NULL COMMENT 'Ngay bat dau mo mon',
    close_date DATE NULL COMMENT 'Ngay ket thuc/dong mon',
    academic_year VARCHAR(20) NULL COMMENT 'Nam hoc (vd: 2023-2024)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prerequisite_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subject_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    semester VARCHAR(10) DEFAULT NULL,
    action_type ENUM('open', 'close', 'schedule_change') NOT NULL,
    old_status TINYINT DEFAULT NULL,
    new_status TINYINT DEFAULT NULL,
    old_open_date DATE DEFAULT NULL,
    new_open_date DATE DEFAULT NULL,
    old_close_date DATE DEFAULT NULL,
    new_close_date DATE DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_subject_id (subject_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_id INT,
    class_id INT,
    subject_id INT,
    teacher_id INT,
    semester VARCHAR(10),
    study_session ENUM('Sáng', 'Chiều', 'Tối'),
    start_period INT,
    end_period INT,
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_subject_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT NOT NULL,
    group_code VARCHAR(10) NOT NULL,
    room VARCHAR(50),
    day_of_week INT,
    start_period INT,
    end_period INT,
    sub_teacher_id INT,
    FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng buổi học bù / đột xuất
CREATE TABLE extra_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_group_id INT NOT NULL,
    extra_date DATE NOT NULL COMMENT 'Ngày học bù',
    day_of_week INT COMMENT 'Thứ trong tuần',
    start_period INT COMMENT 'Tiết bắt đầu',
    end_period INT COMMENT 'Tiết kết thúc',
    room VARCHAR(50),
    note VARCHAR(255) COMMENT 'Ghi chú',
    is_regular TINYINT(1) DEFAULT 0 COMMENT '1: Buổi học thường xuyên, 0: Buổi bù đột xuất',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_subject_group_id) REFERENCES class_subject_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng sinh viên theo từng nhóm học phần (lưu trong bộ nhớ tạm trên trình duyệt
-- khi chưa đẩy lên DB, sau đó sync lên DB khi admin xác nhận)
CREATE TABLE group_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_group_id INT NOT NULL,
    student_id INT NULL COMMENT 'NULL nếu SV chưa có tài khoản',
    mssv VARCHAR(50) NULL COMMENT 'MSSV dự phòng khi chưa có tài khoản',
    full_name VARCHAR(100) NULL COMMENT 'Họ tên SV (lưu trực tiếp khi upload)',
    birth_date VARCHAR(20) NULL COMMENT 'Ngày sinh (lưu trực tiếp khi upload)',
    class_name VARCHAR(50) NULL COMMENT 'Tên lớp của SV',
    status VARCHAR(20) DEFAULT 'Đang học',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_group_student (class_subject_group_id, student_id),
    UNIQUE KEY uk_group_mssv (class_subject_group_id, mssv),
    FOREIGN KEY (class_subject_group_id) REFERENCES class_subject_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_subject_registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_subject_group_id INT NOT NULL,
    status VARCHAR(20) DEFAULT 'Đang học',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_subject_group_id) REFERENCES class_subject_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 3. NHÓM ĐIỂM DANH & MINH CHỨNG
-- ==========================================

CREATE TABLE attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_group_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    created_by INT COMMENT 'ID của BCS điểm danh',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_subject_group_id) REFERENCES class_subject_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status INT NOT NULL COMMENT '1: Có mặt, 2: Vắng có phép, 3: Vắng không phép',
    evidence_file VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_evidences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_record_id INT NOT NULL,
    drive_link VARCHAR(255),
    drive_file_id VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT NULL COMMENT 'ID của BCS đã duyệt minh chứng',
    FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 4. NHÓM ĐIỂM SỐ, TÀI LIỆU & ĐÁNH GIÁ
-- ==========================================

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_subject_group_id INT NOT NULL,
    assignment_score FLOAT COMMENT 'Điểm quá trình/bài tập',
    midterm_score FLOAT COMMENT 'Điểm giữa kỳ',
    final_score FLOAT COMMENT 'Điểm cuối kỳ',
    total_score FLOAT COMMENT 'Điểm tổng kết',
    grade_letter VARCHAR(2) COMMENT 'Điểm chữ (A, B, C...)',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_subject_group_id) REFERENCES class_subject_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    note VARCHAR(255),
    category VARCHAR(50) COMMENT 'VD: Thông báo, Học liệu',
    drive_link VARCHAR(255),
    drive_file_id VARCHAR(255),
    icon_type VARCHAR(20),
    custom_icon VARCHAR(255),
    class_subject_id INT NOT NULL,
    uploader_id INT COMMENT 'ID của Admin hoặc BCS',
    semester VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(200),
    content TEXT,
    status ENUM('Pending', 'Resolved') DEFAULT 'Pending',
    reply_content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 5. TẠO CÁC CHỈ MỤC (INDEX) TỐI ƯU TỐC ĐỘ
-- ==========================================
CREATE INDEX idx_student_session ON attendance_records(student_id, session_id);
CREATE INDEX idx_student_class_group ON student_subject_registration(student_id, class_subject_group_id);
CREATE INDEX idx_document_class ON documents(class_subject_id);

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 6. BANG PHONG HOC (rooms)
-- ==========================================

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Ma phong (VD: A201, PM3)',
    room_name VARCHAR(100) NOT NULL COMMENT 'Ten phong day du',
    building VARCHAR(50) COMMENT 'Toa nha',
    capacity INT DEFAULT 40 COMMENT 'Suc chua toi da (cho ngoi)',
    room_type ENUM('lecture','lab','computer') DEFAULT 'lecture' COMMENT 'Loai phong',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1=Dang hoat dong, 0=Ngung su dung',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rooms (room_code, room_name, building, capacity, room_type) VALUES
('A1.1',   'Phong A1.1',      'Toa A', 50, 'lecture'),
('A1.2',   'Phong A1.2',      'Toa A', 60, 'lecture'),
('A1.3',   'Phong A1.3',      'Toa A', 60, 'lecture'),
('PLAB',   'Phong P.LAB',     'Toa P', 30, 'lab'),
('PM3',    'Phong May PM3',   'Toa P', 40, 'computer'),
('C4.0.1', 'Phong C4.0.1',   'Toa C', 45, 'lecture');
