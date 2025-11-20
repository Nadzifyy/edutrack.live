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

// Handle remark submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'save') {
        $student_id = intval($_POST['student_id']);
        $grading_period = sanitize($_POST['grading_period']);
        $remark_text = sanitize($_POST['remark_text']);
        
        if (!empty($remark_text)) {
            $stmt = $conn->prepare("
                INSERT INTO teacher_remarks (student_id, teacher_id, grading_period, remark_text)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE remark_text = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param("iisss", $student_id, $teacher_id, $grading_period, $remark_text, $remark_text);
            
            if ($stmt->execute()) {
                $message = 'Remark saved successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to save remark.';
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Remark text cannot be empty.';
            $message_type = 'error';
        }
    }
}

// Get assigned sections
$sections = [];
$stmt = $conn->prepare("
    SELECT DISTINCT sec.id, sec.section_name, sec.grade_level
    FROM teacher_subject_sections tss
    JOIN sections sec ON tss.section_id = sec.id
    WHERE tss.teacher_id = ?
    ORDER BY sec.grade_level, sec.section_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();

// Get selected section
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$selected_period = isset($_GET['period']) ? sanitize($_GET['period']) : 'Q1';
$students = [];
$remarks = [];

if ($selected_section_id) {
    // Get students
    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, u.first_name, u.last_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.section_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->bind_param("i", $selected_section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get existing remarks
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $conn->prepare("
            SELECT student_id, remark_text
            FROM teacher_remarks
            WHERE student_id IN ($placeholders) AND teacher_id = ? AND grading_period = ?
        ");
        $types = str_repeat('i', count($student_ids)) . 'is';
        $params = array_merge($student_ids, [$teacher_id, $selected_period]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $remarks[$row['student_id']] = $row['remark_text'];
        }
        $stmt->close();
    }
}

$page_title = 'Teacher Remarks';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Teacher Remarks</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="classes.php">My Classes</a></li>
            <li class="nav-item"><a href="grades.php">Manage Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php" class="active">Remarks</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Select Class and Grading Period</h2>
        </div>
        <form method="GET" action="">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Section</label>
                    <select name="section_id" class="form-control" required onchange="this.form.submit()">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo ($selected_section_id == $section['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Grading Period</label>
                    <select name="period" class="form-control" required onchange="this.form.submit()">
                        <?php foreach (getGradingPeriods() as $period): ?>
                            <option value="<?php echo $period; ?>" <?php echo ($selected_period == $period) ? 'selected' : ''; ?>>
                                <?php echo getGradingPeriodName($period); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($selected_section_id && !empty($students)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Remarks for <?php echo getGradingPeriodName($selected_period); ?></h2>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Student Name</th>
                            <th>Remark</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $remark = $remarks[$student['id']] ?? '';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td>
                                    <form method="POST" action="" id="form-<?php echo $student['id']; ?>">
                                        <input type="hidden" name="action" value="save">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <input type="hidden" name="grading_period" value="<?php echo $selected_period; ?>">
                                        <textarea name="remark_text" class="form-control" rows="2" 
                                                  onchange="document.getElementById('form-<?php echo $student['id']; ?>').submit();"><?php echo htmlspecialchars($remark); ?></textarea>
                                    </form>
                                </td>
                                <td>
                                    <button type="submit" form="form-<?php echo $student['id']; ?>" class="btn btn-primary btn-sm">Save</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($selected_section_id && empty($students)): ?>
        <div class="card">
            <div class="empty-state">No students found in this section.</div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

