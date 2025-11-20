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
$edit_section = null;

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload_sections') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 3) continue; // Skip incomplete rows
            
            $section_name = trim($data[0]);
            $grade_level = trim($data[1]);
            $school_year = trim($data[2]);
            
            if (empty($section_name) || empty($grade_level) || empty($school_year)) {
                $error_count++;
                $errors[] = "Row skipped: Missing required fields (Section Name, Grade Level, School Year)";
                continue;
            }
            
            // Validate grade level (1-6)
            $grade_level_int = intval($grade_level);
            if ($grade_level_int < 1 || $grade_level_int > 6) {
                $error_count++;
                $errors[] = "Invalid grade level: $grade_level (must be 1-6)";
                continue;
            }
            
            // Check for duplicate section (same name, grade level, and school year)
            $check_stmt = $conn->prepare("SELECT id FROM sections WHERE LOWER(TRIM(section_name)) = LOWER(TRIM(?)) AND grade_level = ? AND LOWER(TRIM(school_year)) = LOWER(TRIM(?))");
            $check_stmt->bind_param("sss", $section_name, $grade_level, $school_year);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_count++;
                $errors[] = "Duplicate section: $section_name - Grade $grade_level ($school_year) (already exists)";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert section
            $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, school_year) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $section_name, $grade_level, $school_year);
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Failed to create section: $section_name - Grade $grade_level ($school_year) - " . $conn->error;
            }
            $stmt->close();
        }
        
        fclose($handle);
        
        $message = "Bulk upload completed. Success: $success_count, Errors: $error_count";
        $message_type = $error_count > 0 ? 'warning' : 'success';
        $upload_results = [
            'success' => $success_count,
            'errors' => $error_count,
            'error_details' => array_slice($errors, 0, 10)
        ];
    } else {
        $message = 'Please select a valid CSV file.';
        $message_type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'bulk_upload_sections') {
    if ($_POST['action'] === 'create') {
        $section_name = sanitize($_POST['section_name']);
        $grade_level = sanitize($_POST['grade_level']);
        $school_year = sanitize($_POST['school_year']);
        
        // Validate grade level (1-6)
        $grade_level_int = intval($grade_level);
        if ($grade_level_int < 1 || $grade_level_int > 6) {
            $message = 'Invalid grade level. Must be between 1 and 6.';
            $message_type = 'error';
        } else {
            // Check for duplicate section (same name, grade level, and school year)
            $check_stmt = $conn->prepare("SELECT id FROM sections WHERE LOWER(TRIM(section_name)) = LOWER(TRIM(?)) AND grade_level = ? AND LOWER(TRIM(school_year)) = LOWER(TRIM(?))");
            $check_stmt->bind_param("sss", $section_name, $grade_level, $school_year);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Section with this name, grade level, and school year already exists.';
                $message_type = 'error';
                $check_stmt->close();
            } else {
                $check_stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, school_year) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $section_name, $grade_level, $school_year);
        
        if ($stmt->execute()) {
            $message = 'Section created successfully.';
            $message_type = 'success';
        } else {
                    $message = 'Failed to create section: ' . htmlspecialchars($conn->error);
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_section = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $section_name = sanitize($_POST['section_name']);
        $grade_level = sanitize($_POST['grade_level']);
        $school_year = sanitize($_POST['school_year']);
        
        // Validate grade level (1-6)
        $grade_level_int = intval($grade_level);
        if ($grade_level_int < 1 || $grade_level_int > 6) {
            $message = 'Invalid grade level. Must be between 1 and 6.';
            $message_type = 'error';
        } else {
            // Check for duplicate section (excluding current record)
            $check_stmt = $conn->prepare("SELECT id FROM sections WHERE LOWER(TRIM(section_name)) = LOWER(TRIM(?)) AND grade_level = ? AND LOWER(TRIM(school_year)) = LOWER(TRIM(?)) AND id != ?");
            $check_stmt->bind_param("sssi", $section_name, $grade_level, $school_year, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Section with this name, grade level, and school year already exists.';
                $message_type = 'error';
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                $stmt = $conn->prepare("UPDATE sections SET section_name = ?, grade_level = ?, school_year = ? WHERE id = ?");
                $stmt->bind_param("sssi", $section_name, $grade_level, $school_year, $id);
            
                if ($stmt->execute()) {
                    $message = 'Section updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update section: ' . htmlspecialchars($conn->error);
            $message_type = 'error';
        }
        $stmt->close();
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Section deleted successfully.';
            $message_type = 'success';
        }
        $stmt->close();
    }
}

$sections = [];
$result = $conn->query("SELECT * FROM sections ORDER BY school_year DESC, grade_level, section_name");
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$page_title = 'Manage Sections';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Sections</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php" class="active">Sections</a></li>
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
            <h2 class="card-title">Bulk Upload Sections</h2>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_upload_sections">
            <div class="form-group">
                <label>Upload CSV File *</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    CSV format: Section Name, Grade Level, School Year<br>
                    <a href="javascript:void(0)" onclick="downloadTemplate('sections')" style="color: var(--primary-color);">Download CSV Template</a>
                </small>
            </div>
            <button type="submit" class="btn btn-primary">Upload Sections</button>
        </form>
    </div>
    
    <?php if ($edit_section): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Section</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $edit_section['id']; ?>">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Section Name *</label>
                        <input type="text" name="section_name" class="form-control" value="<?php echo htmlspecialchars($edit_section['section_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level *</label>
                        <select name="grade_level" class="form-control" required>
                            <option value="">Select Grade Level</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($edit_section['grade_level'] == $i) ? 'selected' : ''; ?>>
                                    Grade <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year *</label>
                        <input type="text" name="school_year" class="form-control" value="<?php echo htmlspecialchars($edit_section['school_year']); ?>" required placeholder="e.g., 2024-2025">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Section</button>
                    <a href="sections.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Add New Section</h2>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-3">
                <div class="form-group">
                    <label>Section Name *</label>
                    <input type="text" name="section_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Grade Level *</label>
                        <select name="grade_level" class="form-control" required>
                            <option value="">Select Grade Level</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                </div>
                <div class="form-group">
                    <label>School Year *</label>
                    <input type="text" name="school_year" class="form-control" required placeholder="e.g., 2024-2025">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Section</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Sections</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Section Name</th>
                        <th>Grade Level</th>
                        <th>School Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sections)): ?>
                        <tr><td colspan="5" class="empty-state">No sections found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?php echo $section['id']; ?></td>
                                <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                <td><?php echo htmlspecialchars($section['grade_level']); ?></td>
                                <td><?php echo htmlspecialchars($section['school_year']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this section?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
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
    if (type === 'sections') {
        csvContent = 'Section Name,Grade Level,School Year\n';
        csvContent += 'Section A,1,2024-2025\n';
        csvContent += 'Section B,1,2024-2025\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sections_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
