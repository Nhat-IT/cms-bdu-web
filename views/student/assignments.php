<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';

requireRole('student');

header('Location: home.php?notice=assignments_disabled');
exit;
