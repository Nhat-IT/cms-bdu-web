<?php
/**
 * CMS BDU - Class Subject Controller
 * Xử lý thao tác đóng/mở lớp học phần và cập nhật lịch nhóm.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Chỉ kiểm tra POST cho các action cần bảo mật
$isExport = isset($_GET['action']) && $_GET['action'] === 'export_group_students';
$isGetAllowed = isset($_GET['action']) && in_array($_GET['action'], ['export_group_students', 'get_group_student_counts'], true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isGetAllowed) {
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
    if (strpos($accept, 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
        exit;
    }
    redirect('../../views/admin/home.php');
}

// Debug log
error_log('classSubjectController: action=' . ($_POST['action'] ?? 'none') . ', class_subject_id=' . ($_POST['class_subject_id'] ?? 'none'));

requireRole('admin');

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['action'] ?? '')
    : ($_GET['action'] ?? '');
$classSubjectId = (int) ($_POST['class_subject_id'] ?? 0);
$return = $_POST['return'] ?? 'assignments';
$returnPage = $return === 'home' ? 'home.php' : 'assignments.php';

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function respondOrRedirect(bool $ok, string $message, string $returnPage): void {
    // Luôn trả JSON nếu có format=json
    if (isset($_POST['format']) && $_POST['format'] === 'json') {
        jsonResponse(['ok' => $ok, 'message' => $message], $ok ? 200 : 400);
        return;
    }

    $code = $ok ? 'ok' : 'error';
    redirect('../../views/admin/' . $returnPage . '?class_subject_' . $code . '=' . urlencode($message));
}

if ($classSubjectId <= 0 && $action !== 'add_group' && $action !== 'get_group_student_counts') {
    respondOrRedirect(false, 'missing_id', $returnPage);
}

// Action: lấy số SV mỗi nhóm (GET)
if ($action === 'get_group_student_counts') {
    $rows = db_fetch_all(
        'SELECT csg.class_subject_id, csg.group_code, COUNT(gs.id) AS cnt
         FROM class_subject_groups csg
         LEFT JOIN group_students gs ON gs.class_subject_group_id = csg.id
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
        $groupCodeRaw = $_POST['group_code_select'] ?? $_POST['group_code'] ?? '';
        $groupCode = trim((string) $groupCodeRaw);
        $teacherMain = (int) ($_POST['teacher_main_id'] ?? 0);
        $dayOfWeek = (int) ($_POST['day_of_week'] ?? 0);
        $startPeriod = (int) ($_POST['start_period'] ?? 0);
        $endPeriod = (int) ($_POST['end_period'] ?? 0);
        $room = trim($_POST['room'] ?? '');

        if ($classSubjectId <= 0 || $groupCode === '' || $teacherMain <= 0 || $dayOfWeek < 2 || $dayOfWeek > 8 || $startPeriod < 1 || $endPeriod < $startPeriod || $room === '') {
            respondOrRedirect(false, 'invalid_schedule_data', $returnPage);
        }

        $groupData = db_fetch_one('SELECT id, sub_teacher_id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1', [$classSubjectId, $groupCode]);
        $isUpdate = (bool) $groupData;

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
             WHERE (cs.teacher_id = ? OR csg.sub_teacher_id = ?)
               AND csg.day_of_week = ?
               AND csg.start_period <= ?
               AND csg.end_period >= ?
               AND csg.room IS NOT NULL
               AND NOT (csg.class_subject_id = ? AND csg.group_code = ?)
             LIMIT 1',
            [$teacherMain, $teacherMain, $dayOfWeek, $endPeriod, $startPeriod, $classSubjectId, $groupCode]
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
        if ($teacherSubRaw === null && $isUpdate) {
            $teacherSub = $groupData['sub_teacher_id'];
        } else {
            $teacherSub = ($teacherSubRaw === '') ? null : (int) $teacherSubRaw;
        }

        if ($isUpdate) {
            db_query(
                'UPDATE class_subject_groups
                 SET room = ?, day_of_week = ?, start_period = ?, end_period = ?, sub_teacher_id = ?
                 WHERE id = ?',
                [$room, $dayOfWeek, $startPeriod, $endPeriod, $teacherSub, (int) $groupData['id']]
            );
        } else {
            db_query(
                'INSERT INTO class_subject_groups (class_subject_id, group_code, room, day_of_week, start_period, end_period, sub_teacher_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$classSubjectId, $groupCode, $room, $dayOfWeek, $startPeriod, $endPeriod, $teacherSub]
            );
        }

        db_query('UPDATE class_subjects SET teacher_id = ? WHERE id = ?', [$teacherMain, $classSubjectId]);
        $logMsg = $isUpdate ? "Cập nhật" : "Tạo";
        logSystem("$logMsg lịch nhóm $groupCode - lớp học phần ID #$classSubjectId", 'class_subject_groups', $isUpdate ? (int) $groupData['id'] : null);
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
             WHERE class_subject_id = ? AND group_code = ?',
            [$classSubjectId, $groupCode]
        );
        respondOrRedirect(true, 'schedule_cleared', $returnPage);
    }

    if ($action === 'save_extra_class') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0 && isset($_POST['class_subject_id'], $_POST['group_code'])) {
            $groupData = db_fetch_one('SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1', [$_POST['class_subject_id'], $_POST['group_code']]);
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

        if ($groupId <= 0 || $extraDate === '' || $extraDay < 2 || $extraDay > 8 || $extraStart < 1 || $extraEnd < $extraStart || $extraRoom === '') {
            respondOrRedirect(false, 'invalid_schedule_data', $returnPage);
        }

        db_query(
            'INSERT INTO extra_classes (class_subject_group_id, extra_date, day_of_week, start_period, end_period, room, note, is_regular)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$groupId, $extraDate, $extraDay, $extraStart, $extraEnd, $extraRoom, $extraNote !== '' ? $extraNote : null, $isRegular]
        );
        logSystem("Thêm buổi học bù cho nhóm ID #$groupId", 'extra_classes', null);
        respondOrRedirect(true, 'extra_class_added', $returnPage);
    }

    if ($action === 'add_group') {
        $classSubjectId = (int) ($_POST['class_subject_id'] ?? 0);
        if ($classSubjectId <= 0) {
            respondOrRedirect(false, 'missing_id', $returnPage);
        }

        $codes = db_fetch_all('SELECT group_code FROM class_subject_groups WHERE class_subject_id = ?', [$classSubjectId]);
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
        respondOrRedirect(true, 'group_added', $returnPage);
    }

    if ($action === 'delete_group') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $groupCode = trim($_POST['group_code'] ?? '');

        if ($classSubjectId <= 0 || ($groupId <= 0 && $groupCode === '')) {
            respondOrRedirect(false, 'invalid_group', $returnPage);
        }

        if ($groupId > 0) {
            db_query('DELETE FROM class_subject_groups WHERE id = ?', [$groupId]);
            logSystem("Xóa nhóm ID #$groupId khỏi lớp học phần ID #$classSubjectId", 'class_subject_groups', $groupId);
        } else {
            db_query('DELETE FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ?', [$classSubjectId, $groupCode]);
            logSystem("Xóa nhóm $groupCode khỏi lớp học phần ID #$classSubjectId", 'class_subject_groups', null);
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
            'SELECT gs.id, gs.student_id, gs.mssv, gs.full_name, gs.birth_date, gs.class_name, gs.status,
                    u.username
             FROM group_students gs
             LEFT JOIN users u ON u.id = gs.student_id
             WHERE gs.class_subject_group_id = ?
             ORDER BY COALESCE(gs.full_name, u.full_name, gs.mssv) ASC',
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

        // Reset AUTO_INCREMENT về MAX(id)+1 trước khi insert (reuse ID đã xóa)
        $maxRow = db_fetch_one('SELECT MAX(id) AS max_id FROM group_students WHERE class_subject_group_id = ?', [$targetGroupId]);
        $nextId = ((int) ($maxRow['max_id'] ?? 0)) + 1;
        db_query("ALTER TABLE group_students AUTO_INCREMENT = ?", [$nextId]);

        foreach ($students as $i => $s) {
            $mssv = trim((string) ($s['mssv'] ?? ''));
            $name = trim((string) ($s['name'] ?? ''));
            $dob = trim((string) ($s['dob'] ?? ''));
            $className = trim((string) ($s['class'] ?? ''));

            if ($mssv === '' || $name === '') {
                $skipped++;
                continue;
            }

            $studentId = null;
            $user = db_fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', [$mssv]);
            if ($user) {
                $studentId = (int) $user['id'];
            }

            // Kiểm tra đã tồn tại (theo student_id hoặc mssv)
            $existing = null;
            if ($studentId) {
                $existing = db_fetch_one(
                    'SELECT id FROM group_students WHERE class_subject_group_id = ? AND student_id = ? LIMIT 1',
                    [$targetGroupId, $studentId]
                );
            }
            if (!$existing) {
                $existing = db_fetch_one(
                    'SELECT id FROM group_students WHERE class_subject_group_id = ? AND mssv = ? LIMIT 1',
                    [$targetGroupId, $mssv]
                );
            }
            if ($existing) {
                $skipped++;
                continue;
            }

            try {
                db_query(
                    'INSERT INTO group_students (class_subject_group_id, student_id, mssv, full_name, birth_date, class_name) VALUES (?, ?, ?, ?, ?, ?)',
                    [$targetGroupId, $studentId, $studentId ? null : $mssv, $name ?: null, $dob ?: null, $className ?: null]
                );
                $imported++;
            } catch (Exception $e) {
                $skipped++;
            }
        }

        logSystem("Import $imported sinh viên vào nhóm ID #$targetGroupId", 'group_students', $targetGroupId);
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

        if ($groupId <= 0 && $csId > 0 && $groupCode === '') {
            $students = db_fetch_all(
                'SELECT u.username, u.full_name, u.birth_date, gs.status, gs.mssv, gs.class_name
                 FROM group_students gs
                 JOIN class_subject_groups csg ON csg.id = gs.class_subject_group_id
                 LEFT JOIN users u ON u.id = gs.student_id
                 WHERE csg.class_subject_id = ?
                 ORDER BY COALESCE(u.full_name, gs.full_name, gs.mssv) ASC',
                [$csId]
            );
            jsonResponse(['ok' => true, 'students' => $students]);
        }

        $targetGroupId = $groupId;
        if ($targetGroupId <= 0 && $csId > 0) {
            $g = db_fetch_one(
                'SELECT id FROM class_subject_groups WHERE class_subject_id = ? AND group_code = ? LIMIT 1',
                [$csId, $groupCode ?: 'N1']
            );
            $targetGroupId = (int) ($g['id'] ?? 0);
        }

        $students = [];
        if ($targetGroupId > 0) {
            $students = db_fetch_all(
                'SELECT u.username, u.full_name, u.birth_date, gs.status, gs.mssv, gs.class_name
                 FROM group_students gs
                 LEFT JOIN users u ON u.id = gs.student_id
                 WHERE gs.class_subject_group_id = ?
                 ORDER BY u.full_name ASC, gs.mssv ASC',
                [$targetGroupId]
            );
        }

        // Trả về JSON để JS tạo XLSX
        jsonResponse(['ok' => true, 'students' => $students]);
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
            'SELECT gs.id, gs.student_id, gs.mssv, gs.full_name, gs.birth_date, gs.class_name, gs.status,
                    u.username
             FROM group_students gs
             LEFT JOIN users u ON u.id = gs.student_id
             WHERE gs.class_subject_group_id = ?
             ORDER BY COALESCE(gs.full_name, u.full_name, gs.mssv) ASC',
            [$targetGroupId]
        );

        $rows = [['STT', 'MSSV', 'Ho va ten', 'Ngay sinh', 'Lop']];
        $stt = 1;
        foreach ($students as $s) {
            $mssv = !empty($s['username']) ? $s['username'] : ($s['mssv'] ?? '');
            $fullName = $s['full_name'] ?: ($s['username'] ?? '');

            $birthDate = $s['birth_date'] ?? '';
            if ($birthDate !== '' && is_numeric($birthDate)) {
                $serial = (int) $birthDate;
                $d = new DateTime('1899-12-30');
                $d->add(new DateInterval('P' . $serial . 'D'));
                $birthDate = $d->format('d/m/Y');
            }

            $rows[] = [
                $stt++,
                $mssv,
                $fullName,
                $birthDate,
                $s['class_name'] ?? ''
            ];
        }

        $sheetName = 'Danh sach SV';
        $wb = [[
            'sheetName' => $sheetName,
            'data' => $rows
        ]];
        jsonResponse(['ok' => true, 'workbook' => $wb, 'filename' => 'DanhSachSV_Nhom' . ($groupCode ?: $targetGroupId)]);
    }

    if ($action === 'export_group_students') {
        $groupId = (int) ($_GET['group_id'] ?? 0);
        $groupCode = trim($_GET['group_code'] ?? '');
        $csId = (int) ($_GET['class_subject_id'] ?? 0);
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
            'SELECT gs.id, gs.student_id, gs.mssv, gs.full_name, gs.birth_date, gs.class_name, gs.status,
                    u.username
             FROM group_students gs
             LEFT JOIN users u ON u.id = gs.student_id
             WHERE gs.class_subject_group_id = ?
             ORDER BY COALESCE(gs.full_name, u.full_name, gs.mssv) ASC',
            [$targetGroupId]
        );

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="DanhSachSV_Nhom' . ($groupCode ?: $targetGroupId) . '_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['STT', 'MSSV', 'Ho va ten', 'Ngay sinh', 'Lop']);
        $stt = 1;
        foreach ($students as $s) {
            $mssv = !empty($s['username']) ? $s['username'] : ($s['mssv'] ?? '');
            $fullName = $s['full_name'] ?: ($s['username'] ?? '');
            $birthDate = $s['birth_date'] ?? '';
            fputcsv($out, [
                $stt++,
                $mssv,
                $fullName,
                $birthDate,
                $s['class_name'] ?? ''
            ]);
        }
        fclose($out);
        exit;
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
