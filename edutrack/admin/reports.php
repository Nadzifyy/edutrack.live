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

// Total grades entered
$result = $conn->query("SELECT COUNT(*) as count FROM grades");
$stats['total_grades'] = $result->fetch_assoc()['count'];

// Total attendance records
$result = $conn->query("SELECT COUNT(*) as count FROM attendance");
$stats['total_attendance'] = $result->fetch_assoc()['count'];

// Get grade distribution
$grade_distribution = [];
$result = $conn->query("
    SELECT 
        CASE 
            WHEN grade_value >= 90 THEN 'A (90-100)'
            WHEN grade_value >= 80 THEN 'B (80-89)'
            WHEN grade_value >= 70 THEN 'C (70-79)'
            WHEN grade_value >= 60 THEN 'D (60-69)'
            ELSE 'F (Below 60)'
        END as grade_range,
        COUNT(*) as count
    FROM grades
    GROUP BY grade_range
    ORDER BY MIN(grade_value) DESC
");
while ($row = $result->fetch_assoc()) {
    $grade_distribution[] = $row;
}

// Get attendance summary
$attendance_summary = [];
$result = $conn->query("
    SELECT status, COUNT(*) as count
    FROM attendance
    GROUP BY status
");
while ($row = $result->fetch_assoc()) {
    $attendance_summary[$row['status']] = $row['count'];
}

$page_title = 'System Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>System Reports</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php" class="active">Reports</a></li>
        </ul>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_students']; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_teachers']; ?></div>
            <div class="stat-label">Total Teachers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_subjects']; ?></div>
            <div class="stat-label">Total Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_sections']; ?></div>
            <div class="stat-label">Total Sections</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_grades']; ?></div>
            <div class="stat-label">Grades Entered</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_attendance']; ?></div>
            <div class="stat-label">Attendance Records</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grade Distribution</h2>
        </div>
        <?php if (empty($grade_distribution)): ?>
            <div class="empty-state">No grade data available.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Grade Range</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grade_distribution as $dist): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dist['grade_range']); ?></td>
                                <td><?php echo $dist['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Attendance Summary</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Present</td>
                        <td><?php echo $attendance_summary['Present'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <td>Absent</td>
                        <td><?php echo $attendance_summary['Absent'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <td>Tardy</td>
                        <td><?php echo $attendance_summary['Tardy'] ?? 0; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

