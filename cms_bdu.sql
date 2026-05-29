-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3307
-- Thời gian đã tạo: Th5 29, 2026 lúc 06:00 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `cms_bdu`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `registration_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL COMMENT '1: Có mặt, 2: Vắng có phép, 3: Vắng không phép',
  `note` varchar(255) DEFAULT NULL,
  `evidence_file` varchar(255) DEFAULT NULL,
  `evidence_link` varchar(255) DEFAULT NULL,
  `evidence_file_id` varchar(255) DEFAULT NULL,
  `evidence_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `evidence_approved_by` int(11) DEFAULT NULL,
  `evidence_uploaded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `session_id`, `student_id`, `registration_id`, `status`, `note`, `evidence_file`, `evidence_link`, `evidence_file_id`, `evidence_status`, `evidence_approved_by`, `evidence_uploaded_at`) VALUES
(13, 4, 6, NULL, 1, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL),
(14, 4, 2, NULL, 1, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL),
(29, 3, 6, NULL, 1, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL),
(30, 3, 2, NULL, 1, NULL, NULL, NULL, NULL, 'Pending', NULL, NULL),
(161, 6, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(162, 6, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(163, 6, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(164, 6, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(165, 6, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(166, 6, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(167, 6, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(168, 6, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(169, 6, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(170, 6, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(171, 5, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(172, 5, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(173, 5, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(174, 5, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(175, 5, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(176, 5, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(177, 5, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(178, 5, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(179, 5, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(180, 5, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(181, 5, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(259, 7, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(260, 7, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(261, 7, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(262, 7, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(263, 7, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(264, 7, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(265, 7, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(266, 7, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(267, 7, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(268, 7, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(269, 7, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(303, 8, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(304, 8, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(305, 8, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(306, 8, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(307, 8, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(308, 8, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(309, 8, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(310, 8, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(311, 8, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(312, 8, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(313, 8, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(358, 10, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(359, 10, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(360, 10, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(361, 10, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(362, 10, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(363, 10, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(364, 10, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(365, 10, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(366, 10, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(367, 10, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(368, 10, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(369, 11, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(370, 11, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(371, 11, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(372, 11, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(373, 11, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(374, 11, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(375, 11, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(376, 11, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(377, 11, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(378, 11, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(379, 11, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(468, 9, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(469, 9, NULL, 307, 3, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(470, 9, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(471, 9, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(472, 9, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(473, 9, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(474, 9, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(475, 9, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(476, 9, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(477, 9, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(478, 9, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(501, 13, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(502, 13, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(503, 13, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(504, 13, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(505, 13, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(506, 13, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(507, 13, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(508, 13, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(509, 13, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(510, 13, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(511, 13, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(512, 12, NULL, 306, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(513, 12, NULL, 307, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(514, 12, NULL, 308, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(515, 12, 2, 327, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(516, 12, NULL, 309, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(517, 12, NULL, 310, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(518, 12, NULL, 311, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(519, 12, NULL, 312, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(520, 12, NULL, 313, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(521, 12, 6, 328, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL),
(522, 12, NULL, 329, 1, '', NULL, NULL, NULL, 'Pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL,
  `class_subject_group_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `study_session` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'ID của BCS điểm danh',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `attendance_sessions`
--

INSERT INTO `attendance_sessions` (`id`, `class_subject_group_id`, `attendance_date`, `study_session`, `created_by`, `created_at`) VALUES
(3, 68, '2026-05-11', NULL, 2, '2026-05-11 15:39:21'),
(4, 68, '2026-05-04', NULL, 2, '2026-05-11 15:53:25'),
(5, 68, '2026-05-13', 'Chiều', 2, '2026-05-13 15:31:38'),
(6, 68, '2026-05-20', 'Chiều', 2, '2026-05-13 16:58:50'),
(7, 68, '2026-05-15', 'Chiều', 2, '2026-05-15 07:26:46'),
(8, 68, '2026-05-16', 'Chiều', 2, '2026-05-16 13:20:16'),
(9, 68, '2026-05-28', 'Chiều', 2, '2026-05-28 15:16:03'),
(10, 68, '2026-05-29', 'Chiều', 2, '2026-05-28 15:16:18'),
(11, 68, '2026-05-30', 'Chiều', 2, '2026-05-28 15:16:21'),
(12, 68, '2026-05-27', 'Chiều', 2, '2026-05-28 15:19:50'),
(13, 68, '2026-05-26', 'Chiều', 2, '2026-05-28 15:19:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `class_name` varchar(50) NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `department_id`, `class_name`, `academic_year`, `created_at`) VALUES
(2, 1, '26TH02', '2023-2027', '2026-04-20 16:38:13'),
(8, 1, '26TH03', '2023-2027', '2026-04-24 03:40:04'),
(16, 1, '25TH02', '2022-2026', '2026-05-05 16:01:04'),
(20, 1, '25TH01', '2022-2026', '2026-05-11 12:41:24');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_students`
--

CREATE TABLE `class_students` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `class_students`
--

INSERT INTO `class_students` (`id`, `class_id`, `student_id`) VALUES
(22, 16, 2);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_subjects`
--

CREATE TABLE `class_subjects` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `class_subjects`
--

INSERT INTO `class_subjects` (`id`, `semester_id`, `class_id`, `subject_id`, `teacher_id`, `semester`, `study_session`, `start_period`, `end_period`, `start_date`, `end_date`, `created_at`) VALUES
(30, 4, 16, 15, 3, NULL, NULL, NULL, NULL, '2026-01-01', '2026-05-31', '2026-05-05 16:16:45'),
(31, 4, 16, 16, 8, NULL, NULL, NULL, NULL, '2026-01-01', '2026-05-31', '2026-05-05 16:17:25'),
(32, 4, 16, 17, 3, NULL, NULL, NULL, NULL, '2026-06-01', '2026-07-31', '2026-05-10 11:16:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_subject_groups`
--

CREATE TABLE `class_subject_groups` (
  `id` int(11) NOT NULL,
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
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `class_subject_groups`
--

INSERT INTO `class_subject_groups` (`id`, `class_subject_id`, `group_code`, `room`, `day_of_week`, `start_period`, `end_period`, `sub_teacher_id`, `main_teacher_id`, `is_extra`, `extra_date`, `note`) VALUES
(67, 30, 'N1', 'SMARTLAB', 2, 1, 5, NULL, 3, 0, NULL, NULL),
(68, 31, 'N1', 'A2.2', 4, 6, 10, NULL, 3, 0, NULL, NULL),
(73, 31, 'N2', 'A2.2', 7, 6, 10, NULL, 5, 0, NULL, NULL),
(76, 30, 'N2', 'A1.1', 6, 6, 10, NULL, 8, 0, NULL, NULL),
(77, 32, 'N1', 'DSLAB', 3, 1, 5, NULL, 3, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_code` varchar(20) DEFAULT NULL,
  `department_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `created_at`) VALUES
(1, '7480201', 'Công nghệ thông tin', '2026-04-20 13:24:39'),
(3, '4561934', 'Kĩ thuật Điện điện tử', '2026-04-24 03:34:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL COMMENT 'VD: Thông báo, Học liệu, Danh sách lớp',
  `drive_link` varchar(255) DEFAULT NULL,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `icon_type` varchar(20) DEFAULT NULL,
  `file_data` mediumblob DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `custom_icon` varchar(255) DEFAULT NULL,
  `class_subject_id` int(11) DEFAULT NULL,
  `uploader_id` int(11) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `documents`
--

INSERT INTO `documents` (`id`, `title`, `note`, `category`, `drive_link`, `drive_file_id`, `icon_type`, `file_data`, `file_size`, `file_mime`, `original_filename`, `custom_icon`, `class_subject_id`, `uploader_id`, `semester`, `created_at`) VALUES
(10, 'Đề tài tham khảo môn học An ninh cơ sở dữ liệu', NULL, 'Học liệu', 'http://localhost/cms/public/uploads/documents/20260513_191901_627f486e_AnNinhCoSoDuLieu-Tieu-Luan-Tieu-Chi-DanhGia.pdf', NULL, 'file', NULL, NULL, NULL, NULL, NULL, 32, 2, 'HK3', '2026-05-13 17:19:01'),
(11, 'Mẫu bài báo cáo môn học', NULL, 'Học liệu', 'http://localhost/cms/public/uploads/documents/20260515_085013_a69fb823_Bao_cao.pdf', NULL, 'file', NULL, NULL, NULL, NULL, NULL, NULL, 2, 'HK3', '2026-05-15 06:50:13');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` enum('Pending','Resolved') DEFAULT 'Pending',
  `reply_content` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `feedbacks`
--

INSERT INTO `feedbacks` (`id`, `student_id`, `title`, `content`, `status`, `reply_content`, `updated_at`) VALUES
(1, 2, 'Thắc mắc điểm danh', 'Tôi có học đủ các ngày học của môn Cơ sở dữ liệu', 'Pending', NULL, '2026-04-24 02:56:14'),
(2, 2, 'Góp ý tài liệu học tập', 'Các tài liệu nên mô tả thêm', 'Resolved', NULL, '2026-05-13 17:39:17'),
(3, 6, 'Góp ý tài liệu học tập', 'cần bổ sung file Ctr Đào tạo', 'Pending', NULL, '2026-04-24 16:35:09');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_subject_group_id` int(11) NOT NULL,
  `assignment_score` float DEFAULT NULL COMMENT 'Điểm quá trình/bài tập',
  `midterm_score` float DEFAULT NULL COMMENT 'Điểm giữa kỳ',
  `final_score` float DEFAULT NULL COMMENT 'Điểm cuối kỳ',
  `total_score` float DEFAULT NULL COMMENT 'Điểm tổng kết',
  `grade_letter` varchar(2) DEFAULT NULL COMMENT 'Điểm chữ (A, B, C...)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Đề xuất khen thưởng Tổng kết', 'Đề xuất Khen thưởng sinh viên xuất sắc trong năm học', 1, '2026-04-24 02:02:41'),
(2, 2, 'Nghỉ lễ 30/4 -1/5', 'Sinh viên được nghỉ lễ 02 ngày từ ngày 30/4 đến 1/5/2026', 1, '2026-04-24 03:57:22'),
(7, 2, 'Báo cáo môn Đồ án ngành', 'Sinh viên tham gia báo cáo cuối kỳ môn Đồ án ngành vào ngày 30/05/2026', 1, '2026-05-16 14:11:23'),
(8, 6, 'Báo cáo môn Đồ án ngành', 'Sinh viên tham gia báo cáo cuối kỳ môn Đồ án ngành vào ngày 30/05/2026', 0, '2026-05-16 14:11:23');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_code` varchar(20) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `room_type` enum('lecture','lab','computer') DEFAULT 'lecture',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1: Có thể sử dụng, 0: Đang bảo trì',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `rooms`
--

INSERT INTO `rooms` (`id`, `room_code`, `room_name`, `building`, `capacity`, `room_type`, `is_active`, `note`, `created_at`) VALUES
(1, 'PM3', 'Phòng máy 3', 'Khu B Lầu 2', 40, 'computer', 1, NULL, '2026-04-29 05:36:39'),
(2, 'SMARTLAB', 'Phòng SmartLab', 'Khu B Lầu 2', 30, 'lab', 1, NULL, '2026-04-29 05:36:39'),
(3, 'A1.1', 'Phòng A1.1', 'Khu A lầu 1', 40, 'lecture', 1, NULL, '2026-04-29 05:36:39'),
(4, 'A2.2', 'Phòng B2.2', 'Khu A lầu 2', 45, 'lecture', 1, NULL, '2026-04-29 05:36:39'),
(5, 'DSLAB', 'DS Lab', 'Khu B Lầu 1', 30, 'lab', 1, NULL, '2026-05-05 14:40:55');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `semester_name` varchar(20) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `semesters`
--

INSERT INTO `semesters` (`id`, `semester_name`, `academic_year`, `start_date`, `end_date`) VALUES
(1, 'HK2', '2025-2026', '2026-01-01', '2026-04-30'),
(2, 'HK1', '2025-2026', '2025-08-15', '2025-12-31'),
(4, 'HK3', '2025-2026', '2026-05-01', '2026-08-31'),
(5, 'HK1', '2026-2027', '2026-09-14', '2026-12-31');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'current_semester_id', '4', '2026-05-11 20:20:28');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_subject_registration`
--

CREATE TABLE `student_subject_registration` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL COMMENT 'NULL nếu sinh viên chưa tạo tài khoản',
  `class_subject_group_id` int(11) NOT NULL,
  `mssv` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `birth_date` varchar(20) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Đang học',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_subject_registration`
--

INSERT INTO `student_subject_registration` (`id`, `student_id`, `class_subject_group_id`, `mssv`, `full_name`, `birth_date`, `class_name`, `status`, `created_at`) VALUES
(296, NULL, 67, '22050001', 'Phạm Thanh Phong', '38290', '25TH01', 'Đang học', '2026-05-05 17:12:55'),
(297, NULL, 67, '22050002', 'Nguyễn Quốc Thái', '38314', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(298, NULL, 67, '22050003', 'Trần Việt Anh', '38289', '25TH01', 'Đang học', '2026-05-05 17:12:55'),
(299, NULL, 67, '22050005', 'Nguyễn Minh Đức', '38155', '25TH01', 'Đang học', '2026-05-05 17:12:55'),
(300, NULL, 67, '22050006', 'Nguyễn Quốc Duy Khang', '37682', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(301, NULL, 67, '22050017', 'Nghiêm Trung Hiếu', '38247', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(302, NULL, 67, '22050071', 'Huỳnh Trọng Hoàng', '36560', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(303, NULL, 67, '22050007', 'Ngô Lê Thành Hải', '37316', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(304, NULL, 67, '22050089', 'Phạm Hồng Quý', '38145', '25TH02', 'Đang học', '2026-05-05 17:12:55'),
(305, NULL, 67, '22050030', 'Vy Ngọc Nhân', '38146', '25TH01', 'Đang học', '2026-05-05 17:12:55'),
(306, NULL, 68, '22050001', 'PHẠM THANH PHONG', '38290', '25TH01', 'Đang học', '2026-05-05 17:13:07'),
(307, NULL, 68, '22050002', 'NGUYỄN QUỐC THÁI', '38314', '25TH02', 'Đang học', '2026-05-05 17:13:07'),
(308, NULL, 68, '22050003', 'TRẦN VIỆT ANH', '38289', '25TH01', 'Đang học', '2026-05-05 17:13:07'),
(309, NULL, 68, '22050005', 'NGUYỄN MINH ĐỨC', '38155', '25TH01', 'Đang học', '2026-05-05 17:13:07'),
(310, NULL, 68, '22050006', 'NGUYỄN QUỐC DUY KHANG', '37682', '25TH02', 'Đang học', '2026-05-05 17:13:07'),
(311, NULL, 68, '22050007', 'NGÔ LÊ THÀNH HẢI', '38247', '25TH02', 'Đang học', '2026-05-05 17:13:07'),
(312, NULL, 68, '22050008', 'BÙI VĂN ANH THẾ', '36560', '25TH02', 'Đang học', '2026-05-05 17:13:07'),
(313, NULL, 68, '22050009', 'LÊ NGỌC HẢI', '37316', '25TH02', 'Đang học', '2026-05-05 17:13:07'),
(314, NULL, 67, '22050008', 'BÙI VĂN ANH THẾ', '36560', '25TH02', 'Đang học', '2026-05-06 15:02:32'),
(315, NULL, 67, '22050009', 'LÊ NGỌC HẢI', '37316', '25TH02', 'Đang học', '2026-05-06 15:02:32'),
(316, 6, 67, '22050010', 'ĐỖ HỮU TRÍ', '38145', '25TH02', 'Đang học', '2026-05-06 15:02:32'),
(317, NULL, 76, '22050001', 'PHẠM THANH PHONG', '38290', '25TH01', 'Đang học', '2026-05-10 04:39:12'),
(318, NULL, 76, '22050002', 'NGUYỄN QUỐC THÁI', '38314', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(319, NULL, 76, '22050003', 'TRẦN VIỆT ANH', '38289', '25TH01', 'Đang học', '2026-05-10 04:39:12'),
(320, 2, 76, '22050004', 'PHẠM HUỲNH NHẬT Ý', '36734', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(321, NULL, 76, '22050005', 'NGUYỄN MINH ĐỨC', '38155', '25TH01', 'Đang học', '2026-05-10 04:39:12'),
(322, NULL, 76, '22050006', 'NGUYỄN QUỐC DUY KHANG', '37682', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(323, NULL, 76, '22050007', 'NGÔ LÊ THÀNH HẢI', '38247', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(324, NULL, 76, '22050008', 'BÙI VĂN ANH THẾ', '36560', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(325, NULL, 76, '22050009', 'LÊ NGỌC HẢI', '37316', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(326, 6, 76, '22050010', 'ĐỖ HỮU TRÍ', '38145', '25TH02', 'Đang học', '2026-05-10 04:39:12'),
(327, 2, 68, '22050004', 'PHẠM HUỲNH NHẬT Ý', '36734', '25TH02', 'Đang học', '2026-05-11 13:24:00'),
(328, 6, 68, '22050010', 'ĐỖ HỮU TRÍ', '38145', '25TH02', 'Đang học', '2026-05-11 13:24:00'),
(329, NULL, 68, '22050124', 'Nguyễn Bích Ngọc', '2004-01-24', '25TH02', 'Đang học', '2026-05-13 17:05:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `credits`, `year_level`, `semester`, `academic_year`, `prerequisite_id`, `is_active`, `open_date`, `close_date`, `created_at`) VALUES
(13, 'INF0433', 'Nhập môn lập trình', 3, 1, 'HK1', '2025-2026', NULL, 0, '2025-09-11', '2025-12-20', '2026-04-24 14:42:39'),
(14, 'INF0083', 'Cơ sở dữ liệu', 3, 1, 'HK1', '2025-2026', NULL, 0, '2025-09-17', '2025-12-24', '2026-04-24 14:43:51'),
(15, 'INF0823', 'Thiết kế web', 3, 2, 'HK3', '2025-2026', 13, 1, '2026-05-01', '2026-08-06', '2026-04-24 14:45:01'),
(16, 'INF0912', 'An ninh mạng', 2, 2, 'HK3', '2025-2026', NULL, 1, '2026-05-01', '2026-08-20', '2026-04-24 14:59:15'),
(17, 'INF1133', 'Lập trình hệ thống', 3, 3, 'HK3', '2025-2026', NULL, 1, '2026-05-01', '2026-08-05', '2026-04-24 15:01:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `target_table`, `target_id`, `created_at`) VALUES
(1, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-20 12:51:06'),
(14, 1, 'Tạo tài khoản mới - Phạm Huỳnh Nhật Ý (vai trò: bcs)', 'users', 2, '2026-04-20 13:28:15'),
(23, 1, 'Tạo môn học mới - An Ninh Mạng', 'subjects', 1, '2026-04-20 13:43:38'),
(31, 1, 'Tạo tài khoản mới - Nguyễn Hồ Hải (vai trò: teacher)', 'users', 3, '2026-04-20 16:29:47'),
(517, 1, 'Import 10 sinh viên vào nhóm ID #62', 'group_students', 62, '2026-04-24 15:58:06'),
(536, 2, 'Đăng nhập thành công', 'users', 2, '2026-04-24 16:48:04'),
(537, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 05:06:49'),
(538, 1, 'Đặt lại mật khẩu tài khoản ID #3', 'users', 3, '2026-04-29 05:12:10'),
(539, 3, 'Đăng nhập thành công', 'users', 3, '2026-04-29 05:13:33'),
(540, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 05:16:59'),
(541, 1, 'Cập nhật phòng học #1 - PM3', 'rooms', 1, '2026-04-29 05:37:07'),
(542, 1, 'Cập nhật phòng học #2 - SMARTLAB', 'rooms', 2, '2026-04-29 05:37:24'),
(543, 1, 'Đặt lại mật khẩu tài khoản ID #2', 'users', 2, '2026-04-29 05:43:34'),
(544, 2, 'Đăng nhập thành công', 'users', 2, '2026-04-29 05:43:46'),
(545, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 06:04:58'),
(546, 1, 'Cập nhật tài khoản ID #2 - Phạm Huỳnh Nhật Ý (vai trò: bcs)', 'users', 2, '2026-04-29 06:05:09'),
(547, 2, 'Đăng nhập thành công', 'users', 2, '2026-04-29 06:05:17'),
(548, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 06:26:21'),
(549, 1, 'Cập nhật tài khoản ID #8 - Dương Anh Tuấn (vai trò: support_admin)', 'users', 8, '2026-04-29 06:26:58'),
(550, 1, 'Đặt lại mật khẩu tài khoản ID #8', 'users', 8, '2026-04-29 06:27:15'),
(551, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 06:27:28'),
(552, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 06:44:32'),
(553, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 06:48:37'),
(554, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 06:54:30'),
(555, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 07:16:56'),
(556, 1, 'Đổi mật khẩu', 'users', 1, '2026-04-29 07:26:27'),
(557, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 07:26:47'),
(558, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 07:33:04'),
(559, 8, 'Đổi mật khẩu', 'users', 8, '2026-04-29 07:33:38'),
(560, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 07:33:49'),
(561, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 07:34:31'),
(562, 8, 'Đổi mật khẩu', 'users', 8, '2026-04-29 08:00:01'),
(563, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 08:00:10'),
(564, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 08:00:33'),
(565, 1, 'Đổi mật khẩu', 'users', 1, '2026-04-29 08:00:58'),
(566, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 08:01:07'),
(567, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 08:01:24'),
(568, 1, 'Đặt lại mật khẩu tài khoản ID #8', 'users', 8, '2026-04-29 08:01:42'),
(569, 1, 'Đặt lại mật khẩu tài khoản ID #1', 'users', 1, '2026-04-29 08:02:18'),
(570, 8, 'Đổi mật khẩu', 'users', 8, '2026-04-29 08:02:31'),
(571, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 08:02:41'),
(572, 1, 'Đặt lại mật khẩu tài khoản ID #8', 'users', 8, '2026-04-29 08:02:50'),
(573, 8, 'Đăng nhập thành công', 'users', 8, '2026-04-29 08:02:55'),
(574, 2, 'Đăng nhập thành công', 'users', 2, '2026-04-29 08:20:25'),
(575, 1, 'Đăng nhập thành công', 'users', 1, '2026-04-29 08:20:39'),
(576, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 08:21:11'),
(577, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-04-29 08:38:02'),
(578, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 08:48:34'),
(579, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 08:48:54'),
(580, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 08:49:31'),
(581, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 09:01:43'),
(582, 1, 'Thêm buổi học bù cho nhóm ID #62', 'class_subject_groups', 63, '2026-04-29 09:33:49'),
(583, 1, 'Import 0 sinh viên vào nhóm ID #59', 'student_subject_registration', 59, '2026-04-29 09:36:42'),
(584, 1, 'Thêm nhóm N2 vào lớp học phần ID #28', 'class_subject_groups', 64, '2026-04-29 09:40:57'),
(585, 1, 'Xóa nhóm N2 (ID #64) khỏi lớp học phần ID #28; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 64, '2026-04-29 09:41:11'),
(586, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 13:06:44'),
(587, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-05 13:13:25'),
(588, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 13:45:46'),
(589, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 13:57:31'),
(590, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 13:57:36'),
(591, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 14:06:34'),
(592, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 14:06:38'),
(593, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-05 14:06:41'),
(594, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 14:06:59'),
(595, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 14:28:54'),
(596, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 14:29:00'),
(597, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 14:29:10'),
(598, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 14:29:44'),
(599, 1, 'Tạo lịch nhóm N1 - lớp học phần ID #29', 'class_subject_groups', NULL, '2026-05-05 14:30:31'),
(600, 1, 'Tạo học kỳ mới - HK1', 'semesters', 5, '2026-05-05 14:39:57'),
(601, 1, 'Tạo phòng học mới - DSLAB', 'rooms', 5, '2026-05-05 14:40:55'),
(602, 1, 'Cập nhật phòng học #5 - DSLAB', 'rooms', 5, '2026-05-05 14:41:15'),
(603, 1, 'Cập nhật phòng học #1 - PM3', 'rooms', 1, '2026-05-05 14:41:25'),
(604, 1, 'Cập nhật phòng học #2 - SMARTLAB', 'rooms', 2, '2026-05-05 14:41:33'),
(605, 1, 'Tạo lớp học mới - 25TH01', 'classes', 13, '2026-05-05 14:42:59'),
(606, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 14:53:23'),
(607, 1, 'Thêm nhóm N2 vào lớp học phần ID #27', 'class_subject_groups', 66, '2026-05-05 14:53:56'),
(608, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #27', 'class_subject_groups', 66, '2026-05-05 14:54:27'),
(609, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #27', 'class_subject_groups', 66, '2026-05-05 14:54:46'),
(610, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #27', 'class_subject_groups', 66, '2026-05-05 14:55:04'),
(611, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #28', 'class_subject_groups', 62, '2026-05-05 14:55:32'),
(612, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-05 14:57:07'),
(613, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 14:57:21'),
(614, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 14:57:46'),
(615, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 14:58:10'),
(616, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-05 14:58:47'),
(617, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #27', 'class_subject_groups', 66, '2026-05-05 14:59:12'),
(618, 1, 'Xóa nhóm N2 (ID #66) khỏi lớp học phần ID #27; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 66, '2026-05-05 14:59:21'),
(619, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #27', 'class_subject_groups', 59, '2026-05-05 14:59:42'),
(620, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #27', 'class_subject_groups', 59, '2026-05-05 15:00:18'),
(621, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 15:47:15'),
(622, 1, 'Xóa lớp học ID #13 - ', 'classes', 13, '2026-05-05 15:54:29'),
(623, 1, 'Tạo lớp học mới - 25TH01', 'classes', 14, '2026-05-05 15:54:38'),
(624, 1, 'Xóa lớp học ID #14 - ', 'classes', 14, '2026-05-05 15:56:56'),
(625, 1, 'Xóa lớp học ID #15 - ', 'classes', 15, '2026-05-05 15:59:20'),
(626, 1, 'Xóa lớp học ID #12 - ', 'classes', 12, '2026-05-05 16:00:54'),
(627, 1, 'Tạo lớp học mới - 25TH02', 'classes', 16, '2026-05-05 16:01:04'),
(628, 1, 'Tạo lớp học mới - 25TH01', 'classes', 17, '2026-05-05 16:01:20'),
(629, 1, 'Cập nhật lớp học ID #16 - 25TH02', 'classes', 16, '2026-05-05 16:02:27'),
(630, 1, 'Xóa lớp học ID #17 - ', 'classes', 17, '2026-05-05 16:07:42'),
(631, 1, 'Tạo lớp học mới - 25TH01', 'classes', 18, '2026-05-05 16:07:50'),
(632, 1, 'Xóa lớp học ID #18 - ', 'classes', 18, '2026-05-05 16:12:26'),
(633, 1, 'Tạo lớp học mới - 25TH01', 'classes', 19, '2026-05-05 16:12:34'),
(634, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-05 16:15:50'),
(635, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 16:15:55'),
(636, 1, 'Tạo lịch nhóm N1 - lớp học phần ID #30', 'class_subject_groups', NULL, '2026-05-05 16:16:45'),
(637, 1, 'Tạo lịch nhóm N1 - lớp học phần ID #31', 'class_subject_groups', NULL, '2026-05-05 16:17:25'),
(638, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 16:17:56'),
(639, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-05 16:18:06'),
(640, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-05 16:19:02'),
(641, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 16:31:07'),
(642, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-05 16:31:12'),
(643, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-05 16:39:55'),
(644, 1, 'Import 1 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-05 16:57:39'),
(645, 1, 'Import 2 sinh viên vào nhóm ID #68', 'student_subject_registration', 68, '2026-05-05 16:57:54'),
(646, 1, 'Import 10 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-05 17:12:55'),
(647, 1, 'Import 8 sinh viên vào nhóm ID #68', 'student_subject_registration', 68, '2026-05-05 17:13:07'),
(648, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 13:05:14'),
(649, 1, 'Xóa lớp học ID #19 - ', 'classes', 19, '2026-05-06 13:05:39'),
(650, 1, 'Thêm nhóm N2 vào lớp học phần ID #31', 'class_subject_groups', 69, '2026-05-06 13:06:25'),
(651, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 69, '2026-05-06 13:06:49'),
(652, 1, 'Xóa nhóm N2 (ID #69) khỏi lớp học phần ID #31; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 69, '2026-05-06 13:07:12'),
(653, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #31', 'class_subject_groups', 68, '2026-05-06 13:07:32'),
(654, 1, 'Thêm nhóm N2 vào lớp học phần ID #30', 'class_subject_groups', 70, '2026-05-06 13:07:58'),
(655, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 70, '2026-05-06 13:08:12'),
(656, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #31', 'class_subject_groups', 68, '2026-05-06 13:08:44'),
(657, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 13:43:14'),
(658, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 70, '2026-05-06 13:44:01'),
(659, 1, 'Thêm nhóm N2 vào lớp học phần ID #31', 'class_subject_groups', 71, '2026-05-06 13:44:28'),
(660, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 71, '2026-05-06 13:44:48'),
(661, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 71, '2026-05-06 13:53:20'),
(662, 1, 'Xóa nhóm N2 (ID #71) khỏi lớp học phần ID #31; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 71, '2026-05-06 13:53:33'),
(663, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #31', 'class_subject_groups', 68, '2026-05-06 13:54:18'),
(664, 1, 'Cập nhật lịch nhóm N1 - lớp học phần ID #31', 'class_subject_groups', 68, '2026-05-06 13:54:27'),
(665, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 14:18:52'),
(666, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 70, '2026-05-06 14:22:01'),
(667, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 70, '2026-05-06 14:22:06'),
(668, 1, 'Thêm nhóm N2 vào lớp học phần ID #31', 'class_subject_groups', 72, '2026-05-06 14:22:10'),
(669, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 72, '2026-05-06 14:22:27'),
(670, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 72, '2026-05-06 14:28:54'),
(671, 1, 'Xóa nhóm N2 (ID #72) khỏi lớp học phần ID #31; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 72, '2026-05-06 14:33:10'),
(672, 1, 'Thêm nhóm N2 vào lớp học phần ID #31', 'class_subject_groups', 73, '2026-05-06 14:33:17'),
(673, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 73, '2026-05-06 14:33:35'),
(674, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #31', 'class_subject_groups', 73, '2026-05-06 14:33:54'),
(675, 1, 'Thêm nhóm N3 vào lớp học phần ID #30', 'class_subject_groups', 74, '2026-05-06 14:34:04'),
(676, 1, 'Cập nhật lịch nhóm N3 - lớp học phần ID #30', 'class_subject_groups', 74, '2026-05-06 14:34:26'),
(677, 1, 'Xóa nhóm N3 (ID #74) khỏi lớp học phần ID #30; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 74, '2026-05-06 14:44:53'),
(678, 1, 'Xóa nhóm N2 (ID #70) khỏi lớp học phần ID #30; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 70, '2026-05-06 14:44:55'),
(679, 1, 'Thêm nhóm N2 vào lớp học phần ID #30', 'class_subject_groups', 75, '2026-05-06 14:45:01'),
(680, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 75, '2026-05-06 14:45:17'),
(681, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 75, '2026-05-06 14:49:00'),
(682, 1, 'Xóa nhóm N2 (ID #75) khỏi lớp học phần ID #30; xóa 0 sinh viên thuộc nhóm', 'class_subject_groups', 75, '2026-05-06 14:49:08'),
(683, 1, 'Thêm nhóm N2 vào lớp học phần ID #30', 'class_subject_groups', 76, '2026-05-06 14:49:10'),
(684, 1, 'Cập nhật lịch nhóm N2 - lớp học phần ID #30', 'class_subject_groups', 76, '2026-05-06 14:49:31'),
(685, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-06 14:50:26'),
(686, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-06 14:50:41'),
(687, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-06 14:50:46'),
(688, 1, 'Import 0 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 14:51:52'),
(689, 1, 'Import 0 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 15:01:58'),
(690, 1, 'Import 0 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 15:02:12'),
(691, 1, 'Import 3 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 15:02:32'),
(692, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 15:15:57'),
(693, 1, 'Import 0 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 15:32:56'),
(694, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 15:51:53'),
(695, 1, 'Cập nhật môn học ID #15 - Thiết kế web', 'subjects', 15, '2026-05-06 16:00:58'),
(696, 1, 'Cập nhật môn học ID #16 - An ninh mạng', 'subjects', 16, '2026-05-06 16:01:04'),
(697, 1, 'Cập nhật môn học ID #17 - Lập trình hệ thống', 'subjects', 17, '2026-05-06 16:01:09'),
(698, 1, 'Import 0 sinh viên vào nhóm ID #67', 'student_subject_registration', 67, '2026-05-06 16:17:33'),
(699, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 16:26:20'),
(700, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-06 16:47:10'),
(701, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 04:38:39'),
(702, 1, 'Import 10 sinh viên vào nhóm ID #76', 'student_subject_registration', 76, '2026-05-10 04:39:12'),
(703, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 05:43:52'),
(704, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 09:38:51'),
(705, 1, 'Đặt học kỳ ID #4 làm hiện tại', 'semesters', 4, '2026-05-10 10:06:35'),
(706, 1, 'Tạo lịch nhóm N1 - lớp học phần ID #32', 'class_subject_groups', NULL, '2026-05-10 11:16:32'),
(707, 1, 'Đặt lại mật khẩu tài khoản ID #2', 'users', 2, '2026-05-10 11:17:50'),
(708, 1, 'Đặt lại mật khẩu tài khoản ID #6', 'users', 6, '2026-05-10 11:17:59'),
(709, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 11:18:20'),
(710, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 12:40:11'),
(711, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 14:26:03'),
(712, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 14:26:34'),
(713, 1, 'Cập nhật tài khoản ID #2 - Phạm Huỳnh Nhật Ý (vai trò: bcs)', 'users', 2, '2026-05-10 14:26:48'),
(714, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 14:26:55'),
(715, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 15:29:33'),
(716, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 15:29:58'),
(717, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 15:30:27'),
(718, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-10 17:21:04'),
(719, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-10 17:21:27'),
(720, 1, 'Import sinh viên vào lớp 25TH02: 0 thành công, 26 bỏ qua, 0 lỗi', 'class_students', NULL, '2026-05-10 17:30:16'),
(721, 1, 'Import sinh viên vào lớp 25TH02: 0 thành công, 26 bỏ qua, 0 lỗi', 'class_students', NULL, '2026-05-10 17:30:45'),
(722, 1, 'Import sinh viên vào lớp 25TH02: 0 thành công, 26 bỏ qua, 0 lỗi', 'class_students', NULL, '2026-05-10 17:31:02'),
(723, 1, 'Import sinh viên vào lớp 25TH02: 0 thành công, 26 bỏ qua, 0 lỗi', 'class_students', NULL, '2026-05-10 17:47:04'),
(724, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 12:16:28'),
(725, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-11 12:31:14'),
(726, 1, 'Tạo lớp học mới - 25TH01', 'classes', 20, '2026-05-11 12:41:24'),
(727, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 12:41:40'),
(728, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 13:06:30'),
(729, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-11 13:06:38'),
(730, 1, 'Đặt học kỳ ID #4 làm hiện tại', 'semesters', 4, '2026-05-11 13:20:28'),
(731, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 13:23:05'),
(732, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-11 13:23:46'),
(733, 1, 'Import 2 sinh viên vào nhóm ID #68', 'student_subject_registration', 68, '2026-05-11 13:24:00'),
(734, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 13:24:27'),
(735, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-11 13:45:44'),
(736, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 13:47:02'),
(737, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 15:39:06'),
(738, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-11 16:13:18'),
(739, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-13 15:01:58'),
(740, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-13 16:44:35'),
(741, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-15 06:47:46'),
(742, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-15 06:48:54'),
(743, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-15 08:08:40'),
(744, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-15 08:09:44'),
(745, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-15 08:10:47'),
(746, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-15 08:11:41'),
(747, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-15 08:22:44'),
(748, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-15 08:23:16'),
(749, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-15 08:36:22'),
(750, 1, 'Cập nhật học kỳ ID #1 - HK2', 'semesters', 1, '2026-05-15 08:36:54'),
(751, 1, 'Cập nhật học kỳ ID #4 - HK3', 'semesters', 4, '2026-05-15 08:37:05'),
(752, 1, 'Cập nhật học kỳ ID #4 - HK3', 'semesters', 4, '2026-05-15 08:37:15'),
(753, 1, 'Cập nhật học kỳ ID #4 - HK3', 'semesters', 4, '2026-05-15 08:37:26'),
(754, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-15 08:37:41'),
(755, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 06:59:54'),
(756, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 07:29:40'),
(757, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 09:00:21'),
(758, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 09:01:31'),
(759, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 09:05:22'),
(760, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 09:14:01'),
(761, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 09:14:43'),
(762, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 09:15:21'),
(763, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 09:18:18'),
(764, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 09:18:55'),
(765, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 09:19:19'),
(766, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 09:34:54'),
(767, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 09:35:11'),
(768, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 12:02:55'),
(769, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 12:06:12'),
(770, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 12:58:41'),
(771, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 13:01:48'),
(772, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-16 13:18:43'),
(773, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 13:19:57'),
(774, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 13:37:08'),
(775, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 13:39:23'),
(776, 2, 'Lưu điểm danh - nhóm ID #68 ngày 2026-05-16 (11 sinh viên)', 'attendance_sessions', 8, '2026-05-16 13:39:38'),
(777, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-16 buổi Chiều (11 sinh viên)', 'attendance_sessions', 8, '2026-05-16 13:45:05'),
(778, 1, 'Đăng xuất', 'users', 1, '2026-05-16 13:49:08'),
(779, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 13:49:16'),
(780, 2, 'Tạo thông báo: Thông báo báo cáo cuối kỳ môn Đồ án ngành', 'notification_logs', 5, '2026-05-16 13:52:52'),
(781, 2, 'Cập nhật thông báo ID #5: Thông báo báo cáo cuối kỳ môn Đồ án ngành', 'notification_logs', 5, '2026-05-16 13:56:18'),
(782, 2, 'Xóa thông báo ID #5', 'notification_logs', 5, '2026-05-16 13:56:52'),
(783, 2, 'Tạo thông báo: Báo cáo môn Đồ án ngành', 'notification_logs', 6, '2026-05-16 13:57:41'),
(784, 2, 'Đánh dấu thông báo ID #6 là chưa đọc', 'notification_logs', 6, '2026-05-16 13:58:46'),
(785, 2, 'Đọc thông báo ID #6', 'notification_logs', 6, '2026-05-16 13:58:48'),
(786, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 14:08:59'),
(787, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 14:09:58'),
(788, 2, 'Xóa thông báo ID #6', 'notification_logs', 6, '2026-05-16 14:10:53'),
(789, 2, 'Tạo thông báo: Báo cáo môn Đồ án ngành', 'notification_logs', 7, '2026-05-16 14:11:23'),
(790, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 14:11:36'),
(791, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 14:16:39'),
(792, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-16 14:21:28'),
(793, 2, 'Đánh dấu thông báo ID #1 là chưa đọc', 'notification_logs', 1, '2026-05-16 14:21:45'),
(794, 2, 'Đánh dấu thông báo ID #2 là chưa đọc', 'notification_logs', 2, '2026-05-16 14:21:47'),
(795, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-16 14:33:46'),
(796, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-16 14:33:51'),
(797, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 14:34:07'),
(798, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-16 14:34:46'),
(799, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-16 14:34:51'),
(800, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-16 14:34:58'),
(801, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-16 14:59:15'),
(802, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-16 14:59:22'),
(803, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-16 14:59:54'),
(804, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-16 15:00:21'),
(805, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-18 13:28:08'),
(806, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-18 13:28:30'),
(807, 2, 'Đọc thông báo ID #1', 'notification_logs', 1, '2026-05-18 13:28:39'),
(808, 2, 'Đọc thông báo ID #2', 'notification_logs', 2, '2026-05-18 13:28:52'),
(809, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-18 13:33:04'),
(810, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-18 13:43:35'),
(811, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-18 14:26:07'),
(812, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-18 14:26:35'),
(813, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-18 14:26:39'),
(814, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-18 14:45:09'),
(815, 2, 'Đánh dấu thông báo ID #7 là chưa đọc', 'notification_logs', 7, '2026-05-18 15:06:23'),
(816, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-18 15:09:08'),
(817, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-18 15:09:22'),
(818, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-18 15:09:34'),
(819, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-18 15:15:32'),
(820, 6, 'Đăng nhập thành công', 'users', 6, '2026-05-18 15:33:25'),
(821, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-18 15:58:52'),
(822, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-18 16:04:04'),
(823, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-18 16:04:16'),
(824, 6, 'Đọc thông báo ID #8', 'notification_logs', 8, '2026-05-18 16:04:37'),
(825, 6, 'Đánh dấu thông báo ID #8 là chưa đọc', 'notification_logs', 8, '2026-05-18 16:04:41'),
(826, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-25 14:39:25'),
(827, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-25 15:24:57'),
(828, 1, 'Đăng xuất', 'users', 1, '2026-05-25 15:35:46'),
(829, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-25 15:36:01'),
(830, 1, 'Đăng xuất', 'users', 1, '2026-05-25 15:39:31'),
(831, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-25 15:39:37'),
(832, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-28 13:28:35'),
(833, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-28 13:28:49'),
(834, 2, 'Đọc thông báo ID #7', 'notification_logs', 7, '2026-05-28 14:06:49'),
(835, 2, 'Đăng nhập thành công', 'users', 2, '2026-05-28 14:22:56'),
(836, 1, 'Đăng nhập thành công', 'users', 1, '2026-05-28 15:05:24'),
(837, 1, 'Đăng xuất', 'users', 1, '2026-05-28 15:05:58'),
(838, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-28 15:06:06'),
(839, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-28 buổi Chiều (11 sinh viên)', 'attendance_sessions', 9, '2026-05-28 15:16:03'),
(840, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-29 buổi Chiều (11 sinh viên)', 'attendance_sessions', 10, '2026-05-28 15:16:18'),
(841, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-30 buổi Chiều (11 sinh viên)', 'attendance_sessions', 11, '2026-05-28 15:16:21'),
(842, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-28 buổi Chiều (11 sinh viên)', 'attendance_sessions', 9, '2026-05-28 15:18:51'),
(843, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-29 buổi Chiều (11 sinh viên)', 'attendance_sessions', 10, '2026-05-28 15:18:56'),
(844, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-30 buổi Chiều (11 sinh viên)', 'attendance_sessions', 11, '2026-05-28 15:19:00'),
(845, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-28 buổi Chiều (11 sinh viên)', 'attendance_sessions', 9, '2026-05-28 15:19:45'),
(846, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-27 buổi Chiều (11 sinh viên)', 'attendance_sessions', 12, '2026-05-28 15:19:50'),
(847, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-28 15:19:54'),
(848, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-28 15:28:47'),
(849, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-28 buổi Chiều (11 sinh viên)', 'attendance_sessions', 9, '2026-05-28 15:35:53'),
(850, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-27 buổi Chiều (11 sinh viên)', 'attendance_sessions', 12, '2026-05-28 15:35:56'),
(851, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-28 15:36:00'),
(852, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-28 15:36:04'),
(853, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-28 15:36:38'),
(854, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-28 buổi Chiều (11 sinh viên)', 'attendance_sessions', 9, '2026-05-28 15:36:42'),
(855, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-27 buổi Chiều (11 sinh viên)', 'attendance_sessions', 12, '2026-05-28 15:39:44'),
(856, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-28 15:39:48'),
(857, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-28 16:12:36'),
(858, 2, 'Đăng nhập bằng Google', 'users', 2, '2026-05-29 03:13:17'),
(859, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-26 buổi Chiều (11 sinh viên)', 'attendance_sessions', 13, '2026-05-29 03:13:44'),
(860, 2, 'Lưu điểm danh An ninh mạng (INF0912) - Nhóm N1 - Lớp 25TH02 ngày 2026-05-27 buổi Chiều (11 sinh viên)', 'attendance_sessions', 12, '2026-05-29 03:13:49');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `google_id`, `role`, `secondary_role`, `is_active`, `position`, `academic_title`, `avatar`, `birth_date`, `phone_number`, `address`, `created_at`) VALUES
(1, 'admin', '$2y$10$6lr28aW.TQQhM4Gg3VFoEeLPP49fnj79mvJl499daGrVI/Vu2k.ZW', 'Quản trị viên Hệ thống', 'admin@bdu.edu.vn', NULL, 'admin', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-20 12:49:37'),
(2, '22050004', '$2y$10$CQiwL4S.fW4eFCitViBnJONQkYtlAs9FmnjS4OQWIQapRDilolDcy', 'Phạm Huỳnh Nhật Ý', '22050004@student.bdu.edu.vn', NULL, 'bcs', 'student', 1, 'Lớp trưởng', NULL, NULL, '2000-07-27', '0383330056', NULL, '2026-04-20 13:28:15'),
(3, 'GVNHH', '$2y$10$kGK2/U59aj7B0PotrUUkFu77LlvFgAUQEHr5ptP/hdSSPuuDkQM9u', 'Nguyễn Hồ Hải', 'nguyenhh@bdu.edu.vn', NULL, 'support_admin', 'teacher', 1, NULL, 'ThS', NULL, NULL, NULL, NULL, '2026-04-20 16:29:47'),
(5, 'GVHQD', '$2y$10$YdY.HtimuGMOS5C2/KxfJe0VWHRuyMTnNy/RGAwJIJeuBO6g37AA2', 'Huỳnh Quang Đức', 'hqduc@bdu.edu.vn', NULL, 'teacher', NULL, 1, NULL, 'ThS', NULL, NULL, NULL, NULL, '2026-04-22 08:02:03'),
(6, '22050010', '$2y$10$A0K3vfAIToWMbfzvIQQOauu2uYFM.5zy//SM0Ql/Kvco5/A/Zh.A2', 'Đỗ Hữu Trí', '22050010@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-04-16', NULL, NULL, '2026-04-24 03:35:49'),
(7, '22050020', '$2y$10$qDBFTWNa2YF69b1mPYG.X.0p0gNd8boMf1FrDT.f84xserFnkiNFO', 'Bùi Hữu Phước', '22050020@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-06-22', NULL, NULL, '2026-04-24 03:39:21'),
(8, 'GVDAT', '$2y$10$1ANYG4Tiw95zz0HLKJZ2VuGnJTsJhr8jG5hEyXW1VRAuku8W.s81e', 'Dương Anh Tuấn', 'datuan@bdu.edu.vn', NULL, 'support_admin', NULL, 1, 'Phó trưởng bộ môn', 'ThS', NULL, '1985-08-16', NULL, NULL, '2026-04-24 07:00:06'),
(16, '22050040', '$2y$10$alX/O/.QJ58DsrdqExlW8.3FrsSoV/c63m2bnIdvQu1qq7.Evs.b.', 'Quách Thị Thu', '22050040@student.bdu.edu.vn', NULL, 'student', NULL, 1, NULL, NULL, NULL, '2004-02-16', NULL, NULL, '2026-04-24 16:46:34');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `idx_student_session` (`student_id`,`session_id`),
  ADD KEY `evidence_approved_by` (`evidence_approved_by`),
  ADD KEY `idx_registration_session` (`registration_id`,`session_id`);

--
-- Chỉ mục cho bảng `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_subject_group_id` (`class_subject_group_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Chỉ mục cho bảng `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `class_subjects`
--
ALTER TABLE `class_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Chỉ mục cho bảng `class_subject_groups`
--
ALTER TABLE `class_subject_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_subject_id` (`class_subject_id`),
  ADD KEY `sub_teacher_id` (`sub_teacher_id`);

--
-- Chỉ mục cho bảng `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploader_id` (`uploader_id`),
  ADD KEY `idx_document_class` (`class_subject_id`);

--
-- Chỉ mục cho bảng `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_subject_group_id` (`class_subject_group_id`);

--
-- Chỉ mục cho bảng `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_code` (`room_code`);

--
-- Chỉ mục cho bảng `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Chỉ mục cho bảng `student_subject_registration`
--
ALTER TABLE `student_subject_registration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_registration_student` (`class_subject_group_id`,`student_id`),
  ADD UNIQUE KEY `uk_registration_mssv` (`class_subject_group_id`,`mssv`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `prerequisite_id` (`prerequisite_id`);

--
-- Chỉ mục cho bảng `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=523;

--
-- AUTO_INCREMENT cho bảng `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT cho bảng `class_students`
--
ALTER TABLE `class_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT cho bảng `class_subjects`
--
ALTER TABLE `class_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT cho bảng `class_subject_groups`
--
ALTER TABLE `class_subject_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT cho bảng `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `student_subject_registration`
--
ALTER TABLE `student_subject_registration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=330;

--
-- AUTO_INCREMENT cho bảng `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT cho bảng `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=861;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_records_ibfk_3` FOREIGN KEY (`evidence_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD CONSTRAINT `attendance_sessions_ibfk_1` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_sessions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `class_subjects`
--
ALTER TABLE `class_subjects`
  ADD CONSTRAINT `class_subjects_ibfk_1` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subjects_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subjects_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subjects_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `class_subject_groups`
--
ALTER TABLE `class_subject_groups`
  ADD CONSTRAINT `class_subject_groups_ibfk_1` FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subject_groups_ibfk_2` FOREIGN KEY (`sub_teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `student_subject_registration`
--
ALTER TABLE `student_subject_registration`
  ADD CONSTRAINT `student_subject_registration_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_subject_registration_ibfk_2` FOREIGN KEY (`class_subject_group_id`) REFERENCES `class_subject_groups` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`prerequisite_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
