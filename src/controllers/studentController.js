const studentModel = require('../models/studentModel');

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

        res.json(profile);
    } catch (error) {
        console.error('Profile error:', error);
        res.status(500).json({ error: error.message });
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
        res.status(500).json({ error: error.message });
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
        res.status(500).json({ error: error.message });
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
        res.status(500).json({ error: error.message });
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
        res.status(500).json({ error: error.message });
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
        res.status(500).json({ error: error.message });
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
