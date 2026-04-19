<?php
/**
 * CMS BDU - Document Controller
 * Xử lý các chức năng liên quan đến tài liệu
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

// Lấy danh sách tài liệu theo lớp học phần
function getDocumentsByClassSubject($classSubjectId) {
    return db_fetch_all("
        SELECT d.*, u.full_name as uploader_name
        FROM documents d
        LEFT JOIN users u ON d.uploader_id = u.id
        WHERE d.class_subject_id = ?
        ORDER BY d.created_at DESC
    ", [$classSubjectId]);
}

// Thêm tài liệu mới
function addDocument($data) {
    try {
        db_query("
            INSERT INTO documents (title, note, category, drive_link, drive_file_id, 
                                   icon_type, custom_icon, class_subject_id, uploader_id, semester)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['title'],
            $data['note'] ?? null,
            $data['category'],
            $data['drive_link'] ?? null,
            $data['drive_file_id'] ?? null,
            $data['icon_type'] ?? 'file',
            $data['custom_icon'] ?? null,
            $data['class_subject_id'],
            $data['uploader_id'],
            $data['semester'] ?? null
        ]);
        
        return ['success' => true, 'id' => mysqli_insert_id($GLOBALS['conn'])];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

// Xóa tài liệu
function deleteDocument($documentId, $userId) {
    try {
        $doc = db_fetch_one("SELECT uploader_id FROM documents WHERE id = ?", [$documentId]);
        
        if (!$doc) {
            return ['success' => false, 'message' => 'Tài liệu không tồn tại.'];
        }
        
        if ($doc['uploader_id'] != $userId && $_SESSION['role'] != 'admin') {
            return ['success' => false, 'message' => 'Bạn không có quyền xóa tài liệu này.'];
        }
        
        db_query("DELETE FROM documents WHERE id = ?", [$documentId]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}
