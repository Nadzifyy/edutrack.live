<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$role = $user['role'];

// Redirect based on role
switch ($role) {
    case 'administrator':
        redirect('admin/dashboard.php');
        break;
    case 'teacher':
        redirect('teacher/dashboard.php');
        break;
    case 'student':
        redirect('student/dashboard.php');
        break;
    case 'parent':
        redirect('parent/dashboard.php');
        break;
    default:
        redirect('login.php');
        break;
}

