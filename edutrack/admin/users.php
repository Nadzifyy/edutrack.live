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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $password = $_POST['password'];
            $role = sanitize($_POST['role']);
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                $message_type = 'error';
            } elseif (!validateEmail($email)) {
                $message = 'Invalid email address.';
                $message_type = 'error';
            } else {
                // Check for duplicate username
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($result->num_rows > 0) {
                    $message = 'Username already exists. Please choose a different username.';
                    $message_type = 'error';
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Check for duplicate email
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    if ($result->num_rows > 0) {
                        $message = 'Email already exists. Please use a different email.';
                        $message_type = 'error';
                        $check_stmt->close();
                    } else {
                        $check_stmt->close();
                        
                        $auth = new Auth();
                        $user_id = $auth->createUser($username, $email, $password, $role, $first_name, $last_name);
                        
                        if ($user_id) {
                            // Create role-specific records
                            if ($role === 'student') {
                                $student_number = sanitize($_POST['student_number'] ?? '');
                                $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
                                
                                // Check for duplicate LRN
                                if (!empty($student_number)) {
                                    $check_stmt = $conn->prepare("SELECT id FROM students WHERE student_number = ?");
                                    $check_stmt->bind_param("s", $student_number);
                                    $check_stmt->execute();
                                    $result = $check_stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $message = 'LRN already exists. Please use a different LRN.';
                                        $message_type = 'error';
                                        $check_stmt->close();
                                        // Delete user since student record creation will fail
                                        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                                        $del_stmt->bind_param("i", $user_id);
                                        $del_stmt->execute();
                                        $del_stmt->close();
                                    } else {
                                        $check_stmt->close();
                                        
                                        // Validate section_id if provided
                                        if ($section_id !== null) {
                                            $check_stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
                                            $check_stmt->bind_param("i", $section_id);
                                            $check_stmt->execute();
                                            $check_result = $check_stmt->get_result();
                                            if ($check_result->num_rows === 0) {
                                                $section_id = null; // Section doesn't exist, set to NULL
                                            }
                                            $check_stmt->close();
                                        }
                                        
                                        // Handle NULL section_id properly - use separate queries
                                        if ($section_id === null) {
                                            $stmt = $conn->prepare("INSERT INTO students (user_id, student_number, section_id) VALUES (?, ?, NULL)");
                                            $stmt->bind_param("is", $user_id, $student_number);
                                        } else {
                                            $stmt = $conn->prepare("INSERT INTO students (user_id, student_number, section_id) VALUES (?, ?, ?)");
                                            $stmt->bind_param("isi", $user_id, $student_number, $section_id);
                                        }
                                        
                                        if ($stmt->execute()) {
                                            $message = 'User created successfully.';
                                            $message_type = 'success';
                                        } else {
                                            $message = 'User created but failed to create student record: ' . $stmt->error;
                                            $message_type = 'error';
                                        }
                                        $stmt->close();
                                    }
                                } else {
                                    $message = 'LRN is required for students.';
                                    $message_type = 'error';
                                    // Delete user since student record creation will fail
                                    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                                    $del_stmt->bind_param("i", $user_id);
                                    $del_stmt->execute();
                                    $del_stmt->close();
                                }
                            } elseif ($role === 'teacher') {
                                $employee_number = sanitize($_POST['employee_number'] ?? '');
                                
                                // Check for duplicate employee number
                                if (!empty($employee_number)) {
                                    $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE employee_number = ?");
                                    $check_stmt->bind_param("s", $employee_number);
                                    $check_stmt->execute();
                                    $result = $check_stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $message = 'Employee number already exists. Please use a different employee number.';
                                        $message_type = 'error';
                                        $check_stmt->close();
                                        // Delete user since teacher record creation will fail
                                        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                                        $del_stmt->bind_param("i", $user_id);
                                        $del_stmt->execute();
                                        $del_stmt->close();
                                    } else {
                                        $check_stmt->close();
                                        
                                        $stmt = $conn->prepare("INSERT INTO teachers (user_id, employee_number) VALUES (?, ?)");
                                        $stmt->bind_param("is", $user_id, $employee_number);
                                        
                                        if ($stmt->execute()) {
                                            $message = 'User created successfully.';
                                            $message_type = 'success';
                                        } else {
                                            $message = 'User created but failed to create teacher record: ' . $stmt->error;
                                            $message_type = 'error';
                                        }
                                        $stmt->close();
                                    }
                                } else {
                                    $message = 'Employee number is required for teachers.';
                                    $message_type = 'error';
                                    // Delete user since teacher record creation will fail
                                    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                                    $del_stmt->bind_param("i", $user_id);
                                    $del_stmt->execute();
                                    $del_stmt->close();
                                }
                            } else {
                                // For other roles (administrator, parent), just create the user
                                $message = 'User created successfully.';
                                $message_type = 'success';
                            }
                        } else {
                            $message = 'Failed to create user. Username or email may already exist.';
                            $message_type = 'error';
                        }
                    }
                }
            }
        } elseif ($_POST['action'] === 'update') {
            $user_id = intval($_POST['user_id']);
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            $first_name = sanitize($_POST['first_name']);
            $last_name = sanitize($_POST['last_name']);
            $password = !empty($_POST['password']) ? $_POST['password'] : null;
            
            if (!validateEmail($email)) {
                $message = 'Invalid email address.';
                $message_type = 'error';
            } elseif ($password !== null && strlen($password) < PASSWORD_MIN_LENGTH) {
                $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                $message_type = 'error';
            } else {
                // Check if username or email already exists for another user
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check_stmt->bind_param("ssi", $username, $email, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $message = 'Username or email already exists.';
                    $message_type = 'error';
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Handle profile picture upload
                    $profile_picture = null;
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadProfilePicture($_FILES['profile_picture'], $user_id);
                        if ($upload_result['success']) {
                            $profile_picture = $upload_result['filename'];
                            // Delete old profile picture if exists
                            $old_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                            $old_stmt->bind_param("i", $user_id);
                            $old_stmt->execute();
                            $old_result = $old_stmt->get_result();
                            $old_row = $old_result->fetch_assoc();
                            if ($old_row && !empty($old_row['profile_picture'])) {
                                $old_file = __DIR__ . '/../uploads/profiles/' . $old_row['profile_picture'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                            }
                            $old_stmt->close();
                        } else {
                            $message = $upload_result['error'];
                            $message_type = 'error';
                        }
                    }
                    
                    // Update user basic info
                    if ($password !== null && $profile_picture !== null) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, first_name = ?, last_name = ?, profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("sssssssi", $username, $email, $password_hash, $role, $first_name, $last_name, $profile_picture, $user_id);
                    } elseif ($password !== null) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ?, first_name = ?, last_name = ? WHERE id = ?");
                        $stmt->bind_param("ssssssi", $username, $email, $password_hash, $role, $first_name, $last_name, $user_id);
                    } elseif ($profile_picture !== null) {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ?, profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("ssssssi", $username, $email, $role, $first_name, $last_name, $profile_picture, $user_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ? WHERE id = ?");
                        $stmt->bind_param("sssssi", $username, $email, $role, $first_name, $last_name, $user_id);
                    }
                    
                    if ($stmt->execute()) {
                        // Update role-specific records
                        if ($role === 'student') {
                            $student_number = sanitize($_POST['student_number'] ?? '');
                            $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
                            
                            // Check for duplicate LRN (excluding current student)
                            if (!empty($student_number)) {
                                $check_lrn_stmt = $conn->prepare("SELECT id FROM students WHERE student_number = ? AND user_id != ?");
                                $check_lrn_stmt->bind_param("si", $student_number, $user_id);
                                $check_lrn_stmt->execute();
                                $lrn_result = $check_lrn_stmt->get_result();
                                
                                if ($lrn_result->num_rows > 0) {
                                    $message = 'LRN already exists. Please use a different LRN.';
                                    $message_type = 'error';
                                    $check_lrn_stmt->close();
                                } else {
                                    $check_lrn_stmt->close();
                                    
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
                                    
                                    // Check if student record exists
                                    $check_stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
                                    $check_stmt->bind_param("i", $user_id);
                                    $check_stmt->execute();
                                    $check_result = $check_stmt->get_result();
                                    
                                    if ($check_result->num_rows > 0) {
                                        // Update existing student record
                                        if ($section_id === null) {
                                            $update_stmt = $conn->prepare("UPDATE students SET student_number = ?, section_id = NULL WHERE user_id = ?");
                                            $update_stmt->bind_param("si", $student_number, $user_id);
                                        } else {
                                            $update_stmt = $conn->prepare("UPDATE students SET student_number = ?, section_id = ? WHERE user_id = ?");
                                            $update_stmt->bind_param("sii", $student_number, $section_id, $user_id);
                                        }
                                        $update_stmt->execute();
                                        $update_stmt->close();
                                    } else {
                                        // Create student record if it doesn't exist
                                        if ($section_id === null) {
                                            $insert_stmt = $conn->prepare("INSERT INTO students (user_id, student_number, section_id) VALUES (?, ?, NULL)");
                                            $insert_stmt->bind_param("is", $user_id, $student_number);
                                        } else {
                                            $insert_stmt = $conn->prepare("INSERT INTO students (user_id, student_number, section_id) VALUES (?, ?, ?)");
                                            $insert_stmt->bind_param("isi", $user_id, $student_number, $section_id);
                                        }
                                        $insert_stmt->execute();
                                        $insert_stmt->close();
                                    }
                                    $check_stmt->close();
                                }
                            } else {
                                $message = 'LRN is required for students.';
                                $message_type = 'error';
                            }
                            
                            // Remove teacher record if exists
                            $delete_stmt = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
                            $delete_stmt->bind_param("i", $user_id);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                        } elseif ($role === 'teacher') {
                            $employee_number = sanitize($_POST['employee_number'] ?? '');
                            
                            // Check for duplicate employee number (excluding current teacher)
                            if (!empty($employee_number)) {
                                $check_emp_stmt = $conn->prepare("SELECT id FROM teachers WHERE employee_number = ? AND user_id != ?");
                                $check_emp_stmt->bind_param("si", $employee_number, $user_id);
                                $check_emp_stmt->execute();
                                $emp_result = $check_emp_stmt->get_result();
                                
                                if ($emp_result->num_rows > 0) {
                                    $message = 'Employee number already exists. Please use a different employee number.';
                                    $message_type = 'error';
                                    $check_emp_stmt->close();
                                } else {
                                    $check_emp_stmt->close();
                                    
                                    // Check if teacher record exists
                                    $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ?");
                                    $check_stmt->bind_param("i", $user_id);
                                    $check_stmt->execute();
                                    $check_result = $check_stmt->get_result();
                                    
                                    if ($check_result->num_rows > 0) {
                                        // Update existing teacher record
                                        $update_stmt = $conn->prepare("UPDATE teachers SET employee_number = ? WHERE user_id = ?");
                                        $update_stmt->bind_param("si", $employee_number, $user_id);
                                        $update_stmt->execute();
                                        $update_stmt->close();
                                    } else {
                                        // Create teacher record if it doesn't exist
                                        $insert_stmt = $conn->prepare("INSERT INTO teachers (user_id, employee_number) VALUES (?, ?)");
                                        $insert_stmt->bind_param("is", $user_id, $employee_number);
                                        $insert_stmt->execute();
                                        $insert_stmt->close();
                                    }
                                    $check_stmt->close();
                                }
                            } else {
                                $message = 'Employee number is required for teachers.';
                                $message_type = 'error';
                            }
                            
                            // Remove student record if exists
                            $delete_stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
                            $delete_stmt->bind_param("i", $user_id);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                        } else {
                            // For other roles, remove student and teacher records if they exist
                            $delete_stmt = $conn->prepare("DELETE FROM students WHERE user_id = ?");
                            $delete_stmt->bind_param("i", $user_id);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                            
                            $delete_stmt = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
                            $delete_stmt->bind_param("i", $user_id);
                            $delete_stmt->execute();
                            $delete_stmt->close();
                        }
                        
                        $message = 'User updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update user.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $user_id = intval($_POST['user_id']);
            if ($user_id != $auth->getUserId()) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $message = 'User deleted successfully.';
                    $message_type = 'success';
                }
                $stmt->close();
            } else {
                $message = 'You cannot delete your own account.';
                $message_type = 'error';
            }
        }
    }
}

// Get user to edit if requested
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_user_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT u.*, 
               s.student_number, s.section_id,
               t.employee_number,
               sec.section_name, sec.grade_level
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_user = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all users
$users = [];
$result = $conn->query("
    SELECT u.*, 
           s.student_number, s.section_id,
           t.employee_number,
           sec.section_name, sec.grade_level
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN teachers t ON u.id = t.user_id
    LEFT JOIN sections sec ON s.section_id = sec.id
    ORDER BY u.role, u.last_name, u.first_name
");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Get sections for dropdown
$sections = [];
$result = $conn->query("SELECT id, section_name, grade_level FROM sections ORDER BY grade_level, section_name");
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$page_title = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Manage Users</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php" class="active">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
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
    
    <?php if ($edit_user): ?>
        <div class="card" style="margin-bottom: 20px; border: 2px solid #3b82f6;">
            <div class="card-header">
                <h2 class="card-title">Edit User: <?php echo htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name']); ?></h2>
                <a href="users.php" class="btn btn-secondary btn-sm" style="float: right;">Cancel</a>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <?php if (!empty($edit_user['profile_picture'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="<?php echo getProfilePicture($edit_user['profile_picture'], $edit_user['id']); ?>" alt="Current Profile Picture" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #ddd;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="profile_picture" class="form-control" accept="image/jpeg,image/png,image/gif,image/jpg">
                        <small style="color: #666;">Max size: 2MB. Formats: JPEG, PNG, GIF</small>
                    </div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password (leave blank to keep current password)</label>
                        <input type="password" name="password" class="form-control" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" placeholder="Enter new password or leave blank">
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" class="form-control" required id="edit-role-select">
                            <option value="administrator" <?php echo $edit_user['role'] === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="teacher" <?php echo $edit_user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="student" <?php echo $edit_user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="parent" <?php echo $edit_user['role'] === 'parent' ? 'selected' : ''; ?>>Parent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($edit_user['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($edit_user['last_name']); ?>" required>
                    </div>
                    <div class="form-group" id="edit-student-number-field" style="display: <?php echo $edit_user['role'] === 'student' ? 'block' : 'none'; ?>;">
                        <label>LRN</label>
                        <input type="text" name="student_number" class="form-control" value="<?php echo htmlspecialchars($edit_user['student_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group" id="edit-section-field" style="display: <?php echo $edit_user['role'] === 'student' ? 'block' : 'none'; ?>;">
                        <label>Section</label>
                        <select name="section_id" class="form-control">
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo ($edit_user['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="edit-employee-number-field" style="display: <?php echo $edit_user['role'] === 'teacher' ? 'block' : 'none'; ?>;">
                        <label>Employee Number</label>
                        <input type="text" name="employee_number" class="form-control" value="<?php echo htmlspecialchars($edit_user['employee_number'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Create New User</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required id="role-select">
                        <option value="">Select Role</option>
                        <option value="administrator">Administrator</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                        <option value="parent">Parent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                <div class="form-group" id="student-number-field" style="display: none;">
                    <label>Student Number</label>
                    <input type="text" name="student_number" class="form-control">
                </div>
                <div class="form-group" id="section-field" style="display: none;">
                    <label>Section</label>
                    <select name="section_id" class="form-control">
                        <option value="">Select Section</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="employee-number-field" style="display: none;">
                    <label>Employee Number</label>
                    <input type="text" name="employee_number" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Users</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                <td>
                                    <?php if ($user['student_number']): ?>
                                        LRN: <?php echo htmlspecialchars($user['student_number']); ?>
                                        <?php if ($user['section_name']): ?>
                                            - <?php echo htmlspecialchars($user['section_name']); ?>
                                        <?php endif; ?>
                                    <?php elseif ($user['employee_number']): ?>
                                        Employee #<?php echo htmlspecialchars($user['employee_number']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <?php if ($user['id'] != $auth->getUserId()): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-secondary" style="padding: 5px 10px; font-size: 0.875em;">Current User</span>
                                        <?php endif; ?>
                                    </div>
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
// Handle role selection for create form
const roleSelect = document.getElementById('role-select');
if (roleSelect) {
    roleSelect.addEventListener('change', function() {
        const role = this.value;
        const studentFields = document.getElementById('student-number-field');
        const sectionField = document.getElementById('section-field');
        const employeeField = document.getElementById('employee-number-field');
        
        studentFields.style.display = 'none';
        sectionField.style.display = 'none';
        employeeField.style.display = 'none';
        
        if (role === 'student') {
            studentFields.style.display = 'block';
            sectionField.style.display = 'block';
        } else if (role === 'teacher') {
            employeeField.style.display = 'block';
        }
    });
}

// Handle role selection for edit form
const editRoleSelect = document.getElementById('edit-role-select');
if (editRoleSelect) {
    editRoleSelect.addEventListener('change', function() {
        const role = this.value;
        const studentFields = document.getElementById('edit-student-number-field');
        const sectionField = document.getElementById('edit-section-field');
        const employeeField = document.getElementById('edit-employee-number-field');
        
        studentFields.style.display = 'none';
        sectionField.style.display = 'none';
        employeeField.style.display = 'none';
        
        if (role === 'student') {
            studentFields.style.display = 'block';
            sectionField.style.display = 'block';
        } else if (role === 'teacher') {
            employeeField.style.display = 'block';
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

