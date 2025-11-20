<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('administrator');

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if subject_code column allows NULL and auto-migrate if needed
$column_check = $conn->query("SHOW COLUMNS FROM subjects WHERE Field = 'subject_code'");
$column_info = $column_check->fetch_assoc();
$subject_code_nullable = ($column_info && $column_info['Null'] === 'YES');

// Auto-migrate if column is NOT NULL
if (!$subject_code_nullable) {
    // Update existing empty strings to temporary unique codes
    $conn->query("UPDATE subjects SET subject_code = CONCAT('TEMP_', id) WHERE (subject_code = '' OR subject_code IS NULL)");
    
    // Make column nullable
    $migration_result = $conn->query("ALTER TABLE subjects MODIFY COLUMN subject_code VARCHAR(20) NULL");
    if ($migration_result) {
        // Update all temporary codes back to NULL
        $conn->query("UPDATE subjects SET subject_code = NULL WHERE subject_code LIKE 'TEMP_%'");
        $subject_code_nullable = true;
    }
}

$message = '';
$message_type = '';
$upload_results = [];
$edit_subject = null;

// Handle bulk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload_subjects') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header row
        $header = fgetcsv($handle);
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 1) continue; // Skip incomplete rows
            
            $subject_name = isset($data[0]) ? trim($data[0]) : '';
            $description = isset($data[1]) ? trim($data[1]) : '';
            
            if (empty($subject_name)) {
                $error_count++;
                $errors[] = "Row skipped: Missing required field (Subject Name)";
                continue;
            }
            
            // Check for duplicate subject name (case-insensitive)
            $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE LOWER(TRIM(subject_name)) = LOWER(TRIM(?))");
            $check_stmt->bind_param("s", $subject_name);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_count++;
                $errors[] = "Duplicate subject name: $subject_name (already exists)";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert subject (subject_code set to NULL for elementary school)
            if ($subject_code_nullable) {
                $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, description) VALUES (NULL, ?, ?)");
                $stmt->bind_param("ss", $subject_name, $description);
            } else {
                // Fallback: use temporary unique code
                $temp_code = 'SUBJ_' . time() . '_' . rand(1000, 9999) . '_' . $success_count;
                $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $temp_code, $subject_name, $description);
            }
            
            if ($stmt->execute()) {
                $success_count++;
                // Try to set to NULL if column allows it
                if (!$subject_code_nullable) {
                    $new_id = $conn->insert_id;
                    @$conn->query("UPDATE subjects SET subject_code = NULL WHERE id = $new_id");
                }
            } else {
                $error_count++;
                $errors[] = "Failed to create subject: $subject_name - " . $conn->error;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'bulk_upload_subjects') {
    if ($_POST['action'] === 'create') {
        $subject_name = sanitize($_POST['subject_name']);
        $description = sanitize($_POST['description'] ?? '');
        
        // Check for duplicate subject name (case-insensitive)
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE LOWER(TRIM(subject_name)) = LOWER(TRIM(?))");
        $check_stmt->bind_param("s", $subject_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Subject with this name already exists. Please use a different name.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Insert subject with NULL for subject_code (elementary school doesn't use codes)
            if ($subject_code_nullable) {
                $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, description) VALUES (NULL, ?, ?)");
                $stmt->bind_param("ss", $subject_name, $description);
        
        if ($stmt->execute()) {
            $message = 'Subject created successfully.';
            $message_type = 'success';
        } else {
                    $message = 'Failed to create subject: ' . htmlspecialchars($conn->error);
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                // Fallback: use temporary unique code if migration failed
                $temp_code = 'SUBJ_' . time() . '_' . rand(1000, 9999);
                $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $temp_code, $subject_name, $description);
                
                if ($stmt->execute()) {
                    // Try to update back to NULL if column allows it now
                    $new_id = $conn->insert_id;
                    @$conn->query("UPDATE subjects SET subject_code = NULL WHERE id = $new_id");
                    $message = 'Subject created successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to create subject: ' . htmlspecialchars($conn->error);
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_subject = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $subject_name = sanitize($_POST['subject_name']);
        $description = sanitize($_POST['description'] ?? '');
        
        // Check for duplicate subject name (excluding current record)
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE LOWER(TRIM(subject_name)) = LOWER(TRIM(?)) AND id != ?");
        $check_stmt->bind_param("si", $subject_name, $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Subject with this name already exists. Please use a different name.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $subject_name, $description, $id);
            
            if ($stmt->execute()) {
                $message = 'Subject updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update subject: ' . htmlspecialchars($conn->error);
            $message_type = 'error';
        }
        $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Subject deleted successfully.';
            $message_type = 'success';
        }
        $stmt->close();
    }
}

$subjects = [];
$result = $conn->query("SELECT * FROM subjects ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$page_title = 'Manage Subjects';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Subjects</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php" class="active">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if (!$subject_code_nullable): ?>
        <div class="alert alert-warning">
            <strong>Migration Required:</strong> The database needs to be updated to remove subject codes. 
            Please run the migration script: <a href="<?php echo baseUrl('run_subject_code_migration.php'); ?>" style="color: var(--primary-color); font-weight: bold;">Run Migration Now</a>
        </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
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
            <h2 class="card-title">Bulk Upload Subjects</h2>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="bulk_upload_subjects">
            <div class="form-group">
                <label>Upload CSV File *</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    CSV format: Subject Name, Description (optional)<br>
                    <a href="javascript:void(0)" onclick="downloadTemplate('subjects')" style="color: var(--primary-color);">Download CSV Template</a>
                </small>
            </div>
            <button type="submit" class="btn btn-primary">Upload Subjects</button>
        </form>
    </div>
    
    <?php if ($edit_subject): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Subject</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $edit_subject['id']; ?>">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Subject Name *</label>
                        <input type="text" name="subject_name" class="form-control" value="<?php echo htmlspecialchars($edit_subject['subject_name']); ?>" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_subject['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Subject</button>
                    <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Add New Subject</h2>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Subject</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Subjects</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="4" class="empty-state">No subjects found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo $subject['id']; ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['description'] ?? ''); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $subject['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subject?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $subject['id']; ?>">
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
    if (type === 'subjects') {
        csvContent = 'Subject Name,Description\n';
        csvContent += 'Mathematics,Basic Mathematics\n';
        csvContent += 'English,English Language\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'subjects_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
