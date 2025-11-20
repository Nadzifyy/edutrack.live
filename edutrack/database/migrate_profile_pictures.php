<?php
/**
 * Migration Script: Add Profile Pictures Column
 * Run this file once to add the profile_picture column to the users table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if column already exists
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");

if ($check_column->num_rows > 0) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Migration Status</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; border: 1px solid #bee5eb; }
        </style>
    </head>
    <body>
        <div class='info'>
            <h2>Migration Already Completed</h2>
            <p>The profile_picture column already exists in the users table.</p>
            <p>You can safely delete this migration file.</p>
        </div>
    </body>
    </html>";
} else {
    // Add the column
    $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name";
    
    if ($conn->query($sql)) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Migration Status</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; }
                .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; }
            </style>
        </head>
        <body>
            <div class='success'>
                <h2>Migration Successful!</h2>
                <p>The profile_picture column has been successfully added to the users table.</p>
                <p>You can now:</p>
                <ul>
                    <li>Upload profile pictures for users in the admin panel</li>
                    <li>Profile pictures will appear in the header</li>
                </ul>
                <p><strong>You can safely delete this migration file now.</strong></p>
            </div>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Migration Error</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; }
            </style>
        </head>
        <body>
            <div class='error'>
                <h2>Migration Failed</h2>
                <p>Error: " . htmlspecialchars($conn->error) . "</p>
                <p>Please check your database connection and try again.</p>
            </div>
        </body>
        </html>";
    }
}

$conn->close();
?>

