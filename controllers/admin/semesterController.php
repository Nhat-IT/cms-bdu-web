<?php
/**
 * CMS BDU - Semester Controller
 * Xử lý thêm/sửa học kỳ từ admin org-settings
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/org-settings.php?tab=semester');
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$semesterName = trim($_POST['semester_name'] ?? '');
$academicYear = trim($_POST['academic_year'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');

if (!in_array($action, ['save', 'delete', 'set_current'], true)) {
    redirect('../../views/admin/org-settings.php?tab=semester');
}

if ($action === 'save' && ($semesterName === '' || $academicYear === '' || $startDate === '' || $endDate === '')) {
    redirect('../../views/admin/org-settings.php?tab=semester&semester_error=missing_data');
}

try {
    if ($action === 'set_current') {
        if ($id > 0) {
            $_SESSION['current_semester_id'] = $id;
            logSystem("Đặt học kỳ ID #$id làm hiện tại", 'semesters', $id);
            redirect('../../views/admin/org-settings.php?tab=semester&semester_current=1');
        }
        redirect('../../views/admin/org-settings.php?tab=semester&semester_error=missing_id');
    }

    if ($action === 'delete') {
        if ($id > 0) {
            db_query('DELETE FROM semesters WHERE id = ?', [$id]);
            if (isset($_SESSION['current_semester_id']) && (int) $_SESSION['current_semester_id'] === $id) {
                unset($_SESSION['current_semester_id']);
            }
            logSystem("Xóa học kỳ ID #$id - $semesterName", 'semesters', $id);
            redirect('../../views/admin/org-settings.php?tab=semester&semester_deleted=1');
        }
        redirect('../../views/admin/org-settings.php?tab=semester&semester_error=missing_id');
    }

    if ($id > 0) {
        db_query(
            'UPDATE semesters SET semester_name = ?, academic_year = ?, start_date = ?, end_date = ? WHERE id = ?',
            [$semesterName, $academicYear, $startDate, $endDate, $id]
        );
        logSystem("Cập nhật học kỳ ID #$id - $semesterName", 'semesters', $id);
    } else {
        db_query(
            'INSERT INTO semesters (semester_name, academic_year, start_date, end_date) VALUES (?, ?, ?, ?)',
            [$semesterName, $academicYear, $startDate, $endDate]
        );
        $created = db_fetch_one('SELECT id FROM semesters WHERE semester_name = ? AND academic_year = ? LIMIT 1', [$semesterName, $academicYear]);
        $newId = (int) ($created['id'] ?? 0);
        logSystem("Tạo học kỳ mới - $semesterName", 'semesters', $newId);
    }

    redirect('../../views/admin/org-settings.php?tab=semester&semester_success=1');
} catch (Exception $e) {
    redirect('../../views/admin/org-settings.php?tab=semester&semester_error=1');
}
