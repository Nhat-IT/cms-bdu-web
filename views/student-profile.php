<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';

requireRole('student');

header('Location: ' . BASE_URL . '/views/student/student-profile.php');
exit;
