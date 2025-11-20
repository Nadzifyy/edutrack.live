<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('administrator');

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$message_type = '';
$edit_student = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'edit') {
        $student_id = intval($_POST['student_id']);
        $stmt = $conn->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_student = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_POST['action'] === 'update') {
        $student_id = intval($_POST['student_id']);
        $student_number = sanitize($_POST['student_number']);
        $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
        
        // Check for duplicate LRN (excluding current student)
        $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_number = ? AND id != ?");
        $check_stmt->bind_param("si", $student_number, $student_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'LRN already exists. Please use a different LRN.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Validate section_id if provided
            if ($section_id !== null) {
                $check_stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
                $check_stmt->bind_param("i", $section_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows === 0) {
                    $section_id = null;
                }
                $check_stmt->close();
            }
            
            $stmt = $conn->prepare("UPDATE students SET student_number = ?, section_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $student_number, $section_id, $student_id);
            
            if ($stmt->execute()) {
                $message = 'Student updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update student: ' . htmlspecialchars($conn->error);
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Get all students
$students = [];
$result = $conn->query("
    SELECT s.*, u.first_name, u.last_name, u.email, sec.section_name, sec.grade_level
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    ORDER BY u.last_name, u.first_name
");
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Get sections for dropdown
$sections = [];
$result = $conn->query("SELECT id, section_name, grade_level, school_year FROM sections ORDER BY grade_level, section_name");
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$page_title = 'Manage Students';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Students</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php" class="active">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($edit_student): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Student</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" value="<?php echo $edit_student['id']; ?>">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_student['first_name'] . ' ' . $edit_student['last_name']); ?>" disabled>
                        <small>Name can be changed in Manage Users section.</small>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_student['email']); ?>" disabled>
                        <small>Email can be changed in Manage Users section.</small>
                    </div>
                    <div class="form-group">
                        <label>LRN *</label>
                        <input type="text" name="student_number" class="form-control" value="<?php echo htmlspecialchars($edit_student['student_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" class="form-control">
                            <option value="">Not assigned</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo ($edit_student['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level'] . ' (' . $section['school_year'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Student</button>
                    <a href="students.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Students</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>LRN</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Section</th>
                        <th>Grade Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="7" class="empty-state">No students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['section_name'] ?? 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
