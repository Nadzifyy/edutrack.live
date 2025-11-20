<?php
/**
 * Migration script to add promotion system
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "Starting promotion system migration...<br>";

// Check if promotion_status column already exists
$column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'promotion_status'");
if ($column_check->num_rows > 0) {
    echo "Promotion columns already exist in students table. Skipping...<br>";
} else {
    // Add promotion columns to students table
    $sql = "
    ALTER TABLE students 
    ADD COLUMN promotion_status ENUM('Active', 'Promoted', 'Retained', 'Transferred', 'Graduated', 'Dropped') DEFAULT 'Active' AFTER section_id,
    ADD COLUMN current_grade_level INT NULL AFTER promotion_status,
    ADD INDEX idx_promotion_status (promotion_status),
    ADD INDEX idx_grade_level (current_grade_level);
    ";
    
    if ($conn->query($sql)) {
        echo "Successfully added promotion columns to students table.<br>";
        
        // Initialize current_grade_level from sections
        $update_sql = "
        UPDATE students s
        JOIN sections sec ON s.section_id = sec.id
        SET s.current_grade_level = CAST(sec.grade_level AS UNSIGNED)
        WHERE s.section_id IS NOT NULL AND sec.grade_level REGEXP '^[0-9]+$'
        ";
        $conn->query($update_sql);
        echo "Initialized current_grade_level from existing sections.<br>";
    } else {
        echo "Error adding promotion columns: " . $conn->error . "<br>";
    }
}

// Check if promotions table exists
$table_check = $conn->query("SHOW TABLES LIKE 'student_promotions'");
if ($table_check->num_rows > 0) {
    echo "student_promotions table already exists. Skipping...<br>";
} else {
    // Create promotions table
    $sql = "
    CREATE TABLE student_promotions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        promotion_type ENUM('Promoted', 'Retained', 'Transferred', 'Graduated', 'Dropped') NOT NULL,
        from_grade_level INT NOT NULL,
        to_grade_level INT NULL,
        from_section_id INT,
        to_section_id INT,
        from_school_year VARCHAR(20) NOT NULL,
        to_school_year VARCHAR(20) NOT NULL,
        reason TEXT,
        promoted_by INT NOT NULL,
        promotion_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (from_section_id) REFERENCES sections(id) ON DELETE SET NULL,
        FOREIGN KEY (to_section_id) REFERENCES sections(id) ON DELETE SET NULL,
        FOREIGN KEY (promoted_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_student (student_id),
        INDEX idx_type (promotion_type),
        INDEX idx_school_year (to_school_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($sql)) {
        echo "Successfully created student_promotions table.<br>";
    } else {
        echo "Error creating promotions table: " . $conn->error . "<br>";
    }
}

echo "Migration complete.<br>";
?>

