<?php
/**
 * API: BCS Documents CRUD
 * Uses DB config from config.php (env-aware via .env.local)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
requireRole('bcs');

$userId = (int)($_SESSION['user_id'] ?? 0);

function docs_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function docs_detect_icon(string $title, string $fallback = 'file'): string {
    $t = strtolower(trim($title));
    if (preg_match('/\.pdf$/', $t)) return 'pdf';
    if (preg_match('/\.(doc|docx)$/', $t)) return 'doc';
    if (preg_match('/\.(xls|xlsx|csv)$/', $t)) return 'xls';
    if (preg_match('/\.(zip|rar|7z)$/', $t)) return 'zip';
    return $fallback;
}

function docs_get_bcs_class_id(int $userId): int {
    // Ưu tiên từ class_students
    $row = db_fetch_one('SELECT class_id FROM class_students WHERE student_id = ? LIMIT 1', [$userId]);
    $classId = (int)($row['class_id'] ?? 0);
    if ($classId > 0) {
        return $classId;
    }
    // Fallback: không có class_id từ class_students, trả về 0
    return 0;
}

function docs_get_bcs_class_name(int $userId): string {
    $row = db_fetch_one('SELECT c.class_name FROM class_students cs JOIN classes c ON cs.class_id = c.id WHERE cs.student_id = ? LIMIT 1', [$userId]);
    return $row['class_name'] ?? '';
}

function docs_find_class_subject_id(int $classId, string $className, string $year, string $semester): int {
    if ($classId > 0) {
        $row = db_fetch_one(
            "SELECT cs.id
             FROM class_subjects cs
             LEFT JOIN semesters sm ON sm.id = cs.semester_id
             WHERE cs.class_id = ?
               AND (? = '' OR sm.academic_year = ?)
               AND (? = '' OR UPPER(sm.semester_name) = UPPER(?))
             ORDER BY cs.id DESC
             LIMIT 1",
            [$classId, $year, $year, $semester, $semester]
        );

        if ($row && !empty($row['id'])) return (int)$row['id'];

        $fallback = db_fetch_one('SELECT id FROM class_subjects WHERE class_id = ? ORDER BY id DESC LIMIT 1', [$classId]);
        return (int)($fallback['id'] ?? 0);
    }
    
    // Với class_students: tìm theo class_id
    $row = db_fetch_one(
        "SELECT cs.id
         FROM class_subjects cs
         LEFT JOIN semesters sm ON sm.id = cs.semester_id
         WHERE cs.class_id = ?
           AND (? = '' OR sm.academic_year = ?)
           AND (? = '' OR UPPER(sm.semester_name) = UPPER(?))
         ORDER BY cs.id DESC
         LIMIT 1",
        [$classId, $year, $year, $semester, $semester]
    );

    if ($row && !empty($row['id'])) return (int)$row['id'];

    $fallback = db_fetch_one('SELECT id FROM class_subjects WHERE class_id = ? ORDER BY id DESC LIMIT 1', [$classId]);
    return (int)($fallback['id'] ?? 0);
}

function docs_find_class_subject_by_semester_id(int $classId, string $className, int $semesterId): int {
    if ($semesterId <= 0) return 0;

    if ($classId > 0) {
        $row = db_fetch_one(
            "SELECT id
             FROM class_subjects
             WHERE class_id = ? AND semester_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$classId, $semesterId]
        );
        if ($row && !empty($row['id'])) {
            return (int)$row['id'];
        }

        $fallback = db_fetch_one(
            "SELECT id
             FROM class_subjects
             WHERE class_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$classId]
        );
        return (int)($fallback['id'] ?? 0);
    }
    
    // Với class_students
    $row = db_fetch_one(
        "SELECT id
         FROM class_subjects
         WHERE class_id = ? AND semester_id = ?
         ORDER BY id DESC
         LIMIT 1",
        [$classId, $semesterId]
    );
    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
    }

    $fallback = db_fetch_one(
        "SELECT id
         FROM class_subjects
         WHERE class_id = ?
         ORDER BY id DESC
         LIMIT 1",
        [$classId]
    );
    return (int)($fallback['id'] ?? 0);
}

try {
    getDBConnection();

    $classId = docs_get_bcs_class_id($userId);
    $className = docs_get_bcs_class_name($userId);
    
    // Kiểm tra: cần có classId hoặc className
    if ($classId <= 0 && $className === '') {
        docs_json(400, ['ok' => false, 'error' => 'Tài khoản BCS chưa được gán lớp.']);
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true);
    if (!is_array($input)) {
        $input = [];
    }

    if ($method !== 'POST') {
        docs_json(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $action = strtolower(trim((string)($input['action'] ?? '')));
    if ($action === '') {
        docs_json(400, ['ok' => false, 'error' => 'Thiếu action']);
    }

    if ($action === 'create') {
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $note = trim((string)($input['note'] ?? ''));
        $driveLink = trim((string)($input['drive_link'] ?? ''));
        $driveFileId = trim((string)($input['drive_file_id'] ?? ''));
        $semester = strtoupper(trim((string)($input['semester'] ?? '')));
        $year = trim((string)($input['year'] ?? ''));
        $semesterId = (int)($input['semester_id'] ?? 0);

        if ($semester === 'ALL') {
            $semester = '';
        }

        if ($title === '' || $category === '') {
            docs_json(400, ['ok' => false, 'error' => 'Thiếu tiêu đề hoặc danh mục']);
        }

        $classSubjectId = $semesterId > 0
            ? docs_find_class_subject_by_semester_id($classId, $className, $semesterId)
            : docs_find_class_subject_id($classId, $className, $year, $semester);

        if ($classSubjectId <= 0) {
            docs_json(400, ['ok' => false, 'error' => 'Không tìm thấy lớp học phần phù hợp để lưu tài liệu']);
        }

        if ($semester === '' && $semesterId > 0) {
            $semRow = db_fetch_one('SELECT semester_name FROM semesters WHERE id = ? LIMIT 1', [$semesterId]);
            $semester = strtoupper(trim((string)($semRow['semester_name'] ?? '')));
        }

        $iconType = docs_detect_icon($title, 'file');
        db_query(
            "INSERT INTO documents (title, note, category, drive_link, drive_file_id, icon_type, class_subject_id, uploader_id, semester)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $title,
                $note !== '' ? $note : null,
                $category,
                $driveLink !== '' ? $driveLink : null,
                $driveFileId !== '' ? $driveFileId : null,
                $iconType,
                $classSubjectId,
                $userId,
                $semester !== '' ? $semester : null
            ]
        );

        docs_json(200, ['ok' => true, 'message' => 'created']);
    }

    if ($action === 'update') {
        $docId = (int)($input['id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $note = trim((string)($input['note'] ?? ''));
        $driveLink = trim((string)($input['drive_link'] ?? ''));
        $driveFileId = trim((string)($input['drive_file_id'] ?? ''));

        if ($docId <= 0 || $title === '' || $category === '') {
            docs_json(400, ['ok' => false, 'error' => 'Dữ liệu cập nhật không hợp lệ']);
        }

        // Tìm tài liệu - kiểm tra quyền sửa
        $doc = null;
        if ($classId > 0) {
            $doc = db_fetch_one(
                "SELECT d.id, d.uploader_id
                 FROM documents d
                 JOIN class_subjects cs ON cs.id = d.class_subject_id
                 WHERE d.id = ? AND cs.class_id = ?
                 LIMIT 1",
                [$docId, $classId]
            );
        }
        if (!$doc && $classId > 0) {
            $doc = db_fetch_one(
                "SELECT d.id, d.uploader_id
                 FROM documents d
                 JOIN class_subjects cs ON cs.id = d.class_subject_id
                 WHERE d.id = ? AND cs.class_id = ?
                 LIMIT 1",
                [$docId, $classId]
            );
        }

        if (!$doc) {
            docs_json(404, ['ok' => false, 'error' => 'Không tìm thấy tài liệu']);
        }
        if ((int)($doc['uploader_id'] ?? 0) !== $userId) {
            docs_json(403, ['ok' => false, 'error' => 'Bạn không có quyền sửa tài liệu này']);
        }

        $iconType = docs_detect_icon($title, 'file');
        if ($driveLink !== '') {
            db_query(
                "UPDATE documents
                 SET title = ?, note = ?, category = ?, drive_link = ?, drive_file_id = ?, icon_type = ?
                 WHERE id = ?",
                [$title, $note !== '' ? $note : null, $category, $driveLink, $driveFileId !== '' ? $driveFileId : null, $iconType, $docId]
            );
        } else {
            db_query(
                "UPDATE documents
                 SET title = ?, note = ?, category = ?, icon_type = ?
                 WHERE id = ?",
                [$title, $note !== '' ? $note : null, $category, $iconType, $docId]
            );
        }

        docs_json(200, ['ok' => true, 'message' => 'updated']);
    }

    if ($action === 'delete') {
        $docId = (int)($input['id'] ?? 0);
        if ($docId <= 0) {
            docs_json(400, ['ok' => false, 'error' => 'Thiếu id tài liệu']);
        }

        // Tìm tài liệu - kiểm tra quyền xóa
        $doc = null;
        if ($classId > 0) {
            $doc = db_fetch_one(
                "SELECT d.id, d.uploader_id
                 FROM documents d
                 JOIN class_subjects cs ON cs.id = d.class_subject_id
                 WHERE d.id = ? AND cs.class_id = ?
                 LIMIT 1",
                [$docId, $classId]
            );
        }
        if (!$doc && $classId > 0) {
            $doc = db_fetch_one(
                "SELECT d.id, d.uploader_id
                 FROM documents d
                 JOIN class_subjects cs ON cs.id = d.class_subject_id
                 WHERE d.id = ? AND cs.class_id = ?
                 LIMIT 1",
                [$docId, $classId]
            );
        }

        if (!$doc) {
            docs_json(404, ['ok' => false, 'error' => 'Không tìm thấy tài liệu']);
        }
        if ((int)($doc['uploader_id'] ?? 0) !== $userId) {
            docs_json(403, ['ok' => false, 'error' => 'Bạn không có quyền xóa tài liệu này']);
        }

        db_query('DELETE FROM documents WHERE id = ?', [$docId]);
        docs_json(200, ['ok' => true, 'message' => 'deleted']);
    }

    docs_json(400, ['ok' => false, 'error' => 'Action không hợp lệ']);
} catch (Exception $e) {
    error_log('api/bcs/documents error: ' . $e->getMessage());
    docs_json(500, ['ok' => false, 'error' => 'Lỗi hệ thống khi xử lý tài liệu']);
}
