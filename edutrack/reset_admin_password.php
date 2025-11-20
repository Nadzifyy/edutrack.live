<?php
/**
 * Admin Password Reset Script
 * Use this to reset the admin password if login fails
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Generate new password hash for 'admin123'
$new_password = 'admin123';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h1>Admin Password Reset</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing admin password
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $password_hash);
        
        if ($stmt->execute()) {
            echo "<p style='color: green; font-size: 18px;'>✓ Admin password has been reset successfully!</p>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
            echo "<p><a href='login.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px;'>Go to Login</a></p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to update password: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } else {
        // Create admin user if it doesn't exist
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES ('admin', 'admin@edutrack.com', ?, 'administrator', 'System', 'Administrator')");
        $stmt->bind_param("s", $password_hash);
        
        if ($stmt->execute()) {
            echo "<p style='color: green; font-size: 18px;'>✓ Admin user has been created successfully!</p>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
            echo "<p><a href='login.php' style='padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px;'>Go to Login</a></p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create admin user: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
    
    // Show current hash for verification
    echo "<hr>";
    echo "<h3>Password Hash Generated:</h3>";
    echo "<p style='font-family: monospace; font-size: 12px; word-break: break-all;'>" . htmlspecialchars($password_hash) . "</p>";
    
    // Test the password
    echo "<h3>Verification:</h3>";
    if (password_verify($new_password, $password_hash)) {
        echo "<p style='color: green;'>✓ Password hash verified successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Password hash verification failed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection in config/config.php</p>";
}

echo "<hr>";
echo "<p><strong>Security Note:</strong> Delete this file after resetting the password!</p>";
?>

