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
$edit_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create') {
        $parent_user_id = intval($_POST['parent_user_id']);
        $student_id = intval($_POST['student_id']);
        $relationship = sanitize($_POST['relationship'] ?? 'Parent');
        
        // Check for duplicate parent-student link
        $check_stmt = $conn->prepare("SELECT id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ?");
        $check_stmt->bind_param("ii", $parent_user_id, $student_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'This parent-student link already exists.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO parent_student_links (parent_user_id, student_id, relationship) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $parent_user_id, $student_id, $relationship);
            
            if ($stmt->execute()) {
                $message = 'Parent-Student link created successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to create link: ' . htmlspecialchars($conn->error);
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("SELECT * FROM parent_student_links WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_link = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $parent_user_id = intval($_POST['parent_user_id']);
        $student_id = intval($_POST['student_id']);
        $relationship = sanitize($_POST['relationship'] ?? 'Parent');
        
        // Check for duplicate parent-student link (excluding current record)
        $check_stmt = $conn->prepare("SELECT id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ? AND id != ?");
        $check_stmt->bind_param("iii", $parent_user_id, $student_id, $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'This parent-student link already exists.';
            $message_type = 'error';
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            $stmt = $conn->prepare("UPDATE parent_student_links SET parent_user_id = ?, student_id = ?, relationship = ? WHERE id = ?");
            $stmt->bind_param("iisi", $parent_user_id, $student_id, $relationship, $id);
            
            if ($stmt->execute()) {
                $message = 'Parent-Student link updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update link: ' . htmlspecialchars($conn->error);
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM parent_student_links WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Link deleted successfully.';
            $message_type = 'success';
        }
        $stmt->close();
    }
}

// Get all parent-student links
$links = [];
$result = $conn->query("
    SELECT psl.*,
           pu.first_name as parent_first, pu.last_name as parent_last, pu.email as parent_email,
           su.first_name as student_first, su.last_name as student_last, s.student_number
    FROM parent_student_links psl
    JOIN users pu ON psl.parent_user_id = pu.id
    JOIN students s ON psl.student_id = s.id
    JOIN users su ON s.user_id = su.id
    ORDER BY pu.last_name, su.last_name
");
while ($row = $result->fetch_assoc()) {
    $links[] = $row;
}

// Get parents
$parents = [];
$result = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'parent' ORDER BY last_name, first_name");
while ($row = $result->fetch_assoc()) {
    $parents[] = $row;
}

// Get students
$students = [];
$result = $conn->query("
    SELECT s.id, u.first_name, u.last_name, s.student_number
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY u.last_name, u.first_name
");
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$page_title = 'Parent-Student Links';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Parent-Student Links</h1>
    
    <div class="nav">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a href="users.php">Manage Users</a></li>
            <li class="nav-item"><a href="students.php">Students</a></li>
            <li class="nav-item"><a href="teachers.php">Teachers</a></li>
            <li class="nav-item"><a href="subjects.php">Subjects</a></li>
            <li class="nav-item"><a href="sections.php">Sections</a></li>
            <li class="nav-item"><a href="assignments.php" class="active">Parent-Student Links</a></li>
            <li class="nav-item"><a href="reports.php">Reports</a></li>
        </ul>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($edit_link): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Edit Parent-Student Link</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $edit_link['id']; ?>">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Parent *</label>
                        <select name="parent_user_id" class="form-control" required>
                            <option value="">Select Parent</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>" <?php echo ($edit_link['parent_user_id'] == $parent['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Student *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($edit_link['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="relationship" class="form-control" value="<?php echo htmlspecialchars($edit_link['relationship']); ?>" placeholder="e.g., Parent, Guardian">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Link</button>
                    <a href="assignments.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Link Parent to Student</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label>Parent *</label>
                        <select name="parent_user_id" class="form-control" required>
                            <option value="">Select Parent</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Student *</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="relationship" class="form-control" value="Parent" placeholder="e.g., Parent, Guardian">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Link</button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Parent-Student Links</h2>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Parent</th>
                        <th>Student</th>
                        <th>LRN</th>
                        <th>Relationship</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($links)): ?>
                        <tr><td colspan="6" class="empty-state">No links found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($links as $link): ?>
                            <tr>
                                <td><?php echo $link['id']; ?></td>
                                <td><?php echo htmlspecialchars($link['parent_first'] . ' ' . $link['parent_last']); ?></td>
                                <td><?php echo htmlspecialchars($link['student_first'] . ' ' . $link['student_last']); ?></td>
                                <td><?php echo htmlspecialchars($link['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($link['relationship']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this link?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

