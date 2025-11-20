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
$stmt = $conn->prepare("SELECT s.id, s.student_number, s.section_id, sec.section_name, sec.grade_level 
                        FROM students s 
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$student_id = $student['id'] ?? null;
$stmt->close();

if (!$student_id) {
    die("Student profile not found. Please contact administrator.");
}

// Get grades summary
$grades_summary = [];
$stmt = $conn->prepare("
    SELECT g.subject_id, s.subject_name,
           AVG(CASE WHEN g.grading_period = 'Q1' THEN g.grade_value END) as q1,
           AVG(CASE WHEN g.grading_period = 'Q2' THEN g.grade_value END) as q2,
           AVG(CASE WHEN g.grading_period = 'Q3' THEN g.grade_value END) as q3,
           AVG(CASE WHEN g.grading_period = 'Q4' THEN g.grade_value END) as q4,
           AVG(g.grade_value) as average
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    GROUP BY g.subject_id, s.subject_name
    ORDER BY s.subject_name
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $grades_summary[] = $row;
}
$stmt->close();

// Get attendance summary
$attendance_summary = [];
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$total_attendance = 0;
while ($row = $result->fetch_assoc()) {
    $attendance_summary[$row['status']] = $row['count'];
    $total_attendance += $row['count'];
}
$stmt->close();

$page_title = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="nav" style="display: none;">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php" class="active">Dashboard</a></li>
            <li class="nav-item"><a href="grades.php">My Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Teacher Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Performance Report</a></li>
        </ul>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Student Information</h2>
        </div>
        <div class="grid grid-2">
            <div>
                <strong>LRN:</strong> <?php echo htmlspecialchars($student['student_number']); ?>
            </div>
            <div>
                <strong>Section:</strong> <?php echo htmlspecialchars($student['section_name'] ?? 'Not assigned'); ?>
            </div>
            <div>
                <strong>Grade Level:</strong> <?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?>
            </div>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($grades_summary); ?></div>
            <div class="stat-label">Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $attendance_summary['Present'] ?? 0; ?></div>
            <div class="stat-label">Days Present</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $attendance_summary['Absent'] ?? 0; ?></div>
            <div class="stat-label">Days Absent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $attendance_summary['Tardy'] ?? 0; ?></div>
            <div class="stat-label">Days Tardy</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grades Overview</h2>
        </div>
        <?php if (empty($grades_summary)): ?>
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
                        <?php foreach ($grades_summary as $grade): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo $grade['q1'] ? number_format($grade['q1'], 2) : '-'; ?></td>
                                <td><?php echo $grade['q2'] ? number_format($grade['q2'], 2) : '-'; ?></td>
                                <td><?php echo $grade['q3'] ? number_format($grade['q3'], 2) : '-'; ?></td>
                                <td><?php echo $grade['q4'] ? number_format($grade['q4'], 2) : '-'; ?></td>
                                <td><strong><?php echo number_format($grade['average'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

