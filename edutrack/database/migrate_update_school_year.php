<?php
/**
 * Migration Script: Update School Year from 2024-2025 to 2025-2026
 * 
 * This script updates all school year references in the database.
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update School Year Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b3d7ff; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Update School Year Migration</h1>
    <p>Updating school year from <strong>2024-2025</strong> to <strong>2025-2026</strong>...</p>";

try {
    $conn->begin_transaction();
    
    $updates = [];
    
    // Update sections table
    $stmt = $conn->prepare("UPDATE sections SET school_year = '2025-2026' WHERE school_year = '2024-2025'");
    $stmt->execute();
    $affected_sections = $stmt->affected_rows;
    $stmt->close();
    $updates[] = "Sections: {$affected_sections} record(s) updated";
    
    // Update teacher_subject_sections table
    $stmt = $conn->prepare("UPDATE teacher_subject_sections SET school_year = '2025-2026' WHERE school_year = '2024-2025'");
    $stmt->execute();
    $affected_assignments = $stmt->affected_rows;
    $stmt->close();
    $updates[] = "Teacher Assignments: {$affected_assignments} record(s) updated";
    
    // Check if student_promotions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_promotions'");
    if ($table_check->num_rows > 0) {
        // Update from_school_year
        $stmt = $conn->prepare("UPDATE student_promotions SET from_school_year = '2025-2026' WHERE from_school_year = '2024-2025'");
        $stmt->execute();
        $affected_from = $stmt->affected_rows;
        $stmt->close();
        if ($affected_from > 0) {
            $updates[] = "Promotions (from_school_year): {$affected_from} record(s) updated";
        }
        
        // Update to_school_year
        $stmt = $conn->prepare("UPDATE student_promotions SET to_school_year = '2025-2026' WHERE to_school_year = '2024-2025'");
        $stmt->execute();
        $affected_to = $stmt->affected_rows;
        $stmt->close();
        if ($affected_to > 0) {
            $updates[] = "Promotions (to_school_year): {$affected_to} record(s) updated";
        }
    }
    
    $conn->commit();
    
    echo "<div class='success'><strong>✓ Migration completed successfully!</strong></div>";
    echo "<div class='info'><strong>Summary:</strong><ul>";
    foreach ($updates as $update) {
        echo "<li>{$update}</li>";
    }
    echo "</ul></div>";
    
    // Show current school years in database
    echo "<div class='info'><strong>Current school years in database:</strong><ul>";
    $result = $conn->query("SELECT DISTINCT school_year FROM sections ORDER BY school_year DESC");
    while ($row = $result->fetch_assoc()) {
        echo "<li>{$row['school_year']}</li>";
    }
    echo "</ul></div>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='error'><strong>✗ Migration failed:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>

