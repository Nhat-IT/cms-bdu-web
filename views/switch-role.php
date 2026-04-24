<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireLogin();

$role = strtolower(trim((string)($_GET['role'] ?? '')));
$next = strtolower(trim((string)($_GET['next'] ?? 'home')));

if ($role === '') {
    header('Location: ' . getHomeUrl($_SESSION['role'] ?? 'student'));
    exit;
}

$roles = $_SESSION['roles'] ?? [];
if (!is_array($roles)) $roles = [];
$roles = array_values(array_filter(array_map('strval', $roles)));

if (!in_array($role, $roles, true)) {
    header('Location: ' . BASE_URL . '/unauthorized.php');
    exit;
}

$_SESSION['role'] = $role;

$target = getHomeUrl($role);
if ($role === 'student' && $next === 'notifications') {
    $target = BASE_URL . '/views/student/notifications-all.php';
}

header('Location: ' . $target);
exit;

