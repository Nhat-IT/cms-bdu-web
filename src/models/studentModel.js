const db = require('../config/db');

// ========== HỒ SƠ SINH VIÊN ==========
exports.getStudentProfile = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT id, username, full_name, email, avatar, role, created_at 
             FROM users WHERE id = ? AND role = 'student'`,
            [userId]
        );
        return rows[0] || null;
    } catch (error) {
        console.error('Error getting student profile:', error);
        throw error;
    }
};

// ========== LỚP HỌC CỦA SINH VIÊN ==========
exports.getStudentClasses = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT DISTINCT
                cs.id as class_subject_id,
                s.subject_name,
                s.subject_code,
                s.credits,
                c.class_name,
                cs.study_session,
                cs.start_date,
                cs.end_date,
                u.full_name as teacher_name,
                csg.group_code,
                csg.room
            FROM student_subject_registration ssr
            JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
            JOIN class_subjects cs ON csg.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN users u ON cs.teacher_id = u.id
            WHERE ssr.student_id = ? AND ssr.status = 'Đang học'
            ORDER BY cs.start_date DESC`,
            [userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting student classes:', error);
        throw error;
    }
};

// ========== THỐNG KÊ ĐIỂM DANH ==========
exports.getAttendanceStats = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN ar.status = 1 THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN ar.status = 2 THEN 1 ELSE 0 END) as excused_absent,
                SUM(CASE WHEN ar.status = 3 THEN 1 ELSE 0 END) as unexcused_absent
            FROM attendance_records ar
            JOIN attendance_sessions asess ON ar.session_id = asess.id
            JOIN class_subject_groups csg ON asess.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            WHERE ar.student_id = ? AND ssr.student_id = ?`,
            [userId, userId]
        );
        return rows[0] || { total_sessions: 0, present: 0, excused_absent: 0, unexcused_absent: 0 };
    } catch (error) {
        console.error('Error getting attendance stats:', error);
        throw error;
    }
};

// ========== CHI TIẾT ĐIỂM DANH ==========
exports.getAttendanceRecords = async (userId, limit = 50) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                ar.id,
                asess.attendance_date,
                s.subject_name,
                CASE 
                    WHEN ar.status = 1 THEN 'Có mặt'
                    WHEN ar.status = 2 THEN 'Vắng có phép'
                    WHEN ar.status = 3 THEN 'Vắng không phép'
                END as status_text,
                ar.status,
                ae.drive_link,
                ae.status as evidence_status
            FROM attendance_records ar
            JOIN attendance_sessions asess ON ar.session_id = asess.id
            JOIN class_subject_groups csg ON asess.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            JOIN class_subjects cs ON csg.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            LEFT JOIN attendance_evidences ae ON ar.id = ae.attendance_record_id
            WHERE ar.student_id = ? AND ssr.student_id = ?
            ORDER BY asess.attendance_date DESC
            LIMIT ?`,
            [userId, userId, limit]
        );
        return rows;
    } catch (error) {
        console.error('Error getting attendance records:', error);
        throw error;
    }
};

// ========== ĐIỂM TỪ CÁC MÔN HỌC ==========
exports.getGrades = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                g.id,
                s.subject_name,
                s.subject_code,
                csg.group_code,
                g.assignment_score,
                g.midterm_score,
                g.final_score,
                g.total_score,
                g.grade_letter,
                cs.end_date
            FROM grades g
            JOIN class_subject_groups csg ON g.class_subject_group_id = csg.id
            JOIN class_subjects cs ON csg.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            WHERE g.student_id = ? AND ssr.student_id = ?
            ORDER BY cs.end_date DESC`,
            [userId, userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting grades:', error);
        throw error;
    }
};

// ========== BÀI TẬP CỦA SINH VIÊN ==========
exports.getAssignments = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                a.id,
                a.title,
                a.description,
                a.deadline,
                s.subject_name,
                s.subject_code,
                asub.score,
                asub.feedback,
                asub.submitted_at,
                asub.drive_link,
                CASE 
                    WHEN asub.id IS NULL THEN 'Chưa nộp'
                    WHEN a.deadline < NOW() AND asub.submitted_at > a.deadline THEN 'Nộp trễ'
                    ELSE 'Đã nộp'
                END as submission_status
            FROM assignments a
            JOIN class_subjects cs ON a.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN class_subject_groups csg ON cs.id = csg.class_subject_id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ssr.student_id
            WHERE ssr.student_id = ?
            ORDER BY a.deadline DESC`,
            [userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting assignments:', error);
        throw error;
    }
};

// ========== TÀI LIỆU LỚP HỌC ==========
exports.getClassDocuments = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                d.id,
                d.title,
                d.note,
                d.category,
                d.drive_link,
                d.created_at,
                s.subject_name,
                s.subject_code,
                u.full_name as uploader_name
            FROM documents d
            JOIN class_subjects cs ON d.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN class_subject_groups csg ON cs.id = csg.class_subject_id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            LEFT JOIN users u ON d.uploader_id = u.id
            WHERE ssr.student_id = ?
            ORDER BY d.created_at DESC`,
            [userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting documents:', error);
        throw error;
    }
};

// ========== THÔNG BÁO ==========
exports.getNotifications = async (userId, limit = 20) => {
    try {
        const [rows] = await db.query(
            `SELECT 
                id,
                title,
                message,
                is_read,
                created_at
            FROM notification_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?`,
            [userId, limit]
        );
        return rows;
    } catch (error) {
        console.error('Error getting notifications:', error);
        throw error;
    }
};

// ========== CẬP NHẬT TRẠNG THÁI THÔNG BÁO ==========
exports.markNotificationsAsRead = async (userId) => {
    try {
        const [result] = await db.query(
            `UPDATE notification_logs SET is_read = 1 WHERE user_id = ? AND is_read = 0`,
            [userId]
        );
        return result.affectedRows;
    } catch (error) {
        console.error('Error marking notifications as read:', error);
        throw error;
    }
};

// ========== THỐNG KÊ TỔNG HỢP SINH VIÊN ==========
exports.getStudentDashboard = async (userId) => {
    try {
        const [profile] = await Promise.all([
            this.getStudentProfile(userId),
            this.getAttendanceStats(userId),
            this.getGrades(userId)
        ]);

        const attendance = await this.getAttendanceStats(userId);
        const grades = await this.getGrades(userId);
        const classes = await this.getStudentClasses(userId);
        const unreadNotifications = await db.query(
            `SELECT COUNT(*) as count FROM notification_logs WHERE user_id = ? AND is_read = 0`,
            [userId]
        );

        const avgGrade = grades.length > 0 
            ? (grades.reduce((sum, g) => sum + (g.total_score || 0), 0) / grades.length).toFixed(2)
            : 0;

        return {
            profile,
            attendance,
            grades: {
                count: grades.length,
                averageScore: avgGrade,
                recent: grades.slice(0, 5)
            },
            classes: {
                count: classes.length,
                list: classes
            },
            notifications: {
                unread: unreadNotifications[0][0].count
            }
        };
    } catch (error) {
        console.error('Error getting student dashboard:', error);
        throw error;
    }
};
