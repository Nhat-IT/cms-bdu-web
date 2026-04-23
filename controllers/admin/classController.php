<?php
/**
 * CMS BDU - Class Controller
 * Xử lý thêm/sửa lớp học từ admin classes-subjects
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/classes-subjects.php');
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$className = trim($_POST['class_name'] ?? '');
$academicYear = trim($_POST['academic_year'] ?? '');
$departmentId = trim($_POST['department_id'] ?? '');

if (!in_array($action, ['save', 'delete'], true)) {
    redirect('../../views/admin/classes-subjects.php');
}

if ($action === 'save' && ($className === '' || $academicYear === '')) {
    redirect('../../views/admin/classes-subjects.php?class_error=missing_data');
}

$departmentIdValue = $departmentId === '' ? null : (int) $departmentId;

try {
    if ($action === 'delete') {
        if ($id > 0) {
            db_query('DELETE FROM classes WHERE id = ?', [$id]);
            logSystem("Xóa lớp học ID #$id - $className", 'classes', $id);
            redirect('../../views/admin/classes-subjects.php?class_deleted=1');
        }
        redirect('../../views/admin/classes-subjects.php?class_error=missing_id');
    }

    if ($id > 0) {
        db_query(
            'UPDATE classes SET class_name = ?, academic_year = ?, department_id = ? WHERE id = ?',
            [$className, $academicYear, $departmentIdValue, $id]
        );
        logSystem("Cập nhật lớp học ID #$id - $className", 'classes', $id);
    } else {
        db_query(
            'INSERT INTO classes (class_name, academic_year, department_id) VALUES (?, ?, ?)',
            [$className, $academicYear, $departmentIdValue]
        );
        $created = db_fetch_one('SELECT id FROM classes WHERE class_name = ? AND academic_year = ? LIMIT 1', [$className, $academicYear]);
        $newId = (int) ($created['id'] ?? 0);
        logSystem("Tạo lớp học mới - $className", 'classes', $newId);
    }

    redirect('../../views/admin/classes-subjects.php?class_success=1');
} catch (Exception $e) {
    redirect('../../views/admin/classes-subjects.php?class_error=1');
}
