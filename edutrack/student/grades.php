<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('student');

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $auth->getUserId();

// Get student ID
$stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$student_id = $student['id'] ?? null;
$stmt->close();

if (!$student_id) {
    die("Student profile not found.");
}

// Get all grades
$grades = [];
$stmt = $conn->prepare("
    SELECT g.*, s.subject_name
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    ORDER BY s.subject_name, g.grading_period
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $grades[] = $row;
}
$stmt->close();

// Organize by subject
$grades_by_subject = [];
foreach ($grades as $grade) {
    $subject_id = $grade['subject_id'];
    if (!isset($grades_by_subject[$subject_id])) {
        $grades_by_subject[$subject_id] = [
            'subject_name' => $grade['subject_name'],
            'quarters' => []
        ];
    }
    $grades_by_subject[$subject_id]['quarters'][$grade['grading_period']] = $grade['grade_value'];
}

$page_title = 'My Grades';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>My Grades</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="grades.php" class="active">My Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Teacher Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Performance Report</a></li>
        </ul>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grades by Subject</h2>
        </div>
        <?php if (empty($grades_by_subject)): ?>
            <div class="empty-state">No grades available yet.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Q1</th>
                            <th>Q2</th>
                            <th>Q3</th>
                            <th>Q4</th>
                            <th>Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades_by_subject as $subject): 
                            $quarters = $subject['quarters'];
                            $avg = calculateAverage(array_values($quarters));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo isset($quarters['Q1']) ? number_format($quarters['Q1'], 2) : '-'; ?></td>
                                <td><?php echo isset($quarters['Q2']) ? number_format($quarters['Q2'], 2) : '-'; ?></td>
                                <td><?php echo isset($quarters['Q3']) ? number_format($quarters['Q3'], 2) : '-'; ?></td>
                                <td><?php echo isset($quarters['Q4']) ? number_format($quarters['Q4'], 2) : '-'; ?></td>
                                <td><strong><?php echo number_format($avg, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

