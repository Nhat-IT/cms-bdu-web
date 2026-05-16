<?php
/**
 * CMS BDU - Đăng xuất
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    logSystem("Đăng xuất", 'users', $userId);
}
logout();
