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
$upload_results = [];

$edit_assignment = null;

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload_teachers') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 4) continue; // Skip incomplete rows
            
            $employee_number = trim($data[0]);
            $first_name = trim($data[1]);
            $last_name = trim($data[2]);
            $email = trim($data[3]);
            
            if (empty($employee_number) || empty($first_name) || empty($last_name) || empty($email)) {
                $error_count++;
                $errors[] = "Row skipped: Missing required fields (Employee Number, First Name, Last Name, Email)";
                continue;
            }
            
            // Generate username from email
            $username = explode('@', $email)[0];
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            
            // Check if username already exists, append number if needed
            $original_username = $username;
            $counter = 1;
            while (true) {
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows == 0) {
                    break;
                }
                $username = $original_username . $counter;
                $counter++;
                $check_stmt->close();
            }
            if (isset($check_stmt)) $check_stmt->close();
            
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows > 0) {
                $error_count++;
                $errors[] = "Email already exists: $email";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Check if employee number already exists
            $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE employee_number = ?");
            $check_stmt->bind_param("s", $employee_number);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows > 0) {
                $error_count++;
                $errors[] = "Employee number already exists: $employee_number";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Create user account
            $auth = new Auth();
            $user_id = $auth->createUser($username, $email, DEFAULT_PASSWORD, 'teacher', $first_name, $last_name);
            
            if ($user_id) {
                // Create teacher record
                $stmt = $conn->prepare("INSERT INTO teachers (user_id, employee_number) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $employee_number);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Failed to create teacher record for: $first_name $last_name";
                    // Delete user if teacher creation failed
                    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->bind_param("i", $user_id);
                    $del_stmt->execute();
                    $del_stmt->close();
                }
                $stmt->close();
            } else {
                $error_count++;
                $errors[] = "Failed to create user account for: $first_name $last_name";
            }
        }
        
        fclose($handle);
        
        $message = "Bulk upload completed. Success: $success_count, Errors: $error_count";
        $message_type = $error_count > 0 ? 'warning' : 'success';
        $upload_results = [
            'success' => $success_count,
            'errors' => $error_count,
            'error_details' => array_slice($errors, 0, 10) // Show first 10 errors
        ];
    } else {
        $message = 'Please select a valid CSV file.';
        $message_type = 'error';
    }
}

// Handle teacher-subject-section assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'bulk_upload_teachers') {
    if ($_POST['action'] === 'assign') {
        $teacher_id = intval($_POST['teacher_id']);
        $subject_id = intval($_POST['subject_id']);
        $section_id = intval($_POST['section_id']);
        $school_year = sanitize($_POST['school_year']);
        
        // Check for duplicate assignment
        $check_stmt = $conn->prepare("SELECT id FROM teacher_subject_sections WHERE teacher_id = ? AND subject_id = ? AND section_id = ? AND LOWER(TRIM(school_year)) = LOWER(TRIM(?))");
        $check_stmt->bind_param("iiis", $teacher_id, $subject_id, $section_id, $school_year);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'This teacher is already assigned to this subject, section, and school year.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO teacher_subject_sections (teacher_id, subject_id, section_id, school_year) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $teacher_id, $subject_id, $section_id, $school_year);
            
            if ($stmt->execute()) {
                $message = 'Assignment created successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to create assignment: ' . htmlspecialchars($conn->error);
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'edit_assignment') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM teacher_subject_sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_assignment = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_POST['action'] === 'update_assignment') {
        $id = intval($_POST['id']);
        $teacher_id = intval($_POST['teacher_id']);
        $subject_id = intval($_POST['subject_id']);
        $section_id = intval($_POST['section_id']);
        $school_year = sanitize($_POST['school_year']);
        
        $stmt = $conn->prepare("UPDATE teacher_subject_sections SET teacher_id = ?, subject_id = ?, section_id = ?, school_year = ? WHERE id = ?");
        $stmt->bind_param("iiisi", $teacher_id, $subject_id, $section_id, $school_year, $id);
        
        if ($stmt->execute()) {
            $message = 'Assignment updated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Failed to update assignment.';
            $message_type = 'error';
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete_assignment') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM teacher_subject_sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Assignment deleted successfully.';
            $message_type = 'success';
        }
        $stmt->close();
    }
}

// Get all teachers
$teachers = [];
$result = $conn->query("
    SELECT t.*, u.first_name, u.last_name, u.email
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.last_name, u.first_name
");
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

// Get assignments
$assignments = [];
$result = $conn->query("
    SELECT tss.*, 
           u.first_name as teacher_first, u.last_name as teacher_last,
           s.subject_name,
           sec.section_name, sec.grade_level
    FROM teacher_subject_sections tss
    JOIN teachers t ON tss.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    JOIN subjects s ON tss.subject_id = s.id
    JOIN sections sec ON tss.section_id = sec.id
    ORDER BY u.last_name, sec.grade_level, sec.section_name
");
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

// Get subjects and sections for dropdown
$subjects = [];
$result = $conn->query("SELECT id, subject_name FROM subjects ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$sections = [];
$result = $conn->query("SELECT id, section_name, grade_level, school_year FROM sections ORDER BY grade_level, section_name");
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$page_title = 'Manage Teachers';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Teachers</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php" class="active">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
            <?php if (!empty($upload_results) && !empty($upload_results['error_details'])): ?>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; font-weight: bold;">View Error Details</summary>
                    <ul style="margin-top: 10px; padding-left: 20px;">
                        <?php foreach ($upload_results['error_details'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Bulk Upload Teachers</h2>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_upload_teachers">
            <div class="form-group">
                <label>Upload CSV File *</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    CSV format: Employee Number, First Name, Last Name, Email<br>
                    Default password for all teachers: <strong><?php echo DEFAULT_PASSWORD; ?></strong><br>
                    <a href="javascript:void(0)" onclick="downloadTemplate('teachers')" style="color: var(--primary-color);">Download CSV Template</a>
                </small>
            </div>
            <button type="submit" class="btn btn-primary">Upload Teachers</button>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Teachers</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee Number</th>
                        <th>Name</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr><td colspan="4" class="empty-state">No teachers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo $teacher['id']; ?></td>
                                <td><?php echo htmlspecialchars($teacher['employee_number']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($edit_assignment): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Teacher Assignment</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_assignment">
                <input type="hidden" name="id" value="<?php echo $edit_assignment['id']; ?>">
                <div class="grid grid-4">
                    <div class="form-group">
                        <label>Teacher *</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($edit_assignment['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo ($edit_assignment['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section *</label>
                        <select name="section_id" class="form-control" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo ($edit_assignment['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year *</label>
                        <input type="text" name="school_year" class="form-control" value="<?php echo htmlspecialchars($edit_assignment['school_year']); ?>" required placeholder="e.g., 2024-2025">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Assignment</button>
                    <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Assign Teacher to Subject & Section</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="grid grid-4">
                    <div class="form-group">
                        <label>Teacher *</label>
                        <select name="teacher_id" class="form-control" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section *</label>
                        <select name="section_id" class="form-control" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>">
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year *</label>
                        <input type="text" name="school_year" class="form-control" required placeholder="e.g., 2024-2025">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Assignment</button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Assignments</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Section</th>
                        <th>Grade Level</th>
                        <th>School Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="6" class="empty-state">No assignments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['teacher_first'] . ' ' . $assignment['teacher_last']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['section_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['grade_level']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['school_year']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="edit_assignment">
                                        <input type="hidden" name="id" value="<?php echo $assignment['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this assignment?');">
                                        <input type="hidden" name="action" value="delete_assignment">
                                        <input type="hidden" name="id" value="<?php echo $assignment['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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

<script>
function downloadTemplate(type) {
    let csvContent = '';
    if (type === 'teachers') {
        csvContent = 'Employee Number,First Name,Last Name,Email\n';
        csvContent += 'EMP001,John,Doe,john.doe@school.edu\n';
        csvContent += 'EMP002,Jane,Smith,jane.smith@school.edu\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'teachers_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

