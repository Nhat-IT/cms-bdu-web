<?php
/**
 * CMS BDU - Admin classes-subjects redirect
 * Chuyen huong /admin/classes-subjects.php -> /views/admin/classes-subjects.php
 */
require_once __DIR__ . '/../config/config.php';
header('Location: ' . BASE_URL . '/views/admin/classes-subjects.php');
exit;

