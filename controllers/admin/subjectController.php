<?php
/**
 * CMS BDU - Subject Controller
 * Xử lý thêm/sửa môn học từ admin classes-subjects
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole(['admin', 'support_admin']);

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/classes-subjects.php');
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$subjectCode = trim($_POST['subject_code'] ?? '');
$subjectName = trim($_POST['subject_name'] ?? '');
$credits = (int) ($_POST['credits'] ?? 0);
$yearLevel = isset($_POST['year_level']) && $_POST['year_level'] !== '' ? (int) $_POST['year_level'] : null;
$semester = trim($_POST['semester'] ?? '');
$prerequisiteCode = trim($_POST['prerequisite_code'] ?? '');
$isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$openDate  = trim($_POST['open_date']  ?? '') ?: null;
$closeDate = trim($_POST['close_date'] ?? '') ?: null;
$academicYear = trim($_POST['academic_year'] ?? '') ?: null;

$today = date('Y-m-d');
if (empty($openDate)) {
    $isActive = 0;
} elseif ($openDate > $today) {
    $isActive = 0;
} elseif (!empty($closeDate) && $closeDate < $today) {
    $isActive = 0;
} else {
    $isActive = 1;
}

if (!in_array($action, ['save', 'delete', 'get_history', 'update_status'], true)) {
    redirect('../../views/admin/classes-subjects.php');
}

    if ($action === 'get_history') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    if ($subjectId <= 0) {
        jsonResponse(['ok' => false, 'message' => 'missing_params'], 400);
    }

    $history = db_fetch_all(
        "SELECT h.*,
                DATE(h.new_open_date)  AS new_open_date,
                DATE(h.new_close_date) AS new_close_date,
                DATE(h.old_open_date)  AS old_open_date,
                DATE(h.old_close_date) AS old_close_date,
                u.full_name AS changed_by_name
         FROM subject_status_history h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.subject_id = ?
         ORDER BY h.created_at DESC",
        [$subjectId]
    );

    jsonResponse(['ok' => true, 'history' => $history]);
}

if ($action === 'update_status') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $openDate  = trim($_POST['open_date']  ?? '') ?: null;
    $closeDate = trim($_POST['close_date'] ?? '') ?: null;
    $note      = trim($_POST['note'] ?? '') ?: null;

    $newStatus = isset($_POST['status']) ? (int) $_POST['status'] : null;
    if ($newStatus !== null) {
        $today = date('Y-m-d');
        if (empty($openDate)) {
            $newStatus = 0;
        } elseif ($openDate > $today) {
            $newStatus = 0;
        } elseif (!empty($closeDate) && $closeDate < $today) {
            $newStatus = 0;
        } else {
            $newStatus = 1;
        }
    }
    $currentUser = getCurrentUser();

    if ($subjectId <= 0) {
        jsonResponse(['ok' => false, 'message' => 'missing_params'], 400);
    }
    if ($newStatus === null) {
        jsonResponse(['ok' => false, 'message' => 'missing_status'], 400);
    }

    $subject = db_fetch_one('SELECT * FROM subjects WHERE id = ?', [$subjectId]);
    if (!$subject) {
        jsonResponse(['ok' => false, 'message' => 'not_found'], 404);
    }

    $oldStatus   = (int) $subject['is_active'];
    $oldOpenDate  = $subject['open_date'];
    $oldCloseDate = $subject['close_date'];
    $actionType = ($newStatus == $oldStatus && $openDate !== $oldOpenDate) ? 'schedule_change' : (($newStatus == 1) ? 'open' : 'close');

    db_query(
        'UPDATE subjects SET is_active = ?, open_date = ?, close_date = ? WHERE id = ?',
        [$newStatus, $openDate, $closeDate, $subjectId]
    );

    db_query(
        'INSERT INTO subject_status_history (subject_id, academic_year, semester, action_type, old_status, new_status, old_open_date, new_open_date, old_close_date, new_close_date, note, changed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$subjectId, $subject['academic_year'] ?? null, $subject['semester'] ?? null, $actionType, $oldStatus, $newStatus, $oldOpenDate, $openDate, $oldCloseDate, $closeDate, $note, $currentUser['id'] ?? null]
    );

    logSystem("Thay đổi trạng thái môn ID #$subjectId - " . ($newStatus == 1 ? 'Mở' : 'Đóng'), 'subjects', $subjectId);
    jsonResponse(['ok' => true, 'message' => 'updated']);
}

if ($action === 'save' && ($subjectCode === '' || $subjectName === '' || $credits <= 0)) {
    redirect('../../views/admin/classes-subjects.php?subject_error=missing_data&tab=subject');
}

// Xử lý prerequisite_id từ prerequisite_code
$prerequisiteId = null;
if ($prerequisiteCode !== '') {
    $prereq = db_fetch_one("SELECT id FROM subjects WHERE subject_code = ? LIMIT 1", [$prerequisiteCode]);
    if ($prereq) {
        $prerequisiteId = (int) $prereq['id'];
    }
}

// Helper: kiểm tra bảng subject_status_history có tồn tại không
function subjectHistoryTableExists(): bool {
    static $exists = null;
    if ($exists === null) {
        $row = db_fetch_one(
            "SELECT COUNT(*) AS total FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'subject_status_history'"
        );
        $exists = ((int) ($row['total'] ?? 0)) > 0;
    }
    return $exists;
}

// Helper: ghi lịch sử thay đổi trạng thái môn học (không ném lỗi ra ngoài)
function tryInsertSubjectHistory(int $subjectId, ?string $academicYear, ?string $semester,
                                  string $actionType,
                                  ?int $oldStatus, int $newStatus,
                                  ?string $oldOpen, ?string $newOpen,
                                  ?string $oldClose, ?string $newClose,
                                  string $note, ?int $changedBy): void {
    if (!subjectHistoryTableExists()) {
        return; // Bảng chưa được tạo — bỏ qua, không làm hỏng save
    }
    try {
        db_query(
            'INSERT INTO subject_status_history
             (subject_id, academic_year, semester, action_type,
              old_status, new_status,
              old_open_date, new_open_date,
              old_close_date, new_close_date,
              note, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$subjectId, $academicYear, $semester, $actionType,
             $oldStatus, $newStatus,
             $oldOpen, $newOpen,
             $oldClose, $newClose,
             $note, $changedBy]
        );
    } catch (Exception $histEx) {
        error_log('[subjectController] Lỗi ghi subject_status_history: ' . $histEx->getMessage());
    }
}

try {
    if ($action === 'delete') {
        if ($id > 0) {
            db_query('DELETE FROM subjects WHERE id = ?', [$id]);
            logSystem("Xóa môn học ID #$id - $subjectName", 'subjects', $id);
            redirect('../../views/admin/classes-subjects.php?subject_deleted=1&tab=subject');
        }
        redirect('../../views/admin/classes-subjects.php?subject_error=missing_id');
    }

    $currentUser = getCurrentUser();
    $currentUserId = (int) ($currentUser['id'] ?? 0) ?: null;

    if ($id > 0) {
        // ── Cập nhật môn học ──────────────────────────────────────────────
        $old = db_fetch_one('SELECT is_active, open_date, close_date FROM subjects WHERE id = ? LIMIT 1', [$id]);
        if (!$old) {
            redirect('../../views/admin/classes-subjects.php?subject_error=not_found&tab=subject');
        }
        $oldStatus    = (int) ($old['is_active']  ?? 0);
        $oldOpenDate  = $old['open_date']  ?? null;
        $oldCloseDate = $old['close_date'] ?? null;

        db_query(
            'UPDATE subjects
             SET subject_name = ?, credits = ?, year_level = ?, semester = ?,
                 prerequisite_id = ?, is_active = ?, open_date = ?, close_date = ?, academic_year = ?
             WHERE id = ?',
            [$subjectName, $credits, $yearLevel, $semester,
             $prerequisiteId, $isActive, $openDate, $closeDate, $academicYear, $id]
        );

        // Ghi lịch sử (tách biệt — không làm hỏng save nếu bảng chưa tồn tại)
        if ($isActive != $oldStatus || $openDate !== $oldOpenDate || $closeDate !== $oldCloseDate) {
            $actionType = ($isActive == $oldStatus)
                ? 'schedule_change'
                : ($isActive == 1 ? 'open' : 'close');
            tryInsertSubjectHistory(
                $id, $academicYear, $semester, $actionType,
                $oldStatus, $isActive,
                $oldOpenDate, $openDate,
                $oldCloseDate, $closeDate,
                'Cập nhật từ form môn học', $currentUserId
            );
        }

        logSystem("Cập nhật môn học ID #$id - $subjectName", 'subjects', $id);

    } else {
        // ── Thêm môn học mới ──────────────────────────────────────────────
        // Kiểm tra trùng subject_code trước khi INSERT
        $duplicate = db_fetch_one('SELECT id FROM subjects WHERE subject_code = ? LIMIT 1', [$subjectCode]);
        if ($duplicate) {
            redirect('../../views/admin/classes-subjects.php?subject_error=duplicate_code&tab=subject');
        }

        db_query(
            'INSERT INTO subjects
             (subject_code, subject_name, credits, year_level, semester,
              prerequisite_id, is_active, open_date, close_date, academic_year)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$subjectCode, $subjectName, $credits, $yearLevel, $semester,
             $prerequisiteId, $isActive, $openDate, $closeDate, $academicYear]
        );

        $created = db_fetch_one('SELECT id FROM subjects WHERE subject_code = ? LIMIT 1', [$subjectCode]);
        $newId   = (int) ($created['id'] ?? 0);

        // Ghi lịch sử tạo mới (tách biệt)
        if ($newId > 0) {
            tryInsertSubjectHistory(
                $newId, $academicYear, $semester, 'open',
                null, $isActive,
                null, $openDate,
                null, $closeDate,
                'Tạo môn học mới', $currentUserId
            );
        }

        logSystem("Tạo môn học mới - $subjectName ($subjectCode)", 'subjects', $newId);
    }

    redirect('../../views/admin/classes-subjects.php?subject_success=1&tab=subject');
} catch (Exception $e) {
    error_log('[subjectController] Lỗi lưu môn học: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine());
    redirect('../../views/admin/classes-subjects.php?subject_error=1');
}