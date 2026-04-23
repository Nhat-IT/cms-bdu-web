<?php
/**
 * CMS BDU - Room Controller
 * Xử lý thêm/sửa/xóa phòng học từ admin classes-subjects
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/classes-subjects.php?tab=room');
}

$action   = $_POST['action'] ?? '';
$id       = (int) ($_POST['id'] ?? 0);
$roomCode = strtoupper(trim($_POST['room_code'] ?? ''));
$roomName = trim($_POST['room_name'] ?? '');
$building = trim($_POST['building'] ?? '');
$capacity = max(1, (int) ($_POST['capacity'] ?? 40));
$roomType = in_array($_POST['room_type'] ?? '', ['lecture', 'lab', 'computer']) ? $_POST['room_type'] : 'lecture';
$isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
$note     = trim($_POST['note'] ?? '');

if (!in_array($action, ['save', 'delete'], true)) {
    redirect('../../views/admin/classes-subjects.php?tab=room');
}

try {
    if ($action === 'delete') {
        if ($id > 0) {
            db_query('DELETE FROM rooms WHERE id = ?', [$id]);
            logSystem("Xóa phòng học ID #$id", 'rooms', $id);
            redirect('../../views/admin/classes-subjects.php?room_success=1&tab=room');
        }
        redirect('../../views/admin/classes-subjects.php?room_error=missing_id&tab=room');
    }

    // Validate required
    if ($roomCode === '' || $roomName === '') {
        redirect('../../views/admin/classes-subjects.php?room_error=missing_data&tab=room');
    }

    if ($id > 0) {
        // Sửa — không đổi room_code (khóa readonly)
        db_query(
            'UPDATE rooms SET room_name = ?, building = ?, capacity = ?, room_type = ?, is_active = ?, note = ? WHERE id = ?',
            [$roomName, $building ?: null, $capacity, $roomType, $isActive, $note ?: null, $id]
        );
        logSystem("Cập nhật phòng học #$id - $roomCode", 'rooms', $id);
    } else {
        // Thêm mới — room_code là UNIQUE
        $exists = db_fetch_one('SELECT id FROM rooms WHERE room_code = ? LIMIT 1', [$roomCode]);
        if ($exists) {
            redirect('../../views/admin/classes-subjects.php?room_error=duplicate_code&tab=room');
        }
        db_query(
            'INSERT INTO rooms (room_code, room_name, building, capacity, room_type, is_active, note) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$roomCode, $roomName, $building ?: null, $capacity, $roomType, $isActive, $note ?: null]
        );
        $created = db_fetch_one('SELECT id FROM rooms WHERE room_code = ? LIMIT 1', [$roomCode]);
        $newId   = (int) ($created['id'] ?? 0);
        logSystem("Tạo phòng học mới - $roomCode", 'rooms', $newId);
    }

    redirect('../../views/admin/classes-subjects.php?room_success=1&tab=room');
} catch (Exception $e) {
    redirect('../../views/admin/classes-subjects.php?room_error=1&tab=room');
}
