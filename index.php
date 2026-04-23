<?php
/**
 * CMS BDU - Entry Point
 * Redirect to login page
 */
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL . '/login.php');
exit;
