<?php
/**
 * CMS BDU - Admin home redirect
 * Chuyển hướng /admin/home.php → /views/admin/home.php
 */
require_once __DIR__ . '/../config/config.php';
header('Location: ' . BASE_URL . '/views/admin/home.php');
exit;
