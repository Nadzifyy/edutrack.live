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

$message = '';
$message_type = '';

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'save') {
        $student_id = intval($_POST['student_id']);
        $subject_id = intval($_POST['subject_id']);
        $grading_period = sanitize($_POST['grading_period']);
        $grade_value = floatval($_POST['grade_value']);
        
        if ($grade_value >= 0 && $grade_value <= 100) {
            $stmt = $conn->prepare("
                INSERT INTO grades (student_id, subject_id, teacher_id, grading_period, grade_value)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE grade_value = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param("iiisdd", $student_id, $subject_id, $teacher_id, $grading_period, $grade_value, $grade_value);
            
            if ($stmt->execute()) {
                $message = 'Grade saved successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to save grade.';
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Grade must be between 0 and 100.';
            $message_type = 'error';
        }
    }
}

// Get assigned subjects and sections
$assignments = [];
$stmt = $conn->prepare("
    SELECT tss.*, s.subject_name, sec.section_name, sec.grade_level
    FROM teacher_subject_sections tss
    JOIN subjects s ON tss.subject_id = s.id
    JOIN sections sec ON tss.section_id = sec.id
    WHERE tss.teacher_id = ?
    ORDER BY sec.grade_level, sec.section_name, s.subject_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

// Get selected assignment
$selected_assignment = null;
$students = [];
$grades = [];

if (isset($_GET['assignment_id'])) {
    $assignment_id = intval($_GET['assignment_id']);
    $selected_assignment = array_filter($assignments, function($a) use ($assignment_id) {
        return $a['id'] == $assignment_id;
    });
    $selected_assignment = reset($selected_assignment);
    
    if ($selected_assignment) {
        // Get students in this section
        $stmt = $conn->prepare("
            SELECT s.id, s.student_number, u.first_name, u.last_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.section_id = ?
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->bind_param("i", $selected_assignment['section_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        
        // Get existing grades
        $stmt = $conn->prepare("
            SELECT student_id, grading_period, grade_value
            FROM grades
            WHERE student_id IN (" . implode(',', array_column($students, 'id')) . ")
            AND subject_id = ? AND teacher_id = ?
        ");
        $stmt->bind_param("ii", $selected_assignment['subject_id'], $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grades[$row['student_id']][$row['grading_period']] = $row['grade_value'];
        }
        $stmt->close();
    }
}

$page_title = 'Manage Grades';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Grades</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="classes.php">My Classes</a></li>
            <li class="nav-item"><a href="grades.php" class="active">Manage Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Select Class</h2>
        </div>
        <form method="GET" action="">
            <div class="form-group">
                <label>Select Subject & Section</label>
                <select name="assignment_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">-- Select --</option>
                    <?php foreach ($assignments as $assignment): ?>
                        <option value="<?php echo $assignment['id']; ?>" <?php echo (isset($_GET['assignment_id']) && $_GET['assignment_id'] == $assignment['id']) ? 'selected' : ''; ?>>
                            <?php echo displayText($assignment['subject_name'] . ' - ' . $assignment['section_name'] . ' (Grade ' . $assignment['grade_level'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    
    <?php if ($selected_assignment && !empty($students)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?php echo displayText($selected_assignment['subject_name']); ?> - 
                    <?php echo displayText($selected_assignment['section_name']); ?>
                </h2>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Student Name</th>
                            <th>Q1</th>
                            <th>Q2</th>
                            <th>Q3</th>
                            <th>Q4</th>
                            <th>Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <?php
                                $student_grades = [];
                                foreach (getGradingPeriods() as $period):
                                    $grade = $grades[$student['id']][$period] ?? null;
                                    $student_grades[] = $grade;
                                ?>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="saveGrade(event, this)">
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="subject_id" value="<?php echo $selected_assignment['subject_id']; ?>">
                                            <input type="hidden" name="grading_period" value="<?php echo $period; ?>">
                                            <input type="number" name="grade_value" 
                                                   value="<?php echo $grade !== null ? $grade : ''; ?>" 
                                                   min="0" max="100" step="0.01" 
                                                   style="width: 80px; padding: 4px;"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <strong>
                                        <?php 
                                        $valid_grades = array_filter($student_grades, function($g) { return $g !== null; });
                                        echo !empty($valid_grades) ? number_format(calculateAverage($valid_grades), 2) : '-';
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($selected_assignment && empty($students)): ?>
        <div class="card">
            <div class="empty-state">No students found in this section.</div>
        </div>
    <?php endif; ?>
</div>

<script>
function saveGrade(event, form) {
    event.preventDefault();
    form.submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

