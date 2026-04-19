USE buzwdyz5iqgcj9toob1b;

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
    role ENUM('student', 'bcs', 'teacher', 'admin') DEFAULT 'student',
    position VARCHAR(100) DEFAULT NULL COMMENT 'Chức vụ (BCS): Lớp trưởng, Thư ký, BTV...',
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

-- (Thêm sẵn data cho roles)
INSERT INTO roles (id, role_name) VALUES (1, 'student'), (2, 'bcs'), (3, 'teacher'), (4, 'admin');

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
    department_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(20),
    academic_year VARCHAR(20),
    start_date DATE,
    end_date DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    credits INT NOT NULL
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

CREATE TABLE class_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
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
    created_by INT,
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
    FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 4. NHÓM HỌC TẬP, TÀI LIỆU & ĐÁNH GIÁ
-- ==========================================

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    drive_link VARCHAR(255),
    drive_file_id VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score FLOAT,
    feedback TEXT,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_subject_group_id INT NOT NULL,
    assignment_score FLOAT,
    midterm_score FLOAT,
    final_score FLOAT,
    total_score FLOAT,
    grade_letter VARCHAR(2),
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
    category VARCHAR(50),
    drive_link VARCHAR(255),
    drive_file_id VARCHAR(255),
    icon_type VARCHAR(20),
    custom_icon VARCHAR(255),
    class_subject_id INT NOT NULL,
    uploader_id INT,
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

-- ==========================================
-- 6. MIGRATION CHO DB ĐÃ TỒN TẠI
-- ==========================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL;

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;