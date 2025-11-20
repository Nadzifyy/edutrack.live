<?php
/**
 * Diagnostic Test File
 * Use this to check if PHP and basic configuration are working
 */

// Display PHP info
echo "<h1>PHP Configuration Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
require_once 'config/config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>Database Connection Failed: " . $conn->connect_error . "</p>";
        echo "<p>Please check:</p>";
        echo "<ul>";
        echo "<li>Database name: " . DB_NAME . "</li>";
        echo "<li>Database user: " . DB_USER . "</li>";
        echo "<li>Database host: " . DB_HOST . "</li>";
        echo "<li>Make sure the database exists</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>Database Connection: SUCCESS</p>";
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test file includes
echo "<h2>File Include Test</h2>";
$files_to_test = [
    'config/config.php',
    'config/database.php',
    'includes/auth.php',
    'includes/functions.php'
];

foreach ($files_to_test as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $file NOT FOUND</p>";
    }
}

// Test session
echo "<h2>Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p style='color: green;'>Session started successfully</p>";
} else {
    echo "<p style='color: green;'>Session already active</p>";
}

echo "<h2>PHP Extensions</h2>";
$required_extensions = ['mysqli', 'session', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✓ $ext extension loaded</p>";
    } else {
        echo "<p style='color: red;'>✗ $ext extension NOT loaded</p>";
    }
}

echo "<hr>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>

