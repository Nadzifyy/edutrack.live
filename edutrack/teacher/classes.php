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
$upload_results = [];

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload_students') {
    $section_id = intval($_POST['section_id']);
    
    // Verify teacher is assigned to this section
    $check_stmt = $conn->prepare("SELECT id FROM teacher_subject_sections WHERE teacher_id = ? AND section_id = ?");
    $check_stmt->bind_param("ii", $teacher_id, $section_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows == 0) {
        $message = 'You are not assigned to this section.';
        $message_type = 'error';
        $check_stmt->close();
    } else {
        $check_stmt->close();
        
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
                
                $lrn = trim($data[0]);
                $first_name = trim($data[1]);
                $last_name = trim($data[2]);
                $email = trim($data[3]);
                
                if (empty($lrn) || empty($first_name) || empty($last_name) || empty($email)) {
                    $error_count++;
                    $errors[] = "Row skipped: Missing required fields (LRN, First Name, Last Name, Email)";
                    continue;
                }
                
                // Check if LRN already exists
                $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_number = ?");
                $check_stmt->bind_param("s", $lrn);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows > 0) {
                    $error_count++;
                    $errors[] = "LRN already exists: $lrn";
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();
                
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
                
                // Create user account
                $auth = new Auth();
                $user_id = $auth->createUser($username, $email, DEFAULT_PASSWORD, 'student', $first_name, $last_name);
                
                if ($user_id) {
                    // Create student record
                    $stmt = $conn->prepare("INSERT INTO students (user_id, student_number, section_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $user_id, $lrn, $section_id);
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Failed to create student record for: $first_name $last_name";
                        // Delete user if student creation failed
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
}

// Get assigned sections for dropdown
$assigned_sections = [];
$stmt = $conn->prepare("
    SELECT DISTINCT sec.id, sec.section_name, sec.grade_level, sec.school_year
    FROM teacher_subject_sections tss
    JOIN sections sec ON tss.section_id = sec.id
    WHERE tss.teacher_id = ?
    ORDER BY sec.grade_level, sec.section_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assigned_sections[] = $row;
}
$stmt->close();

// Get assigned classes
$classes = [];
$stmt = $conn->prepare("
    SELECT tss.*, s.subject_name, sec.section_name, sec.grade_level, sec.school_year,
           COUNT(DISTINCT st.id) as student_count
    FROM teacher_subject_sections tss
    JOIN subjects s ON tss.subject_id = s.id
    JOIN sections sec ON tss.section_id = sec.id
    LEFT JOIN students st ON sec.id = st.section_id
    WHERE tss.teacher_id = ?
    GROUP BY tss.id, s.subject_name, sec.section_name, sec.grade_level, sec.school_year
    ORDER BY sec.grade_level, sec.section_name, s.subject_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

$page_title = 'My Classes';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>My Classes</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="classes.php" class="active">My Classes</a></li>
            <li class="nav-item"><a href="grades.php">Manage Grades</a></li>
            <li class="nav-item"><a href="attendance.php">Attendance</a></li>
            <li class="nav-item"><a href="remarks.php">Remarks</a></li>
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
    
    <?php if (!empty($assigned_sections)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Bulk Upload Students</h2>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_upload_students">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Select Section *</label>
                    <select name="section_id" class="form-control" required>
                        <option value="">Select Section</option>
                        <?php foreach ($assigned_sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level'] . ' (' . $section['school_year'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">Only sections you are assigned to are available.</small>
                </div>
                <div class="form-group">
                    <label>Upload CSV File *</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        CSV format: LRN, First Name, Last Name, Email<br>
                        Default password for all students: <strong><?php echo DEFAULT_PASSWORD; ?></strong><br>
                        <a href="javascript:void(0)" onclick="downloadTemplate('students')" style="color: var(--primary-color);">Download CSV Template</a>
                    </small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Upload Students</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Assigned Classes</h2>
        </div>
        <?php if (empty($classes)): ?>
            <div class="empty-state">No classes assigned yet. Please contact administrator.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Grade Level</th>
                            <th>School Year</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo displayText($class['subject_name']); ?></td>
                                <td><?php echo displayText($class['section_name']); ?></td>
                                <td><?php echo displayText($class['grade_level']); ?></td>
                                <td><?php echo displayText($class['school_year']); ?></td>
                                <td><?php echo $class['student_count']; ?></td>
                                <td>
                                    <a href="grades.php?assignment_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">Manage Grades</a>
                                    <a href="attendance.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-secondary btn-sm">Attendance</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadTemplate(type) {
    let csvContent = '';
    if (type === 'students') {
        csvContent = 'LRN,First Name,Last Name,Email\n';
        csvContent += '2024-0001,Juan,Delacruz,juan.delacruz@student.edu\n';
        csvContent += '2024-0002,Maria,Santos,maria.santos@student.edu\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

