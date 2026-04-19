<?php
/**
 * CMS BDU - Feedback Controller
 * Xử lý các chức năng liên quan đến phản hồi
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Lấy phản hồi của sinh viên
function getStudentFeedbacks($studentId) {
    return db_fetch_all("SELECT * FROM feedbacks WHERE student_id = ? ORDER BY created_at DESC", [$studentId]);
}

// Gửi phản hồi mới
function submitFeedback($studentId, $title, $content) {
    try {
        db_query(
            "INSERT INTO feedbacks (student_id, title, content, status) VALUES (?, ?, ?, 'Pending')",
            [$studentId, $title, $content]
        );
        
        return ['success' => true, 'id' => mysqli_insert_id($GLOBALS['conn'])];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

// Lấy phản hồi của lớp (cho BCS)
function getClassFeedbacks($classId) {
    return db_fetch_all("
        SELECT f.*, u.username, u.full_name, c.class_name
        FROM feedbacks f
        JOIN users u ON f.student_id = u.id
        JOIN class_students cs ON u.id = cs.student_id
        JOIN classes c ON cs.class_id = c.id
        WHERE cs.class_id = ?
        ORDER BY f.updated_at DESC
    ", [$classId]);
}

// Trả lời phản hồi (BCS/GV)
function replyFeedback($feedbackId, $replyContent) {
    try {
        db_query(
            "UPDATE feedbacks SET reply_content = ?, status = 'Resolved', updated_at = NOW() WHERE id = ?",
            [$replyContent, $feedbackId]
        );
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}
