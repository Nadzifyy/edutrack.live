<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$error = '';
$success = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $student_lrns = $_POST['student_lrn'] ?? [];
    $relationships = $_POST['relationship'] ?? [];
    
    // Ensure arrays are properly formatted
    if (!is_array($student_lrns)) {
        $student_lrns = [$student_lrns];
    }
    if (!is_array($relationships)) {
        $relationships = [$relationships];
    }
    
    // Filter out empty LRNs and reindex
    $student_lrns = array_values(array_filter(array_map('trim', $student_lrns)));
    
    // Sanitize relationships and ensure array matches LRNs count
    $relationships = array_map('sanitize', $relationships);
    $default_relationship = 'Parent';
    
    // Ensure relationships array matches LRNs count (fill missing with default)
    while (count($relationships) < count($student_lrns)) {
        $relationships[] = $default_relationship;
    }
    // Trim to match LRNs count
    $relationships = array_slice($relationships, 0, count($student_lrns));
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address.';
    } elseif (empty($student_lrns)) {
        $error = 'Please enter at least one child\'s LRN to link your account.';
    } else {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check for duplicate username
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows > 0) {
            $error = 'Username already exists. Please choose a different username.';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Check for duplicate email
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows > 0) {
                $error = 'Email already exists. Please use a different email.';
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                // Create parent user account first
                $user_id = $auth->createUser($username, $email, $password, 'parent', $first_name, $last_name);
                
                if (!$user_id) {
                    $error = 'Failed to create account. Please try again.';
                } else {
                    // Process each child LRN
                    $linked_count = 0;
                    $failed_lrns = [];
                    $success_messages = [];
                    
                    foreach ($student_lrns as $index => $student_lrn) {
                        $student_lrn = trim($student_lrn);
                        if (empty($student_lrn)) continue;
                        
                        $relationship = isset($relationships[$index]) ? $relationships[$index] : $default_relationship;
                        
                        // Find student by LRN
                        $check_stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.student_number = ?");
                        $check_stmt->bind_param("s", $student_lrn);
                        $check_stmt->execute();
                        $result = $check_stmt->get_result();
                        
                        if ($result->num_rows === 0) {
                            $failed_lrns[] = $student_lrn;
                            $check_stmt->close();
                            continue;
                        }
                        
                        $student_data = $result->fetch_assoc();
                        $student_id = $student_data['id'];
                        $check_stmt->close();
                        
                        // Check if link already exists
                        $check_link_stmt = $conn->prepare("SELECT id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ?");
                        $check_link_stmt->bind_param("ii", $user_id, $student_id);
                        $check_link_stmt->execute();
                        $link_result = $check_link_stmt->get_result();
                        
                        if ($link_result->num_rows > 0) {
                            $check_link_stmt->close();
                            continue; // Already linked, skip
                        }
                        $check_link_stmt->close();
                        
                        // Create parent-student link
                        $link_stmt = $conn->prepare("INSERT INTO parent_student_links (parent_user_id, student_id, relationship) VALUES (?, ?, ?)");
                        $link_stmt->bind_param("iis", $user_id, $student_id, $relationship);
                        
                        if ($link_stmt->execute()) {
                            $linked_count++;
                        } else {
                            $failed_lrns[] = $student_lrn;
                        }
                        $link_stmt->close();
                    }
                    
                    if ($linked_count > 0) {
                        $success_msg = 'Account created successfully! ';
                        if ($linked_count == 1) {
                            $success_msg .= 'You have been linked to your child.';
                        } else {
                            $success_msg .= "You have been linked to $linked_count children.";
                        }
                        if (!empty($failed_lrns)) {
                            $success_msg .= ' However, some LRNs were not found: ' . implode(', ', array_map('htmlspecialchars', $failed_lrns));
                        }
                        $success = $success_msg . ' You can now <a href="login.php">login here</a>.';
                    } else {
                        $error = 'Account created but failed to link to any children. ';
                        if (!empty($failed_lrns)) {
                            $error .= 'LRNs not found: ' . implode(', ', array_map('htmlspecialchars', $failed_lrns));
                        }
                        $error .= ' Please contact administrator.';
                        // Delete user if no links created
                        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $del_stmt->bind_param("i", $user_id);
                        $del_stmt->execute();
                        $del_stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Registration - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-layout">
            <div class="login-box">
                <div class="login-header">
                    <div class="logo-container">
                        <img src="LOGO.png" alt="<?php echo SCHOOL_NAME; ?> Logo" class="school-logo">
                    </div>
                    <h1 class="school-name"><?php echo SCHOOL_NAME; ?></h1>
                    <p class="system-subtitle">Parent Registration</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px; vertical-align: middle;">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px; vertical-align: middle;">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST" action="" class="login-form">
                    <div class="grid grid-2" style="gap: 15px;">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="grid grid-2" style="gap: 15px;">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <small style="color: #666; display: block; margin-top: 5px;">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Child 1's LRN (Learner Reference Number) *</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="student_lrn[]" class="form-control" value="<?php echo htmlspecialchars(isset($_POST['student_lrn'][0]) ? $_POST['student_lrn'][0] : ''); ?>" required placeholder="Enter your child's LRN" style="flex: 1;">
                            <select name="relationship[]" class="form-control" required style="width: 150px;">
                                <option value="Parent" <?php echo (isset($_POST['relationship'][0]) && $_POST['relationship'][0] === 'Parent') || (!isset($_POST['relationship'][0]) && !isset($_POST['relationship'])) ? 'selected' : ''; ?>>Parent</option>
                                <option value="Guardian" <?php echo (isset($_POST['relationship'][0]) && $_POST['relationship'][0] === 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
                                <option value="Mother" <?php echo (isset($_POST['relationship'][0]) && $_POST['relationship'][0] === 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                <option value="Father" <?php echo (isset($_POST['relationship'][0]) && $_POST['relationship'][0] === 'Father') ? 'selected' : ''; ?>>Father</option>
                            </select>
                        </div>
                        <small style="color: #666; display: block; margin-top: 5px;">Enter your child's LRN to link your account to their records</small>
                    </div>
                    
                    <div id="additional-children" style="display: none;">
                        <!-- Additional children will be added here dynamically -->
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="add-child-btn" class="btn btn-secondary" style="width: 100%;">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/>
                            </svg>
                            Add Another Child
                        </button>
                    </div>
                    
                    <script>
                    let childCount = 1;
                    document.getElementById('add-child-btn').addEventListener('click', function() {
                        childCount++;
                        const container = document.getElementById('additional-children');
                        container.style.display = 'block';
                        
                        const div = document.createElement('div');
                        div.className = 'form-group';
                        div.innerHTML = `
                            <label>Child ${childCount}'s LRN (Learner Reference Number)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" name="student_lrn[]" class="form-control" placeholder="Enter LRN" style="flex: 1;">
                                <select name="relationship[]" class="form-control" style="width: 150px;">
                                    <option value="Parent">Parent</option>
                                    <option value="Guardian">Guardian</option>
                                    <option value="Mother">Mother</option>
                                    <option value="Father">Father</option>
                                </select>
                                <button type="button" class="btn btn-secondary" onclick="this.parentElement.parentElement.remove(); if(document.getElementById('additional-children').children.length === 0) document.getElementById('additional-children').style.display='none';" style="width: auto; padding: 0 15px;">Remove</button>
                            </div>
                        `;
                        container.appendChild(div);
                    });
                    </script>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-login">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px; vertical-align: middle;">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                        </svg>
                        Register
                    </button>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <p style="color: #666;">Already have an account? <a href="login.php" style="color: var(--primary-color); font-weight: bold;">Sign In</a></p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="about-school">
                <div class="about-header">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 10px; vertical-align: middle;">
                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                        </svg>
                        Parent Registration
                    </h2>
                </div>
                <div class="about-content">
                    <p><strong>Welcome to <?php echo SCHOOL_NAME; ?> Parent Portal!</strong></p>
                    <p>Register here to access your children's academic progress, grades, attendance, and teacher remarks. To register, you will need:</p>
                    <ul style="text-align: left; margin: 15px 0; padding-left: 20px;">
                        <li>Your personal information (name, email, username)</li>
                        <li>Your child's/children's LRN (Learner Reference Number)</li>
                        <li>A secure password</li>
                    </ul>
                    <p><strong>Multiple Children:</strong> You can link multiple children to your account during registration. Just click "Add Another Child" to add more LRNs.</p>
                    <p>After registration, you'll be able to monitor all your children's performance and stay connected with their teachers.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

