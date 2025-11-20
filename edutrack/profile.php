<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();
$user_id = $auth->getUserId();

$message = '';
$message_type = '';

// Check if profile_picture column exists
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
$has_profile_picture_column = $check_column && $check_column->num_rows > 0;

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Get role-specific data
$role_data = [];
if ($current_user['role'] === 'student') {
    $stmt = $conn->prepare("SELECT student_number, section_id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role_data = $result->fetch_assoc() ?? [];
    $stmt->close();
} elseif ($current_user['role'] === 'teacher') {
    $stmt = $conn->prepare("SELECT employee_number FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role_data = $result->fetch_assoc() ?? [];
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    
    // Validate email
    if (!validateEmail($email)) {
        $message = 'Invalid email address.';
        $message_type = 'error';
    } elseif ($password !== null && strlen($password) < PASSWORD_MIN_LENGTH) {
        $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        $message_type = 'error';
    } else {
        // Check if email already exists for another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Email already exists. Please use a different email.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            // Check if profile_picture column exists
            $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            $has_profile_picture_column = $check_column && $check_column->num_rows > 0;
            
            // Handle profile picture upload
            $profile_picture = null;
            if ($has_profile_picture_column && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadProfilePicture($_FILES['profile_picture'], $user_id);
                if ($upload_result['success']) {
                    $profile_picture = $upload_result['filename'];
                    // Delete old profile picture if exists
                    if (!empty($current_user['profile_picture'])) {
                        $old_file = __DIR__ . '/uploads/profiles/' . $current_user['profile_picture'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                } else {
                    $message = $upload_result['error'];
                    $message_type = 'error';
                }
            }
            
            // Update user info
            $update_stmt = null;
            if ($has_profile_picture_column && $password !== null && $profile_picture !== null) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET email = ?, password_hash = ?, first_name = ?, last_name = ?, profile_picture = ? WHERE id = ?");
                $update_stmt->bind_param("sssssi", $email, $password_hash, $first_name, $last_name, $profile_picture, $user_id);
            } elseif ($has_profile_picture_column && $profile_picture !== null) {
                $update_stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, profile_picture = ? WHERE id = ?");
                $update_stmt->bind_param("ssssi", $email, $first_name, $last_name, $profile_picture, $user_id);
            } elseif ($password !== null) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET email = ?, password_hash = ?, first_name = ?, last_name = ? WHERE id = ?");
                $update_stmt->bind_param("ssssi", $email, $password_hash, $first_name, $last_name, $user_id);
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE id = ?");
                $update_stmt->bind_param("sssi", $email, $first_name, $last_name, $user_id);
            }
            
            if ($update_stmt->execute()) {
                // Update session data
                $_SESSION['email'] = $email;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                // Close update statement
                $update_stmt->close();
                
                // Refresh user data
                $select_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $select_stmt->bind_param("i", $user_id);
                $select_stmt->execute();
                $result = $select_stmt->get_result();
                $current_user = $result->fetch_assoc();
                $select_stmt->close();
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update profile.';
                $message_type = 'error';
                $update_stmt->close();
            }
        }
    }
}

// Get updated user data
$user = $auth->getUser();

$page_title = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h1>My Profile</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Profile Information</h2>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid grid-2">
                <?php if ($has_profile_picture_column): ?>
                <div class="form-group">
                    <label>Profile Picture</label>
                    <div style="margin-bottom: 15px;">
                        <img src="<?php echo getProfilePicture($user['profile_picture'] ?? null, $user['id']); ?>" 
                             alt="Profile Picture" 
                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid var(--primary-color); box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    </div>
                    <input type="file" name="profile_picture" class="form-control" accept="image/jpeg,image/png,image/gif,image/jpg">
                    <small style="color: #666; display: block; margin-top: 5px;">Max size: 2MB. Formats: JPEG, PNG, GIF</small>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled>
                    <small style="color: #666; display: block; margin-top: 5px;">Username cannot be changed. Contact administrator if needed.</small>
                </div>
                
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($current_user['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($current_user['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Password (leave blank to keep current password)</label>
                    <input type="password" name="password" class="form-control" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" placeholder="Enter new password or leave blank">
                    <small style="color: #666; display: block; margin-top: 5px;">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                </div>
                
                <?php if ($current_user['role'] === 'student' && !empty($role_data)): ?>
                    <div class="form-group">
                        <label>LRN</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($role_data['student_number'] ?? ''); ?>" disabled>
                        <small style="color: #666; display: block; margin-top: 5px;">Contact administrator to change.</small>
                    </div>
                <?php endif; ?>
                
                <?php if ($current_user['role'] === 'teacher' && !empty($role_data)): ?>
                    <div class="form-group">
                        <label>Employee Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($role_data['employee_number'] ?? ''); ?>" disabled>
                        <small style="color: #666; display: block; margin-top: 5px;">Contact administrator to change.</small>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid var(--border-color);">
                <button type="submit" class="btn btn-primary">Update Profile</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Account Information</h2>
        </div>
        <div class="grid grid-2">
            <div>
                <strong>Role:</strong> 
                <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($current_user['role'])); ?></span>
            </div>
            <div>
                <strong>Account Created:</strong> <?php echo formatDate($current_user['created_at'], 'F d, Y'); ?>
            </div>
            <div>
                <strong>Last Updated:</strong> <?php echo formatDate($current_user['updated_at'], 'F d, Y h:i A'); ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

