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
    die("Teacher profile not found.");
}

// Get assigned classes
$classes = [];
$stmt = $conn->prepare("
    SELECT tss.*, s.subject_name, sec.section_name, sec.grade_level,
           COUNT(DISTINCT st.id) as student_count
    FROM teacher_subject_sections tss
    JOIN subjects s ON tss.subject_id = s.id
    JOIN sections sec ON tss.section_id = sec.id
    LEFT JOIN students st ON sec.id = st.section_id
    WHERE tss.teacher_id = ?
    GROUP BY tss.id, s.subject_name, sec.section_name, sec.grade_level
    ORDER BY sec.grade_level, sec.section_name, s.subject_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Reports</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="classes.php">My Classes</a></li>
            <li class="nav-item"><a href="grades.php">Manage Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Remarks</a></li>
            <li class="nav-item"><a href="reports.php" class="active">Reports</a></li>
        </ul>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Class Reports</h2>
        </div>
        <p>Select a class from "My Classes" to view detailed reports. You can generate individual student progress reports from the grade management page.</p>
        
        <?php if (empty($classes)): ?>
            <div class="empty-state">No classes assigned yet.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Grade Level</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo displayText($class['subject_name']); ?></td>
                                <td><?php echo displayText($class['section_name']); ?></td>
                                <td><?php echo displayText($class['grade_level']); ?></td>
                                <td><?php echo $class['student_count']; ?></td>
                                <td>
                                    <a href="grades.php?assignment_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">View Grades</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

