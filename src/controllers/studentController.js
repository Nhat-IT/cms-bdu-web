const studentModel = require('../models/studentModel');
const bcrypt = require('bcryptjs');

const DEFAULT_ACCOUNT_PASSWORD = process.env.DEFAULT_ACCOUNT_PASSWORD || 'Bdu@123456';
const SCHEMA_ERROR_CODES = new Set([
    'ER_NO_SUCH_TABLE',
    'ER_BAD_FIELD_ERROR',
    'ER_BAD_DB_ERROR',
    'ER_USER_LIMIT_REACHED',
    'PROTOCOL_CONNECTION_LOST',
    'ETIMEDOUT',
    'ECONNREFUSED',
    'EHOSTUNREACH',
    'EACCES'
]);

function defaultMe(req) {
    return {
        id: req.user?.id || null,
        username: req.user?.username || '',
        full_name: req.user?.full_name || req.user?.name || '',
        email: req.user?.email || '',
        role: req.user?.role || '',
        avatar: req.user?.avatar || null,
        birth_date: null,
        phone_number: null,
        address: null,
        class_name: null,
        department_name: null
    };
}

function handleReadError(req, res, error, fallback) {
    if (SCHEMA_ERROR_CODES.has(error?.code)) {
        return res.json(fallback);
    }
    return res.status(500).json({ error: error.message });
}

// ========== DASHBOARD SINH VIÊN ==========
exports.getDashboard = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const dashboard = await studentModel.getStudentDashboard(userId);
        res.json(dashboard);
    } catch (error) {
        console.error('Dashboard error:', error);
        return handleReadError(req, res, error, {
            profile: defaultMe(req),
            attendance: { total_sessions: 0, present: 0, excused_absent: 0, unexcused_absent: 0 },
            grades: { count: 0, averageScore: 0, recent: [] },
            classes: { count: 0, list: [] },
            notifications: { unread: 0 }
        });
    }
};

// ========== DASHBOARD GIẢNG VIÊN ==========
exports.getTeacherDashboard = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const role = req.user?.role;
        if (role !== 'teacher' && role !== 'admin') {
            return res.status(403).json({ error: 'Forbidden' });
        }

        const dashboard = await studentModel.getTeacherDashboard(userId);
        res.json(dashboard);
    } catch (error) {
        console.error('Teacher dashboard error:', error);
        return handleReadError(req, res, error, {
            stats: { classCount: 0, weeklySessions: 0, pendingGrading: 0, pendingEvidence: 0 },
            classes: []
        });
    }
};

// ========== DASHBOARD BAN CÁN SỰ ==========
exports.getBcsDashboard = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const role = req.user?.role;
        if (role !== 'bcs' && role !== 'admin') {
            return res.status(403).json({ error: 'Forbidden' });
        }

        const dashboard = await studentModel.getBcsDashboard(userId);
        res.json(dashboard);
    } catch (error) {
        console.error('BCS dashboard error:', error);
        return handleReadError(req, res, error, {
            stats: { totalStudents: 0, absentToday: 0, pendingEvidence: 0, newFeedback: 0 },
            classInfo: { className: null },
            todaySchedule: [],
            announcements: []
        });
    }
};

// ========== THÔNG TIN USER ĐANG ĐĂNG NHẬP (DÙNG CHUNG) ==========
exports.getMe = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const me = await studentModel.getCurrentUser(userId);
        if (!me) {
            return res.status(404).json({ error: 'User not found' });
        }

        res.json(me);
    } catch (error) {
        console.error('Get me error:', error);
        return handleReadError(req, res, error, defaultMe(req));
    }
};

// ========== SỐ THÔNG BÁO CHƯA ĐỌC (DÙNG CHUNG) ==========
exports.getUnreadNotificationCount = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const unreadCount = await studentModel.getUnreadNotificationCount(userId);
        res.json({ unreadCount });
    } catch (error) {
        console.error('Unread count error:', error);
        return handleReadError(req, res, error, { unreadCount: 0 });
    }
};

exports.updateMe = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { fullName, email, phoneNumber, address, birthDate, avatar } = req.body;

        const updated = await studentModel.updateCurrentUserProfile(userId, {
            fullName,
            email,
            phoneNumber,
            address,
            birthDate,
            avatar
        });

        res.json(updated);
    } catch (error) {
        console.error('Update me error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.changeMyPassword = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { oldPassword, newPassword } = req.body;
        if (!oldPassword || !newPassword || newPassword.length < 6) {
            return res.status(400).json({ error: 'Mật khẩu mới phải có ít nhất 6 ký tự' });
        }

        const userAuth = await studentModel.getUserPasswordInfo(userId);
        if (!userAuth) {
            return res.status(404).json({ error: 'User not found' });
        }

        if (!userAuth.password) {
            return res.status(400).json({ error: 'Tài khoản này chưa có mật khẩu nội bộ' });
        }

        const isBcryptHash = String(userAuth.password).startsWith('$2a$')
            || String(userAuth.password).startsWith('$2b$')
            || String(userAuth.password).startsWith('$2y$');

        const matched = isBcryptHash
            ? await bcrypt.compare(oldPassword, userAuth.password)
            : oldPassword === userAuth.password;

        if (!matched) {
            return res.status(400).json({ error: 'Mật khẩu hiện tại không đúng' });
        }

        const nextHash = await bcrypt.hash(newPassword, 10);
        await studentModel.updateUserPasswordHash(userId, nextHash);

        res.json({ success: true, message: 'Đổi mật khẩu thành công' });
    } catch (error) {
        console.error('Change my password error:', error);
        res.status(500).json({ error: error.message });
    }
};

function ensureAdmin(req, res) {
    const role = req.user?.role;
    if (role !== 'admin') {
        res.status(403).json({ error: 'Forbidden' });
        return false;
    }
    return true;
}

exports.getAdminDashboard = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        if (!ensureAdmin(req, res)) {
            return;
        }

        const data = await studentModel.getAdminDashboard();
        res.json(data);
    } catch (error) {
        console.error('Admin dashboard error:', error);
        return handleReadError(req, res, error, {
            stats: { totalStudents: 0, totalTeachers: 0, totalClasses: 0, totalOpenClassSubjects: 0 },
            classSubjects: []
        });
    }
};

exports.getAdminAccounts = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        if (!ensureAdmin(req, res)) {
            return;
        }

        const rows = await studentModel.getAdminAccounts({
            keyword: req.query.keyword || '',
            role: req.query.role || 'all'
        });
        res.json(rows);
    } catch (error) {
        console.error('Admin accounts list error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.createAdminAccount = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        if (!ensureAdmin(req, res)) {
            return;
        }

        const { username, fullName, email, role, className } = req.body;
        if (!username || !fullName || !email || !role) {
            return res.status(400).json({ error: 'Thiếu dữ liệu bắt buộc' });
        }

        const passwordHash = await bcrypt.hash(DEFAULT_ACCOUNT_PASSWORD, 10);
        const createdId = await studentModel.createAdminAccount({
            username,
            fullName,
            email,
            role,
            className,
            passwordHash
        });

        res.json({ success: true, id: createdId });
    } catch (error) {
        console.error('Admin create account error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.updateAdminAccount = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        if (!ensureAdmin(req, res)) {
            return;
        }

        const targetId = Number(req.params.id);
        if (!targetId) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const { username, fullName, email, role, className } = req.body;
        if (!username || !fullName || !email || !role) {
            return res.status(400).json({ error: 'Thiếu dữ liệu bắt buộc' });
        }

        await studentModel.updateAdminAccount(targetId, {
            username,
            fullName,
            email,
            role,
            className
        });

        res.json({ success: true });
    } catch (error) {
        console.error('Admin update account error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.resetAdminAccountPassword = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        if (!ensureAdmin(req, res)) {
            return;
        }

        const targetId = Number(req.params.id);
        if (!targetId) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const passwordHash = await bcrypt.hash(DEFAULT_ACCOUNT_PASSWORD, 10);
        await studentModel.updateUserPasswordHash(targetId, passwordHash);

        res.json({ success: true });
    } catch (error) {
        console.error('Admin reset password error:', error);
        res.status(500).json({ error: error.message });
    }
};

function ensureTeacher(req, res) {
    const role = req.user?.role;
    if (role !== 'teacher' && role !== 'admin') {
        res.status(403).json({ error: 'Forbidden' });
        return false;
    }
    return true;
}

exports.getTeacherGroups = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const rows = await studentModel.getTeacherGroups(userId);
        res.json(rows);
    } catch (error) {
        console.error('Teacher groups error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.getTeacherAttendanceRoster = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const groupId = Number(req.query.groupId);
        const attendanceDate = req.query.date;
        if (!groupId || !attendanceDate) {
            return res.status(400).json({ error: 'groupId và date là bắt buộc' });
        }

        const data = await studentModel.getTeacherAttendanceRoster(userId, groupId, attendanceDate);
        if (data === null) {
            return res.status(404).json({ error: 'Không tìm thấy nhóm học phần' });
        }

        res.json(data);
    } catch (error) {
        console.error('Teacher roster error:', error);
        return handleReadError(req, res, error, { students: [] });
    }
};

exports.saveTeacherAttendance = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const ok = await studentModel.saveTeacherAttendance(userId, req.body || {});
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền lưu điểm danh cho nhóm này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Save teacher attendance error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.addTeacherStudentToGroup = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const groupId = Number(req.params.groupId || 0);
        const username = String(req.body?.username || '').trim();
        const className = String(req.body?.className || '').trim();

        if (!groupId || !username) {
            return res.status(400).json({ error: 'groupId và username là bắt buộc' });
        }

        const result = await studentModel.addTeacherStudentToGroup(userId, groupId, username, className);
        if (!result) {
            return res.status(403).json({ error: 'Không có quyền thêm sinh viên vào nhóm này' });
        }
        if (result.error) {
            return res.status(400).json({ error: result.error });
        }

        res.json({ success: true, student: result.student || null });
    } catch (error) {
        console.error('Add teacher student to group error:', error);
        res.status(500).json({ error: error.message });
    }
};

function ensureBcs(req, res) {
    const role = req.user?.role;
    if (role !== 'bcs' && role !== 'admin') {
        res.status(403).json({ error: 'Forbidden' });
        return false;
    }
    return true;
}

exports.getBcsGroups = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const rows = await studentModel.getBcsGroups(userId);
        res.json(rows);
    } catch (error) {
        console.error('BCS groups error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.getBcsAttendanceRoster = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const groupId = Number(req.query.groupId || 0);
        const attendanceDate = req.query.date;
        if (!groupId || !attendanceDate) {
            return res.status(400).json({ error: 'groupId và date là bắt buộc' });
        }

        const data = await studentModel.getBcsAttendanceRoster(userId, groupId, attendanceDate);
        if (data === null) {
            return res.status(404).json({ error: 'Không tìm thấy nhóm học phần' });
        }

        res.json(data);
    } catch (error) {
        console.error('BCS roster error:', error);
        return handleReadError(req, res, error, { students: [] });
    }
};

exports.saveBcsAttendance = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const ok = await studentModel.saveBcsAttendance(userId, req.body || {});
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền lưu điểm danh cho nhóm này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Save BCS attendance error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getBcsDocuments = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const rows = await studentModel.getBcsDocuments(userId, {
            keyword: req.query.keyword || '',
            category: req.query.category || 'all'
        });
        res.json(rows);
    } catch (error) {
        console.error('BCS documents error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.createBcsDocument = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const createdId = await studentModel.createBcsDocument(userId, req.body || {});
        if (!createdId) {
            return res.status(400).json({ error: 'Không thể tạo tài liệu' });
        }

        res.json({ success: true, id: createdId });
    } catch (error) {
        console.error('BCS create document error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.updateBcsDocument = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const docId = Number(req.params.id || 0);
        if (!docId) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const ok = await studentModel.updateBcsDocument(userId, docId, req.body || {});
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy tài liệu cần cập nhật' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS update document error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.deleteBcsDocument = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const docId = Number(req.params.id || 0);
        if (!docId) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const ok = await studentModel.deleteBcsDocument(userId, docId);
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy tài liệu để xóa' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS delete document error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getBcsAnnouncements = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const rows = await studentModel.getBcsAnnouncements(userId, {
            keyword: req.query.keyword || ''
        });
        res.json(rows);
    } catch (error) {
        console.error('BCS announcements error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.createBcsAnnouncement = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const id = await studentModel.createBcsAnnouncement(userId, req.body || {});
        if (!id) {
            return res.status(400).json({ error: 'Không thể tạo bản tin' });
        }

        res.json({ success: true, id });
    } catch (error) {
        console.error('BCS create announcement error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.updateBcsAnnouncement = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const id = Number(req.params.id || 0);
        if (!id) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const ok = await studentModel.updateBcsAnnouncement(userId, id, req.body || {});
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy bản tin cần cập nhật' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS update announcement error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.deleteBcsAnnouncement = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const id = Number(req.params.id || 0);
        if (!id) {
            return res.status(400).json({ error: 'id không hợp lệ' });
        }

        const ok = await studentModel.deleteBcsAnnouncement(userId, id);
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy bản tin để xóa' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS delete announcement error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getBcsFeedbacks = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const rows = await studentModel.getBcsFeedbacks(userId, {
            keyword: req.query.keyword || '',
            status: req.query.status || 'all'
        });

        res.json(rows);
    } catch (error) {
        console.error('BCS feedbacks error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.resolveBcsFeedback = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const feedbackId = Number(req.params.id || 0);
        if (!feedbackId) {
            return res.status(400).json({ error: 'feedback id không hợp lệ' });
        }

        const replyContent = String(req.body?.replyContent || '').trim();
        const status = String(req.body?.status || 'Resolved').trim();

        const ok = await studentModel.resolveBcsFeedback(userId, feedbackId, replyContent, status);
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy phản hồi để cập nhật' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS resolve feedback error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.deleteBcsFeedback = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const feedbackId = Number(req.params.id || 0);
        if (!feedbackId) {
            return res.status(400).json({ error: 'feedback id không hợp lệ' });
        }

        const ok = await studentModel.deleteBcsFeedback(userId, feedbackId);
        if (!ok) {
            return res.status(404).json({ error: 'Không tìm thấy phản hồi để xóa' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('BCS delete feedback error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getBcsDashboardDetail = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureBcs(req, res)) return;

        const keyword = String(req.query.keyword || '').trim();
        const data = await studentModel.getBcsDashboardDetail(userId, keyword);
        res.json(data);
    } catch (error) {
        console.error('BCS dashboard detail error:', error);
        return handleReadError(req, res, error, {
            stats: { totalStudents: 0, warningStudents: 0, warningSubjects: 0 },
            rows: []
        });
    }
};

exports.getAdminClassesSubjects = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureAdmin(req, res)) return;

        const data = await studentModel.getAdminClassesSubjects();
        res.json(data);
    } catch (error) {
        console.error('Admin classes-subjects error:', error);
        return handleReadError(req, res, error, { classes: [], subjects: [] });
    }
};

exports.getAdminSystemLogs = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureAdmin(req, res)) return;

        const rows = await studentModel.getAdminSystemLogs({
            keyword: req.query.keyword || '',
            role: req.query.role || 'all',
            action: req.query.action || 'all',
            date: req.query.date || ''
        });
        res.json(rows);
    } catch (error) {
        console.error('Admin system logs error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.getAdminOrgSettings = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureAdmin(req, res)) return;

        const data = await studentModel.getAdminOrgSettings();
        res.json(data);
    } catch (error) {
        console.error('Admin org settings error:', error);
        return handleReadError(req, res, error, { departments: [], semesters: [] });
    }
};

exports.getAdminTeachingAssignments = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureAdmin(req, res)) return;

        const data = await studentModel.getAdminTeachingAssignments();
        res.json(data);
    } catch (error) {
        console.error('Admin teaching assignments error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.getTeacherEvidences = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const rows = await studentModel.getTeacherEvidences(userId, {
            status: req.query.status || 'all',
            keyword: req.query.keyword || '',
            groupId: req.query.groupId || 0
        });

        res.json(rows);
    } catch (error) {
        console.error('Teacher evidences error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.reviewTeacherEvidence = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const evidenceId = Number(req.params.id);
        const action = String(req.body?.action || '').toLowerCase();
        if (!evidenceId || !['approve', 'reject'].includes(action)) {
            return res.status(400).json({ error: 'Dữ liệu không hợp lệ' });
        }

        const ok = await studentModel.reviewTeacherEvidence(userId, evidenceId, action);
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền duyệt minh chứng này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Review evidence error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getTeacherAssignments = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const groupId = Number(req.query.groupId || 0);
        const rows = await studentModel.getTeacherAssignments(userId, groupId);
        res.json(rows);
    } catch (error) {
        console.error('Teacher assignments error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.createTeacherAssignment = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const createdId = await studentModel.createTeacherAssignment(userId, req.body || {});
        if (!createdId) {
            return res.status(403).json({ error: 'Không có quyền tạo bài tập cho nhóm này' });
        }

        res.json({ success: true, id: createdId });
    } catch (error) {
        console.error('Create teacher assignment error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.updateTeacherAssignment = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const assignmentId = Number(req.params.id);
        if (!assignmentId) {
            return res.status(400).json({ error: 'assignment id không hợp lệ' });
        }

        const ok = await studentModel.updateTeacherAssignment(userId, assignmentId, req.body || {});
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền sửa bài tập này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Update teacher assignment error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.deleteTeacherAssignment = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const assignmentId = Number(req.params.id);
        if (!assignmentId) {
            return res.status(400).json({ error: 'assignment id không hợp lệ' });
        }

        const ok = await studentModel.deleteTeacherAssignment(userId, assignmentId);
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền xóa bài tập này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Delete teacher assignment error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getTeacherAssignmentSubmissions = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const assignmentId = Number(req.params.id);
        if (!assignmentId) {
            return res.status(400).json({ error: 'assignment id không hợp lệ' });
        }

        const rows = await studentModel.getTeacherAssignmentSubmissions(userId, assignmentId);
        res.json(rows);
    } catch (error) {
        console.error('Teacher submissions error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.gradeTeacherSubmission = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const submissionId = Number(req.params.id);
        const score = req.body?.score === '' || req.body?.score === null ? null : Number(req.body?.score);
        const feedback = req.body?.feedback || null;

        if (!submissionId || (score !== null && (!Number.isFinite(score) || score < 0 || score > 10))) {
            return res.status(400).json({ error: 'Dữ liệu không hợp lệ' });
        }

        const ok = await studentModel.gradeTeacherSubmission(userId, submissionId, score, feedback);
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền chấm bài nộp này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Grade submission error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.getTeacherGrades = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const groupId = Number(req.query.groupId || 0);
        if (!groupId) {
            return res.status(400).json({ error: 'groupId là bắt buộc' });
        }

        const rows = await studentModel.getTeacherGradesByGroup(userId, groupId);
        if (rows === null) {
            return res.status(403).json({ error: 'Không có quyền xem bảng điểm nhóm này' });
        }

        res.json(rows);
    } catch (error) {
        console.error('Teacher grades error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.saveTeacherGrades = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) return res.status(401).json({ error: 'Unauthorized' });
        if (!ensureTeacher(req, res)) return;

        const groupId = Number(req.body?.groupId || 0);
        const rows = Array.isArray(req.body?.rows) ? req.body.rows : [];
        if (!groupId) {
            return res.status(400).json({ error: 'groupId là bắt buộc' });
        }

        const ok = await studentModel.saveTeacherGrades(userId, groupId, rows);
        if (!ok) {
            return res.status(403).json({ error: 'Không có quyền lưu bảng điểm nhóm này' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Save teacher grades error:', error);
        res.status(500).json({ error: error.message });
    }
};

// ========== HỒ SƠ SINH VIÊN ==========
exports.getProfile = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const profile = await studentModel.getStudentProfile(userId);
        if (!profile) {
            return res.status(404).json({ error: 'Profile not found' });
        }

        // Thêm cờ is_bcs để frontend hiển thị ô chức vụ
        profile.is_bcs = profile.role === 'bcs' || profile.position !== null;

        res.json(profile);
    } catch (error) {
        console.error('Profile error:', error);
        return handleReadError(req, res, error, defaultMe(req));
    }
};

exports.updateProfile = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { birthDate, phoneNumber, address, position } = req.body;
        const profile = await studentModel.updateStudentProfile(userId, {
            birthDate,
            phoneNumber,
            address,
            position
        });

        res.json(profile);
    } catch (error) {
        console.error('Update profile error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.changePassword = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { oldPassword, newPassword } = req.body;
        if (!oldPassword || !newPassword || newPassword.length < 6) {
            return res.status(400).json({ error: 'Mật khẩu mới phải có ít nhất 6 ký tự' });
        }

        const userAuth = await studentModel.getUserPasswordInfo(userId);
        if (!userAuth) {
            return res.status(404).json({ error: 'User not found' });
        }

        if (!userAuth.password) {
            return res.status(400).json({ error: 'Tài khoản này chưa có mật khẩu nội bộ. Hãy đặt mật khẩu lần đầu từ trang quản trị.' });
        }

        const isBcryptHash = String(userAuth.password).startsWith('$2a$')
            || String(userAuth.password).startsWith('$2b$')
            || String(userAuth.password).startsWith('$2y$');

        const matched = isBcryptHash
            ? await bcrypt.compare(oldPassword, userAuth.password)
            : oldPassword === userAuth.password;

        if (!matched) {
            return res.status(400).json({ error: 'Mật khẩu hiện tại không đúng' });
        }

        const nextHash = await bcrypt.hash(newPassword, 10);
        await studentModel.updateUserPasswordHash(userId, nextHash);

        res.json({ success: true, message: 'Đổi mật khẩu thành công' });
    } catch (error) {
        console.error('Change password error:', error);
        res.status(500).json({ error: error.message });
    }
};

// ========== HỌC KỲ ==========
exports.getSemesters = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        const semesters = await studentModel.getStudentSemesters(userId);
        res.json(semesters);
    } catch (error) {
        console.error('Get semesters error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== TUẦN HỌC ==========
exports.getWeeks = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }
        const { semester_id } = req.query;
        const weeks = await studentModel.getStudentWeeks(userId, semester_id);
        res.json(weeks);
    } catch (error) {
        console.error('Get weeks error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== LỚP HỌC ==========
exports.getClasses = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const classes = await studentModel.getStudentClasses(userId);
        res.json(classes);
    } catch (error) {
        console.error('Classes error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== ĐIỂM DANH ==========
exports.getAttendance = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const stats = await studentModel.getAttendanceStats(userId);
        const records = await studentModel.getAttendanceRecords(userId);
        
        res.json({
            stats,
            records
        });
    } catch (error) {
        console.error('Attendance error:', error);
        return handleReadError(req, res, error, {
            stats: { total_sessions: 0, present: 0, excused_absent: 0, unexcused_absent: 0 },
            records: []
        });
    }
};

// ========== ĐIỂM SỐ ==========
exports.getGrades = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const grades = await studentModel.getGrades(userId);
        res.json(grades);
    } catch (error) {
        console.error('Grades error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== BÀI TẬP ==========
exports.getAssignments = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const assignments = await studentModel.getAssignments(userId);
        res.json(assignments);
    } catch (error) {
        console.error('Assignments error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.submitAssignment = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const assignmentId = Number(req.params.id);
        const { driveLink, driveFileId } = req.body;

        if (!assignmentId || !driveLink) {
            return res.status(400).json({ error: 'assignment id và driveLink là bắt buộc' });
        }

        const result = await studentModel.submitAssignment(userId, assignmentId, driveLink, driveFileId || null);
        if (result === null) {
            return res.status(404).json({ error: 'Assignment không thuộc môn đã đăng ký' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Submit assignment error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.unsubmitAssignment = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const assignmentId = Number(req.params.id);
        if (!assignmentId) {
            return res.status(400).json({ error: 'assignment id không hợp lệ' });
        }

        const deleted = await studentModel.unsubmitAssignment(userId, assignmentId);
        if (!deleted) {
            return res.status(404).json({ error: 'Không tìm thấy bài nộp để hủy' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Unsubmit assignment error:', error);
        res.status(500).json({ error: error.message });
    }
};

// ========== TÀI LIỆU ==========
exports.getDocuments = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const documents = await studentModel.getClassDocuments(userId);
        res.json(documents);
    } catch (error) {
        console.error('Documents error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== THÔNG BÁO ==========
exports.getNotifications = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const notifications = await studentModel.getNotifications(userId);
        res.json(notifications);
    } catch (error) {
        console.error('Notifications error:', error);
        return handleReadError(req, res, error, []);
    }
};

// ========== ĐỨC THÔNG BÁO ĐÃ ĐỌC ==========
exports.markNotificationsAsRead = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const affectedRows = await studentModel.markNotificationsAsRead(userId);
        res.json({ success: true, markedAsRead: affectedRows });
    } catch (error) {
        console.error('Mark notifications error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.submitAttendanceEvidence = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { attendanceRecordId, driveLink, driveFileId } = req.body;
        if (!attendanceRecordId || !driveLink) {
            return res.status(400).json({ error: 'attendanceRecordId và driveLink là bắt buộc' });
        }

        const result = await studentModel.submitAttendanceEvidence(
            userId,
            Number(attendanceRecordId),
            driveLink,
            driveFileId || null
        );

        if (result === null) {
            return res.status(404).json({ error: 'Không tìm thấy buổi điểm danh phù hợp' });
        }

        if (result === false) {
            return res.status(400).json({ error: 'Không thể nộp minh chứng cho buổi có mặt' });
        }

        res.json({ success: true });
    } catch (error) {
        console.error('Submit attendance evidence error:', error);
        res.status(500).json({ error: error.message });
    }
};

// ========== PHẢN HỒI ==========
exports.getFeedbacks = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const feedbacks = await studentModel.getFeedbacks(userId);
        res.json(feedbacks);
    } catch (error) {
        console.error('Feedback list error:', error);
        return handleReadError(req, res, error, []);
    }
};

exports.createFeedback = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const { title, content } = req.body;
        if (!title || !content) {
            return res.status(400).json({ error: 'title and content are required' });
        }

        const created = await studentModel.createFeedback(userId, title.trim(), content.trim());
        res.status(201).json(created);
    } catch (error) {
        console.error('Create feedback error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.updateFeedback = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const feedbackId = Number(req.params.id);
        const { title, content } = req.body;
        if (!feedbackId || !title || !content) {
            return res.status(400).json({ error: 'invalid payload' });
        }

        const updated = await studentModel.updateFeedback(userId, feedbackId, title.trim(), content.trim());
        if (!updated) {
            return res.status(404).json({ error: 'Feedback not found or cannot be edited' });
        }

        res.json(updated);
    } catch (error) {
        console.error('Update feedback error:', error);
        res.status(500).json({ error: error.message });
    }
};

exports.deleteFeedback = async (req, res) => {
    try {
        const userId = req.user?.id;
        if (!userId) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        const feedbackId = Number(req.params.id);
        if (!feedbackId) {
            return res.status(400).json({ error: 'invalid feedback id' });
        }

        const deleted = await studentModel.deleteFeedback(userId, feedbackId);
        if (!deleted) {
            return res.status(404).json({ error: 'Feedback not found or cannot be deleted' });
        }

        res.json({ success: true, deletedId: feedbackId });
    } catch (error) {
        console.error('Delete feedback error:', error);
        res.status(500).json({ error: error.message });
    }
};
