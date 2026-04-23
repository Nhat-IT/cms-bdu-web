<?php
/**
 * CMS BDU - Department Controller
 * Xử lý thêm/sửa ngành học từ admin org-settings
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/org-settings.php');
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$departmentCode = trim($_POST['department_code'] ?? '');
$departmentName = trim($_POST['department_name'] ?? '');

if (!in_array($action, ['save', 'delete'], true)) {
    redirect('../../views/admin/org-settings.php?tab=major');
}

if ($action === 'save' && ($departmentCode === '' || $departmentName === '')) {
    redirect('../../views/admin/org-settings.php?tab=major&major_error=missing_name');
}

function departmentHasCodeColumn() {
    $row = db_fetch_one("SELECT COUNT(*) as total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'departments' AND column_name = 'department_code'");
    return ((int) ($row['total'] ?? 0)) > 0;
}

try {
    $hasCodeColumn = departmentHasCodeColumn();

    if ($action === 'delete') {
        if ($id > 0) {
            db_query('DELETE FROM departments WHERE id = ?', [$id]);
            logSystem("Xóa ngành học ID #$id - $departmentName", 'departments', $id);
            redirect('../../views/admin/org-settings.php?tab=major&major_deleted=1');
        }
        redirect('../../views/admin/org-settings.php?tab=major&major_error=missing_id');
    }

    if ($id > 0) {
        if ($hasCodeColumn) {
            db_query('UPDATE departments SET department_code = ?, department_name = ? WHERE id = ?', [$departmentCode, $departmentName, $id]);
        } else {
            db_query('UPDATE departments SET department_name = ? WHERE id = ?', [$departmentName, $id]);
        }
        logSystem("Cập nhật ngành học ID #$id - $departmentName", 'departments', $id);
    } else {
        if ($hasCodeColumn) {
            db_query('INSERT INTO departments (department_code, department_name) VALUES (?, ?)', [$departmentCode, $departmentName]);
        } else {
            db_query('INSERT INTO departments (department_name) VALUES (?)', [$departmentName]);
        }
        $created = db_fetch_one('SELECT id FROM departments WHERE department_name = ? LIMIT 1', [$departmentName]);
        $newId = (int) ($created['id'] ?? 0);
        logSystem("Tạo ngành học mới - $departmentName", 'departments', $newId);
    }

    redirect('../../views/admin/org-settings.php?tab=major&major_success=1');
} catch (Exception $e) {
    redirect('../../views/admin/org-settings.php?tab=major&major_error=1');
}
