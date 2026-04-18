const db = require('../config/db');

// ========== HỒ SƠ SINH VIÊN ==========
exports.getStudentProfile = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT id, username, full_name, email, avatar, role, created_at,
                    birth_date, phone_number, address
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
                csg.id as class_subject_group_id,
                cs.id as class_subject_id,
                s.subject_name,
                s.subject_code,
                s.credits,
                c.class_name,
                cs.study_session,
                csg.day_of_week,
                csg.start_period,
                csg.end_period,
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
                csg.id as class_subject_group_id,
                s.subject_name,
                csg.group_code,
                CASE 
                    WHEN ar.status = 1 THEN 'Có mặt'
                    WHEN ar.status = 2 THEN 'Vắng có phép'
                    WHEN ar.status = 3 THEN 'Vắng không phép'
                END as status_text,
                ar.status,
                ae.drive_link,
                ae.drive_file_id,
                ae.status as evidence_status
            FROM attendance_records ar
            JOIN attendance_sessions asess ON ar.session_id = asess.id
            JOIN class_subject_groups csg ON asess.class_subject_group_id = csg.id
            JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
            JOIN class_subjects cs ON csg.class_subject_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            LEFT JOIN (
                SELECT e1.*
                FROM attendance_evidences e1
                INNER JOIN (
                    SELECT attendance_record_id, MAX(id) AS latest_id
                    FROM attendance_evidences
                    GROUP BY attendance_record_id
                ) e2 ON e1.id = e2.latest_id
            ) ae ON ar.id = ae.attendance_record_id
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
                asub.id as submission_id,
                asub.score,
                asub.feedback,
                asub.submitted_at,
                asub.drive_link,
                asub.drive_file_id,
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
        const profile = await this.getStudentProfile(userId);
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

// ========== DANH SÁCH PHẢN HỒI CỦA SINH VIÊN ==========
exports.getFeedbacks = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT id, title, content, status, reply_content, updated_at
             FROM feedbacks
             WHERE student_id = ?
             ORDER BY updated_at DESC, id DESC`,
            [userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting feedbacks:', error);
        throw error;
    }
};

// ========== TẠO PHẢN HỒI MỚI ==========
exports.createFeedback = async (userId, title, content) => {
    try {
        const [result] = await db.query(
            `INSERT INTO feedbacks (student_id, title, content, status)
             VALUES (?, ?, ?, 'Pending')`,
            [userId, title, content]
        );

        const [rows] = await db.query(
            `SELECT id, title, content, status, reply_content, updated_at
             FROM feedbacks
             WHERE id = ? AND student_id = ?`,
            [result.insertId, userId]
        );

        return rows[0] || null;
    } catch (error) {
        console.error('Error creating feedback:', error);
        throw error;
    }
};

// ========== CẬP NHẬT PHẢN HỒI (CHỈ KHI PENDING) ==========
exports.updateFeedback = async (userId, feedbackId, title, content) => {
    try {
        const [result] = await db.query(
            `UPDATE feedbacks
             SET title = ?, content = ?
             WHERE id = ? AND student_id = ? AND status = 'Pending'`,
            [title, content, feedbackId, userId]
        );

        if (!result.affectedRows) {
            return null;
        }

        const [rows] = await db.query(
            `SELECT id, title, content, status, reply_content, updated_at
             FROM feedbacks
             WHERE id = ? AND student_id = ?`,
            [feedbackId, userId]
        );

        return rows[0] || null;
    } catch (error) {
        console.error('Error updating feedback:', error);
        throw error;
    }
};

// ========== XÓA PHẢN HỒI (CHỈ KHI PENDING) ==========
exports.deleteFeedback = async (userId, feedbackId) => {
    try {
        const [result] = await db.query(
            `DELETE FROM feedbacks
             WHERE id = ? AND student_id = ? AND status = 'Pending'`,
            [feedbackId, userId]
        );
        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error deleting feedback:', error);
        throw error;
    }
};

// ========== THÔNG TIN USER ĐANG ĐĂNG NHẬP (DÙNG CHUNG) ==========
exports.getCurrentUser = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT id, username, full_name, email, role, avatar
             FROM users
             WHERE id = ?`,
            [userId]
        );
        return rows[0] || null;
    } catch (error) {
        console.error('Error getting current user:', error);
        throw error;
    }
};

// ========== SỐ THÔNG BÁO CHƯA ĐỌC (DÙNG CHUNG) ==========
exports.getUnreadNotificationCount = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT COUNT(*) AS unreadCount
             FROM notification_logs
             WHERE user_id = ? AND is_read = 0`,
            [userId]
        );
        return Number(rows[0]?.unreadCount || 0);
    } catch (error) {
        console.error('Error getting unread notification count:', error);
        throw error;
    }
};

// ========== CẬP NHẬT HỒ SƠ SINH VIÊN ==========
exports.updateStudentProfile = async (userId, profileData) => {
    try {
        const birthDate = profileData.birthDate || null;
        const phoneNumber = profileData.phoneNumber || null;
        const address = profileData.address || null;

        await db.query(
            `UPDATE users
             SET birth_date = ?, phone_number = ?, address = ?
             WHERE id = ? AND role = 'student'`,
            [birthDate, phoneNumber, address, userId]
        );

        return this.getStudentProfile(userId);
    } catch (error) {
        console.error('Error updating student profile:', error);
        throw error;
    }
};

// ========== THÔNG TIN MẬT KHẨU NGƯỜI DÙNG ==========
exports.getUserPasswordInfo = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT id, password, google_id
             FROM users
             WHERE id = ?`,
            [userId]
        );
        return rows[0] || null;
    } catch (error) {
        console.error('Error getting password info:', error);
        throw error;
    }
};

// ========== CẬP NHẬT HASH MẬT KHẨU ==========
exports.updateUserPasswordHash = async (userId, passwordHash) => {
    try {
        const [result] = await db.query(
            `UPDATE users
             SET password = ?
             WHERE id = ?`,
            [passwordHash, userId]
        );
        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error updating password hash:', error);
        throw error;
    }
};

// ========== NỘP BÀI TẬP ==========
exports.submitAssignment = async (userId, assignmentId, driveLink, driveFileId = null) => {
    try {
        const [allowed] = await db.query(
            `SELECT a.id
             FROM assignments a
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON cs.id = csg.class_subject_id
             JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
             WHERE a.id = ? AND ssr.student_id = ?
             LIMIT 1`,
            [assignmentId, userId]
        );

        if (!allowed.length) {
            return null;
        }

        const [existing] = await db.query(
            `SELECT id
             FROM assignment_submissions
             WHERE assignment_id = ? AND student_id = ?
             ORDER BY id DESC
             LIMIT 1`,
            [assignmentId, userId]
        );

        if (existing.length) {
            await db.query(
                `UPDATE assignment_submissions
                 SET drive_link = ?, drive_file_id = ?, submitted_at = CURRENT_TIMESTAMP
                 WHERE id = ?`,
                [driveLink, driveFileId, existing[0].id]
            );
        } else {
            await db.query(
                `INSERT INTO assignment_submissions (assignment_id, student_id, drive_link, drive_file_id)
                 VALUES (?, ?, ?, ?)`,
                [assignmentId, userId, driveLink, driveFileId]
            );
        }

        return true;
    } catch (error) {
        console.error('Error submitting assignment:', error);
        throw error;
    }
};

// ========== HỦY NỘP BÀI ==========
exports.unsubmitAssignment = async (userId, assignmentId) => {
    try {
        const [result] = await db.query(
            `DELETE s
             FROM assignment_submissions s
             JOIN assignments a ON s.assignment_id = a.id
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON cs.id = csg.class_subject_id
             JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
             WHERE s.assignment_id = ? AND s.student_id = ? AND ssr.student_id = ?`,
            [assignmentId, userId, userId]
        );

        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error unsubmit assignment:', error);
        throw error;
    }
};

// ========== NỘP MINH CHỨNG ĐIỂM DANH ==========
exports.submitAttendanceEvidence = async (userId, attendanceRecordId, driveLink, driveFileId = null) => {
    try {
        const [records] = await db.query(
            `SELECT ar.id, ar.status
             FROM attendance_records ar
             JOIN attendance_sessions s ON ar.session_id = s.id
             JOIN class_subject_groups csg ON s.class_subject_group_id = csg.id
             JOIN student_subject_registration ssr ON csg.id = ssr.class_subject_group_id
             WHERE ar.id = ? AND ar.student_id = ? AND ssr.student_id = ?
             LIMIT 1`,
            [attendanceRecordId, userId, userId]
        );

        if (!records.length) {
            return null;
        }

        if (Number(records[0].status) === 1) {
            return false;
        }

        const [existing] = await db.query(
            `SELECT id
             FROM attendance_evidences
             WHERE attendance_record_id = ?
             ORDER BY id DESC
             LIMIT 1`,
            [attendanceRecordId]
        );

        if (existing.length) {
            await db.query(
                `UPDATE attendance_evidences
                 SET drive_link = ?, drive_file_id = ?, status = 'Pending', uploaded_at = CURRENT_TIMESTAMP
                 WHERE id = ?`,
                [driveLink, driveFileId, existing[0].id]
            );
        } else {
            await db.query(
                `INSERT INTO attendance_evidences (attendance_record_id, drive_link, drive_file_id, status)
                 VALUES (?, ?, ?, 'Pending')`,
                [attendanceRecordId, driveLink, driveFileId]
            );
        }

        await db.query(
            `UPDATE attendance_records
             SET evidence_file = ?
             WHERE id = ?`,
            [driveLink, attendanceRecordId]
        );

        return true;
    } catch (error) {
        console.error('Error submitting attendance evidence:', error);
        throw error;
    }
};

// ========== CẬP NHẬT THÔNG TIN USER ĐANG ĐĂNG NHẬP (DÙNG CHUNG) ==========
exports.updateCurrentUserProfile = async (userId, profileData) => {
    try {
        const fullName = (profileData.fullName || '').trim();
        const email = (profileData.email || '').trim();
        const phoneNumber = (profileData.phoneNumber || '').trim() || null;
        const address = (profileData.address || '').trim() || null;
        const birthDate = profileData.birthDate || null;
        const avatar = (profileData.avatar || '').trim() || null;

        if (!fullName || !email) {
            throw new Error('fullName và email là bắt buộc');
        }

        await db.query(
            `UPDATE users
             SET full_name = ?,
                 email = ?,
                 phone_number = ?,
                 address = ?,
                 birth_date = ?,
                 avatar = COALESCE(?, avatar)
             WHERE id = ?`,
            [fullName, email, phoneNumber, address, birthDate, avatar, userId]
        );

        const [rows] = await db.query(
            `SELECT id, username, full_name, email, role, avatar, birth_date, phone_number, address, created_at
             FROM users
             WHERE id = ?`,
            [userId]
        );

        return rows[0] || null;
    } catch (error) {
        console.error('Error updating current user profile:', error);
        throw error;
    }
};

// ========== DASHBOARD ADMIN ==========
exports.getAdminDashboard = async () => {
    try {
        const [[studentRow]] = await db.query(`SELECT COUNT(*) AS totalStudents FROM users WHERE role = 'student'`);
        const [[teacherRow]] = await db.query(`SELECT COUNT(*) AS totalTeachers FROM users WHERE role = 'teacher'`);
        const [[classRow]] = await db.query(`SELECT COUNT(*) AS totalClasses FROM classes`);
        const [[openClassRow]] = await db.query(
            `SELECT COUNT(*) AS totalOpenClassSubjects
             FROM class_subjects
             WHERE CURDATE() BETWEEN COALESCE(start_date, '1900-01-01') AND COALESCE(end_date, '2999-12-31')`
        );

        const [recentRows] = await db.query(
            `SELECT
                c.class_name,
                s.subject_name,
                u.full_name AS teacher_name,
                cs.start_date,
                cs.end_date
             FROM class_subjects cs
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.teacher_id = u.id
             ORDER BY cs.id DESC
             LIMIT 10`
        );

        return {
            stats: {
                totalStudents: Number(studentRow?.totalStudents || 0),
                totalTeachers: Number(teacherRow?.totalTeachers || 0),
                totalClasses: Number(classRow?.totalClasses || 0),
                totalOpenClassSubjects: Number(openClassRow?.totalOpenClassSubjects || 0)
            },
            classSubjects: recentRows
        };
    } catch (error) {
        console.error('Error getting admin dashboard:', error);
        throw error;
    }
};

// ========== DANH SÁCH TÀI KHOẢN ADMIN ==========
exports.getAdminAccounts = async (filters = {}) => {
    try {
        const keyword = (filters.keyword || '').trim();
        const role = (filters.role || '').trim();

        const params = [];
        let whereSql = 'WHERE 1=1';

        if (keyword) {
            whereSql += ' AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`);
        }

        if (role && role !== 'all') {
            whereSql += ' AND u.role = ?';
            params.push(role);
        }

        const [rows] = await db.query(
            `SELECT
                u.id,
                u.username,
                u.full_name,
                u.email,
                u.role,
                u.avatar,
                u.created_at,
                c.class_name
             FROM users u
             LEFT JOIN class_students cls ON cls.student_id = u.id
             LEFT JOIN classes c ON c.id = cls.class_id
             ${whereSql}
             ORDER BY u.id DESC
             LIMIT 300`,
            params
        );

        return rows;
    } catch (error) {
        console.error('Error getting admin accounts:', error);
        throw error;
    }
};

exports.createAdminAccount = async (payload) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const role = payload.role;
        const username = payload.username;
        const fullName = payload.fullName;
        const email = payload.email;
        const passwordHash = payload.passwordHash;
        const className = payload.className || null;

        const [result] = await conn.query(
            `INSERT INTO users (username, password, full_name, email, role)
             VALUES (?, ?, ?, ?, ?)`,
            [username, passwordHash, fullName, email, role]
        );

        const userId = result.insertId;

        if ((role === 'student' || role === 'bcs') && className) {
            const [classRows] = await conn.query(
                `SELECT id FROM classes WHERE class_name = ? LIMIT 1`,
                [className]
            );

            if (classRows.length) {
                await conn.query(
                    `INSERT INTO class_students (class_id, student_id) VALUES (?, ?)`,
                    [classRows[0].id, userId]
                );
            }
        }

        await conn.commit();
        return userId;
    } catch (error) {
        await conn.rollback();
        console.error('Error creating admin account:', error);
        throw error;
    } finally {
        conn.release();
    }
};

exports.updateAdminAccount = async (userId, payload) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const role = payload.role;
        const username = payload.username;
        const fullName = payload.fullName;
        const email = payload.email;
        const className = payload.className || null;

        await conn.query(
            `UPDATE users
             SET username = ?, full_name = ?, email = ?, role = ?
             WHERE id = ?`,
            [username, fullName, email, role, userId]
        );

        await conn.query(`DELETE FROM class_students WHERE student_id = ?`, [userId]);

        if ((role === 'student' || role === 'bcs') && className) {
            const [classRows] = await conn.query(
                `SELECT id FROM classes WHERE class_name = ? LIMIT 1`,
                [className]
            );

            if (classRows.length) {
                await conn.query(
                    `INSERT INTO class_students (class_id, student_id) VALUES (?, ?)`,
                    [classRows[0].id, userId]
                );
            }
        }

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error updating admin account:', error);
        throw error;
    } finally {
        conn.release();
    }
};

// ========== DASHBOARD GIẢNG VIÊN ==========
exports.getTeacherDashboard = async (userId) => {
    try {
        const [summaryRows] = await db.query(
            `SELECT
                COUNT(DISTINCT csg.id) AS class_count,
                COUNT(DISTINCT a.id) AS assignment_count,
                SUM(CASE WHEN asub.id IS NOT NULL AND asub.score IS NULL THEN 1 ELSE 0 END) AS pending_grading,
                SUM(CASE WHEN ae.status = 'Pending' THEN 1 ELSE 0 END) AS pending_evidence
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             LEFT JOIN assignments a ON a.class_subject_id = cs.id
             LEFT JOIN assignment_submissions asub ON asub.assignment_id = a.id
             LEFT JOIN attendance_sessions ass ON ass.class_subject_group_id = csg.id
             LEFT JOIN attendance_records ar ON ar.session_id = ass.id
             LEFT JOIN attendance_evidences ae ON ae.attendance_record_id = ar.id
             WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)`,
            [userId, userId]
        );

        const [weeklyRows] = await db.query(
            `SELECT COUNT(*) AS weekly_sessions
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
               AND csg.day_of_week BETWEEN 2 AND 7`,
            [userId, userId]
        );

        const [classRows] = await db.query(
            `SELECT
                c.class_name,
                s.subject_name,
                csg.day_of_week,
                csg.start_period,
                csg.end_period,
                COUNT(DISTINCT ssr.student_id) AS student_count
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             GROUP BY csg.id, c.class_name, s.subject_name, csg.day_of_week, csg.start_period, csg.end_period
             ORDER BY c.class_name ASC, s.subject_name ASC
             LIMIT 12`,
            [userId, userId]
        );

        const summary = summaryRows[0] || {};
        return {
            stats: {
                classCount: Number(summary.class_count || 0),
                weeklySessions: Number(weeklyRows[0]?.weekly_sessions || 0),
                pendingGrading: Number(summary.pending_grading || 0),
                pendingEvidence: Number(summary.pending_evidence || 0)
            },
            classes: classRows
        };
    } catch (error) {
        console.error('Error getting teacher dashboard:', error);
        throw error;
    }
};

// ========== DASHBOARD BAN CÁN SỰ ==========
exports.getBcsDashboard = async (userId) => {
    try {
        const [classRows] = await db.query(
            `SELECT c.id, c.class_name
             FROM class_students cls
             JOIN classes c ON c.id = cls.class_id
             WHERE cls.student_id = ?
             ORDER BY cls.id ASC
             LIMIT 1`,
            [userId]
        );

        const primaryClass = classRows[0] || null;
        if (!primaryClass) {
            return {
                classInfo: null,
                stats: {
                    totalStudents: 0,
                    absentToday: 0,
                    pendingEvidence: 0,
                    newFeedback: 0
                },
                todaySchedule: [],
                announcements: []
            };
        }

        const classId = primaryClass.id;

        const [totalStudentRows] = await db.query(
            `SELECT COUNT(*) AS total_students
             FROM class_students
             WHERE class_id = ?`,
            [classId]
        );

        const [absentTodayRows] = await db.query(
            `SELECT COUNT(*) AS absent_today
             FROM attendance_records ar
             JOIN attendance_sessions ass ON ar.session_id = ass.id
             JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE cs.class_id = ?
               AND ass.attendance_date = CURDATE()
               AND ar.status IN (2, 3)`,
            [classId]
        );

        const [pendingEvidenceRows] = await db.query(
            `SELECT COUNT(*) AS pending_evidence
             FROM attendance_evidences ae
             JOIN attendance_records ar ON ae.attendance_record_id = ar.id
             JOIN attendance_sessions ass ON ar.session_id = ass.id
             JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE cs.class_id = ?
               AND ae.status = 'Pending'`,
            [classId]
        );

        const [feedbackRows] = await db.query(
            `SELECT COUNT(*) AS new_feedback
             FROM feedbacks f
             JOIN class_students cls ON f.student_id = cls.student_id
             WHERE cls.class_id = ?
               AND f.status = 'Pending'`,
            [classId]
        );

        const [todayScheduleRows] = await db.query(
            `SELECT
                csg.start_period,
                csg.end_period,
                s.subject_name,
                csg.room,
                u.full_name AS teacher_name
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.teacher_id = u.id
             WHERE cs.class_id = ?
               AND csg.day_of_week = WEEKDAY(CURDATE()) + 2
             ORDER BY csg.start_period ASC`,
            [classId]
        );

        const [announcementRows] = await db.query(
            `SELECT
                id,
                title,
                message,
                is_read,
                created_at
             FROM notification_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 5`,
            [userId]
        );

        return {
            classInfo: {
                id: classId,
                className: primaryClass.class_name
            },
            stats: {
                totalStudents: Number(totalStudentRows[0]?.total_students || 0),
                absentToday: Number(absentTodayRows[0]?.absent_today || 0),
                pendingEvidence: Number(pendingEvidenceRows[0]?.pending_evidence || 0),
                newFeedback: Number(feedbackRows[0]?.new_feedback || 0)
            },
            todaySchedule: todayScheduleRows,
            announcements: announcementRows
        };
    } catch (error) {
        console.error('Error getting BCS dashboard:', error);
        throw error;
    }
};

// ========== TEACHER - DANH SÁCH LỚP/NHÓM PHỤ TRÁCH ==========
exports.getTeacherGroups = async (userId) => {
    try {
        const [rows] = await db.query(
            `SELECT
                csg.id AS group_id,
                cs.id AS class_subject_id,
                c.class_name,
                s.subject_name,
                s.subject_code,
                csg.group_code,
                csg.day_of_week,
                csg.start_period,
                csg.end_period,
                csg.room,
                cs.study_session,
                cs.start_date,
                cs.end_date,
                COUNT(DISTINCT ssr.student_id) AS student_count
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             WHERE cs.teacher_id = ? OR csg.sub_teacher_id = ?
             GROUP BY csg.id, cs.id, c.class_name, s.subject_name, s.subject_code, csg.group_code,
                      csg.day_of_week, csg.start_period, csg.end_period, csg.room, cs.study_session,
                      cs.start_date, cs.end_date
             ORDER BY c.class_name ASC, s.subject_name ASC, csg.group_code ASC`,
            [userId, userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting teacher groups:', error);
        throw error;
    }
};

// ========== TEACHER - DANH SÁCH ĐIỂM DANH THEO NHÓM/NGÀY ==========
exports.getTeacherAttendanceRoster = async (userId, groupId, attendanceDate) => {
    try {
        const [allowedRows] = await db.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [groupId, userId, userId]
        );

        if (!allowedRows.length) {
            return null;
        }

        const [students] = await db.query(
            `SELECT
                u.id AS student_id,
                u.username,
                u.full_name,
                u.birth_date,
                c.class_name
             FROM student_subject_registration ssr
             JOIN users u ON ssr.student_id = u.id
             JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             WHERE ssr.class_subject_group_id = ?
             ORDER BY u.full_name ASC`,
            [groupId]
        );

        const [sessionRows] = await db.query(
            `SELECT id
             FROM attendance_sessions
             WHERE class_subject_group_id = ? AND attendance_date = ?
             ORDER BY id DESC
             LIMIT 1`,
            [groupId, attendanceDate]
        );

        const sessionId = sessionRows[0]?.id || null;
        let existingMap = new Map();

        if (sessionId) {
            const [records] = await db.query(
                `SELECT student_id, status
                 FROM attendance_records
                 WHERE session_id = ?`,
                [sessionId]
            );
            existingMap = new Map(records.map((r) => [Number(r.student_id), Number(r.status)]));
        }

        return {
            sessionId,
            students: students.map((s) => ({
                ...s,
                status: existingMap.get(Number(s.student_id)) || 1
            }))
        };
    } catch (error) {
        console.error('Error getting teacher attendance roster:', error);
        throw error;
    }
};

// ========== TEACHER - LƯU ĐIỂM DANH ==========
exports.saveTeacherAttendance = async (userId, payload) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const groupId = Number(payload.groupId);
        const attendanceDate = payload.attendanceDate;
        const records = Array.isArray(payload.records) ? payload.records : [];

        const [allowedRows] = await conn.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [groupId, userId, userId]
        );

        if (!allowedRows.length) {
            await conn.rollback();
            return false;
        }

        const [sessionRows] = await conn.query(
            `SELECT id
             FROM attendance_sessions
             WHERE class_subject_group_id = ? AND attendance_date = ?
             ORDER BY id DESC
             LIMIT 1`,
            [groupId, attendanceDate]
        );

        let sessionId = sessionRows[0]?.id || null;
        if (!sessionId) {
            const [insertSession] = await conn.query(
                `INSERT INTO attendance_sessions (class_subject_group_id, attendance_date, created_by)
                 VALUES (?, ?, ?)`,
                [groupId, attendanceDate, userId]
            );
            sessionId = insertSession.insertId;
        }

        for (const row of records) {
            const studentId = Number(row.studentId);
            const status = Number(row.status);
            if (!studentId || ![1, 2, 3].includes(status)) {
                continue;
            }

            const [existingRows] = await conn.query(
                `SELECT id
                 FROM attendance_records
                 WHERE session_id = ? AND student_id = ?
                 LIMIT 1`,
                [sessionId, studentId]
            );

            if (existingRows.length) {
                await conn.query(
                    `UPDATE attendance_records
                     SET status = ?
                     WHERE id = ?`,
                    [status, existingRows[0].id]
                );
            } else {
                await conn.query(
                    `INSERT INTO attendance_records (session_id, student_id, status)
                     VALUES (?, ?, ?)`,
                    [sessionId, studentId, status]
                );
            }
        }

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error saving teacher attendance:', error);
        throw error;
    } finally {
        conn.release();
    }
};

// ========== TEACHER - MINH CHỨNG ==========
exports.getTeacherEvidences = async (userId, filters = {}) => {
    try {
        const status = String(filters.status || 'all');
        const keyword = String(filters.keyword || '').trim();
        const groupId = Number(filters.groupId || 0);

        let whereSql = `WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)`;
        const params = [userId, userId];

        if (status !== 'all') {
            whereSql += ' AND ae.status = ?';
            params.push(status === 'pending' ? 'Pending' : (status === 'approved' ? 'Approved' : 'Rejected'));
        }

        if (groupId) {
            whereSql += ' AND csg.id = ?';
            params.push(groupId);
        }

        if (keyword) {
            whereSql += ' AND (u.username LIKE ? OR u.full_name LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`);
        }

        const [rows] = await db.query(
            `SELECT
                ae.id AS evidence_id,
                ae.status AS evidence_status,
                ae.drive_link,
                ae.uploaded_at,
                ar.id AS attendance_record_id,
                ar.status AS attendance_status,
                ass.attendance_date,
                u.id AS student_id,
                u.username,
                u.full_name,
                c.class_name,
                s.subject_name,
                csg.id AS group_id,
                csg.group_code
             FROM attendance_evidences ae
             JOIN attendance_records ar ON ae.attendance_record_id = ar.id
             JOIN attendance_sessions ass ON ar.session_id = ass.id
             JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             JOIN users u ON ar.student_id = u.id
             ${whereSql}
             ORDER BY ae.uploaded_at DESC, ae.id DESC
             LIMIT 200`,
            params
        );

        return rows;
    } catch (error) {
        console.error('Error getting teacher evidences:', error);
        throw error;
    }
};

exports.reviewTeacherEvidence = async (userId, evidenceId, action) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const [rows] = await conn.query(
            `SELECT
                ae.id,
                ar.id AS attendance_record_id,
                csg.id AS group_id,
                cs.teacher_id,
                csg.sub_teacher_id
             FROM attendance_evidences ae
             JOIN attendance_records ar ON ae.attendance_record_id = ar.id
             JOIN attendance_sessions ass ON ar.session_id = ass.id
             JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE ae.id = ?
             LIMIT 1`,
            [evidenceId]
        );

        if (!rows.length) {
            await conn.rollback();
            return false;
        }

        const owner = rows[0];
        if (Number(owner.teacher_id) !== Number(userId) && Number(owner.sub_teacher_id) !== Number(userId)) {
            await conn.rollback();
            return false;
        }

        const evidenceStatus = action === 'approve' ? 'Approved' : 'Rejected';
        const attendanceStatus = action === 'approve' ? 2 : 3;

        await conn.query(
            `UPDATE attendance_evidences
             SET status = ?
             WHERE id = ?`,
            [evidenceStatus, evidenceId]
        );

        await conn.query(
            `UPDATE attendance_records
             SET status = ?
             WHERE id = ?`,
            [attendanceStatus, owner.attendance_record_id]
        );

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error reviewing teacher evidence:', error);
        throw error;
    } finally {
        conn.release();
    }
};

// ========== TEACHER - BÀI TẬP ==========
exports.getTeacherAssignments = async (userId, groupId = 0) => {
    try {
        const params = [userId, userId];
        let whereSql = `WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)`;

        if (groupId) {
            whereSql += ' AND csg.id = ?';
            params.push(Number(groupId));
        }

        const [rows] = await db.query(
            `SELECT
                a.id,
                a.title,
                a.description,
                a.deadline,
                a.created_at,
                c.class_name,
                s.subject_name,
                csg.id AS group_id,
                csg.group_code,
                COUNT(DISTINCT ssr.student_id) AS total_students,
                COUNT(DISTINCT asub.id) AS submitted_count
             FROM assignments a
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             LEFT JOIN assignment_submissions asub ON asub.assignment_id = a.id AND asub.student_id = ssr.student_id
             ${whereSql}
             GROUP BY a.id, a.title, a.description, a.deadline, a.created_at,
                      c.class_name, s.subject_name, csg.id, csg.group_code
             ORDER BY a.deadline DESC, a.id DESC
             LIMIT 300`,
            params
        );
        return rows;
    } catch (error) {
        console.error('Error getting teacher assignments:', error);
        throw error;
    }
};

exports.createTeacherAssignment = async (userId, payload) => {
    try {
        const groupId = Number(payload.groupId);
        const title = String(payload.title || '').trim();
        const description = String(payload.description || '').trim() || null;
        const deadline = payload.deadline || null;

        const [groupRows] = await db.query(
            `SELECT csg.class_subject_id, cs.teacher_id, csg.sub_teacher_id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ?
             LIMIT 1`,
            [groupId]
        );

        if (!groupRows.length) {
            return null;
        }

        const grp = groupRows[0];
        if (Number(grp.teacher_id) !== Number(userId) && Number(grp.sub_teacher_id) !== Number(userId)) {
            return null;
        }

        const [result] = await db.query(
            `INSERT INTO assignments (class_subject_id, title, description, deadline, created_by)
             VALUES (?, ?, ?, ?, ?)`,
            [grp.class_subject_id, title, description, deadline, userId]
        );

        return result.insertId;
    } catch (error) {
        console.error('Error creating teacher assignment:', error);
        throw error;
    }
};

exports.updateTeacherAssignment = async (userId, assignmentId, payload) => {
    try {
        const [allowedRows] = await db.query(
            `SELECT a.id
             FROM assignments a
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             WHERE a.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [assignmentId, userId, userId]
        );

        if (!allowedRows.length) {
            return false;
        }

        await db.query(
            `UPDATE assignments
             SET title = ?, description = ?, deadline = ?
             WHERE id = ?`,
            [String(payload.title || '').trim(), String(payload.description || '').trim() || null, payload.deadline || null, assignmentId]
        );

        return true;
    } catch (error) {
        console.error('Error updating teacher assignment:', error);
        throw error;
    }
};

exports.deleteTeacherAssignment = async (userId, assignmentId) => {
    try {
        const [result] = await db.query(
            `DELETE a
             FROM assignments a
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             WHERE a.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)` ,
            [assignmentId, userId, userId]
        );

        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error deleting teacher assignment:', error);
        throw error;
    }
};

exports.getTeacherAssignmentSubmissions = async (userId, assignmentId) => {
    try {
        const [rows] = await db.query(
            `SELECT
                asub.id AS submission_id,
                asub.assignment_id,
                asub.submitted_at,
                asub.drive_link,
                asub.score,
                asub.feedback,
                u.id AS student_id,
                u.username,
                u.full_name
             FROM assignment_submissions asub
             JOIN assignments a ON asub.assignment_id = a.id
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             JOIN users u ON asub.student_id = u.id
             WHERE asub.assignment_id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             ORDER BY asub.submitted_at DESC`,
            [assignmentId, userId, userId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting teacher assignment submissions:', error);
        throw error;
    }
};

exports.gradeTeacherSubmission = async (userId, submissionId, score, feedback) => {
    try {
        const [allowedRows] = await db.query(
            `SELECT asub.id
             FROM assignment_submissions asub
             JOIN assignments a ON asub.assignment_id = a.id
             JOIN class_subjects cs ON a.class_subject_id = cs.id
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             WHERE asub.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [submissionId, userId, userId]
        );

        if (!allowedRows.length) {
            return false;
        }

        await db.query(
            `UPDATE assignment_submissions
             SET score = ?, feedback = ?
             WHERE id = ?`,
            [score, feedback || null, submissionId]
        );

        return true;
    } catch (error) {
        console.error('Error grading teacher submission:', error);
        throw error;
    }
};

// ========== TEACHER - BẢNG ĐIỂM ==========
exports.getTeacherGradesByGroup = async (userId, groupId) => {
    try {
        const [allowedRows] = await db.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [groupId, userId, userId]
        );

        if (!allowedRows.length) {
            return null;
        }

        const [rows] = await db.query(
            `SELECT
                u.id AS student_id,
                u.username,
                u.full_name,
                g.assignment_score,
                g.midterm_score,
                g.final_score,
                g.total_score,
                g.grade_letter
             FROM student_subject_registration ssr
             JOIN users u ON ssr.student_id = u.id
             LEFT JOIN grades g ON g.student_id = ssr.student_id AND g.class_subject_group_id = ssr.class_subject_group_id
             WHERE ssr.class_subject_group_id = ?
             ORDER BY u.full_name ASC`,
            [groupId]
        );
        return rows;
    } catch (error) {
        console.error('Error getting teacher grades:', error);
        throw error;
    }
};

exports.saveTeacherGrades = async (userId, groupId, rows) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const [allowedRows] = await conn.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
             LIMIT 1`,
            [groupId, userId, userId]
        );

        if (!allowedRows.length) {
            await conn.rollback();
            return false;
        }

        for (const row of rows) {
            const studentId = Number(row.studentId);
            const assignmentScore = row.assignmentScore === null || row.assignmentScore === '' ? null : Number(row.assignmentScore);
            const midtermScore = row.midtermScore === null || row.midtermScore === '' ? null : Number(row.midtermScore);
            const finalScore = row.finalScore === null || row.finalScore === '' ? null : Number(row.finalScore);

            let totalScore = null;
            let gradeLetter = null;
            if ([assignmentScore, midtermScore, finalScore].every((v) => Number.isFinite(v))) {
                totalScore = Number((assignmentScore * 0.2 + midtermScore * 0.3 + finalScore * 0.5).toFixed(1));
                if (totalScore >= 8.5) gradeLetter = 'A';
                else if (totalScore >= 7) gradeLetter = 'B';
                else if (totalScore >= 5.5) gradeLetter = 'C';
                else if (totalScore >= 4) gradeLetter = 'D';
                else gradeLetter = 'F';
            }

            const [existingRows] = await conn.query(
                `SELECT id
                 FROM grades
                 WHERE student_id = ? AND class_subject_group_id = ?
                 LIMIT 1`,
                [studentId, groupId]
            );

            if (existingRows.length) {
                await conn.query(
                    `UPDATE grades
                     SET assignment_score = ?, midterm_score = ?, final_score = ?, total_score = ?, grade_letter = ?
                     WHERE id = ?`,
                    [assignmentScore, midtermScore, finalScore, totalScore, gradeLetter, existingRows[0].id]
                );
            } else {
                await conn.query(
                    `INSERT INTO grades (student_id, class_subject_group_id, assignment_score, midterm_score, final_score, total_score, grade_letter)
                     VALUES (?, ?, ?, ?, ?, ?, ?)`,
                    [studentId, groupId, assignmentScore, midtermScore, finalScore, totalScore, gradeLetter]
                );
            }
        }

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error saving teacher grades:', error);
        throw error;
    } finally {
        conn.release();
    }
};

exports.addTeacherStudentToGroup = async (userId, groupId, username, className) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const [groupRows] = await conn.query(
            `SELECT csg.id AS group_id, c.id AS class_id, c.class_name, cs.teacher_id, csg.sub_teacher_id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             WHERE csg.id = ?
             LIMIT 1`,
            [groupId]
        );

        if (!groupRows.length) {
            await conn.rollback();
            return { error: 'Không tìm thấy nhóm học phần' };
        }

        const group = groupRows[0];
        if (Number(group.teacher_id) !== Number(userId) && Number(group.sub_teacher_id) !== Number(userId)) {
            await conn.rollback();
            return false;
        }

        if (className && String(group.class_name).toLowerCase() !== String(className).toLowerCase()) {
            await conn.rollback();
            return { error: `Sinh viên này không thuộc lớp ${group.class_name}` };
        }

        const [studentRows] = await conn.query(
            `SELECT id, username, full_name
             FROM users
             WHERE username = ? AND role = 'student'
             LIMIT 1`,
            [username]
        );

        if (!studentRows.length) {
            await conn.rollback();
            return { error: 'Không tìm thấy sinh viên theo MSSV đã nhập' };
        }

        const student = studentRows[0];

        const [classStudentRows] = await conn.query(
            `SELECT id
             FROM class_students
             WHERE class_id = ? AND student_id = ?
             LIMIT 1`,
            [group.class_id, student.id]
        );

        if (!classStudentRows.length) {
            await conn.rollback();
            return { error: `Sinh viên ${username} chưa thuộc lớp ${group.class_name}` };
        }

        const [existingRows] = await conn.query(
            `SELECT id
             FROM student_subject_registration
             WHERE student_id = ? AND class_subject_group_id = ?
             LIMIT 1`,
            [student.id, groupId]
        );

        if (!existingRows.length) {
            await conn.query(
                `INSERT INTO student_subject_registration (student_id, class_subject_group_id, status)
                 VALUES (?, ?, 'Đang học')`,
                [student.id, groupId]
            );
        }

        await conn.commit();

        return {
            student: {
                student_id: student.id,
                username: student.username,
                full_name: student.full_name,
                class_name: group.class_name,
                status: 1
            }
        };
    } catch (error) {
        await conn.rollback();
        console.error('Error adding student to teacher group:', error);
        throw error;
    } finally {
        conn.release();
    }
};

async function getPrimaryClassByUser(userId) {
    const [rows] = await db.query(
        `SELECT c.id, c.class_name
         FROM class_students cls
         JOIN classes c ON c.id = cls.class_id
         WHERE cls.student_id = ?
         ORDER BY cls.id ASC
         LIMIT 1`,
        [userId]
    );
    return rows[0] || null;
}

exports.getBcsGroups = async (userId) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return [];

        const [rows] = await db.query(
            `SELECT
                csg.id AS group_id,
                cs.id AS class_subject_id,
                c.class_name,
                s.subject_name,
                s.subject_code,
                csg.group_code,
                csg.day_of_week,
                csg.start_period,
                csg.end_period,
                csg.room,
                cs.study_session,
                cs.start_date,
                cs.end_date,
                u.full_name AS teacher_name,
                COUNT(DISTINCT ssr.student_id) AS student_count
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON cs.teacher_id = u.id
             LEFT JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             WHERE cs.class_id = ?
             GROUP BY csg.id, cs.id, c.class_name, s.subject_name, s.subject_code, csg.group_code,
                      csg.day_of_week, csg.start_period, csg.end_period, csg.room, cs.study_session,
                      cs.start_date, cs.end_date, u.full_name
             ORDER BY s.subject_name ASC, csg.group_code ASC`,
            [cls.id]
        );
        return rows;
    } catch (error) {
        console.error('Error getting BCS groups:', error);
        throw error;
    }
};

exports.getBcsAttendanceRoster = async (userId, groupId, attendanceDate) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return null;

        const [allowedRows] = await db.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND cs.class_id = ?
             LIMIT 1`,
            [groupId, cls.id]
        );

        if (!allowedRows.length) {
            return null;
        }

        const [students] = await db.query(
            `SELECT
                u.id AS student_id,
                u.username,
                u.full_name,
                u.birth_date,
                c.class_name
             FROM student_subject_registration ssr
             JOIN users u ON ssr.student_id = u.id
             JOIN class_subject_groups csg ON ssr.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             WHERE ssr.class_subject_group_id = ?
             ORDER BY u.full_name ASC`,
            [groupId]
        );

        const [sessionRows] = await db.query(
            `SELECT id
             FROM attendance_sessions
             WHERE class_subject_group_id = ? AND attendance_date = ?
             ORDER BY id DESC
             LIMIT 1`,
            [groupId, attendanceDate]
        );

        const sessionId = sessionRows[0]?.id || null;
        let existingMap = new Map();

        if (sessionId) {
            const [records] = await db.query(
                `SELECT student_id, status
                 FROM attendance_records
                 WHERE session_id = ?`,
                [sessionId]
            );
            existingMap = new Map(records.map((r) => [Number(r.student_id), Number(r.status)]));
        }

        return {
            sessionId,
            students: students.map((s) => ({
                ...s,
                status: existingMap.get(Number(s.student_id)) || 1
            }))
        };
    } catch (error) {
        console.error('Error getting BCS attendance roster:', error);
        throw error;
    }
};

exports.saveBcsAttendance = async (userId, payload) => {
    const conn = await db.getConnection();
    try {
        await conn.beginTransaction();

        const cls = await getPrimaryClassByUser(userId);
        if (!cls) {
            await conn.rollback();
            return false;
        }

        const groupId = Number(payload.groupId || 0);
        const attendanceDate = payload.attendanceDate;
        const records = Array.isArray(payload.records) ? payload.records : [];

        const [allowedRows] = await conn.query(
            `SELECT csg.id
             FROM class_subject_groups csg
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             WHERE csg.id = ? AND cs.class_id = ?
             LIMIT 1`,
            [groupId, cls.id]
        );

        if (!allowedRows.length) {
            await conn.rollback();
            return false;
        }

        const [sessionRows] = await conn.query(
            `SELECT id
             FROM attendance_sessions
             WHERE class_subject_group_id = ? AND attendance_date = ?
             ORDER BY id DESC
             LIMIT 1`,
            [groupId, attendanceDate]
        );

        let sessionId = sessionRows[0]?.id || null;
        if (!sessionId) {
            const [insertSession] = await conn.query(
                `INSERT INTO attendance_sessions (class_subject_group_id, attendance_date, created_by)
                 VALUES (?, ?, ?)`,
                [groupId, attendanceDate, userId]
            );
            sessionId = insertSession.insertId;
        }

        for (const row of records) {
            const studentId = Number(row.studentId);
            const status = Number(row.status);
            if (!studentId || ![1, 2, 3].includes(status)) {
                continue;
            }

            const [existingRows] = await conn.query(
                `SELECT id
                 FROM attendance_records
                 WHERE session_id = ? AND student_id = ?
                 LIMIT 1`,
                [sessionId, studentId]
            );

            if (existingRows.length) {
                await conn.query(
                    `UPDATE attendance_records
                     SET status = ?
                     WHERE id = ?`,
                    [status, existingRows[0].id]
                );
            } else {
                await conn.query(
                    `INSERT INTO attendance_records (session_id, student_id, status)
                     VALUES (?, ?, ?)`,
                    [sessionId, studentId, status]
                );
            }
        }

        await conn.commit();
        return true;
    } catch (error) {
        await conn.rollback();
        console.error('Error saving BCS attendance:', error);
        throw error;
    } finally {
        conn.release();
    }
};

exports.getBcsDocuments = async (userId, filters = {}) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return [];

        const keyword = String(filters.keyword || '').trim();
        const category = String(filters.category || 'all').trim();

        const params = [cls.id];
        let whereSql = 'WHERE cs.class_id = ?';

        if (keyword) {
            whereSql += ' AND (d.title LIKE ? OR d.note LIKE ? OR u.full_name LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`);
        }

        if (category && category !== 'all') {
            whereSql += ' AND d.category = ?';
            params.push(category);
        }

        const [rows] = await db.query(
            `SELECT
                d.id,
                d.title,
                d.note,
                d.category,
                d.drive_link,
                d.created_at,
                d.semester,
                s.subject_name,
                c.class_name,
                u.full_name AS uploader_name
             FROM documents d
             JOIN class_subjects cs ON d.class_subject_id = cs.id
             JOIN classes c ON cs.class_id = c.id
             LEFT JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users u ON d.uploader_id = u.id
             ${whereSql}
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT 300`,
            params
        );

        return rows;
    } catch (error) {
        console.error('Error getting BCS documents:', error);
        throw error;
    }
};

exports.createBcsDocument = async (userId, payload) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return null;

        const title = String(payload.title || '').trim();
        if (!title) return null;

        const [classSubjectRows] = await db.query(
            `SELECT id
             FROM class_subjects
             WHERE class_id = ?
             ORDER BY id DESC
             LIMIT 1`,
            [cls.id]
        );

        if (!classSubjectRows.length) return null;

        const [result] = await db.query(
            `INSERT INTO documents (title, note, category, drive_link, class_subject_id, uploader_id, semester)
             VALUES (?, ?, ?, ?, ?, ?, ?)`,
            [
                title,
                String(payload.note || '').trim() || null,
                String(payload.category || 'Học liệu').trim(),
                String(payload.driveLink || '').trim() || null,
                classSubjectRows[0].id,
                userId,
                String(payload.semester || '').trim() || null
            ]
        );

        return result.insertId;
    } catch (error) {
        console.error('Error creating BCS document:', error);
        throw error;
    }
};

exports.updateBcsDocument = async (userId, docId, payload) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return false;

        const [result] = await db.query(
            `UPDATE documents d
             JOIN class_subjects cs ON d.class_subject_id = cs.id
             SET d.title = ?,
                 d.note = ?,
                 d.category = ?,
                 d.drive_link = ?,
                 d.semester = ?
             WHERE d.id = ? AND cs.class_id = ?`,
            [
                String(payload.title || '').trim(),
                String(payload.note || '').trim() || null,
                String(payload.category || '').trim() || null,
                String(payload.driveLink || '').trim() || null,
                String(payload.semester || '').trim() || null,
                docId,
                cls.id
            ]
        );
        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error updating BCS document:', error);
        throw error;
    }
};

exports.deleteBcsDocument = async (userId, docId) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return false;

        const [result] = await db.query(
            `DELETE d
             FROM documents d
             JOIN class_subjects cs ON d.class_subject_id = cs.id
             WHERE d.id = ? AND cs.class_id = ?`,
            [docId, cls.id]
        );
        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error deleting BCS document:', error);
        throw error;
    }
};

exports.getBcsAnnouncements = async (userId, filters = {}) => {
    return this.getBcsDocuments(userId, {
        keyword: filters.keyword || '',
        category: 'Thông báo'
    });
};

exports.createBcsAnnouncement = async (userId, payload) => {
    return this.createBcsDocument(userId, {
        title: payload.title,
        note: payload.content,
        category: 'Thông báo',
        driveLink: payload.driveLink || '',
        semester: payload.semester || ''
    });
};

exports.updateBcsAnnouncement = async (userId, id, payload) => {
    return this.updateBcsDocument(userId, id, {
        title: payload.title,
        note: payload.content,
        category: 'Thông báo',
        driveLink: payload.driveLink || '',
        semester: payload.semester || ''
    });
};

exports.deleteBcsAnnouncement = async (userId, id) => {
    return this.deleteBcsDocument(userId, id);
};

exports.getBcsFeedbacks = async (userId, filters = {}) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return [];

        const keyword = String(filters.keyword || '').trim();
        const status = String(filters.status || 'all').trim();

        const params = [cls.id];
        let whereSql = 'WHERE cls.class_id = ?';

        if (status !== 'all') {
            whereSql += ' AND f.status = ?';
            params.push(status === 'pending' ? 'Pending' : 'Resolved');
        }

        if (keyword) {
            whereSql += ' AND (u.username LIKE ? OR u.full_name LIKE ? OR f.title LIKE ? OR f.content LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`, `%${keyword}%`);
        }

        const [rows] = await db.query(
            `SELECT
                f.id,
                f.title,
                f.content,
                f.status,
                f.reply_content,
                f.updated_at,
                u.username,
                u.full_name
             FROM feedbacks f
             JOIN users u ON f.student_id = u.id
             JOIN class_students cls ON cls.student_id = u.id
             ${whereSql}
             ORDER BY f.updated_at DESC, f.id DESC
             LIMIT 300`,
            params
        );

        return rows;
    } catch (error) {
        console.error('Error getting BCS feedbacks:', error);
        throw error;
    }
};

exports.resolveBcsFeedback = async (userId, feedbackId, replyContent, status) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return false;

        const normalizedStatus = status === 'Pending' ? 'Pending' : 'Resolved';
        const [result] = await db.query(
            `UPDATE feedbacks f
             JOIN class_students cls ON cls.student_id = f.student_id
             SET f.reply_content = ?,
                 f.status = ?
             WHERE f.id = ? AND cls.class_id = ?`,
            [replyContent || null, normalizedStatus, feedbackId, cls.id]
        );

        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error resolving BCS feedback:', error);
        throw error;
    }
};

exports.deleteBcsFeedback = async (userId, feedbackId) => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) return false;

        const [result] = await db.query(
            `DELETE f
             FROM feedbacks f
             JOIN class_students cls ON cls.student_id = f.student_id
             WHERE f.id = ? AND cls.class_id = ?`,
            [feedbackId, cls.id]
        );
        return result.affectedRows > 0;
    } catch (error) {
        console.error('Error deleting BCS feedback:', error);
        throw error;
    }
};

exports.getBcsDashboardDetail = async (userId, keyword = '') => {
    try {
        const cls = await getPrimaryClassByUser(userId);
        if (!cls) {
            return {
                stats: { totalStudents: 0, warningStudents: 0, warningSubjects: 0 },
                rows: []
            };
        }

        const [statsRows] = await db.query(
            `SELECT
                (SELECT COUNT(*) FROM class_students WHERE class_id = ?) AS total_students,
                COUNT(DISTINCT CASE WHEN ar.status IN (2, 3) THEN ar.student_id END) AS warning_students,
                COUNT(DISTINCT CASE WHEN ar.status IN (2, 3) THEN cs.subject_id END) AS warning_subjects
             FROM class_subjects cs
             JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             LEFT JOIN attendance_sessions ass ON ass.class_subject_group_id = csg.id
             LEFT JOIN attendance_records ar ON ar.session_id = ass.id
             WHERE cs.class_id = ?`,
            [cls.id, cls.id]
        );

        const params = [cls.id];
        let keywordSql = '';
        if (keyword) {
            keywordSql = ' AND (u.username LIKE ? OR u.full_name LIKE ? OR s.subject_name LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`);
        }

        const [rows] = await db.query(
            `SELECT
                u.username,
                u.full_name,
                s.subject_name,
                ass.attendance_date,
                cs.study_session,
                ar.status,
                ae.drive_link,
                ae.status AS evidence_status,
                COUNT(*) OVER (PARTITION BY ar.student_id, cs.subject_id) AS total_absent_in_subject
             FROM attendance_records ar
             JOIN attendance_sessions ass ON ar.session_id = ass.id
             JOIN class_subject_groups csg ON ass.class_subject_group_id = csg.id
             JOIN class_subjects cs ON csg.class_subject_id = cs.id
             JOIN subjects s ON cs.subject_id = s.id
             JOIN users u ON ar.student_id = u.id
             LEFT JOIN (
                SELECT e1.*
                FROM attendance_evidences e1
                INNER JOIN (
                    SELECT attendance_record_id, MAX(id) AS latest_id
                    FROM attendance_evidences
                    GROUP BY attendance_record_id
                ) e2 ON e1.id = e2.latest_id
             ) ae ON ae.attendance_record_id = ar.id
             WHERE cs.class_id = ? AND ar.status IN (2, 3)
             ${keywordSql}
             ORDER BY ass.attendance_date DESC, u.full_name ASC
             LIMIT 400`,
            params
        );

        return {
            stats: {
                totalStudents: Number(statsRows[0]?.total_students || 0),
                warningStudents: Number(statsRows[0]?.warning_students || 0),
                warningSubjects: Number(statsRows[0]?.warning_subjects || 0)
            },
            rows
        };
    } catch (error) {
        console.error('Error getting BCS dashboard detail:', error);
        throw error;
    }
};

exports.getAdminClassesSubjects = async () => {
    try {
        const [classes] = await db.query(
            `SELECT
                c.id,
                c.class_name,
                c.academic_year,
                COUNT(DISTINCT cls.student_id) AS student_count,
                CASE WHEN COUNT(DISTINCT cs.id) > 0 THEN 'open' ELSE 'closed' END AS status
             FROM classes c
             LEFT JOIN class_students cls ON cls.class_id = c.id
             LEFT JOIN class_subjects cs ON cs.class_id = c.id
             GROUP BY c.id, c.class_name, c.academic_year
             ORDER BY c.class_name ASC`
        );

        const [subjects] = await db.query(
            `SELECT
                s.id,
                s.subject_code,
                s.subject_name,
                s.credits,
                CASE WHEN COUNT(DISTINCT cs.id) > 0 THEN 'open' ELSE 'closed' END AS status
             FROM subjects s
             LEFT JOIN class_subjects cs ON cs.subject_id = s.id
             GROUP BY s.id, s.subject_code, s.subject_name, s.credits
             ORDER BY s.subject_code ASC`
        );

        return { classes, subjects };
    } catch (error) {
        console.error('Error getting admin classes subjects:', error);
        throw error;
    }
};

exports.getAdminSystemLogs = async (filters = {}) => {
    try {
        const keyword = String(filters.keyword || '').trim();
        const role = String(filters.role || 'all').trim();
        const action = String(filters.action || 'all').trim();
        const date = String(filters.date || '').trim();

        let whereSql = 'WHERE 1=1';
        const params = [];

        if (keyword) {
            whereSql += ' AND (u.username LIKE ? OR u.full_name LIKE ? OR l.action LIKE ? OR l.target_table LIKE ?)';
            params.push(`%${keyword}%`, `%${keyword}%`, `%${keyword}%`, `%${keyword}%`);
        }

        if (role !== 'all') {
            whereSql += ' AND u.role = ?';
            params.push(role);
        }

        if (action !== 'all') {
            whereSql += ' AND l.action LIKE ?';
            params.push(`%${action}%`);
        }

        if (date) {
            whereSql += ' AND DATE(l.created_at) = ?';
            params.push(date);
        }

        const [rows] = await db.query(
            `SELECT
                l.id,
                l.created_at,
                l.action,
                l.target_table,
                l.target_id,
                u.username,
                u.full_name,
                u.role
             FROM system_logs l
             LEFT JOIN users u ON l.user_id = u.id
             ${whereSql}
             ORDER BY l.created_at DESC
             LIMIT 500`,
            params
        );

        return rows;
    } catch (error) {
        console.error('Error getting admin system logs:', error);
        throw error;
    }
};

exports.getAdminOrgSettings = async () => {
    try {
        const [departments] = await db.query(
            `SELECT id, department_name, created_at
             FROM departments
             ORDER BY department_name ASC`
        );

        const [semesters] = await db.query(
            `SELECT id, semester_name, academic_year, start_date, end_date
             FROM semesters
             ORDER BY end_date DESC, id DESC`
        );

        return { departments, semesters };
    } catch (error) {
        console.error('Error getting admin org settings:', error);
        throw error;
    }
};

exports.getAdminTeachingAssignments = async () => {
    try {
        const [rows] = await db.query(
            `SELECT
                cs.id AS class_subject_id,
                c.class_name,
                c.academic_year,
                s.subject_name,
                s.subject_code,
                s.credits,
                cs.semester,
                cs.start_date,
                cs.end_date,
                cs.teacher_id,
                main_t.full_name AS teacher_main_name,
                csg.id AS group_id,
                csg.group_code,
                csg.day_of_week,
                csg.start_period,
                csg.end_period,
                csg.room,
                csg.sub_teacher_id,
                sub_t.full_name AS teacher_sub_name
             FROM class_subjects cs
             JOIN classes c ON cs.class_id = c.id
             JOIN subjects s ON cs.subject_id = s.id
             LEFT JOIN users main_t ON main_t.id = cs.teacher_id
             LEFT JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
             LEFT JOIN users sub_t ON sub_t.id = csg.sub_teacher_id
             ORDER BY c.class_name ASC, s.subject_name ASC, csg.group_code ASC`
        );

        const map = new Map();
        rows.forEach((r) => {
            const key = Number(r.class_subject_id);
            if (!map.has(key)) {
                map.set(key, {
                    id: `${r.class_name || ''}-${r.subject_code || key}`,
                    classCode: r.class_name || '',
                    name: r.subject_name || '',
                    year: String(r.academic_year || '').slice(0, 4),
                    semester: String(r.semester || ''),
                    isOpen: Boolean(r.start_date && r.end_date),
                    credits: Number(r.credits || 0),
                    openWindow: `${r.start_date ? String(r.start_date).slice(0, 10) : '--'} - ${r.end_date ? String(r.end_date).slice(0, 10) : '--'}`,
                    hasSchedule: false,
                    groups: []
                });
            }

            if (r.group_id) {
                const item = map.get(key);
                item.groups.push({
                    code: r.group_code || '',
                    teacherMain: r.teacher_id ? `T${r.teacher_id}` : '',
                    teacherMainName: r.teacher_main_name || '',
                    teacherSub: r.sub_teacher_id ? `T${r.sub_teacher_id}` : '',
                    teacherSubName: r.teacher_sub_name || '',
                    day: r.day_of_week ? String(r.day_of_week) : '',
                    start: r.start_period || '',
                    end: r.end_period || '',
                    room: r.room || ''
                });
                if (r.day_of_week && r.start_period && r.end_period && r.room) {
                    item.hasSchedule = true;
                }
            }
        });

        return Array.from(map.values());
    } catch (error) {
        console.error('Error getting admin teaching assignments:', error);
        throw error;
    }
};
