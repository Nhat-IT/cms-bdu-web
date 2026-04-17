const express = require('express');
const studentController = require('../controllers/studentController');

const router = express.Router();

// ========== STUDENT ENDPOINTS ==========
// Dashboard
router.get('/student/dashboard', studentController.getDashboard);

// Profile
router.get('/student/profile', studentController.getProfile);

// Classes
router.get('/student/classes', studentController.getClasses);

// Attendance
router.get('/student/attendance', studentController.getAttendance);

// Grades
router.get('/student/grades', studentController.getGrades);

// Assignments
router.get('/student/assignments', studentController.getAssignments);

// Documents
router.get('/student/documents', studentController.getDocuments);

// Notifications
router.get('/student/notifications', studentController.getNotifications);
router.post('/student/notifications/mark-read', studentController.markNotificationsAsRead);

module.exports = router;
