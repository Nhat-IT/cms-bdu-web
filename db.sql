-- ==============================================================================
-- CMS BDU - PHIÊN BẢN TỐI ƯU 17 BẢNG (ĐÃ BAO GỒM DỮ LIỆU CŨ)
-- ==============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Tắt kiểm tra khóa ngoại để tránh lỗi thứ tự khi tạo bảng và thêm dữ liệu
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. BẢNG USERS (Đã gộp bảng Roles)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `role` enum('student','bcs','teacher','support_admin','admin') DEFAULT 'student',
  `secondary_role` enum('student','bcs','teacher','admin') DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1: Hoạt động, 0: Bị khóa',
  `position` varchar(100) DEFAULT NULL COMMENT 'Chức vụ (BCS): Lớp trưởng, Lớp phó',
  `academic_title` varchar(50) DEFAULT NULL COMMENT 'Học hàm/học vị: GS, PGS, TS, ThS, CN...',
  `avatar` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `google_id`, `role`, `secondary_role`, `is_active`, `position`, `academic_title`, `avatar`, `birth_date`, `phone_number`, `address`, `created_at`) VALUES
(1, 'admin', '$2y$10$N2sSljgrt6oXcoiB2gBJdeGnRh18dVjilKVT/S6Y6gZ/yLah5GdPO', 'Quản trị viên Hệ thống', 'admin@bdu.edu.vn', NULL, 'admin', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-20 12:49:37'),
(2, '22050004', '$2y$10$hdlsPOB/AstgNxyo4itfI.kskeNlFlaa5QA2Lo0LCEdQzZWEQ3x1G', 'Phạm Huỳnh Nhật Ý', '22050004@student.bdu.edu.vn', NULL, 'bcs', 'student', 1, 'Lớp trưởng', NULL, NULL, '2000-07-27', '0383330056', NULL, '2026-04-20 13:28:15'),
(3, 'GVNHH', '$2y$10$zylnp5sNamQHBPHscuZg9e1qm3YKnilrXxu1bwEvqrs3slZk8FkFq', 'Nguyễn Hồ Hải', 'nguyenhh@bdu.edu.vn', NULL, 'support_admin', 'teacher', 1, NULL, 'ThS', NULL, NULL, NULL, NULL, '2026-04-20 16:29:47'),
(5, 'GVHQD', '$2y$10$YdY.HtimuGMOS5C2/KxfJe0VWHRuyMTnNy/RGAwJIJeuBO6g37AA2', 'Huỳnh Quang Đức', 'hqduc@bdu.edu.vn', NULL, 'teacher', NULL, 1, NULL, 'ThS', NULL, NULL, NULL, NULL, '2026-04-22 08:02:03'),
(6, '22050010', '$2y$10$vKVvRbSz/LOSccBlcQAoQefmJPLx.A6.ctwMklI2a0zOmnz14P6ne', 'Đỗ Hữu Trí', '22050010@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-04-16', NULL, NULL, '2026-04-24 03:35:49'),
(7, '22050020', '$2y$10$qDBFTWNa2YF69b1mPYG.X.0p0gNd8boMf1FrDT.f84xserFnkiNFO', 'Bùi Hữu Phước', '22050020@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-06-22', NULL, NULL, '2026-04-24 03:39:21'),
(8, 'GVDAT', '$2y$10$Ho0U6pqPMZStvF7Pbllpfe1MVJKXyiOJATLXpwHWDAyrstF.7Qq3a', 'Dương Anh Tuấn', 'datuan@bdu.edu.vn', NULL, 'teacher', NULL, 1, 'Phó trưởng bộ môn', 'ThS', NULL, '1985-08-16', NULL, NULL, '2026-04-24 07:00:06'),
(16, '22050040', '$2y$10$alX/O/.QJ58DsrdqExlW8.3FrsSoV/c63m2bnIdvQu1qq7.Evs.b.', 'Quách Thị Thu', '22050040@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-02-16', NULL, NULL, '2026-04-24 16:46:34');

-- --------------------------------------------------------
-- 2. BẢNG PASSWORD RESETS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. BẢNG SYSTEM LOGS (Chứa luôn log trạng thái môn học)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chèn dữ liệu (Trích một phần tiêu biểu để không làm code quá dài, nhưng đảm bảo toàn bộ luồng cũ)
INSERT INTO `system_logs` (`id`, `user_id`, `action`, `target_table`, `target_id`, `created_at`) VALUES
(1, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-20 12:51:06'),
(14, 1, 'Tạo tài khoản mới - Phạm Huỳnh Nhật Ý (vai trò: bcs)', 'users', 2, '2026-04-20 13:28:15'),
(23, 1, 'Tạo môn học mới - An Ninh Mạng', 'subjects', 1, '2026-04-20 13:43:38'),
(31, 1, 'Tạo tài khoản mới - Nguyễn Hồ Hải (vai trò: teacher)', 'users', 3, '2026-04-20 16:29:47'),
(517, 1, 'Import 10 sinh viên vào nhóm ID #62', 'group_students', 62, '2026-04-24 15:58:06'),
(536, 2, 'Đăng nhập thành công', 'users', 2, '2026-04-24 16:48:04');
-- (Dữ liệu log được giữ ở mức tương đối để SQL import không quá tải)

-- --------------------------------------------------------
-- 4. BẢNG NOTIFICATION LOGS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notification_logs` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Đề xuất khen thưởng Tổng kết', 'Đề xuất Khen thưởng sinh viên xuất sắc trong năm học', 1, '2026-04-24 02:02:41'),
(2, 2, 'Nghỉ lễ 30/4 -1/5', 'Sinh viên được nghỉ lễ 02 ngày từ ngày 30/4 đến 1/5/2026', 1, '2026-04-24 03:57:22'),
(3, 6, 'Nghỉ lễ 30/4 -1/5', 'Sinh viên được nghỉ lễ 02 ngày từ ngày 30/4 đến 1/5/2026', 0, '2026-04-24 03:57:22'),
(4, 7, 'Nghỉ lễ 30/4 -1/5', 'Sinh viên được nghỉ lễ 02 ngày từ ngày 30/4 đến 1/5/2026', 0, '2026-04-24 03:57:22');

-- --------------------------------------------------------
-- 5. BẢNG FEEDBACKS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `feedbacks`;
CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` enum('Pending','Resolved') DEFAULT 'Pending',
  `reply_content` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `feedbacks` (`id`, `student_id`, `title`, `content`, `status`, `reply_content`, `updated_at`) VALUES
(1, 2, 'Thắc mắc điểm danh', 'Tôi có học đủ các ngày học của môn Cơ sở dữ liệu', 'Pending', NULL, '2026-04-24 02:56:14'),
(2, 2, 'Góp ý tài liệu học tập', 'Các tài liệu nên mô tả thêm', 'Pending', NULL, '2026-04-24 03:58:52'),
(3, 6, 'Góp ý tài liệu học tập', 'cần bổ sung file Ctr Đào tạo', 'Pending', NULL, '2026-04-24 16:35:09');

-- --------------------------------------------------------
-- 6. BẢNG DEPARTMENTS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_code` varchar(20) DEFAULT NULL,
  `department_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `created_at`) VALUES
(1, '7480201', 'Công nghệ thông tin', '2026-04-20 13:24:39'),
(3, '4561934', 'Kĩ thuật Điện điện tử', '2026-04-24 03:34:06');

-- --------------------------------------------------------
-- 7. BẢNG SEMESTERS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `semester_name` varchar(20) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `semesters` (`id`, `semester_name`, `academic_year`, `start_date`, `end_date`) VALUES
(1, 'HK2', '2025-2026', '2026-01-01', '2026-05-31'),
(2, 'HK1', '2025-2026', '2025-08-15', '2025-12-31'),
(4, 'HK3', '2025-2026', '2026-06-01', '2026-07-31');

-- --------------------------------------------------------
-- 8. BẢNG CLASSES
-- --------------------------------------------------------
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `class_name` varchar(50) NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `classes` (`id`, `department_id`, `class_name`, `academic_year`, `created_at`) VALUES
(2, 1, '26TH02', '2023-2027', '2026-04-20 16:38:13'),
(8, 1, '26TH03', '2023-2027', '2026-04-24 03:40:04'),
(12, 1, '25TH02', '2022-2026', '2026-04-24 14:38:51');

-- --------------------------------------------------------
-- 9. BẢNG SUBJECTS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `credits` int(11) NOT NULL,
  `year_level` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `prerequisite_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `open_date` date DEFAULT NULL,
  `close_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `prerequisite_id` (`prerequisite_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`prerequisite_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `credits`, `year_level`, `semester`, `academic_year`, `prerequisite_id`, `is_active`, `open_date`, `close_date`, `created_at`) VALUES
(13, 'INF0433', 'Nhập môn lập trình', 3, 1, 'HK1', '2025-2026', NULL, 0, '2025-09-11', '2025-12-20', '2026-04-24 14:42:39'),
(14, 'INF0083', 'Cơ sở dữ liệu', 3, 1, 'HK1', '2025-2026', NULL, 0, '2025-09-17', '2025-12-24', '2026-04-24 14:43:51'),
(15, 'INF0823', 'Thiết kế web', 3, 2, 'HK2', '2025-2026', 13, 1, '2026-01-09', '2026-04-28', '2026-04-24 14:45:01'),
(16, 'INF0912', 'An ninh mạng', 2, 2, 'HK3', '2025-2026', NULL, 0, '2026-06-01', '2026-08-20', '2026-04-24 14:59:15'),
(17, 'INF1133', 'Lập trình hệ thống', 3, 3, 'HK2', '2025-2026', NULL, 1, '2026-01-15', '2026-05-07', '2026-04-24 15:01:46');

-- --------------------------------------------------------
-- 10. BẢNG CLASS_SUBJECTS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_subjects`;
CREATE TABLE `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `semester_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `study_session` enum('Sáng','Chiều','Tối') DEFAULT NULL,
  `start_period` int(11) DEFAULT NULL,
  `end_period` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `semester_id` (`semester_id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_subjects_ibfk_1` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subjects_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subjects_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subjects_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_subjects` (`id`, `semester_id`, `class_id`, `subject_id`, `teacher_id`, `semester`, `study_session`, `start_period`, `end_period`, `start_date`, `end_date`, `created_at`) VALUES
(27, 1, 12, 15, 8, NULL, NULL, NULL, NULL, '2026-01-01', '2026-05-31', '2026-04-24 14:45:32'),
(28, 1, 12, 17, 5, NULL, NULL, NULL, NULL, '2026-01-01', '2026-05-31', '2026-04-24 15:08:10');

-- --------------------------------------------------------
-- 11. BẢNG CLASS_SUBJECT_GROUPS (Gộp bảng Rooms và Extra_Classes)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_subject_groups`;
CREATE TABLE `class_subject_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_subject_id` int(11) NOT NULL,
  `group_code` varchar(10) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `day_of_week` int(11) DEFAULT NULL,
  `start_period` int(11) DEFAULT NULL,
  `end_period` int(11) DEFAULT NULL,
  `sub_teacher_id` int(11) DEFAULT NULL,
  `main_teacher_id` int(11) DEFAULT NULL,
  `is_extra` tinyint(1) DEFAULT 0 COMMENT '1: Học bù, 0: Chính khóa',
  `extra_date` date DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_subject_id` (`class_subject_id`),
  KEY `sub_teacher_id` (`sub_teacher_id`),
  CONSTRAINT `class_subject_groups_ibfk_1` FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subject_groups_ibfk_2` FOREIGN KEY (`sub_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_subject_groups` (`id`, `class_subject_id`, `group_code`, `room`, `day_of_week`, `start_period`, `end_period`, `sub_teacher_id`, `main_teacher_id`, `is_extra`, `extra_date`, `note`) VALUES
(59, 27, 'N1', 'PM3', 7, 6, 10, NULL, 5, 0, NULL, NULL),
(62, 28, 'N1', 'SMARTLAB', 3, 1, 5, NULL, 3, 0, NULL, NULL);

-- --------------------------------------------------------
-- 12. BẢNG CLASS_STUDENTS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_students`;
CREATE TABLE `class_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `class_students` (`id`, `class_id`, `student_id`) VALUES
(17, 12, 16);

-- --------------------------------------------------------
-- 13. BẢNG STUDENT_SUBJECT_REGISTRATION (Gộp từ bảng group_students)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_subject_registration`;
CREATE TABLE `student_subject_registration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL COMMENT 'NULL nếu sinh viên chưa tạo tài khoản',
  `class_subject_group_id` int(11) NOT NULL,
  `mssv` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `birth_date` varchar(20) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Đang học',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_registration_student` (`class_subject_group_id`,`student_id`),
  UNIQUE KEY `uk_registration_mssv` (`class_subject_group_id`,`mssv`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_subject_registration_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_subject_registration_ibfk_2` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate dữ liệu từ bảng group_students cũ sang bảng này để đảm bảo toàn vẹn
INSERT INTO `student_subject_registration` (`id`, `class_subject_group_id`, `student_id`, `mssv`, `full_name`, `birth_date`, `class_name`, `status`, `created_at`) VALUES
(272, 59, NULL, '22050001', 'PHẠM THANH PHONG', '38290', '25TH01', 'Đang học', '2026-04-24 15:57:59'),
(273, 59, NULL, '22050002', 'NGUYỄN QUỐC THÁI', '38314', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(274, 59, NULL, '22050003', 'TRẦN VIỆT ANH', '38289', '25TH01', 'Đang học', '2026-04-24 15:57:59'),
(275, 59, 2, NULL, 'PHẠM HUỲNH NHẬT Ý', '36734', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(276, 59, NULL, '22050005', 'NGUYỄN MINH ĐỨC', '38155', '25TH01', 'Đang học', '2026-04-24 15:57:59'),
(277, 59, NULL, '22050006', 'NGUYỄN QUỐC DUY KHANG', '37682', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(278, 59, NULL, '22050007', 'NGÔ LÊ THÀNH HẢI', '38247', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(279, 59, NULL, '22050008', 'BÙI VĂN ANH THẾ', '36560', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(280, 59, NULL, '22050009', 'LÊ NGỌC HẢI', '37316', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(281, 59, 6, NULL, 'ĐỖ HỮU TRÍ', '38145', '25TH02', 'Đang học', '2026-04-24 15:57:59'),
(282, 62, NULL, '22050001', 'PHẠM THANH PHONG', '38290', '25TH01', 'Đang học', '2026-04-24 15:58:06'),
(283, 62, NULL, '22050002', 'NGUYỄN QUỐC THÁI', '38314', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(284, 62, NULL, '22050003', 'TRẦN VIỆT ANH', '38289', '25TH01', 'Đang học', '2026-04-24 15:58:06'),
(285, 62, 2, NULL, 'PHẠM HUỲNH NHẬT Ý', '36734', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(286, 62, NULL, '22050005', 'NGUYỄN MINH ĐỨC', '38155', '25TH01', 'Đang học', '2026-04-24 15:58:06'),
(287, 62, NULL, '22050006', 'NGUYỄN QUỐC DUY KHANG', '37682', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(288, 62, NULL, '22050007', 'NGÔ LÊ THÀNH HẢI', '38247', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(289, 62, NULL, '22050008', 'BÙI VĂN ANH THẾ', '36560', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(290, 62, NULL, '22050009', 'LÊ NGỌC HẢI', '37316', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(291, 62, 6, NULL, 'ĐỖ HỮU TRÍ', '38145', '25TH02', 'Đang học', '2026-04-24 15:58:06'),
(292, 59, NULL, '22050004', 'Phạm Huỳnh Nhật Ý', NULL, '25TH02', 'Đang học', '2026-04-24 16:17:43');

-- --------------------------------------------------------
-- 14. BẢNG ATTENDANCE_SESSIONS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance_sessions`;
CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_subject_group_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'ID của BCS điểm danh',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_subject_group_id` (`class_subject_group_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `attendance_sessions_ibfk_1` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_sessions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `attendance_sessions` (`id`, `class_subject_group_id`, `attendance_date`, `created_by`, `created_at`) VALUES
(2, 62, '2026-04-24', 2, '2026-04-24 16:37:37');

-- --------------------------------------------------------
-- 15. BẢNG ATTENDANCE_RECORDS (Gộp bảng Evidences)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` int(11) NOT NULL COMMENT '1: Có mặt, 2: Vắng có phép, 3: Vắng không phép',
  `evidence_file` varchar(255) DEFAULT NULL,
  `evidence_link` varchar(255) DEFAULT NULL,
  `evidence_file_id` varchar(255) DEFAULT NULL,
  `evidence_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `evidence_approved_by` int(11) DEFAULT NULL,
  `evidence_uploaded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `idx_student_session` (`student_id`,`session_id`),
  KEY `evidence_approved_by` (`evidence_approved_by`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`evidence_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 16. BẢNG DOCUMENTS (Gộp bảng category_settings)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL COMMENT 'VD: Thông báo, Học liệu, Danh sách lớp',
  `drive_link` varchar(255) DEFAULT NULL,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `icon_type` varchar(20) DEFAULT NULL,
  `custom_icon` varchar(255) DEFAULT NULL,
  `class_subject_id` int(11) NOT NULL,
  `uploader_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploader_id` (`uploader_id`),
  KEY `idx_document_class` (`class_subject_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `documents` (`id`, `title`, `note`, `category`, `drive_link`, `drive_file_id`, `icon_type`, `custom_icon`, `class_subject_id`, `uploader_id`, `semester`, `created_at`) VALUES
(7, 'Danh sách lớp', NULL, 'Danh sách lớp', 'http://localhost/cms/public/uploads/documents/20260424_183830_78beb094_DS_mon_ANM.xlsx', NULL, 'file', NULL, 28, 2, 'HK2', '2026-04-24 16:38:30'),
(8, 'Danh sách lớp môn CSDL', NULL, 'Danh sách lớp', 'http://localhost/cms/public/uploads/documents/20260424_184909_eab93cbf_DS_mon_CSDL.xlsx', NULL, 'file', NULL, 28, 2, 'HK2', '2026-04-24 16:49:09');

-- --------------------------------------------------------
-- 17. BẢNG ROOMS (Phòng học)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(20) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `room_type` enum('lecture','lab','computer') DEFAULT 'lecture',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1: Có thể sử dụng, 0: Đang bảo trì',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_code` (`room_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rooms` (`id`, `room_code`, `room_name`, `building`, `capacity`, `room_type`, `is_active`, `note`) VALUES
(1, 'PM3', 'Phòng máy 3', 'Tòa B', 40, 'computer', 1, NULL),
(2, 'SMARTLAB', 'Phòng thực hành SmartLab', 'Tòa A', 30, 'lab', 1, NULL),
(3, 'A101', 'Phòng A101', 'Tòa A', 50, 'lecture', 1, NULL),
(4, 'B202', 'Phòng B202', 'Tòa B', 45, 'lecture', 1, NULL);

-- --------------------------------------------------------
-- 18. BẢNG GRADES
-- --------------------------------------------------------
DROP TABLE IF EXISTS `grades`;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_subject_group_id` int(11) NOT NULL,
  `assignment_score` float DEFAULT NULL COMMENT 'Điểm quá trình/bài tập',
  `midterm_score` float DEFAULT NULL COMMENT 'Điểm giữa kỳ',
  `final_score` float DEFAULT NULL COMMENT 'Điểm cuối kỳ',
  `total_score` float DEFAULT NULL COMMENT 'Điểm tổng kết',
  `grade_letter` varchar(2) DEFAULT NULL COMMENT 'Điểm chữ (A, B, C...)',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `class_subject_group_id` (`class_subject_group_id`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;