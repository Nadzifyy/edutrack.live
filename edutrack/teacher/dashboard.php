<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $auth->getUserId();

// Get teacher ID
$stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$teacher_id = $teacher['id'] ?? null;
$stmt->close();

if (!$teacher_id) {
    die("Teacher profile not found. Please contact administrator.");
}

// Get statistics
$stats = [];

// Get assigned classes
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT tss.section_id) as classes
    FROM teacher_subject_sections tss
    WHERE tss.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['classes'] = $result->fetch_assoc()['classes'] ?? 0;
$stmt->close();

// Get total students
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as students
    FROM teacher_subject_sections tss
    JOIN students s ON tss.section_id = s.section_id
    WHERE tss.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['students'] = $result->fetch_assoc()['students'] ?? 0;
$stmt->close();

// Get assigned subjects
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT tss.subject_id) as subjects
    FROM teacher_subject_sections tss
    WHERE tss.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['subjects'] = $result->fetch_assoc()['subjects'] ?? 0;
$stmt->close();

$page_title = 'Teacher Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="nav" style="display: none;">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php" class="active">Dashboard</a></li>
            <li class="nav-item"><a href="classes.php">My Classes</a></li>
            <li class="nav-item"><a href="grades.php">Manage Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['classes']; ?></div>
            <div class="stat-label">Assigned Classes</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['students']; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['subjects']; ?></div>
            <div class="stat-label">Assigned Subjects</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Welcome</h2>
        </div>
        <p>Use the navigation menu to manage your classes, encode grades, mark attendance, and add remarks for your students.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

