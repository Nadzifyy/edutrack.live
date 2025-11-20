<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$role = $user['role'];

// Define navigation menus for each role
$nav_menus = [
    'administrator' => [
        ['url' => 'admin/dashboard.php', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['url' => 'admin/users.php', 'label' => 'Manage Users', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['url' => 'admin/students.php', 'label' => 'Students', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['url' => 'admin/teachers.php', 'label' => 'Teachers', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['url' => 'admin/subjects.php', 'label' => 'Subjects', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['url' => 'admin/sections.php', 'label' => 'Sections', 'icon' => 'M19 11H5m14-7H5m14 14H5'],
        ['url' => 'admin/assignments.php', 'label' => 'Parent-Student Links', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['url' => 'admin/promotions.php', 'label' => 'Promotions', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['url' => 'admin/logs.php', 'label' => 'User Logs', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['url' => 'admin/reports.php', 'label' => 'Reports', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z'],
    ],
    'teacher' => [
        ['url' => 'teacher/dashboard.php', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['url' => 'teacher/classes.php', 'label' => 'My Classes', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        ['url' => 'teacher/grades.php', 'label' => 'Manage Grades', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['url' => 'teacher/attendance.php', 'label' => 'Attendance', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['url' => 'teacher/remarks.php', 'label' => 'Remarks', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        ['url' => 'teacher/reports.php', 'label' => 'Reports', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z'],
    ],
    'student' => [
        ['url' => 'student/dashboard.php', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['url' => 'student/grades.php', 'label' => 'My Grades', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['url' => 'student/attendance.php', 'label' => 'Attendance', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['url' => 'student/remarks.php', 'label' => 'Teacher Remarks', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        ['url' => 'student/reports.php', 'label' => 'Performance Report', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z'],
    ],
    'parent' => [
        ['url' => 'parent/dashboard.php', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['url' => 'parent/reports.php', 'label' => 'View Reports', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z'],
        ['url' => 'parent/add_child.php', 'label' => 'Add Child', 'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
    ],
];

$current_menu = $nav_menus[$role] ?? [];
$current_path = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo baseUrl('assets/css/style.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <!-- Mobile Menu Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="<?php echo baseUrl('LOGO.png'); ?>" alt="<?php echo SCHOOL_NAME; ?> Logo" style="max-width: 100%; height: auto;">
                <div class="sidebar-title">
                    <div class="sidebar-school-name"><?php echo SCHOOL_NAME; ?></div>
                    <div class="sidebar-app-name"><?php echo APP_NAME; ?></div>
                </div>
            </div>
            <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <?php foreach ($current_menu as $item): 
                    $is_active = (strpos($current_path, $item['url']) !== false);
                ?>
                    <li class="sidebar-menu-item">
                        <a href="<?php echo baseUrl($item['url']); ?>" class="sidebar-menu-link <?php echo $is_active ? 'active' : ''; ?>">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" class="sidebar-icon">
                                <path fill-rule="evenodd" d="<?php echo $item['icon']; ?>" clip-rule="evenodd"/>
                            </svg>
                            <span class="sidebar-menu-text"><?php echo htmlspecialchars($item['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <a href="<?php echo baseUrl('profile.php'); ?>" class="sidebar-user">
                <img src="<?php echo getProfilePicture($user['profile_picture'] ?? null, $user['id']); ?>" alt="Profile Picture">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="sidebar-user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></div>
                </div>
            </a>
            <a href="<?php echo baseUrl('logout.php'); ?>" class="sidebar-logout">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div class="header-breadcrumb">
                    <h1 class="page-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h1>
                </div>
            </div>
            <div class="header-right">
                <a href="<?php echo baseUrl('profile.php'); ?>" class="header-user-link">
                    <img src="<?php echo getProfilePicture($user['profile_picture'] ?? null, $user['id']); ?>" alt="Profile Picture">
                    <div class="header-user-text">
                        <div class="header-user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="header-user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></div>
                    </div>
                </a>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="main-content">

