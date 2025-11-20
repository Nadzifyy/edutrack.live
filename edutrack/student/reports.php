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
$stmt = $conn->prepare("SELECT s.id, s.student_number, s.section_id, sec.section_name, sec.grade_level,
                        u.first_name, u.last_name
                        FROM students s 
                        JOIN users u ON s.user_id = u.id
                        LEFT JOIN sections sec ON s.section_id = sec.id
                        WHERE s.user_id = ?");
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
    SELECT g.*, s.subject_name, t.employee_number,
           u.first_name as teacher_first, u.last_name as teacher_last
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    JOIN teachers t ON g.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
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

// Organize grades by subject
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

// Get attendance
$attendance = [];
$stmt = $conn->prepare("
    SELECT attendance_date, status, remarks
    FROM attendance
    WHERE student_id = ?
    ORDER BY attendance_date DESC
    LIMIT 50
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}
$stmt->close();

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

// Prepare chart data
$chart_labels = [];
$chart_data = [];
foreach ($grades_by_subject as $subject) {
    $chart_labels[] = $subject['subject_name'];
    $avg = 0;
    $count = 0;
    foreach (getGradingPeriods() as $period) {
        if (isset($subject['quarters'][$period])) {
            $avg += $subject['quarters'][$period];
            $count++;
        }
    }
    $chart_data[] = $count > 0 ? round($avg / $count, 2) : 0;
}

$page_title = 'Performance Report';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Performance Report</h1>
    
    <div class="nav no-print">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="grades.php">My Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Teacher Remarks</a></li>
            <li class="nav-item"><a href="reports.php" class="active">Performance Report</a></li>
        </ul>
    </div>
    
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="printReport()" class="btn btn-primary">Print Report</button>
    </div>
    
    <!-- Print Header -->
    <div class="print-only" style="text-align: center; margin-bottom: 30px; padding: 20px; border-bottom: 2px solid #000; display: none;">
        <img src="<?php echo baseUrl('LOGO.png'); ?>" alt="<?php echo SCHOOL_NAME; ?> Logo" style="max-width: 120px; height: auto; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;">
        <h1 style="font-size: 24px; font-weight: bold; margin-bottom: 5px;"><?php echo SCHOOL_NAME; ?></h1>
        <p style="font-size: 14px; color: #666;">Student Performance Report</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Student Information</h2>
        </div>
        <div class="grid grid-2">
            <div><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            <div><strong>LRN:</strong> <?php echo htmlspecialchars($student['student_number']); ?></div>
            <div><strong>Section:</strong> <?php echo htmlspecialchars($student['section_name'] ?? 'Not assigned'); ?></div>
            <div><strong>Grade Level:</strong> <?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grades by Subject</h2>
        </div>
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
    </div>
    
    <?php if (!empty($chart_data)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Performance Trend</h2>
            </div>
            <canvas id="performanceChart" style="max-height: 400px;"></canvas>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Remarks</h2>
        </div>
        <?php if (empty($remarks)): ?>
            <div class="empty-state">No remarks available.</div>
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

<?php if (!empty($chart_data)): ?>
<script>
const ctx = document.getElementById('performanceChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Average Grade',
            data: <?php echo json_encode($chart_data); ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.5)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

