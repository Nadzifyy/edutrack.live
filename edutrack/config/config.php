<?php
/**
 * EduTrack - Configuration File
 * Student Performance Monitoring System
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edutrack');

// Application Configuration
define('APP_NAME', 'EduTrack');
define('SCHOOL_NAME', 'CIRILO BUENO SR. ELEMENTARY SCHOOL');
define('APP_URL', 'http://localhost/edutrack');
define('TIMEZONE', 'Asia/Manila');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Security Configuration
define('PASSWORD_MIN_LENGTH', 8);
define('DEFAULT_PASSWORD', 'Password123'); // Default password for bulk uploads

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

