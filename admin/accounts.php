<?php
/**
 * CMS BDU - Admin accounts redirect
 * Chuyen huong /admin/accounts.php -> /views/admin/accounts.php
 */
require_once __DIR__ . '/../config/config.php';
header('Location: ' . BASE_URL . '/views/admin/accounts.php');
exit;

