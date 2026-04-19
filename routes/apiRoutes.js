const express = require('express');
const studentController = require('../src/controllers/studentController');

const router = express.Router();

// ========== SHARED ENDPOINTS ==========
router.get('/me', studentController.getMe);
router.put('/me', studentController.updateMe);
router.put('/me/password', studentController.changeMyPassword);
router.get('/notifications/unread-count', studentController.getUnreadNotificationCount);
router.get('/teacher/dashboard', studentController.getTeacherDashboard);
router.get('/bcs/dashboard', studentController.getBcsDashboard);
router.get('/admin/dashboard', studentController.getAdminDashboard);
router.get('/admin/accounts', studentController.getAdminAccounts);
router.post('/admin/accounts', studentController.createAdminAccount);
router.put('/admin/accounts/:id', studentController.updateAdminAccount);
router.post('/admin/accounts/:id/reset-password', studentController.resetAdminAccountPassword);

// Teacher
router.get('/teacher/groups', studentController.getTeacherGroups);
router.get('/teacher/attendance/roster', studentController.getTeacherAttendanceRoster);
router.post('/teacher/attendance/save', studentController.saveTeacherAttendance);
router.post('/teacher/groups/:groupId/students', studentController.addTeacherStudentToGroup);
router.get('/teacher/evidences', studentController.getTeacherEvidences);
router.post('/teacher/evidences/:id/review', studentController.reviewTeacherEvidence);
router.get('/teacher/assignments', studentController.getTeacherAssignments);
router.post('/teacher/assignments', studentController.createTeacherAssignment);
router.put('/teacher/assignments/:id', studentController.updateTeacherAssignment);
router.delete('/teacher/assignments/:id', studentController.deleteTeacherAssignment);
router.get('/teacher/assignments/:id/submissions', studentController.getTeacherAssignmentSubmissions);
router.post('/teacher/submissions/:id/grade', studentController.gradeTeacherSubmission);
router.get('/teacher/grades', studentController.getTeacherGrades);
router.post('/teacher/grades/save', studentController.saveTeacherGrades);

// BCS
router.get('/bcs/groups', studentController.getBcsGroups);
router.get('/bcs/attendance/roster', studentController.getBcsAttendanceRoster);
router.post('/bcs/attendance/save', studentController.saveBcsAttendance);
router.get('/bcs/documents', studentController.getBcsDocuments);
router.post('/bcs/documents', studentController.createBcsDocument);
router.put('/bcs/documents/:id', studentController.updateBcsDocument);
router.delete('/bcs/documents/:id', studentController.deleteBcsDocument);
router.get('/bcs/announcements', studentController.getBcsAnnouncements);
router.post('/bcs/announcements', studentController.createBcsAnnouncement);
router.put('/bcs/announcements/:id', studentController.updateBcsAnnouncement);
router.delete('/bcs/announcements/:id', studentController.deleteBcsAnnouncement);
router.get('/bcs/feedbacks', studentController.getBcsFeedbacks);
router.post('/bcs/feedbacks/:id/resolve', studentController.resolveBcsFeedback);
router.delete('/bcs/feedbacks/:id', studentController.deleteBcsFeedback);
router.get('/bcs/dashboard-detail', studentController.getBcsDashboardDetail);

// Admin secondary pages
router.get('/admin/classes-subjects', studentController.getAdminClassesSubjects);
router.get('/admin/system-logs', studentController.getAdminSystemLogs);
router.get('/admin/org-settings', studentController.getAdminOrgSettings);
router.get('/admin/teaching-assignments', studentController.getAdminTeachingAssignments);

// ========== STUDENT ENDPOINTS ==========
// Dashboard
router.get('/student/dashboard', studentController.getDashboard);

// Profile
router.get('/student/profile', studentController.getProfile);
router.put('/student/profile', studentController.updateProfile);
router.put('/student/password', studentController.changePassword);

// Classes
router.get('/student/classes', studentController.getClasses);

// Học kỳ và Tuần (dùng cho trang Lịch học)
router.get('/student/semesters', studentController.getSemesters);
router.get('/student/weeks', studentController.getWeeks);

// Attendance
router.get('/student/attendance', studentController.getAttendance);
router.post('/student/attendance/evidences', studentController.submitAttendanceEvidence);

// Grades
router.get('/student/grades', studentController.getGrades);

// Assignments
router.get('/student/assignments', studentController.getAssignments);
router.post('/student/assignments/:id/submit', studentController.submitAssignment);
router.delete('/student/assignments/:id/submission', studentController.unsubmitAssignment);

// Documents
router.get('/student/documents', studentController.getDocuments);

// Notifications
router.get('/student/notifications', studentController.getNotifications);
router.post('/student/notifications/mark-read', studentController.markNotificationsAsRead);
// Shared aliases for notifications page and bell preview.
router.get('/notifications', studentController.getNotifications);
router.post('/notifications/mark-read', studentController.markNotificationsAsRead);

// Feedbacks
router.get('/student/feedbacks', studentController.getFeedbacks);
router.post('/student/feedbacks', studentController.createFeedback);
router.put('/student/feedbacks/:id', studentController.updateFeedback);
router.delete('/student/feedbacks/:id', studentController.deleteFeedback);

module.exports = router;
