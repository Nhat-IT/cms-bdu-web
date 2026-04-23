<?php
/**
 * CMS BDU - Đăng xuất
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
logout();
if ($userId > 0) {
    logSystem("Đăng xuất", 'users', $userId);
}
