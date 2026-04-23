<?php 
require 'config/config.php'; 
require 'config/session.php'; 
$_SESSION['user_id'] = 1; 
$_SESSION['role'] = 'admin'; 
include 'views/admin/assignments.php';
