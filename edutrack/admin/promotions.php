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
$passing_grade = 75.0; // Default passing grade

// Handle promotion processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process') {
    $from_school_year = sanitize($_POST['from_school_year']);
    $to_school_year = sanitize($_POST['to_school_year']);
    $from_grade_level = intval($_POST['from_grade_level']);
    $promoted_by = $auth->getUserId();
    
    $processed = 0;
    $errors = 0;
    
    if (isset($_POST['students']) && is_array($_POST['students'])) {
        foreach ($_POST['students'] as $student_data) {
            $data = json_decode($student_data, true);
            
            $student_id = intval($data['student_id']);
            $promotion_type = sanitize($data['promotion_type']);
            $to_grade_level = !empty($data['to_grade_level']) ? intval($data['to_grade_level']) : null;
            
            // Get current section_id from database
            $stmt = $conn->prepare("SELECT section_id FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student_record = $result->fetch_assoc();
            $from_section_id = $student_record['section_id'] ?? null;
            $stmt->close();
            
            $to_section_id = !empty($data['to_section_id']) ? intval($data['to_section_id']) : null;
            $reason = sanitize($data['reason'] ?? '');
            $notes = sanitize($data['notes'] ?? '');
            
            if ($to_section_id) {
                if (processStudentPromotion($student_id, $promotion_type, $from_grade_level, $to_grade_level,
                                          $from_section_id, $to_section_id, $from_school_year, $to_school_year,
                                          $promoted_by, $reason, $notes)) {
                    $processed++;
                } else {
                    $errors++;
                }
            }
        }
    }
    
    if ($processed > 0) {
        $message = "Successfully processed {$processed} student(s).";
        if ($errors > 0) {
            $message .= " {$errors} error(s) occurred.";
        }
        $message_type = 'success';
    } else {
        $message = "No students were processed. Please check your selections.";
        $message_type = 'error';
    }
}

// Get filter parameters
$selected_grade = isset($_GET['grade']) ? intval($_GET['grade']) : null;
$from_school_year_filter = isset($_GET['from_year']) ? sanitize($_GET['from_year']) : date('Y') . '-' . (date('Y') + 1);
$to_school_year_filter = isset($_GET['to_year']) ? sanitize($_GET['to_year']) : (date('Y') + 1) . '-' . (date('Y') + 2);

// Get available school years from sections
$school_years = [];
$result = $conn->query("SELECT DISTINCT school_year FROM sections ORDER BY school_year DESC");
while ($row = $result->fetch_assoc()) {
    $school_years[] = $row['school_year'];
}

// Get students if grade is selected
$students = [];
$sections_by_grade = [];

if ($selected_grade) {
    $students = getStudentsForPromotion($selected_grade, $from_school_year_filter);
    
    // Trim the school year filter to handle whitespace issues
    $to_school_year_filter = trim($to_school_year_filter);
    
    // Debug: Log the filter values (uncomment for debugging)
    // error_log("From School Year: '$from_school_year_filter', To School Year: '$to_school_year_filter', Selected Grade: $selected_grade");
    
    // Get sections for target grade (for promotion)
    $target_grade = $selected_grade + 1;
    if ($target_grade <= 6) {
        $stmt = $conn->prepare("
            SELECT id, section_name, grade_level, school_year
            FROM sections
            WHERE CAST(grade_level AS UNSIGNED) = ? AND TRIM(school_year) = ?
            ORDER BY section_name
        ");
        $stmt->bind_param("is", $target_grade, $to_school_year_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections_by_grade[$target_grade] = [];
        while ($row = $result->fetch_assoc()) {
            $sections_by_grade[$target_grade][] = $row;
        }
        // Debug: Log found sections (uncomment for debugging)
        // error_log("Found " . count($sections_by_grade[$target_grade]) . " sections for grade $target_grade, year '$to_school_year_filter'");
        $stmt->close();
    }
    
    // Get sections for same grade (for retention)
    $stmt = $conn->prepare("
        SELECT id, section_name, grade_level, school_year
        FROM sections
        WHERE CAST(grade_level AS UNSIGNED) = ? AND TRIM(school_year) = ?
        ORDER BY section_name
    ");
    $stmt->bind_param("is", $selected_grade, $to_school_year_filter);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!isset($sections_by_grade[$selected_grade])) {
        $sections_by_grade[$selected_grade] = [];
    }
    while ($row = $result->fetch_assoc()) {
        $sections_by_grade[$selected_grade][] = $row;
    }
    // Debug: Log found sections (uncomment for debugging)
    // error_log("Found " . count($sections_by_grade[$selected_grade]) . " sections for grade $selected_grade, year '$to_school_year_filter'");
    $stmt->close();
}

// Check if promotion system is fully migrated
$column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'current_grade_level'");
$has_promotion_columns = $column_check->num_rows > 0;
$table_check = $conn->query("SHOW TABLES LIKE 'student_promotions'");
$has_promotions_table = $table_check->num_rows > 0;

$page_title = 'Student Promotions';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <?php if (!$has_promotion_columns || !$has_promotions_table): ?>
        <div class="alert alert-error" style="background: #fee; border-left: 4px solid var(--danger-color); padding: 15px; margin-bottom: 20px;">
            <strong>⚠️ Migration Required:</strong> Please run the promotion system migration to enable full functionality.
            <br><a href="<?php echo baseUrl('database/migrate_promotion_system.php'); ?>" target="_blank" style="color: var(--primary-color); font-weight: 600;">Run Migration Now</a>
        </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Student Promotion & Retention</h2>
        </div>
        
        <!-- Filter Form -->
        <form method="GET" action="" style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>From Grade Level</label>
                    <select name="grade" class="form-control" required onchange="this.form.submit()">
                        <option value="">-- Select Grade --</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $selected_grade == $i ? 'selected' : ''; ?>>
                                Grade <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>From School Year</label>
                    <input type="text" name="from_year" class="form-control" 
                           value="<?php echo htmlspecialchars($from_school_year_filter); ?>" 
                           placeholder="2024-2025" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>To School Year</label>
                    <input type="text" name="to_year" class="form-control" 
                           value="<?php echo htmlspecialchars($to_school_year_filter); ?>" 
                           placeholder="2025-2026" required>
                </div>
            </div>
        </form>
        
        <?php if ($selected_grade && !empty($students)): ?>
            <?php 
            // Debug: Show sections found (remove this after debugging)
            if (isset($_GET['debug'])) {
                echo '<div style="background: #f0f0f0; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
                echo '<strong>Debug Info:</strong><br>';
                echo 'To School Year: "' . htmlspecialchars($to_school_year_filter) . '"<br>';
                echo 'Selected Grade: ' . $selected_grade . '<br>';
                if (isset($sections_by_grade[$selected_grade + 1])) {
                    echo 'Sections for Grade ' . ($selected_grade + 1) . ': ' . count($sections_by_grade[$selected_grade + 1]) . '<br>';
                    foreach ($sections_by_grade[$selected_grade + 1] as $s) {
                        echo '- ' . htmlspecialchars($s['section_name']) . ' (Grade ' . $s['grade_level'] . ', Year: "' . htmlspecialchars($s['school_year']) . '")<br>';
                    }
                } else {
                    echo 'No sections found for Grade ' . ($selected_grade + 1) . '<br>';
                }
                if (isset($sections_by_grade[$selected_grade])) {
                    echo 'Sections for Grade ' . $selected_grade . ': ' . count($sections_by_grade[$selected_grade]) . '<br>';
                    foreach ($sections_by_grade[$selected_grade] as $s) {
                        echo '- ' . htmlspecialchars($s['section_name']) . ' (Grade ' . $s['grade_level'] . ', Year: "' . htmlspecialchars($s['school_year']) . '")<br>';
                    }
                } else {
                    echo 'No sections found for Grade ' . $selected_grade . '<br>';
                }
                echo '</div>';
            }
            ?>
            <form method="POST" action="" id="promotionForm">
                <input type="hidden" name="action" value="process">
                <input type="hidden" name="from_school_year" value="<?php echo htmlspecialchars($from_school_year_filter); ?>">
                <input type="hidden" name="to_school_year" value="<?php echo htmlspecialchars($to_school_year_filter); ?>">
                <input type="hidden" name="from_grade_level" value="<?php echo $selected_grade; ?>">
                
                <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" onclick="selectAllByType('Promoted')">Select All Promoted</button>
                    <button type="button" class="btn btn-secondary" onclick="selectAllByType('Retained')">Select All Retained</button>
                    <button type="button" class="btn btn-secondary" onclick="clearAllSelections()">Clear All</button>
                    <button type="button" class="btn btn-info" onclick="previewChanges()">Preview Changes</button>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleAllStudents(this)">
                                </th>
                                <th>Student Name</th>
                                <th>LRN</th>
                                <th>Current Section</th>
                                <th>GPA</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Target Section</th>
                                <th>Reason (if Retained)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): 
                                $eligibility = $student['promotion_eligibility'];
                                $suggested_status = $eligibility['suggestion'];
                                $is_eligible = $eligibility['eligible'];
                            ?>
                                <tr class="student-row" data-student-id="<?php echo $student['id']; ?>">
                                    <td>
                                        <input type="checkbox" class="student-checkbox" 
                                               data-student-id="<?php echo $student['id']; ?>"
                                               onchange="updateStudentData(this)">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span style="color: <?php echo $eligibility['gpa'] >= $passing_grade ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600;">
                                            <?php echo number_format($eligibility['gpa'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($eligibility['attendance_rate'], 1); ?>%</td>
                                    <td>
                                        <select name="promotion_type[<?php echo $student['id']; ?>]" 
                                                class="form-control promotion-type" 
                                                data-student-id="<?php echo $student['id']; ?>"
                                                onchange="updatePromotionType(this)">
                                            <option value="Promoted" selected>Promoted</option>
                                            <option value="Retained">Retained</option>
                                            <option value="Transferred">Transferred</option>
                                            <option value="Dropped">Dropped</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="target_section[<?php echo $student['id']; ?>]" 
                                                class="form-control target-section" 
                                                data-student-id="<?php echo $student['id']; ?>"
                                                data-grade-level="<?php echo $selected_grade; ?>"
                                                data-suggested-status="<?php echo $suggested_status; ?>"
                                                onchange="updateTargetSection(this)">
                                            <option value="">-- Select Section --</option>
                                            <?php 
                                            // Always show sections for ALL students, regardless of grades or eligibility
                                            // Show sections for promoted (next grade) - always available
                                            if ($selected_grade < 6 && isset($sections_by_grade[$selected_grade + 1]) && !empty($sections_by_grade[$selected_grade + 1])):
                                                foreach ($sections_by_grade[$selected_grade + 1] as $section):
                                            ?>
                                                <option value="<?php echo $section['id']; ?>" 
                                                        data-grade="<?php echo intval($section['grade_level']); ?>"
                                                        class="promoted-section">
                                                    <?php echo htmlspecialchars($section['section_name']); ?> (Grade <?php echo $section['grade_level']; ?>) 
                                                </option>
                                            <?php 
                                                endforeach;
                                            endif;
                                            // Show sections for retained (same grade) - always available
                                            if (isset($sections_by_grade[$selected_grade]) && !empty($sections_by_grade[$selected_grade])):
                                                foreach ($sections_by_grade[$selected_grade] as $section):
                                            ?>
                                                <option value="<?php echo $section['id']; ?>" 
                                                        data-grade="<?php echo intval($section['grade_level']); ?>"
                                                        class="retained-section">
                                                    <?php echo htmlspecialchars($section['section_name']); ?> (Grade <?php echo $section['grade_level']; ?>)
                                                </option>
                                            <?php 
                                                endforeach;
                                            endif;
                                            
                                            // Show message if no sections available
                                            if (($selected_grade >= 6 || !isset($sections_by_grade[$selected_grade + 1]) || empty($sections_by_grade[$selected_grade + 1])) 
                                                && (!isset($sections_by_grade[$selected_grade]) || empty($sections_by_grade[$selected_grade]))):
                                            ?>
                                                <option value="" disabled style="color: #999; font-style: italic;">
                                                    No sections available for this grade level and school year
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="reason[<?php echo $student['id']; ?>]" 
                                               class="form-control promotion-reason" 
                                               data-student-id="<?php echo $student['id']; ?>"
                                               placeholder="Optional reason">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" onclick="return confirmPromotion()">Process Promotions</button>
                </div>
            </form>
        <?php elseif ($selected_grade && empty($students)): ?>
            <div class="empty-state">
                <p>No students found in Grade <?php echo $selected_grade; ?> for the selected school year.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>Please select a grade level to view students for promotion.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.student-row {
    transition: background-color 0.2s;
}

.student-row:hover {
    background-color: #f8fafc;
}

.promotion-type {
    min-width: 120px;
}

.target-section {
    min-width: 150px;
}

.promotion-reason {
    min-width: 200px;
}
</style>

<script>
// Store student data for form submission
const studentDataMap = {};

function updateStudentData(checkbox) {
    const studentId = checkbox.getAttribute('data-student-id');
    const row = checkbox.closest('.student-row');
    
    if (checkbox.checked) {
        const promotionType = row.querySelector('.promotion-type').value;
        const targetSection = row.querySelector('.target-section').value;
        const reason = row.querySelector('.promotion-reason').value;
        const gradeLevel = row.querySelector('.target-section').getAttribute('data-grade-level');
        
        const targetGrade = promotionType === 'Promoted' ? parseInt(gradeLevel) + 1 : parseInt(gradeLevel);
        
        studentDataMap[studentId] = {
            student_id: studentId,
            promotion_type: promotionType,
            to_grade_level: promotionType === 'Promoted' ? targetGrade : parseInt(gradeLevel),
            from_section_id: null, // Will be set from database
            to_section_id: targetSection ? parseInt(targetSection) : null,
            reason: reason
        };
    } else {
        delete studentDataMap[studentId];
    }
}

function updatePromotionType(select) {
    const studentId = select.getAttribute('data-student-id');
    const row = select.closest('.student-row');
    const targetSection = row.querySelector('.target-section');
    const gradeLevel = parseInt(targetSection.getAttribute('data-grade-level'));
    const promotionType = select.value;
    
    // Update target section options based on promotion type
    const targetGrade = promotionType === 'Promoted' ? gradeLevel + 1 : gradeLevel;
    
    // Show/hide section options based on grade level
    const allOptions = targetSection.querySelectorAll('option');
    allOptions.forEach(option => {
        if (option.value === '') {
            // Keep the placeholder
            return;
        }
        const optionGrade = parseInt(option.getAttribute('data-grade'));
        if (optionGrade === targetGrade) {
            option.style.display = '';
            option.classList.remove('alt-grade-option');
        } else {
            option.style.display = 'none';
            option.classList.add('alt-grade-option');
        }
    });
    
    // Reset selection if current selection doesn't match
    const currentValue = targetSection.value;
    const currentOption = targetSection.querySelector(`option[value="${currentValue}"]`);
    if (currentOption && currentOption.style.display === 'none') {
        targetSection.value = '';
    }
    
    // Update stored data if exists
    if (studentDataMap[studentId]) {
        studentDataMap[studentId].promotion_type = promotionType;
        studentDataMap[studentId].to_grade_level = promotionType === 'Promoted' ? targetGrade : gradeLevel;
        if (targetSection.value === '') {
            studentDataMap[studentId].to_section_id = null; // Reset section if invalid
        }
    }
    
    // Update checkbox data if checked
    const checkbox = row.querySelector('.student-checkbox');
    if (checkbox.checked) {
        updateStudentData(checkbox);
    }
}

function updateTargetSection(select) {
    const studentId = select.getAttribute('data-student-id');
    
    if (studentDataMap[studentId]) {
        studentDataMap[studentId].to_section_id = select.value ? parseInt(select.value) : null;
    }
}

function toggleAllStudents(checkbox) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        updateStudentData(cb);
    });
}

function selectAllByType(type) {
    document.querySelectorAll('.promotion-type').forEach(select => {
        if (select.value === type) {
            const row = select.closest('.student-row');
            const checkbox = row.querySelector('.student-checkbox');
            checkbox.checked = true;
            updateStudentData(checkbox);
        }
    });
}

function clearAllSelections() {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.checked = false;
        const studentId = cb.getAttribute('data-student-id');
        delete studentDataMap[studentId];
    });
    document.getElementById('selectAll').checked = false;
}

function previewChanges() {
    const selected = Object.keys(studentDataMap).length;
    if (selected === 0) {
        alert('Please select at least one student.');
        return;
    }
    
    let preview = `Preview: ${selected} student(s) will be processed:\n\n`;
    document.querySelectorAll('.student-checkbox:checked').forEach(checkbox => {
        const studentId = checkbox.getAttribute('data-student-id');
        const row = checkbox.closest('.student-row');
        const name = row.querySelector('td:nth-child(2) strong').textContent;
        const type = row.querySelector('.promotion-type').value;
        const section = row.querySelector('.target-section').options[row.querySelector('.target-section').selectedIndex]?.text || 'Not selected';
        
        preview += `${name}: ${type} → ${section}\n`;
    });
    
    alert(preview);
}

function confirmPromotion() {
    const selected = Object.keys(studentDataMap).length;
    if (selected === 0) {
        alert('Please select at least one student to process.');
        return false;
    }
    
    // Validate all selected students have target sections
    let missingSections = [];
    Object.keys(studentDataMap).forEach(studentId => {
        if (!studentDataMap[studentId].to_section_id) {
            const row = document.querySelector(`.student-row[data-student-id="${studentId}"]`);
            const name = row.querySelector('td:nth-child(2) strong').textContent;
            missingSections.push(name);
        }
    });
    
    if (missingSections.length > 0) {
        alert('Please select target sections for:\n' + missingSections.join('\n'));
        return false;
    }
    
    // Add student data to form
    const form = document.getElementById('promotionForm');
    Object.keys(studentDataMap).forEach(studentId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'students[]';
        input.value = JSON.stringify(studentDataMap[studentId]);
        form.appendChild(input);
    });
    
    return confirm(`Are you sure you want to process ${selected} student(s)? This action cannot be undone.`);
}

// Initialize section visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    // Sections should ALWAYS be visible for ALL students, regardless of grades or eligibility
    // Default to showing "Promoted" sections (next grade) for everyone
    document.querySelectorAll('.target-section').forEach(select => {
        const gradeLevel = parseInt(select.getAttribute('data-grade-level'));
        
        // Always default to Promoted (next grade) - sections available for all students
        const targetGrade = gradeLevel + 1;
        
        // Show/hide options based on grade level
        const allOptions = select.querySelectorAll('option');
        allOptions.forEach(option => {
            if (option.value === '' || option.disabled) {
                // Keep the placeholder and disabled options visible
                return;
            }
            const optionGrade = parseInt(option.getAttribute('data-grade'));
            // Show sections for promoted (next grade) by default for ALL students
            if (optionGrade === targetGrade) {
                option.style.display = '';
                option.classList.remove('alt-grade-option');
            } else {
                // Hide sections for same grade (retained) initially, but they're still available
                option.style.display = 'none';
                option.classList.add('alt-grade-option');
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

