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

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'save') {
        $attendance_date = sanitize($_POST['attendance_date']);
        $saved_count = 0;
        
        if (isset($_POST['student_id']) && is_array($_POST['student_id'])) {
            foreach ($_POST['student_id'] as $student_id) {
                $student_id = intval($student_id);
                $status = sanitize($_POST['status'][$student_id] ?? 'Present');
                $remarks = sanitize($_POST['remarks'][$student_id] ?? '');
                
                $stmt = $conn->prepare("
                    INSERT INTO attendance (student_id, teacher_id, attendance_date, status, remarks)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->bind_param("issssss", $student_id, $teacher_id, $attendance_date, $status, $remarks, $status, $remarks);
                
                if ($stmt->execute()) {
                    $saved_count++;
                }
                $stmt->close();
            }
        }
        
        if ($saved_count > 0) {
            $message = "Attendance saved successfully for $saved_count student(s).";
            $message_type = 'success';
        } else {
            $message = 'Failed to save attendance.';
            $message_type = 'error';
        }
    }
}

// Get assigned sections
$sections = [];
$stmt = $conn->prepare("
    SELECT DISTINCT sec.id, sec.section_name, sec.grade_level
    FROM teacher_subject_sections tss
    JOIN sections sec ON tss.section_id = sec.id
    WHERE tss.teacher_id = ?
    ORDER BY sec.grade_level, sec.section_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();

// Get selected section and date
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$students = [];
$attendance_records = [];

if ($selected_section_id) {
    // Get students
    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, u.first_name, u.last_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.section_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->bind_param("i", $selected_section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get attendance for selected date
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $conn->prepare("
            SELECT student_id, status, remarks
            FROM attendance
            WHERE student_id IN ($placeholders) AND teacher_id = ? AND attendance_date = ?
        ");
        $types = str_repeat('i', count($student_ids)) . 'is';
        $params = array_merge($student_ids, [$teacher_id, $selected_date]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $attendance_records[$row['student_id']] = $row;
        }
        $stmt->close();
    }
}

$page_title = 'Attendance';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Select Class and Date</h2>
        </div>
        <form method="GET" action="">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Section</label>
                    <select name="section_id" class="form-control" required onchange="this.form.submit()">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo ($selected_section_id == $section['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" required onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($selected_section_id && !empty($students)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Attendance for <?php echo formatDate($selected_date, 'F d, Y'); ?></h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): 
                                $record = $attendance_records[$student['id']] ?? null;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td>
                                        <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                        <input type="hidden" name="status[<?php echo $student['id']; ?>]" id="status_<?php echo $student['id']; ?>" value="<?php echo htmlspecialchars($record['status'] ?? 'Present'); ?>">
                                        <div class="attendance-buttons" data-student-id="<?php echo $student['id']; ?>">
                                            <?php 
                                            $statuses = getAttendanceStatuses();
                                            $current_status = $record['status'] ?? 'Present';
                                            foreach ($statuses as $status): 
                                                $is_active = ($current_status === $status);
                                                $btn_class = 'attendance-btn';
                                                if ($is_active) {
                                                    $btn_class .= ' active';
                                                    switch($status) {
                                                        case 'Present':
                                                            $btn_class .= ' btn-present';
                                                            break;
                                                        case 'Absent':
                                                            $btn_class .= ' btn-absent';
                                                            break;
                                                        case 'Tardy':
                                                            $btn_class .= ' btn-tardy';
                                                            break;
                                                    }
                                                }
                                            ?>
                                                <button type="button" 
                                                        class="<?php echo $btn_class; ?>" 
                                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                                        data-student-id="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[<?php echo $student['id']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($record['remarks'] ?? ''); ?>"
                                               placeholder="Optional remarks">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </form>
        </div>
    <?php elseif ($selected_section_id && empty($students)): ?>
        <div class="card">
            <div class="empty-state">No students found in this section.</div>
        </div>
    <?php endif; ?>
</div>

<style>
.attendance-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.attendance-btn {
    padding: 8px 16px;
    border: 2px solid var(--border-color);
    background: var(--white);
    color: var(--text-primary);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.2s ease;
    min-width: 80px;
}

.attendance-btn:hover {
    background: #f8fafc;
    border-color: var(--primary-color);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.attendance-btn.active {
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.attendance-btn.active.btn-present {
    background: var(--success-color);
    border-color: var(--success-color);
}

.attendance-btn.active.btn-absent {
    background: var(--danger-color);
    border-color: var(--danger-color);
}

.attendance-btn.active.btn-tardy {
    background: var(--warning-color);
    border-color: var(--warning-color);
}

.attendance-btn:active {
    transform: translateY(0);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle attendance button clicks
    document.querySelectorAll('.attendance-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            const status = this.getAttribute('data-status');
            const hiddenInput = document.getElementById('status_' + studentId);
            
            // Update hidden input
            hiddenInput.value = status;
            
            // Update button states
            const buttonGroup = this.closest('.attendance-buttons');
            const allButtons = buttonGroup.querySelectorAll('.attendance-btn');
            
            allButtons.forEach(function(b) {
                b.classList.remove('active', 'btn-present', 'btn-absent', 'btn-tardy');
            });
            
            // Activate clicked button
            this.classList.add('active');
            switch(status) {
                case 'Present':
                    this.classList.add('btn-present');
                    break;
                case 'Absent':
                    this.classList.add('btn-absent');
                    break;
                case 'Tardy':
                    this.classList.add('btn-tardy');
                    break;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

