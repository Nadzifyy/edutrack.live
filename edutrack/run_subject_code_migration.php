<?php
/**
 * Quick Migration Script - Make subject_code nullable
 * Access via: http://localhost/edutrack/run_subject_code_migration.php
 */

require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Check current column definition
$result = $conn->query("SHOW COLUMNS FROM subjects WHERE Field = 'subject_code'");
$column = $result->fetch_assoc();

if ($column) {
    if ($column['Null'] === 'YES') {
        $status = "The subject_code column is already nullable. No migration needed.";
        $type = "info";
    } else {
        // Update existing empty strings to NULL first
        $conn->query("UPDATE subjects SET subject_code = NULL WHERE subject_code = ''");
        
        // Modify column to allow NULL
        $sql = "ALTER TABLE subjects MODIFY COLUMN subject_code VARCHAR(20) NULL";
        
        if ($conn->query($sql)) {
            $status = "Migration completed successfully! The subject_code column is now nullable. You can now create subjects without subject codes.";
            $type = "success";
        } else {
            $status = "Error: " . $conn->error;
            $type = "error";
        }
    }
} else {
    $status = "Error: subject_code column not found.";
    $type = "error";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Subject Code Migration Status</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .status-box { 
            padding: 20px; 
            border-radius: 8px; 
            border: 2px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-color: #c3e6cb; 
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-color: #bee5eb; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-color: #f5c6cb; 
        }
        h2 { margin-top: 0; }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn:hover {
            background: #1e40af;
        }
    </style>
</head>
<body>
    <div class="status-box <?php echo $type; ?>">
        <h2>Subject Code Migration Status</h2>
        <p><?php echo htmlspecialchars($status); ?></p>
        <a href="admin/subjects.php" class="btn">Go to Subjects Page</a>
    </div>
</body>
</html>

