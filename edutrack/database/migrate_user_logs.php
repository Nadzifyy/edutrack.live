<?php
/**
 * Migration script to create user_logs table
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "Creating user_logs table...<br>";

// Check if table already exists
$check_table = $conn->query("SHOW TABLES LIKE 'user_logs'");
if ($check_table->num_rows > 0) {
    echo "Table 'user_logs' already exists. Skipping creation.<br>";
    exit;
}

// Create table
$sql = "
CREATE TABLE IF NOT EXISTS user_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    username VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql)) {
    echo "Successfully created 'user_logs' table.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

echo "Migration complete.<br>";
?>

