<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('parent');

$db = Database::getInstance();
$conn = $db->getConnection();
$parent_user_id = $auth->getUserId();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_lrn = sanitize($_POST['student_lrn'] ?? '');
    $relationship = sanitize($_POST['relationship'] ?? 'Parent');
    
    if (empty($student_lrn)) {
        $message = 'Please enter your child\'s LRN.';
        $message_type = 'error';
    } else {
        // Find student by LRN
        $check_stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.student_number = ?");
        $check_stmt->bind_param("s", $student_lrn);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $message = 'Student with LRN "' . htmlspecialchars($student_lrn) . '" not found. Please verify the LRN and try again.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $student_data = $result->fetch_assoc();
            $student_id = $student_data['id'];
            $check_stmt->close();
            
            // Check if link already exists
            $check_link_stmt = $conn->prepare("SELECT id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ?");
            $check_link_stmt->bind_param("ii", $parent_user_id, $student_id);
            $check_link_stmt->execute();
            $link_result = $check_link_stmt->get_result();
            
            if ($link_result->num_rows > 0) {
                $message = 'You are already linked to this student.';
                $message_type = 'error';
                $check_link_stmt->close();
            } else {
                $check_link_stmt->close();
                
                // Create parent-student link
                $link_stmt = $conn->prepare("INSERT INTO parent_student_links (parent_user_id, student_id, relationship) VALUES (?, ?, ?)");
                $link_stmt->bind_param("iis", $parent_user_id, $student_id, $relationship);
                
                if ($link_stmt->execute()) {
                    $message = 'Child successfully linked to your account!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to link child. Please try again or contact administrator.';
                    $message_type = 'error';
                }
                $link_stmt->close();
            }
        }
    }
}

// Get current linked children
$children = getParentStudents($parent_user_id);

$page_title = 'Add Child to Account';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Add Child to Account</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="reports.php">View Reports</a></li>
            <li class="nav-item"><a href="add_child.php" class="active">Add Child</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Link Another Child</h2>
        </div>
        <form method="POST">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Child's LRN (Learner Reference Number) *</label>
                    <input type="text" name="student_lrn" class="form-control" value="<?php echo htmlspecialchars($_POST['student_lrn'] ?? ''); ?>" required placeholder="Enter your child's LRN">
                    <small style="color: #666; display: block; margin-top: 5px;">Enter your child's LRN to link them to your account</small>
                </div>
                <div class="form-group">
                    <label>Relationship *</label>
                    <select name="relationship" class="form-control" required>
                        <option value="Parent" <?php echo (($_POST['relationship'] ?? 'Parent') === 'Parent') ? 'selected' : ''; ?>>Parent</option>
                        <option value="Guardian" <?php echo (($_POST['relationship'] ?? '') === 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
                        <option value="Mother" <?php echo (($_POST['relationship'] ?? '') === 'Mother') ? 'selected' : ''; ?>>Mother</option>
                        <option value="Father" <?php echo (($_POST['relationship'] ?? '') === 'Father') ? 'selected' : ''; ?>>Father</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Link Child</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <?php if (!empty($children)): ?>
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h2 class="card-title">Currently Linked Children</h2>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>LRN</th>
                            <th>Section</th>
                            <th>Grade Level</th>
                            <th>Relationship</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $child): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($child['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($child['section_name'] ?? 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars($child['grade_level'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    // Get relationship
                                    $rel_stmt = $conn->prepare("SELECT relationship FROM parent_student_links WHERE parent_user_id = ? AND student_id = ?");
                                    $rel_stmt->bind_param("ii", $parent_user_id, $child['id']);
                                    $rel_stmt->execute();
                                    $rel_result = $rel_stmt->get_result();
                                    $rel_data = $rel_result->fetch_assoc();
                                    echo htmlspecialchars($rel_data['relationship'] ?? 'Parent');
                                    $rel_stmt->close();
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

