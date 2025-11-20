<?php
/**
 * Quick Migration Script - Run this file to add profile_picture column
 * Access via: http://localhost/edutrack/run_migration.php
 */

require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if column already exists
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");

if ($check_column && $check_column->num_rows > 0) {
    $status = "Column already exists!";
    $type = "info";
} else {
    // Add the column
    $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER last_name";
    
    if ($conn->query($sql)) {
        $status = "Migration completed successfully! The profile_picture column has been added.";
        $type = "success";
    } else {
        $status = "Error: " . $conn->error;
        $type = "error";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migration Status</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .box { 
            background: white;
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid #c3e6cb; 
            margin: 20px 0;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid #bee5eb; 
            margin: 20px 0;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid #f5c6cb; 
            margin: 20px 0;
        }
        h1 { color: #333; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #1e40af;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Database Migration</h1>
        <div class="<?php echo $type; ?>">
            <strong><?php echo $type === 'error' ? 'Error' : ($type === 'success' ? 'Success' : 'Info'); ?>:</strong>
            <?php echo htmlspecialchars($status); ?>
        </div>
        
        <?php if ($type === 'success'): ?>
            <p>You can now:</p>
            <ul>
                <li>Upload profile pictures for users in the admin panel</li>
                <li>Profile pictures will appear in the header</li>
            </ul>
            <p><strong>You can safely delete this file (run_migration.php) now.</strong></p>
        <?php endif; ?>
        
        <a href="index.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>

