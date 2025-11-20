<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('parent');

$db = Database::getInstance();
$conn = $db->getConnection();
$parent_user_id = $auth->getUserId();

// Get linked children
$children = getParentStudents($parent_user_id);

if (empty($children)) {
    redirect('dashboard.php');
}

// Get selected student
$selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

// Verify parent has access to selected student
$has_access = false;
if ($selected_student_id) {
    foreach ($children as $child) {
        if ($child['id'] == $selected_student_id) {
            $has_access = true;
            break;
        }
    }
}

// If no selection or invalid, use first child
if (!$has_access && !empty($children)) {
    $selected_student_id = $children[0]['id'];
    $has_access = true;
}

if (!$has_access) {
    die("Access denied.");
}

// Get selected student details
$selected_student = null;
foreach ($children as $child) {
    if ($child['id'] == $selected_student_id) {
        $selected_student = $child;
        break;
    }
}

// Get student user details
$stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, s.student_number, s.section_id, sec.section_name, sec.grade_level
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $selected_student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_info = $result->fetch_assoc();
$section_id = $student_info['section_id'];
$stmt->close();

// Get all grades
$grades = [];
$stmt = $conn->prepare("
    SELECT g.*, s.subject_name
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ?
    ORDER BY s.subject_name, g.grading_period
");
$stmt->bind_param("i", $selected_student_id);
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

// Get attendance summary
$attendance_summary = [];
$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $selected_student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance_summary[$row['status']] = $row['count'];
}
$stmt->close();

// Get recent attendance
$recent_attendance = [];
$stmt = $conn->prepare("
    SELECT attendance_date, status, remarks
    FROM attendance
    WHERE student_id = ?
    ORDER BY attendance_date DESC
    LIMIT 20
");
$stmt->bind_param("i", $selected_student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_attendance[] = $row;
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
$stmt->bind_param("i", $selected_student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $remarks[] = $row;
}
$stmt->close();

// Get performance summary
$performance_summary = getStudentPerformanceSummary($selected_student_id);

// Calculate overall average across all subjects
$overall_average = $performance_summary['overall_average'];

// Count how many subjects have grades
$subjects_with_grades = 0;
foreach ($grades_by_subject as $subject) {
    if (!empty($subject['quarters'])) {
        $subjects_with_grades++;
    }
}

// Get total number of subjects assigned to the student's section
$total_subjects = 0;
if ($section_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT tss.subject_id) as total_subjects
        FROM teacher_subject_sections tss
        WHERE tss.section_id = ?
    ");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_subjects = $row['total_subjects'] ?? 0;
    $stmt->close();
} else {
    // If no section, use count of subjects with grades
    $total_subjects = count($grades_by_subject);
}

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

$page_title = 'Student Performance Report';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Student Performance Report</h1>
    
    <div class="nav no-print">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="reports.php" class="active">View Reports</a></li>
            <li class="nav-item"><a href="add_child.php">Add Child</a></li>
        </ul>
    </div>
    
    <?php if (count($children) > 1): ?>
        <div class="card no-print">
            <div class="card-header">
                <h2 class="card-title">Select Child</h2>
            </div>
            <form method="GET" action="">
                <div class="form-group">
                    <select name="student_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['id']; ?>" <?php echo ($selected_student_id == $child['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (' . $child['student_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="printReport()" class="btn btn-primary">Print Report</button>
        <p style="margin-top: 10px; font-size: 0.9em; color: #6b7280;">
            <strong>Note:</strong> All data shown here is updated by your child's teachers. Grades, attendance, and remarks are updated in real-time as teachers make changes.
        </p>
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
            <div><strong>Name:</strong> <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></div>
            <div><strong>LRN:</strong> <?php echo htmlspecialchars($student_info['student_number']); ?></div>
            <div><strong>Section:</strong> <?php echo htmlspecialchars($student_info['section_name'] ?? 'Not assigned'); ?></div>
            <div><strong>Grade Level:</strong> <?php echo htmlspecialchars($student_info['grade_level'] ?? 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stats-grid">
        <?php if ($subjects_with_grades > 0 && $subjects_with_grades >= $total_subjects): ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="stat-value" style="font-size: 2.5em; color: white;">
                <?php echo number_format($overall_average, 1); ?>
            </div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">Overall Average (GPA)</div>
            <div style="font-size: 0.9em; margin-top: 5px; color: rgba(255,255,255,0.8);">
                <?php echo getGradeLetter($overall_average); ?> Grade
            </div>
        </div>
        <?php else: ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); color: white;">
            <div class="stat-value" style="font-size: 2.5em; color: white;">
                -
            </div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">Overall Average (GPA)</div>
            <div style="font-size: 0.9em; margin-top: 5px; color: rgba(255,255,255,0.8);">
                <?php if ($subjects_with_grades == 0): ?>
                    No grades available yet
                <?php else: ?>
                    Incomplete grades (<?php echo $subjects_with_grades; ?> of <?php echo $total_subjects; ?> subjects)
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <div class="stat-value" style="font-size: 2.5em; color: white;">
                <?php echo number_format($performance_summary['attendance_rate'], 1); ?>%
            </div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">Attendance Rate</div>
            <div style="font-size: 0.9em; margin-top: 5px; color: rgba(255,255,255,0.8);">
                <?php echo $performance_summary['present_count']; ?> of <?php echo $performance_summary['total_attendance']; ?> days
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($grades_by_subject); ?></div>
            <div class="stat-label">Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #10b981;"><?php echo $attendance_summary['Present'] ?? 0; ?></div>
            <div class="stat-label">Days Present</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #ef4444;"><?php echo $attendance_summary['Absent'] ?? 0; ?></div>
            <div class="stat-label">Days Absent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f59e0b;"><?php echo $attendance_summary['Tardy'] ?? 0; ?></div>
            <div class="stat-label">Days Tardy</div>
        </div>
    </div>
    
    <!-- Last Update Information -->
    <?php if ($performance_summary['last_grade_update'] || $performance_summary['last_attendance_update'] || $performance_summary['last_remark_update']): ?>
        <div class="card" style="background-color: #f9fafb; margin-top: 20px;">
            <div class="card-header">
                <h2 class="card-title">Last Updated Information</h2>
            </div>
            <div class="grid grid-3">
                <?php if ($performance_summary['last_grade_update']): ?>
                    <div>
                        <p><strong>Grades:</strong> <?php echo formatDate($performance_summary['last_grade_update'], 'M d, Y h:i A'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($performance_summary['last_attendance_update']): ?>
                    <div>
                        <p><strong>Attendance:</strong> <?php echo formatDate($performance_summary['last_attendance_update'], 'M d, Y h:i A'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($performance_summary['last_remark_update']): ?>
                    <div>
                        <p><strong>Remarks:</strong> <?php echo formatDate($performance_summary['last_remark_update'], 'M d, Y h:i A'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grades by Subject</h2>
            <?php if ($subjects_with_grades > 0 && $subjects_with_grades >= $total_subjects): ?>
            <p style="font-size: 0.9em; color: #6b7280; margin-top: 5px;">
                Overall Performance: <strong><?php echo number_format($overall_average, 2); ?></strong> 
                (<?php echo getGradeLetter($overall_average); ?> Grade)
            </p>
            <?php else: ?>
            <p style="font-size: 0.9em; color: #6b7280; margin-top: 5px;">
                <?php if ($subjects_with_grades == 0): ?>
                    <span style="color: #9ca3af;">Overall Performance: No grades available yet</span>
                <?php else: ?>
                    <span style="color: #f59e0b;">Overall Performance: Incomplete (<?php echo $subjects_with_grades; ?> of <?php echo $total_subjects; ?> subjects with grades)</span>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <?php if (empty($grades_by_subject)): ?>
            <div class="empty-state">No grades available yet. Grades will appear here once teachers update them.</div>
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
                            $grade_color = $avg >= 90 ? '#10b981' : ($avg >= 80 ? '#3b82f6' : ($avg >= 70 ? '#f59e0b' : ($avg >= 60 ? '#f97316' : '#ef4444')));
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                <td><?php echo isset($quarters['Q1']) ? number_format($quarters['Q1'], 2) : '<span style="color: #9ca3af;">-</span>'; ?></td>
                                <td><?php echo isset($quarters['Q2']) ? number_format($quarters['Q2'], 2) : '<span style="color: #9ca3af;">-</span>'; ?></td>
                                <td><?php echo isset($quarters['Q3']) ? number_format($quarters['Q3'], 2) : '<span style="color: #9ca3af;">-</span>'; ?></td>
                                <td><?php echo isset($quarters['Q4']) ? number_format($quarters['Q4'], 2) : '<span style="color: #9ca3af;">-</span>'; ?></td>
                                <td><strong style="color: <?php echo $grade_color; ?>;"><?php echo number_format($avg, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
            <h2 class="card-title">Attendance Summary</h2>
            <p style="font-size: 0.9em; color: #6b7280; margin-top: 5px;">
                Attendance Rate: <strong><?php echo number_format($performance_summary['attendance_rate'], 2); ?>%</strong>
                (<?php echo $performance_summary['present_count']; ?> Present out of <?php echo $performance_summary['total_attendance']; ?> total days)
            </p>
        </div>
        <?php if (empty($recent_attendance)): ?>
            <div class="empty-state">No attendance records available. Attendance will appear here once teachers mark it.</div>
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
                        <?php foreach ($recent_attendance as $att): ?>
                            <tr>
                                <td><?php echo formatDate($att['attendance_date'], 'M d, Y'); ?></td>
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
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Remarks & Feedback</h2>
            <p style="font-size: 0.9em; color: #6b7280; margin-top: 5px;">
                Qualitative feedback from teachers about your child's performance
            </p>
        </div>
        <?php if (empty($remarks)): ?>
            <div class="empty-state">No remarks available yet. Remarks will appear here once teachers add them.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Grading Period</th>
                            <th>Teacher</th>
                            <th>Remark</th>
                            <th>Date</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remarks as $remark): ?>
                            <tr>
                                <td><strong><?php echo getGradingPeriodName($remark['grading_period']); ?></strong></td>
                                <td><?php echo htmlspecialchars($remark['teacher_first'] . ' ' . $remark['teacher_last']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($remark['remark_text'])); ?></td>
                                <td><?php echo formatDate($remark['created_at'], 'M d, Y'); ?></td>
                                <td>
                                    <?php if ($remark['updated_at'] != $remark['created_at']): ?>
                                        <?php echo formatDate($remark['updated_at'], 'M d, Y h:i A'); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
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

