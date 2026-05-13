<?php
/**
 * API: Serve a file stored in the documents table (file_data BLOB).
 * URL: /cms/api/serve-file.php?id=<doc_id>&action=view|download
 * Requires logged-in user with appropriate role.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

function serve_error(int $code, string $message): void {
    http_response_code($code);
    echo $message;
    exit;
}

if (!isLoggedIn()) {
    serve_error(401, 'Unauthorized');
}

$docId = (int)($_GET['id'] ?? 0);
$action = strtolower(trim((string)($_GET['action'] ?? 'view')));

if ($docId <= 0) {
    serve_error(400, 'Missing document ID');
}

try {
    getDBConnection();

    $doc = db_fetch_one(
        "SELECT d.id, d.file_data, d.file_mime, d.file_size, d.original_filename,
                d.title, d.uploader_id, d.class_subject_id,
                cs.class_id
         FROM documents d
         LEFT JOIN class_subjects cs ON cs.id = d.class_subject_id
         WHERE d.id = ? AND d.file_data IS NOT NULL AND LENGTH(d.file_data) > 0
         LIMIT 1",
        [$docId]
    );

    if (!$doc) {
        serve_error(404, 'Tài liệu không tồn tại hoặc không có file đính kèm.');
    }

    $fileData = $doc['file_data'];
    $mimeType = trim((string)($doc['file_mime'] ?? ''));
    $fileSize = (int)($doc['file_size'] ?? 0);
    $filename = trim((string)($doc['original_filename'] ?? ''));
    if ($filename === '') {
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $doc['title'] ?? 'document');
    }

    // Set appropriate mime type
    $safeMime = ($mimeType !== '' && strpos($mimeType, '/') !== false) ? $mimeType : 'application/octet-stream';

    // Security: basic role check
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = trim((string)($_SESSION['role'] ?? ''));

    if ($role === 'admin' || $role === 'support_admin') {
        // Allowed
    } elseif ($role === 'bcs') {
        // BCS can only access docs from their own class
        $classRow = db_fetch_one(
            "SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1",
            [$userId]
        );
        $userClassId = (int)($classRow['class_id'] ?? 0);
        $docClassId = (int)($doc['class_id'] ?? 0);
        if ($userClassId !== $docClassId) {
            serve_error(403, 'Bạn không có quyền truy cập tài liệu này.');
        }
    } elseif ($role === 'teacher') {
        // Teacher: check if assigned to the class subject
        $assigned = db_fetch_one(
            "SELECT id FROM class_subjects WHERE id = ? LIMIT 1",
            [$doc['class_subject_id']]
        );
        if (!$assigned) {
            serve_error(403, 'Bạn không có quyền truy cập tài liệu này.');
        }
    } elseif ($role === 'student') {
        // Student: check if enrolled in the class
        $enrolled = db_fetch_one(
            "SELECT id FROM class_students WHERE class_id = ? AND student_id = ? LIMIT 1",
            [$doc['class_id'] ?? 0, $userId]
        );
        if (!$enrolled) {
            serve_error(403, 'Bạn không có quyền truy cập tài liệu này.');
        }
    } else {
        serve_error(403, 'Không xác định được quyền truy cập.');
    }

    // Prevent caching of sensitive documents
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Expires: 0');

    if ($action === 'download') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }

    if ($fileSize > 0) {
        header('Content-Length: ' . $fileSize);
    }
    header('Content-Length: ' . strlen($fileData));

    echo $fileData;
    exit;

} catch (Throwable $e) {
    error_log('serve-file error: ' . $e->getMessage());
    serve_error(500, 'Lỗi hệ thống khi đọc tài liệu.');
}
