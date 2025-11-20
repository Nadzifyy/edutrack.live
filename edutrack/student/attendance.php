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

// Get attendance
$attendance = [];
$stmt = $conn->prepare("
    SELECT attendance_date, status, remarks
    FROM attendance
    WHERE student_id = ?
    ORDER BY attendance_date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}
$stmt->close();

// Get summary
$summary = ['Present' => 0, 'Absent' => 0, 'Tardy' => 0];
foreach ($attendance as $att) {
    $summary[$att['status']] = ($summary[$att['status']] ?? 0) + 1;
}

$page_title = 'My Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>My Attendance</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="grades.php">My Grades</a></li>
            <li class="nav-item"><a href="attendance.php" class="active">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Teacher Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Performance Report</a></li>
        </ul>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary['Present']; ?></div>
            <div class="stat-label">Days Present</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary['Absent']; ?></div>
            <div class="stat-label">Days Absent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summary['Tardy']; ?></div>
            <div class="stat-label">Days Tardy</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($attendance); ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Attendance Records</h2>
        </div>
        <?php if (empty($attendance)): ?>
            <div class="empty-state">No attendance records available.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $att): ?>
                            <tr>
                                <td><?php echo formatDate($att['attendance_date'], 'F d, Y'); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $att['status'] == 'Present' ? 'success' : 
                                            ($att['status'] == 'Absent' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo htmlspecialchars($att['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($att['remarks'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

