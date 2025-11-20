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

// Get remarks
$remarks = [];
$stmt = $conn->prepare("
    SELECT tr.*, u.first_name as teacher_first, u.last_name as teacher_last
    FROM teacher_remarks tr
    JOIN teachers t ON tr.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE tr.student_id = ?
    ORDER BY tr.grading_period, tr.created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $remarks[] = $row;
}
$stmt->close();

$page_title = 'Teacher Remarks';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Teacher Remarks</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="grades.php">My Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php" class="active">Teacher Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Performance Report</a></li>
        </ul>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Feedback</h2>
        </div>
        <?php if (empty($remarks)): ?>
            <div class="empty-state">No remarks available from teachers yet.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Grading Period</th>
                            <th>Teacher</th>
                            <th>Remark</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remarks as $remark): ?>
                            <tr>
                                <td><?php echo getGradingPeriodName($remark['grading_period']); ?></td>
                                <td><?php echo htmlspecialchars($remark['teacher_first'] . ' ' . $remark['teacher_last']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($remark['remark_text'])); ?></td>
                                <td><?php echo formatDate($remark['created_at'], 'M d, Y'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

