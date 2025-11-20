<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('administrator');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total users by role
$result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $result->fetch_assoc()) {
    $stats[$row['role']] = $row['count'];
}

// Total students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$stats['total_students'] = $result->fetch_assoc()['count'];

// Total teachers
$result = $conn->query("SELECT COUNT(*) as count FROM teachers");
$stats['total_teachers'] = $result->fetch_assoc()['count'];

// Total subjects
$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
$stats['total_subjects'] = $result->fetch_assoc()['count'];

// Total sections
$result = $conn->query("SELECT COUNT(*) as count FROM sections");
$stats['total_sections'] = $result->fetch_assoc()['count'];

$page_title = 'Administrator Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="nav" style="display: none;">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php" class="active">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_students'] ?? 0; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_teachers'] ?? 0; ?></div>
            <div class="stat-label">Total Teachers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_subjects'] ?? 0; ?></div>
            <div class="stat-label">Total Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_sections'] ?? 0; ?></div>
            <div class="stat-label">Total Sections</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">System Overview</h2>
        </div>
        <p>Welcome to the Administrator Dashboard. Use the navigation menu to manage users, students, teachers, subjects, sections, and parent-student assignments.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

