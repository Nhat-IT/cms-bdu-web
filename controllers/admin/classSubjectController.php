<?php
/**
 * CMS BDU - Class Subject Controller
 * Xử lý thao tác đóng/mở lớp học phần và cập nhật lịch nhóm.
 */

// Disable output buffering issues - must be at very start
while (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Helper function to send JSON and exit
function sendDebugJson(array $data): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($data['code'] ?? 200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if session has user
$debugSession = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'roles' => $_SESSION['roles'] ?? null,
    'logged_in' => isLoggedIn(),
    'last_activity' => isset($_SESSION['last_activity']) ? (time() - $_SESSION['last_activity']) : 'no_activity',
    'timeout_check' => isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)
];

// If not logged in, return JSON error immediately
if (!isLoggedIn()) {
    error_log('DEBUG: User not logged in');
    sendDebugJson(['ok' => false, 'message' => 'not_logged_in', 'debug' => $debugSession, 'code' => 401]);
}

// Check role
if (!hasRole(['admin', 'support_admin'])) {
    error_log('DEBUG: User does not have required role');
    sendDebugJson(['ok' => false, 'message' => 'forbidden', 'debug' => $debugSession, 'code' => 403]);
}

// Chỉ kiểm tra POST cho các action cần bảo mật
$isGetAllowed = isset($_GET['action']) && in_array($_GET['action'], ['export_group_students', 'get_group_student_counts'], true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isGetAllowed) {
    sendDebugJson(['ok' => false, 'message' => 'method_not_allowed', 'GET' => $_GET, 'code' => 400]);
}

error_log('DEBUG: User passed auth');

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['action'] ?? '')
    : ($_GET['action'] ?? '');
$classSubjectId = (int) ($_POST['class_subject_id'] ?? 0);
$return = $_POST['return'] ?? 'assignments';
$returnPage = $return === 'home' ? 'home.php' : 'assignments.php';

function jsonResponse(array $payload, int $status = 200): void {
    // Clear any previous output
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respondOrRedirect(bool $ok, string $message, string $returnPage): void {
    // Luôn trả JSON nếu có format=json hoặc Accept header yêu cầu JSON
    $wantsJson = isset($_POST['format']) && $_POST['format'] === 'json';
    $acceptJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    
    if ($wantsJson || $acceptJson) {
        jsonResponse(['ok' => $ok, 'message' => $message], $ok ? 200 : 400);
        return;
    }

    $code = $ok ? 'ok' : 'error';
    redirect('../../views/admin/' . $returnPage . '?class_subject_' . $code . '=' . urlencode($message));
}

function dbTableExists(string $tableName): bool {
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }
    $row = db_fetch_one(
        'SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?',
        [$tableName]
    );
    $cache[$tableName] = ((int) ($row['total'] ?? 0)) > 0;
    return $cache[$tableName];
}

if ($classSubjectId <= 0 && $action !== 'add_group' && $action !== 'get_group_student_counts' && $action !== 'save_group_schedule' && $action !== 'export_group_students') {
    respondOrRedirect(false, 'missing_id', $returnPage);
}

// Action: lấy số SV mỗi nhóm (GET)
if ($action === 'get_group_student_counts') {
    $rows = db_fetch_all(
        'SELECT csg.class_subject_id, csg.group_code, COUNT(ssr.id) AS cnt
         FROM class_subject_groups csg
         LEFT JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
         GROUP BY csg.class_subject_id, csg.group_code'
    );
    $counts = [];
    foreach ($rows as $r) {
        $key = $r['class_subject_id'] . '|' . $r['group_code'];
        $counts[$key] = (int) $r['cnt'];
    }
    jsonResponse(['ok' => true, 'counts' => $counts]);
}

try {
    if ($action === 'toggle_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open', 'closed'], true)) {
            respondOrRedirect(false, 'invalid_status', $returnPage);
        }

        if ($status === 'closed') {
            db_query('UPDATE class_subjects SET end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id = ?', [$classSubjectId]);
            logSystem("Đóng lớp học phần ID #$classSubjectId", 'class_subjects', $classSubjectId);
            respondOrRedirect(true, 'closed', $returnPage);
        }

        db_query(
            'UPDATE class_subjects
             SET start_date = COALESCE(NULLIF(start_date, NULL), CURDATE()),
                 end_date = CASE WHEN end_date IS NULL OR end_date < CURDATE() THEN DATE_ADD(CURDATE(), INTERVAL 90 DAY) ELSE end_date END
             WHERE id = ?',
            [$classSubjectId]
        );
        logSystem("Mở lớp học phần ID #$classSubjectId", 'class_subjects', $classSubjectId);
        respondOrRedirect(true, 'opened', $returnPage);
    }

    if ($action === 'save_group_schedule') {
        error_log("=== save_group_schedule START ===");
        error_log("POST data: " . json_encode($_POST));
        
        $groupCodeRaw = $_POST['group_code_select'] ?? $_POST['group_code'] ?? '';
        $groupCode = trim((string) $groupCodeRaw);
        $teacherMain = (int) ($_POST['teacher_main_id'] ?? 0);
        $dayOfWeek = (int) ($_POST['day_of_week'] ?? 0);
        $startPeriod = (int) ($_POST['start_period'] ?? 0);
        $endPeriod = (int) ($_POST['end_period'] ?? 0);
        $room = trim($_POST['room'] ?? '');
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $classCode = trim((string) ($_POST['class_code'] ?? ''));
        $semesterName = trim((string) ($_POST['semester_name'] ?? ''));
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));

        error_log("Parsed: teacherMain=$teacherMain, dayOfWeek=$dayOfWeek, room=$room, classSubjectId=$classSubjectId");
        
        if ($groupCode === '' || $teacherMain <= 0 || $dayOfWeek < 2 || $dayOfWeek > 8 || $startPeriod < 1 || $endPeriod < $startPeriod || $room === '') {
            error_log("Validation failed");
            respondOrRedirect(false, 'invalid_schedule_data', $returnPage);
        }

        // Nếu chưa có class_subject_id (môn mới từ danh mục), tự tạo lớp học phần trước
        if ($classSubjectId <= 0) {
            if ($subjectId <= 0 || $classCode === '') {
                respondOrRedirect(false, 'missing_id', $returnPage);
            }

            $classRow = db_fetch_one('SELECT id FROM classes WHERE class_name = ? LIMIT 1', [$classCode]);
            if (!$classRow) {
                respondOrRedirect(false, 'class_not_found', $returnPage);
            }
            $classId = (int) $classRow['id'];

            $semesterRow = null;
            if ($semesterName !== '' && $academicYear !== '' && $semesterName !== 'all' && $academicYear !== 'all') {
                $semesterRow = db_fetch_one(
                    'SELECT id, start_date, end_date FROM semesters WHERE semester_name = ? AND academic_year = ? LIMIT 1',
                    [$semesterName, $academicYear]
                );
            }
            if (!$semesterRow) {
                $semesterRow = db_fetch_one(
                    'SELECT id, start_date, end_date
                     FROM semesters
                     WHERE CURDATE() BETWEEN start_date AND end_date
                     ORDER BY start_date DESC
                     LIMIT 1'
                );
            }
            if (!$semesterRow) {
                $semesterRow = db_fetch_one(
                    'SELECT id, start_date, end_date
                     FROM semesters
                     ORDER BY academic_year DESC, semester_name DESC
                     LIMIT 1'
                );
            }
            if (!$semesterRow) {
                respondOrRedirect(false, 'semester_not_found', $returnPage);
            }
            $semesterId = (int) $semesterRow['id'];

            $existingCs = db_fetch_one(
                'SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND semester_id = ? LIMIT 1',
                [$classId, $subjectId, $semesterId]
            );
            if ($existingCs) {
                $classSubjectId = (int) $existingCs['id'];
            } else {
                $insertTeacherId = $teacherMain > 0 ? $teacherMain : null;
                db_query(
                    'INSERT INTO class_subjects (semester_id, class_id, subject_id, teacher_id, start_date, end_date)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [
                        $semesterId,
                        $classId,
                        $subjectId,
                        $insertTeacherId,
                        $semesterRow['start_date'] ?? null,
                        $semesterRow['end_date'] ?? null
                    ]
                );
                $createdCs = db_fetch_one(
                    'SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND semester_id = ? ORDER BY id DESC LIMIT 1',
                    [$classId, $subjectId, $semesterId]
                );
                $classSubjectId = (int) ($createdCs['id'] ?? 0);
            }

            if ($classSubjectId <= 0) {
                respondOrRedirect(false, 'cannot_create_class_subject', $returnPage);
            }
        }

        // Chặn xếp lịch cho môn đã đóng
        $subjectStatus = db_fetch_one(
            'SELECT s.is_active, s.open_date, s.close_date
             FROM class_subjects cs
             JOIN subjects s ON s.id = cs.subject_id
             WHERE cs.id = ?
             LIMIT 1',
            [$classSubjectId]
        );
        if ($subjectStatus) {
            $today = date('Y-m-d');
            $isActive = (int)($subjectStatus['is_active'] ?? 0) === 1;
            $openDate = $subjectStatus['open_date'] ?? null;
            $closeDate = $subjectStatus['close_date'] ?? null;
            $isOpenByDate = !empty($openDate) && $openDate <= $today && (empty($closeDate) || $closeDate >= $today);
            if (!$isActive || !$isOpenByDate) {
                jsonResponse(['ok' => false, 'message' => 'subject_closed'], 400);
            }
        }

        $groupData = db_fetch_one(
            'SELECT id, sub_teacher_id, main_teacher_id
             FROM class_subject_groups
             WHERE class_subject_id = ?
               AND group_code = ?
               AND (is_extra = 0 OR is_extra IS NULL)
             LIMIT 1',
            [$classSubjectId, $groupCode]
        );
        $isUpdate = (bool) $groupData;
        $syncMainTeacher = isset($_POST['sync_main_teacher']) ? ((int) $_POST['sync_main_teacher'] === 1) : !$isUpdate;

        // Helper: tên thứ tiếng Việt
        $dayNames = [2=>'Thứ 2', 3=>'Thứ 3', 4=>'Thứ 4', 5=>'Thứ 5', 6=>'Thứ 6', 7=>'Thứ 7', 8=>'Chủ nhật'];
        $newDayName = $dayNames[$dayOfWeek] ?? "Thứ $dayOfWeek";

        // ── Kiểm tra xung đột phòng học ──
        $roomConflict = db_fetch_one(
            'SELECT csg.group_code, csg.day_of_week, csg.start_period, csg.end_period,
                    c.class_name, s.subject_name,
                    COALESCE(r.room_name, csg.room) AS room_display
             FROM class_subject_groups csg
             JOIN class_subjects cs ON cs.id = csg.class_subject_id
             JOIN classes c ON c.id = cs.class_id
             JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN rooms r ON r.room_code = csg.room
             WHERE csg.room = ?
               AND csg.day_of_week = ?
               AND csg.start_period <= ?
               AND csg.end_period >= ?
               AND NOT (csg.class_subject_id = ? AND csg.group_code = ?)
             LIMIT 1',
            [$room, $dayOfWeek, $endPeriod, $startPeriod, $classSubjectId, $groupCode]
        );
        if ($roomConflict) {
            $roomRow = db_fetch_one('SELECT COALESCE(room_name, room_code) AS name FROM rooms WHERE room_code = ? LIMIT 1', [$room]);
            $roomDisplay = $roomRow ? $roomRow['name'] : $room;
            $cDay  = $dayNames[$roomConflict['day_of_week']] ?? "Thứ {$roomConflict['day_of_week']}";
            $cSlot = "Tiết {$roomConflict['start_period']}–{$roomConflict['end_period']}";
            jsonResponse([
                'ok'      => false,
                'message' => 'conflict_room',
                'detail'  => "⚠️ Xung đột phòng học!\n"
                           . "Phòng «{$roomDisplay}» vào {$newDayName}, {$cSlot} "
                           . "đã được sử dụng bởi nhóm {$roomConflict['class_name']}-{$roomConflict['group_code']} "
                           . "(môn {$roomConflict['subject_name']})."
            ], 409);
        }

        // ── Kiểm tra xung đột giảng viên ──
        $teacherConflict = db_fetch_one(
            'SELECT csg.group_code, csg.day_of_week, csg.start_period, csg.end_period,
                    c.class_name, s.subject_name,
                    COALESCE(r.room_name, csg.room) AS room_display
             FROM class_subject_groups csg
             JOIN class_subjects cs ON cs.id = csg.class_subject_id
             JOIN classes c ON c.id = cs.class_id
             JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN rooms r ON r.room_code = csg.room
             WHERE (csg.main_teacher_id = ? OR cs.teacher_id = ? OR csg.sub_teacher_id = ?)
               AND csg.day_of_week = ?
               AND csg.start_period <= ?
               AND csg.end_period >= ?
               AND csg.room IS NOT NULL
               AND NOT (csg.class_subject_id = ? AND csg.group_code = ?)
             LIMIT 1',
            [$teacherMain, $teacherMain, $teacherMain, $dayOfWeek, $endPeriod, $startPeriod, $classSubjectId, $groupCode]
        );
        if ($teacherConflict) {
            $tInfo = db_fetch_one('SELECT full_name, academic_title FROM users WHERE id = ? LIMIT 1', [$teacherMain]);
            $tName = $tInfo ? (($tInfo['academic_title'] ? $tInfo['academic_title'] . '. ' : '') . $tInfo['full_name']) : "GV #$teacherMain";
            $cDay  = $dayNames[$teacherConflict['day_of_week']] ?? "Thứ {$teacherConflict['day_of_week']}";
            $cSlot = "Tiết {$teacherConflict['start_period']}–{$teacherConflict['end_period']}";
            jsonResponse([
                'ok'      => false,
                'message' => 'conflict_teacher',
                'detail'  => "⚠️ Xung đột lịch giảng viên!\n"
                           . "{$tName} vào {$newDayName}, {$cSlot} "
                           . "đã được phân công dạy nhóm {$teacherConflict['class_name']}-{$teacherConflict['group_code']} "
                           . "(môn {$teacherConflict['subject_name']}"
                           . ($teacherConflict['room_display'] ? ", phòng {$teacherConflict['room_display']}" : '')
                           . ")."
            ], 409);
        }

        $teacherSubRaw = $_POST['teacher_sub_id'] ?? null;
        // Handle string "null", empty string, or 0 as NULL
        if ($teacherSubRaw === null || $teacherSubRaw === '' || $teacherSubRaw === 'null' || $teacherSubRaw === '0') {
            $teacherSub = null;
        } else {
            $teacherSubInt = (int) $teacherSubRaw;
            $teacherSub = $teacherSubInt > 0 ? $teacherSubInt : null;
        }

        // Validation: Giảng viên không thể vừa là chính vừa là trợ giảng
        if ($teacherMain > 0 && $teacherSub > 0 && $teacherMain === $teacherSub) {
            jsonResponse([
                'ok' => false,
                'message' => 'same_teacher_roles',
                'detail' => 'Giảng viên không thể vừa là giảng viên chính vừa là trợ giảng cho cùng một nhóm.'
            ], 400);
        }

        if ($isUpdate) {
            $updateSql = 'UPDATE class_subject_groups
                 SET room = ?, day_of_week = ?, start_period = ?, end_period = ?, sub_teacher_id = ?, main_teacher_id = ?
                 WHERE id = ?';
            $updateParams = [$room, $dayOfWeek, $startPeriod, $endPeriod, $teacherSub, $teacherMain > 0 ? $teacherMain : null, (int) $groupData['id']];
            $affected = db_query($updateSql, $updateParams);
            error_log("DB_UPDATE_GROUP affected rows: " . $affected);
        } else {
            $insertSql = 'INSERT INTO class_subject_groups (class_subject_id, group_code, room, day_of_week, start_period, end_period, sub_teacher_id, main_teacher_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $insertParams = [$classSubjectId, $groupCode, $room, $dayOfWeek, $startPeriod, $endPeriod, $teacherSub, $teacherMain > 0 ? $teacherMain : null];
            $affected = db_query($insertSql, $insertParams);
            error_log("DB_INSERT_GROUP affected rows: " . $affected);
        }

        if ($syncMainTeacher && $teacherMain > 0) {
            db_query('UPDATE class_subjects SET teacher_id = ? WHERE id = ?', [$teacherMain, $classSubjectId]);
        }
        $logMsg = $isUpdate ? "Cập nhật" : "Tạo";
        logSystem("$logMsg lịch nhóm $groupCode - lớp học phần ID #$classSubjectId", 'class_subject_groups', $isUpdate ? (int) $groupData['id'] : null);
        error_log("=== save_group_schedule SUCCESS ===");
        
        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            jsonResponse([
                'ok' => true,
                'message' => 'schedule_saved',
                'class_subject_id' => $classSubjectId,
                'group_code' => $groupCode,
                'day_of_week' => (string)$dayOfWeek,
                'start_period' => (string)$startPeriod,
                'end_period' => (string)$endPeriod,
                'room' => $room,
                'teacher_main_id' => (string)$teacherMain,
                'teacher_sub_id' => $teacherSub !== null ? (string)$teacherSub : null
            ]);
        }
        respondOrRedirect(true, 'schedule_saved', $returnPage);
    }

    if ($action === 'clear_group_schedule') {
        $groupCode = trim($_POST['group_code'] ?? '');
        if ($classSubjectId <= 0 || $groupCode === '') {
            respondOrRedirect(false, 'invalid_group', $returnPage);
        }

        db_query(
            'UPDATE class_subject_groups
             SET room = NULL, day_of_week = NULL, start_period = NULL, end_period = NULL, sub_teacher_id = NULL
             WHERE class_subject_id = ? AND group_code = ?
               AND (is_extra = 0 OR is_extra IS NULL)',
            [$classSubjectId, $groupCode]
        );
        respondOrRedirect(true, 'schedule_cleared', $returnPage);
    }

    if ($action === 'save_extra_class') {
        // Debug: log all POST data
        error_log("DEBUG save_extra_class POST: " . json_encode($_POST));

        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0 && isset($_POST['class_subject_id'], $_POST['group_code'])) {
            $groupData = db_fetch_one(
                'SELECT id
                 FROM class_subject_groups
                 WHERE class_subject_id = ?
                   AND group_code = ?
                   AND (is_extra = 0 OR is_extra IS NULL)
                 LIMIT 1',
                [$_POST['class_subject_id'], $_POST['group_code']]
            );
            if ($groupData) {
                $groupId = (int) $groupData['id'];
            }
        }
        $extraDate = trim($_POST['extra_date'] ?? '');
        $extraDay = (int) ($_POST['extra_day_of_week'] ?? 0);
        $extraStart = (int) ($_POST['extra_start_period'] ?? 0);
        $extraEnd = (int) ($_POST['extra_end_period'] ?? 0);
        $extraRoom = trim($_POST['extra_room'] ?? '');
        $extraNote = trim($_POST['extra_note'] ?? '');
        $isRegular = isset($_POST['extra_is_regular']) ? 1 : 0;

        // Debug log chi tiết
        error_log("DEBUG save_extra_class values: groupId=$groupId, date='$extraDate', day=$extraDay, start=$extraStart, end=$extraEnd, room='$extraRoom'");

        // Validate
        $validationErrors = [];
        if ($groupId <= 0) $validationErrors[] = 'groupId_invalid';
        if ($extraDate === '') $validationErrors[] = 'date_empty';
        if ($extraDay < 2 || $extraDay > 8) $validationErrors[] = 'day_invalid';
        if ($extraStart < 1) $validationErrors[] = 'start_invalid';
        if ($extraEnd < $extraStart) $validationErrors[] = 'end_before_start';
        if ($extraRoom === '') $validationErrors[] = 'room_empty';

        if (!empty($validationErrors)) {
            error_log("DEBUG save_extra_class validation FAILED: " . implode(', ', $validationErrors));
            // Trả về chi tiết lỗi trong response
            if (isset($_POST['format']) && $_POST['format'] === 'json') {
                jsonResponse([
                    'ok' => false,
                    'message' => 'invalid_schedule_data',
                    'errors' => $validationErrors,
                    'debug' => [
                        'groupId' => $groupId,
                        'date' => $extraDate,
                        'day' => $extraDay,
                        'start' => $extraStart,
                        'end' => $extraEnd,
                        'room' => $extraRoom
                    ]
                ], 400);
            }
            respondOrRedirect(false, 'invalid_schedule_data: ' . implode(', ', $validationErrors), $returnPage);
        }

        // Kiểm tra xem đã có buổi học bù cho ngày này chưa (trong class_subject_groups)
        $groupCode = $_POST['group_code'] ?? '';
        $existingExtra = null;
        if ($isRegular === 1) {
            $existingExtra = db_fetch_one(
                'SELECT id FROM class_subject_groups
                 WHERE class_subject_id = (SELECT class_subject_id FROM class_subject_groups WHERE id = ?)
                 AND group_code = ? AND is_extra = 1 AND extra_date = ? LIMIT 1',
                [$groupId, $groupCode, $extraDate]
            );
        }

        if ($existingExtra) {
            // UPDATE buổi học bù thay thế lịch cố định
            db_query(
                'UPDATE class_subject_groups
                 SET day_of_week = ?, start_period = ?, end_period = ?, room = ?, note = ?
                 WHERE id = ?',
                [$extraDay, $extraStart, $extraEnd, $extraRoom, $extraNote !== '' ? $extraNote : null, $existingExtra['id']]
            );
            $extraId = (int) $existingExtra['id'];
            logSystem("Cập nhật buổi học bù ID #$extraId cho nhóm ID #$groupId", 'class_subject_groups', $extraId);
            if (isset($_POST['format']) && $_POST['format'] === 'json') {
                jsonResponse([
                    'ok' => true,
                    'message' => 'extra_class_updated',
                    'extra_id' => $extraId,
                    'extra_date' => $extraDate,
                    'day_of_week' => (string) $extraDay,
                    'start_period' => (string) $extraStart,
                    'end_period' => (string) $extraEnd,
                    'room' => $extraRoom,
                    'is_regular' => 1
                ]);
            }
            respondOrRedirect(true, 'extra_class_updated', $returnPage);
        }

        // INSERT buổi học bù mới vào class_subject_groups
        db_query(
            'INSERT INTO class_subject_groups (class_subject_id, group_code, room, day_of_week, start_period, end_period, note, is_extra, extra_date)
             SELECT class_subject_id, ?, ?, ?, ?, ?, ?, 1, ?
             FROM class_subject_groups WHERE id = ?',
            [$groupCode, $extraRoom, $extraDay, $extraStart, $extraEnd, $extraNote !== '' ? $extraNote : null, $extraDate, $groupId]
        );
        $createdExtra = db_fetch_one(
            'SELECT id FROM class_subject_groups
             WHERE class_subject_id = (SELECT class_subject_id FROM class_subject_groups WHERE id = ?)
             AND group_code = ? AND is_extra = 1 AND extra_date = ?
             ORDER BY id DESC LIMIT 1',
            [$groupId, $groupCode, $extraDate]
        );
        $extraId = (int) ($createdExtra['id'] ?? 0);
        logSystem("Thêm buổi học bù cho nhóm ID #$groupId", 'class_subject_groups', $extraId > 0 ? $extraId : null);
        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            jsonResponse([
                'ok' => true,
                'message' => 'extra_class_added',
                'extra_id' => $extraId > 0 ? $extraId : null,
                'extra_date' => $extraDate,
                'day_of_week' => (string) $extraDay,
                'start_period' => (string) $extraStart,
                'end_period' => (string) $extraEnd,
                'room' => $extraRoom,
                'is_regular' => $isRegular
            ]);
        }
        respondOrRedirect(true, 'extra_class_added', $returnPage);
    }

    if ($action === 'add_group') {
        $classSubjectId = (int) ($_POST['class_subject_id'] ?? 0);
        if ($classSubjectId <= 0) {
            if (isset($_POST['format']) && $_POST['format'] === 'json') {
                jsonResponse(['ok' => false, 'message' => 'missing_id'], 400);
            }
            respondOrRedirect(false, 'missing_id', $returnPage);
        }

        $codes = db_fetch_all(
            'SELECT group_code
             FROM class_subject_groups
             WHERE class_subject_id = ?
               AND (is_extra = 0 OR is_extra IS NULL)',
            [$classSubjectId]
        );
        $maxNumber = 0;
        foreach ($codes as $codeRow) {
            $code = (string) ($codeRow['group_code'] ?? '');
            if (preg_match('/^N(\d+)$/i', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $newCode = 'N' . ($maxNumber + 1);
        db_query('INSERT INTO class_subject_groups (class_subject_id, group_code) VALUES (?, ?)', [$classSubjectId, $newCode]);
        $created = db_fetch_one('SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1', [$classSubjectId, $newCode]);
        $newGroupId = (int) ($created['id'] ?? 0);
        logSystem("Thêm nhóm $newCode vào lớp học phần ID #$classSubjectId", 'class_subject_groups', $newGroupId);
        
        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            jsonResponse([
                'ok' => true,
                'message' => 'group_added',
                'group_code' => $newCode,
                'group_id' => $newGroupId,
                'class_subject_id' => $classSubjectId
            ]);
        }
        respondOrRedirect(true, 'group_added', $returnPage);
    }

    if ($action === 'delete_group') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $groupCode = trim($_POST['group_code'] ?? '');

        if ($classSubjectId <= 0 || ($groupId <= 0 && $groupCode === '')) {
            respondOrRedirect(false, 'invalid_group', $returnPage);
        }

        $targetGroup = null;
        if ($groupId > 0) {
            $targetGroup = db_fetch_one(
                'SELECT id, group_code, class_subject_id
                 FROM class_subject_groups
                 WHERE id = ? AND class_subject_id = ?
                   AND (is_extra = 0 OR is_extra IS NULL)
                 LIMIT 1',
                [$groupId, $classSubjectId]
            );
        } else {
            $targetGroup = db_fetch_one(
                'SELECT id, group_code, class_subject_id
                 FROM class_subject_groups
                 WHERE class_subject_id = ? AND group_code = ?
                   AND (is_extra = 0 OR is_extra IS NULL)
                 LIMIT 1',
                [$classSubjectId, $groupCode]
            );
        }

        if (!$targetGroup) {
            jsonResponse(['ok' => false, 'message' => 'group_not_found'], 404);
        }

        $targetGroupId = (int) ($targetGroup['id'] ?? 0);
        $targetGroupCode = (string) ($targetGroup['group_code'] ?? $groupCode);

        // Không cho phép xóa nhóm đầu tiên mặc định N1
        if (strtoupper($targetGroupCode) === 'N1') {
            jsonResponse(['ok' => false, 'message' => 'cannot_delete_default_group'], 400);
        }

        $deletedStudentCount = 0;
        // Đếm sinh viên từ cả hai bảng group_students và student_subject_registration
        if (dbTableExists('group_students')) {
            $cnt = db_fetch_one('SELECT COUNT(*) AS total FROM group_students WHERE class_subject_group_id = ?', [$targetGroupId]);
            $deletedStudentCount = (int) ($cnt['total'] ?? 0);
        }
        if (dbTableExists('student_subject_registration')) {
            $cnt2 = db_fetch_one('SELECT COUNT(*) AS total FROM student_subject_registration WHERE class_subject_group_id = ?', [$targetGroupId]);
            $deletedStudentCount += (int) ($cnt2['total'] ?? 0);
        }

        $dbConn = getDBConnection();
        if (!$dbConn) {
            throw new Exception('database_connection_unavailable');
        }
        mysqli_begin_transaction($dbConn);
        try {
            // Xóa thủ công dữ liệu phụ thuộc để đảm bảo hoạt động cả khi DB thiếu FK CASCADE.
            if (dbTableExists('attendance_evidences') && dbTableExists('attendance_records') && dbTableExists('attendance_sessions')) {
                db_query(
                    'DELETE ae
                     FROM attendance_evidences ae
                     JOIN attendance_records ar ON ar.id = ae.attendance_record_id
                     JOIN attendance_sessions aps ON aps.id = ar.session_id
                     WHERE aps.class_subject_group_id = ?',
                    [$targetGroupId]
                );
            }
            if (dbTableExists('attendance_records') && dbTableExists('attendance_sessions')) {
                db_query(
                    'DELETE ar
                     FROM attendance_records ar
                     JOIN attendance_sessions aps ON aps.id = ar.session_id
                     WHERE aps.class_subject_group_id = ?',
                    [$targetGroupId]
                );
            }
            if (dbTableExists('attendance_sessions')) {
                db_query('DELETE FROM attendance_sessions WHERE class_subject_group_id = ?', [$targetGroupId]);
            }
            // Xóa luôn các bản ghi lịch học bù/ghi đè của cùng nhóm
            db_query(
                'DELETE FROM class_subject_groups
                 WHERE class_subject_id = ?
                   AND group_code = ?
                   AND is_extra = 1',
                [$classSubjectId, $targetGroupCode]
            );
            if (dbTableExists('student_subject_registration')) {
                db_query('DELETE FROM student_subject_registration WHERE class_subject_group_id = ?', [$targetGroupId]);
            }
            if (dbTableExists('grades')) {
                db_query('DELETE FROM grades WHERE class_subject_group_id = ?', [$targetGroupId]);
            }
            if (dbTableExists('group_students')) {
                db_query('DELETE FROM group_students WHERE class_subject_group_id = ?', [$targetGroupId]);
                $remainingGroupStudents = db_fetch_one(
                    'SELECT COUNT(*) AS total FROM group_students WHERE class_subject_group_id = ?',
                    [$targetGroupId]
                );
                if ((int) ($remainingGroupStudents['total'] ?? 0) > 0) {
                    throw new Exception("cannot_delete_group_students_for_group_$targetGroupId");
                }
            }

            db_query('DELETE FROM class_subject_groups WHERE id = ?', [$targetGroupId]);
            $stillExists = db_fetch_one(
                'SELECT id FROM class_subject_groups WHERE id = ? LIMIT 1',
                [$targetGroupId]
            );
            if ($stillExists) {
                throw new Exception("cannot_delete_group_record_$targetGroupId");
            }

            mysqli_commit($dbConn);
        } catch (Exception $inner) {
            mysqli_rollback($dbConn);
            throw $inner;
        }

        logSystem("Xóa nhóm $targetGroupCode (ID #$targetGroupId) khỏi lớp học phần ID #$classSubjectId; xóa {$deletedStudentCount} sinh viên thuộc nhóm", 'class_subject_groups', $targetGroupId);

        if (isset($_POST['format']) && $_POST['format'] === 'json') {
            jsonResponse([
                'ok' => true,
                'message' => 'group_deleted',
                'group_id' => $targetGroupId,
                'group_code' => $targetGroupCode,
                'deleted_student_count' => $deletedStudentCount
            ]);
        }

        respondOrRedirect(true, 'group_deleted', $returnPage);
    }

    if ($action === 'get_group_students') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $groupCode = trim($_POST['group_code'] ?? '');
        $csId = (int) ($_POST['class_subject_id'] ?? 0);

        if ($csId <= 0 && $groupId <= 0) {
            jsonResponse(['ok' => false, 'message' => 'missing_params'], 400);
        }

        $targetGroupId = $groupId;
        if ($targetGroupId <= 0 && $csId > 0) {
            $g = db_fetch_one(
                'SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1',
                [$csId, $groupCode ?: 'N1']
            );
            $targetGroupId = (int) ($g['id'] ?? 0);
        }

        if ($targetGroupId <= 0) {
            jsonResponse(['ok' => true, 'students' => [], 'message' => 'no_group_found']);
        }

        $students = db_fetch_all(
            'SELECT
                COALESCE(u.username, ssr.mssv) AS username,
                COALESCE(u.full_name, ssr.full_name) AS full_name,
                COALESCE(CAST(u.birth_date AS CHAR), ssr.birth_date) AS birth_date,
                COALESCE(NULLIF(ssr.class_name, \'\'), c.class_name) AS class_name,
                ssr.status
             FROM student_subject_registration ssr
             LEFT JOIN users u ON u.id = ssr.student_id AND ssr.student_id > 0
             LEFT JOIN class_subject_groups csg_j ON csg_j.id = ssr.class_subject_group_id
             LEFT JOIN class_subjects csj ON csj.id = csg_j.class_subject_id
             LEFT JOIN classes c ON c.id = csj.class_id
             WHERE ssr.class_subject_group_id = ?
             ORDER BY COALESCE(u.full_name, ssr.full_name) ASC',
            [$targetGroupId]
        );
        jsonResponse(['ok' => true, 'students' => $students]);
    }

    if ($action === 'import_group_students') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $groupCode = trim($_POST['group_code'] ?? '');
        $csId = (int) ($_POST['class_subject_id'] ?? 0);
        $studentsJson = $_POST['students'] ?? '[]';

        if ($csId <= 0 && $groupId <= 0) {
            jsonResponse(['ok' => false, 'message' => 'missing_params'], 400);
        }

        $targetGroupId = $groupId;
        if ($targetGroupId <= 0 && $csId > 0) {
            $g = db_fetch_one(
                'SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1',
                [$csId, $groupCode ?: 'N1']
            );
            $targetGroupId = (int) ($g['id'] ?? 0);
        }

        if ($targetGroupId <= 0) {
            jsonResponse(['ok' => false, 'message' => 'no_group_found'], 404);
        }

        $students = json_decode($studentsJson, true);
        if (!is_array($students)) {
            jsonResponse(['ok' => false, 'message' => 'invalid_data'], 400);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($students as $i => $s) {
            $mssv = trim((string) ($s['mssv'] ?? ''));
            $name = trim((string) ($s['name'] ?? ''));
            $dob = trim((string) ($s['dob'] ?? ''));
            $className = trim((string) ($s['className'] ?? $s['class'] ?? ''));

            if ($mssv === '' || $name === '') {
                $skipped++;
                continue;
            }

            // Tìm tài khoản theo MSSV (username), không bắt buộc phải có
            $studentId = null;
            $user = db_fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', [$mssv]);
            if ($user) {
                $studentId = (int) $user['id'];
            }

            // Kiểm tra trùng: ưu tiên student_id nếu có tài khoản, nếu không thì kiểm tra mssv
            if ($studentId) {
                $existing = db_fetch_one(
                    'SELECT id FROM student_subject_registration WHERE class_subject_group_id = ? AND student_id = ? LIMIT 1',
                    [$targetGroupId, $studentId]
                );
            } else {
                $existing = db_fetch_one(
                    'SELECT id FROM student_subject_registration WHERE class_subject_group_id = ? AND mssv = ? LIMIT 1',
                    [$targetGroupId, $mssv]
                );
            }
            if ($existing) {
                $skipped++;
                continue;
            }

            try {
                db_query(
                    'INSERT INTO student_subject_registration
                        (class_subject_group_id, student_id, mssv, full_name, birth_date, class_name, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$targetGroupId, $studentId, $mssv, $name, $dob !== '' ? $dob : null, $className, 'Đang học']
                );
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Row $i ($mssv): " . $e->getMessage();
                $skipped++;
            }
        }

        logSystem("Import $imported sinh viên vào nhóm ID #$targetGroupId", 'student_subject_registration', $targetGroupId);
        jsonResponse([
            'ok' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10)
        ]);
    }

    if ($action === 'export_group_students') {
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $groupCode = trim($_GET['group_code'] ?? '');
        $csId = (int) ($_GET['class_subject_id'] ?? 0);

        error_log("export_group_students: csId=$csId, groupCode=$groupCode, groupId=$groupId");

        // Debug: Log all params
        error_log("export_group_students params: " . json_encode($_GET));

        try {
            // Query tất cả sinh viên của class_subject (tất cả các nhóm)
            $students = [];

            if ($csId > 0 && $groupCode === '') {
                // Export tất cả sinh viên của class_subject (tất cả các nhóm)
                $students = db_fetch_all(
                    'SELECT DISTINCT
                        COALESCE(u.username, ssr.mssv) AS username,
                        COALESCE(u.full_name, ssr.full_name) AS full_name,
                        COALESCE(CAST(u.birth_date AS CHAR), ssr.birth_date) AS birth_date,
                        COALESCE(NULLIF(ssr.class_name, \'\'), c.class_name) AS class_name,
                        ssr.status,
                        csg.group_code
                     FROM student_subject_registration ssr
                     LEFT JOIN users u ON u.id = ssr.student_id AND ssr.student_id > 0
                     LEFT JOIN class_subject_groups csg ON csg.id = ssr.class_subject_group_id
                     LEFT JOIN class_subjects csj ON csj.id = csg.class_subject_id
                     LEFT JOIN classes c ON c.id = csj.class_id
                     WHERE csg.class_subject_id = ?
                     ORDER BY csg.group_code ASC, COALESCE(u.full_name, ssr.full_name) ASC',
                    [$csId]
                );
                jsonResponse(['ok' => true, 'students' => $students, 'allGroups' => true]);
            } elseif ($csId > 0 && $groupCode !== '') {
                // Export sinh viên của một nhóm cụ thể
                $g = db_fetch_one(
                    'SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1',
                    [$csId, $groupCode]
                );
                $targetGroupId = (int) ($g['id'] ?? 0);

                if ($targetGroupId <= 0) {
                    // Nhóm không tồn tại trong DB — trả lỗi rõ ràng thay vì danh sách rỗng
                    jsonResponse(['ok' => false, 'message' => 'group_not_found', 'groupCode' => $groupCode], 404);
                }

                if ($targetGroupId > 0) {
                    $students = db_fetch_all(
                        'SELECT
                            COALESCE(u.username, ssr.mssv) AS username,
                            COALESCE(u.full_name, ssr.full_name) AS full_name,
                            COALESCE(CAST(u.birth_date AS CHAR), ssr.birth_date) AS birth_date,
                            COALESCE(NULLIF(ssr.class_name, \'\'), c.class_name) AS class_name,
                            ssr.status,
                            csg.group_code
                         FROM student_subject_registration ssr
                         LEFT JOIN users u ON u.id = ssr.student_id AND ssr.student_id > 0
                         LEFT JOIN class_subject_groups csg ON csg.id = ssr.class_subject_group_id
                         LEFT JOIN class_subjects csj ON csj.id = csg.class_subject_id
                         LEFT JOIN classes c ON c.id = csj.class_id
                         WHERE ssr.class_subject_group_id = ?
                         ORDER BY COALESCE(u.full_name, ssr.full_name) ASC',
                        [$targetGroupId]
                    );
                }
                jsonResponse(['ok' => true, 'students' => $students, 'groupCode' => $groupCode]);
            } elseif ($groupId > 0) {
                // Export theo group_id trực tiếp (legacy support)
                $students = db_fetch_all(
                    'SELECT
                        COALESCE(u.username, ssr.mssv) AS username,
                        COALESCE(u.full_name, ssr.full_name) AS full_name,
                        COALESCE(CAST(u.birth_date AS CHAR), ssr.birth_date) AS birth_date,
                        COALESCE(NULLIF(ssr.class_name, \'\'), c.class_name) AS class_name,
                        ssr.status,
                        csg.group_code
                     FROM student_subject_registration ssr
                     LEFT JOIN users u ON u.id = ssr.student_id AND ssr.student_id > 0
                     LEFT JOIN class_subject_groups csg ON csg.id = ssr.class_subject_group_id
                     LEFT JOIN class_subjects csj ON csj.id = csg.class_subject_id
                     LEFT JOIN classes c ON c.id = csj.class_id
                     WHERE ssr.class_subject_group_id = ?
                     ORDER BY COALESCE(u.full_name, ssr.full_name) ASC',
                    [$groupId]
                );
                jsonResponse(['ok' => true, 'students' => $students]);
            } else {
                jsonResponse(['ok' => false, 'message' => 'missing_params'], 400);
            }
        } catch (Exception $e) {
            error_log('export_group_students error: ' . $e->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Lỗi khi tải danh sách: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'download_group_students_xlsx') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $groupCode = trim($_POST['group_code'] ?? '');
        $csId = (int) ($_POST['class_subject_id'] ?? 0);
        $targetGroupId = $groupId;

        if ($targetGroupId <= 0 && $csId > 0) {
            $g = db_fetch_one(
                'SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1',
                [$csId, $groupCode ?: 'N1']
            );
            $targetGroupId = (int) ($g['id'] ?? 0);
        }

        if ($targetGroupId <= 0) {
            jsonResponse(['ok' => false, 'message' => 'no_group_found'], 404);
        }

        $students = db_fetch_all(
            'SELECT
                COALESCE(u.username, ssr.mssv) AS username,
                COALESCE(u.full_name, ssr.full_name) AS full_name,
                COALESCE(CAST(u.birth_date AS CHAR), ssr.birth_date) AS birth_date,
                COALESCE(NULLIF(ssr.class_name, \'\'), c.class_name) AS class_name,
                ssr.status
             FROM student_subject_registration ssr
             LEFT JOIN users u ON u.id = ssr.student_id AND ssr.student_id > 0
             LEFT JOIN class_subject_groups csg_j ON csg_j.id = ssr.class_subject_group_id
             LEFT JOIN class_subjects csj ON csj.id = csg_j.class_subject_id
             LEFT JOIN classes c ON c.id = csj.class_id
             WHERE ssr.class_subject_group_id = ?
             ORDER BY COALESCE(u.full_name, ssr.full_name) ASC',
            [$targetGroupId]
        );

        $rows = [['STT', 'MSSV', 'Ho va ten', 'Ngay sinh', 'Lop']];
        $stt = 1;
        foreach ($students as $s) {
            $mssv = $s['username'] ?? '';
            $fullName = $s['full_name'] ?? '';
            $birthDate = $s['birth_date'] ?? '';
            if ($birthDate !== '' && preg_match('/^\d+$/', $birthDate)) {
                $serial = (int) $birthDate;
                $d = new DateTime('1899-12-30');
                $d->add(new DateInterval('P' . $serial . 'D'));
                $birthDate = $d->format('d/m/Y');
            }
            $rows[] = [$stt++, $mssv, $fullName, $birthDate, $s['class_name'] ?? ''];
        }

        $sheetName = 'Danh sach SV';
        $wb = [['sheetName' => $sheetName, 'data' => $rows]];
        jsonResponse(['ok' => true, 'workbook' => $wb, 'filename' => 'DanhSachSV_Nhom' . ($groupCode ?: $targetGroupId)]);
    }

    if ($action === 'delete') {
        $classSubjectId = (int) ($_POST['class_subject_id'] ?? 0);
        if ($classSubjectId > 0) {
            db_query('DELETE FROM class_subjects WHERE id = ?', [$classSubjectId]);
            logSystem("Xóa phân công giảng dạy ID #$classSubjectId", 'class_subjects', $classSubjectId);
            respondOrRedirect(true, 'deleted', $returnPage);
        }
        respondOrRedirect(false, 'missing_id', $returnPage);
    }

    if ($action === 'create') {
        $classId = (int) ($_POST['class_id'] ?? 0);
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $semesterId = (int) ($_POST['semester_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);

        if ($classId <= 0 && $subjectId <= 0 && $semesterId <= 0) {
            respondOrRedirect(false, 'missing_all_fields', $returnPage);
        }
        if ($classId <= 0) {
            respondOrRedirect(false, 'missing_class', $returnPage);
        }
        if ($subjectId <= 0) {
            respondOrRedirect(false, 'missing_subject', $returnPage);
        }
        if ($semesterId <= 0) {
            respondOrRedirect(false, 'missing_semester', $returnPage);
        }

        $existing = db_fetch_one(
            'SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND semester_id = ? LIMIT 1',
            [$classId, $subjectId, $semesterId]
        );

        if ($existing) {
            respondOrRedirect(false, 'already_exists', $returnPage);
        }

        db_query(
            'INSERT INTO class_subjects (class_id, subject_id, semester_id, teacher_id, start_date, end_date) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY))',
            [$classId, $subjectId, $semesterId, $teacherId > 0 ? $teacherId : null]
        );

        $created = db_fetch_one('SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND semester_id = ? LIMIT 1', [$classId, $subjectId, $semesterId]);
        $newId = (int) ($created['id'] ?? 0);
        logSystem("Tạo phân công giảng dạy ID #$newId", 'class_subjects', $newId);
        respondOrRedirect(true, 'created', $returnPage);
    }

    respondOrRedirect(false, 'invalid_action', $returnPage);
} catch (Exception $e) {
    error_log('classSubjectController EXCEPTION: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    respondOrRedirect(false, 'Lỗi hệ thống, vui lòng thử lại.', $returnPage);
}
