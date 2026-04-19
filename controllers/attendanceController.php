<?php
/**
 * CMS BDU - Attendance Controller
 * Xử lý các chức năng liên quan đến điểm danh
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Lấy danh sách sinh viên trong lớp của BCS
function getStudentsByBCS($bcsId) {
    return db_fetch_all("
        SELECT u.*, c.class_name
        FROM users u
        JOIN class_students cs ON u.id = cs.student_id
        JOIN classes c ON cs.class_id = c.id
        JOIN class_students bcs_cs ON cs.class_id = bcs_cs.class_id
        WHERE bcs_cs.student_id = ? AND u.role = 'student'
        ORDER BY u.full_name
    ", [$bcsId]);
}

// Lấy lịch học của sinh viên
function getStudentSchedule($studentId, $dayOfWeek = null) {
    $sql = "
        SELECT s.subject_name, csg.*, t.full_name as teacher_name
        FROM student_subject_registration ssr
        JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
        JOIN class_subjects cs ON csg.class_subject_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN users t ON cs.teacher_id = t.id
        WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
    ";
    
    $params = [$studentId];
    
    if ($dayOfWeek !== null) {
        $sql .= " AND csg.day_of_week = ?";
        $params[] = $dayOfWeek;
    }
    
    $sql .= " ORDER BY csg.day_of_week, csg.start_period";
    
    return db_fetch_all($sql, $params);
}

// Lấy bản ghi điểm danh của sinh viên
function getStudentAttendanceRecords($studentId, $subjectId = null) {
    $sql = "
        SELECT ar.*, a_s.attendance_date, s.subject_name, csg.room,
               ae.status as evidence_status, ae.drive_link
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
        JOIN class_subjects cs ON csg.class_subject_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        LEFT JOIN attendance_evidences ae ON ar.id = ae.attendance_record_id
        WHERE ar.student_id = ?
    ";
    
    $params = [$studentId];
    
    if ($subjectId !== null) {
        $sql .= " AND cs.subject_id = ?";
        $params[] = $subjectId;
    }
    
    $sql .= " ORDER BY a_s.attendance_date DESC";
    
    return db_fetch_all($sql, $params);
}

// Thống kê điểm danh theo môn
function getAttendanceSummary($studentId) {
    return db_fetch_all("
        SELECT 
            s.id as subject_id,
            s.subject_name,
            COUNT(ar.id) as total_sessions,
            SUM(CASE WHEN ar.status = 1 THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN ar.status = 2 THEN 1 ELSE 0 END) as excused,
            SUM(CASE WHEN ar.status = 3 THEN 1 ELSE 0 END) as absent
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
        JOIN class_subjects cs ON csg.class_subject_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        WHERE ar.student_id = ?
        GROUP BY s.id
        ORDER BY absent DESC
    ", [$studentId]);
}

// Đếm thông báo chưa đọc
function countUnreadNotifications($userId) {
    return db_count("SELECT COUNT(*) FROM notification_logs WHERE user_id = ? AND is_read = 0", [$userId]);
}
