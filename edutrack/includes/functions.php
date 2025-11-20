<?php
/**
 * Utility Functions
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Display text safely - decodes HTML entities first, then encodes properly
 * This prevents double-encoding issues when data already contains HTML entities
 */
function displayText($text) {
    return htmlspecialchars(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

/**
 * Get profile picture URL for a user
 */
function getProfilePicture($profile_picture, $user_id = null) {
    if (!empty($profile_picture) && file_exists(__DIR__ . '/../uploads/profiles/' . $profile_picture)) {
        return baseUrl('uploads/profiles/' . $profile_picture);
    }
    // Return default avatar (SVG data URI)
    return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="#e2e8f0"/><text x="50" y="60" font-size="40" text-anchor="middle" fill="#64748b">👤</text></svg>');
}

/**
 * Handle profile picture upload
 */
function uploadProfilePicture($file, $user_id) {
    $upload_dir = __DIR__ . '/../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid parameters.'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error occurred.'];
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'];
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2097152) {
        return ['success' => false, 'error' => 'File size exceeds 2MB limit.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
    
    return ['success' => true, 'filename' => $filename];
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Get base URL
 */
function baseUrl($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Get grading period name
 */
function getGradingPeriodName($period) {
    $periods = [
        'Q1' => 'First Quarter',
        'Q2' => 'Second Quarter',
        'Q3' => 'Third Quarter',
        'Q4' => 'Fourth Quarter'
    ];
    return isset($periods[$period]) ? $periods[$period] : $period;
}

/**
 * Get all grading periods
 */
function getGradingPeriods() {
    return ['Q1', 'Q2', 'Q3', 'Q4'];
}

/**
 * Get attendance status options
 */
function getAttendanceStatuses() {
    return ['Present', 'Absent', 'Tardy'];
}

/**
 * Calculate average grade
 */
function calculateAverage($grades) {
    if (empty($grades)) return 0;
    $sum = array_sum($grades);
    $count = count($grades);
    return $count > 0 ? round($sum / $count, 2) : 0;
}

/**
 * Get grade letter
 */
function getGradeLetter($grade) {
    if ($grade >= 90) return 'A';
    if ($grade >= 80) return 'B';
    if ($grade >= 70) return 'C';
    if ($grade >= 60) return 'D';
    return 'F';
}

/**
 * Check if user is parent of student
 */
function isParentOfStudent($parent_user_id, $student_id) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id FROM parent_student_links WHERE parent_user_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $parent_user_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

/**
 * Get students linked to parent
 */
function getParentStudents($parent_user_id) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT s.id, s.student_number, u.first_name, u.last_name, s.section_id, sec.section_name, sec.grade_level
        FROM parent_student_links psl
        JOIN students s ON psl.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE psl.parent_user_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->bind_param("i", $parent_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    $stmt->close();
    return $students;
}

/**
 * Calculate overall GPA for a student
 */
function calculateOverallGPA($student_id) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Calculate average per subject first, then average those subject averages
    // This ensures that one subject with grades doesn't show as the "overall average"
    $stmt = $conn->prepare("
        SELECT subject_id, AVG(grade_value) as subject_avg
        FROM grades
        WHERE student_id = ?
        GROUP BY subject_id
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subject_averages = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['subject_avg'] !== null) {
            $subject_averages[] = floatval($row['subject_avg']);
        }
    }
    $stmt->close();
    
    // If no subjects have grades, return 0
    if (empty($subject_averages)) {
        return 0;
    }
    
    // Calculate average of all subject averages
    $overall_avg = array_sum($subject_averages) / count($subject_averages);
    return round($overall_avg, 2);
}

/**
 * Get student performance summary
 */
function getStudentPerformanceSummary($student_id) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get overall average
    $overall_avg = calculateOverallGPA($student_id);
    
    // Get attendance rate
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count
        FROM attendance
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_data = $result->fetch_assoc();
    $stmt->close();
    
    $attendance_rate = 0;
    if ($attendance_data['total'] > 0) {
        $attendance_rate = round(($attendance_data['present_count'] / $attendance_data['total']) * 100, 2);
    }
    
    // Get last grade update
    $stmt = $conn->prepare("
        SELECT MAX(updated_at) as last_grade_update
        FROM grades
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grade_update = $result->fetch_assoc();
    $stmt->close();
    
    // Get last attendance update
    $stmt = $conn->prepare("
        SELECT MAX(updated_at) as last_attendance_update
        FROM attendance
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_update = $result->fetch_assoc();
    $stmt->close();
    
    // Get last remark update
    $stmt = $conn->prepare("
        SELECT MAX(updated_at) as last_remark_update
        FROM teacher_remarks
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $remark_update = $result->fetch_assoc();
    $stmt->close();
    
    // Get recent updates count (last 7 days)
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM grades WHERE student_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_grades,
            (SELECT COUNT(*) FROM attendance WHERE student_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_attendance,
            (SELECT COUNT(*) FROM teacher_remarks WHERE student_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_remarks
    ");
    $stmt->bind_param("iii", $student_id, $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_updates = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'overall_average' => $overall_avg,
        'attendance_rate' => $attendance_rate,
        'total_attendance' => $attendance_data['total'] ?? 0,
        'present_count' => $attendance_data['present_count'] ?? 0,
        'last_grade_update' => $grade_update['last_grade_update'] ?? null,
        'last_attendance_update' => $attendance_update['last_attendance_update'] ?? null,
        'last_remark_update' => $remark_update['last_remark_update'] ?? null,
        'recent_grades' => $recent_updates['recent_grades'] ?? 0,
        'recent_attendance' => $recent_updates['recent_attendance'] ?? 0,
        'recent_remarks' => $recent_updates['recent_remarks'] ?? 0
    ];
}

/**
 * Get recent updates for a student
 */
function getRecentStudentUpdates($student_id, $limit = 10) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $updates = [];
    
    // Get recent grade updates
    $stmt = $conn->prepare("
        SELECT g.updated_at, g.grade_value, g.grading_period, s.subject_name, 'grade' as update_type
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        WHERE g.student_id = ?
        ORDER BY g.updated_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
    $stmt->close();
    
    // Get recent attendance updates
    $stmt = $conn->prepare("
        SELECT COALESCE(updated_at, attendance_date) as updated_at, attendance_date, status, remarks, 'attendance' as update_type
        FROM attendance
        WHERE student_id = ?
        ORDER BY COALESCE(updated_at, attendance_date) DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
    $stmt->close();
    
    // Get recent remark updates
    $stmt = $conn->prepare("
        SELECT tr.updated_at, tr.grading_period, tr.remark_text, u.first_name, u.last_name, 'remark' as update_type
        FROM teacher_remarks tr
        JOIN teachers t ON tr.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE tr.student_id = ?
        ORDER BY tr.updated_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $student_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
    $stmt->close();
    
    // Sort by date and limit
    usort($updates, function($a, $b) {
        $dateA = strtotime($a['updated_at']);
        $dateB = strtotime($b['updated_at']);
        return $dateB - $dateA;
    });
    
    return array_slice($updates, 0, $limit);
}

/**
 * Log user activity
 */
function logUserActivity($action, $description = '', $user_id = null) {
    require_once __DIR__ . '/../config/database.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get user info if not provided
    if ($user_id === null) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Check if table exists before inserting
    $table_check = $conn->query("SHOW TABLES LIKE 'user_logs'");
    if ($table_check->num_rows === 0) {
        // Table doesn't exist yet, skip logging
        return;
    }
    
    // Insert log
    $stmt = $conn->prepare("
        INSERT INTO user_logs (user_id, username, action, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssss", $user_id, $username, $action, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get student promotion eligibility based on performance
 * @param int $student_id Student ID
 * @param float $passing_grade Passing grade threshold (default 75)
 * @return array Promotion suggestion data
 */
function getStudentPromotionEligibility($student_id, $passing_grade = 75.0) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get overall GPA
    $gpa = calculateOverallGPA($student_id);
    
    // Get attendance rate
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count
        FROM attendance
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_data = $result->fetch_assoc();
    $stmt->close();
    
    $attendance_rate = 0;
    if ($attendance_data['total'] > 0) {
        $attendance_rate = round(($attendance_data['present_count'] / $attendance_data['total']) * 100, 2);
    }
    
    // Check if all quarters have grades
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT grading_period) as quarters_count
        FROM grades
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quarters_data = $result->fetch_assoc();
    $stmt->close();
    
    $all_quarters_graded = ($quarters_data['quarters_count'] >= 4);
    
    // Determine eligibility
    $eligible = ($gpa >= $passing_grade && $attendance_rate >= 75 && $all_quarters_graded);
    $suggestion = $eligible ? 'Promoted' : 'Retained';
    
    return [
        'gpa' => $gpa,
        'attendance_rate' => $attendance_rate,
        'all_quarters_graded' => $all_quarters_graded,
        'eligible' => $eligible,
        'suggestion' => $suggestion,
        'passing_grade' => $passing_grade
    ];
}

/**
 * Get students by grade level for promotion
 * @param int $grade_level Grade level (1-6)
 * @param string $school_year School year
 * @return array Students with promotion eligibility
 */
function getStudentsForPromotion($grade_level, $school_year = null) {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if promotion columns exist
    $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'current_grade_level'");
    $has_promotion_columns = $column_check->num_rows > 0;
    
    $students = [];
    
    if ($school_year) {
        if ($has_promotion_columns) {
            $stmt = $conn->prepare("
                SELECT s.id, s.student_number, s.section_id, s.current_grade_level, s.promotion_status,
                       u.first_name, u.last_name, u.email,
                       sec.section_name, sec.grade_level, sec.school_year
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE (s.current_grade_level = ? OR CAST(sec.grade_level AS UNSIGNED) = ?)
                AND (sec.school_year = ? OR s.section_id IS NULL)
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->bind_param("iis", $grade_level, $grade_level, $school_year);
        } else {
            // Fallback if columns don't exist yet
            $stmt = $conn->prepare("
                SELECT s.id, s.student_number, s.section_id, NULL as current_grade_level, 'Active' as promotion_status,
                       u.first_name, u.last_name, u.email,
                       sec.section_name, sec.grade_level, sec.school_year
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE CAST(sec.grade_level AS UNSIGNED) = ?
                AND (sec.school_year = ? OR s.section_id IS NULL)
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->bind_param("is", $grade_level, $school_year);
        }
    } else {
        if ($has_promotion_columns) {
            $stmt = $conn->prepare("
                SELECT s.id, s.student_number, s.section_id, s.current_grade_level, s.promotion_status,
                       u.first_name, u.last_name, u.email,
                       sec.section_name, sec.grade_level, sec.school_year
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE s.current_grade_level = ? OR CAST(sec.grade_level AS UNSIGNED) = ?
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->bind_param("ii", $grade_level, $grade_level);
        } else {
            // Fallback if columns don't exist yet
            $stmt = $conn->prepare("
                SELECT s.id, s.student_number, s.section_id, NULL as current_grade_level, 'Active' as promotion_status,
                       u.first_name, u.last_name, u.email,
                       sec.section_name, sec.grade_level, sec.school_year
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE CAST(sec.grade_level AS UNSIGNED) = ?
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->bind_param("i", $grade_level);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Get promotion eligibility
        $eligibility = getStudentPromotionEligibility($row['id']);
        $row['promotion_eligibility'] = $eligibility;
        $students[] = $row;
    }
    $stmt->close();
    
    return $students;
}

/**
 * Process student promotion/retention
 * @param int $student_id Student ID
 * @param string $promotion_type Promotion type (Promoted, Retained, etc.)
 * @param int $from_grade_level Current grade level
 * @param int $to_grade_level Target grade level (null for retained)
 * @param int $from_section_id Current section ID
 * @param int $to_section_id Target section ID
 * @param string $from_school_year Current school year
 * @param string $to_school_year Target school year
 * @param int $promoted_by Admin user ID
 * @param string $reason Reason for retention/promotion
 * @param string $notes Additional notes
 * @return bool Success status
 */
function processStudentPromotion($student_id, $promotion_type, $from_grade_level, $to_grade_level, 
                                 $from_section_id, $to_section_id, $from_school_year, $to_school_year,
                                 $promoted_by, $reason = '', $notes = '') {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if promotion columns exist
    $column_check = $conn->query("SHOW COLUMNS FROM students LIKE 'current_grade_level'");
    $has_promotion_columns = $column_check->num_rows > 0;
    
    // Check if promotions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_promotions'");
    $has_promotions_table = $table_check->num_rows > 0;
    
    $conn->begin_transaction();
    
    try {
        // Update student record
        if ($has_promotion_columns) {
            $update_sql = "UPDATE students SET section_id = ?, promotion_status = ?, current_grade_level = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            // For retained students, grade level stays the same
            $new_grade_level = ($promotion_type === 'Retained') ? $from_grade_level : $to_grade_level;
            $promotion_status = $promotion_type === 'Promoted' ? 'Promoted' : ($promotion_type === 'Retained' ? 'Retained' : $promotion_type);
            
            $update_stmt->bind_param("isii", $to_section_id, $promotion_status, $new_grade_level, $student_id);
        } else {
            // Fallback: only update section_id
            $update_sql = "UPDATE students SET section_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $to_section_id, $student_id);
        }
        
        $update_stmt->execute();
        $update_stmt->close();
        
        // Create promotion record if table exists
        if ($has_promotions_table) {
            $insert_sql = "
                INSERT INTO student_promotions 
                (student_id, promotion_type, from_grade_level, to_grade_level, from_section_id, to_section_id,
                 from_school_year, to_school_year, reason, promoted_by, promotion_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isiiiiissss", $student_id, $promotion_type, $from_grade_level, $to_grade_level,
                                    $from_section_id, $to_section_id, $from_school_year, $to_school_year,
                                    $reason, $promoted_by, $notes);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
        // Log activity
        logUserActivity('PROMOTION', "Student promotion: {$promotion_type} from Grade {$from_grade_level} to " . ($to_grade_level ? "Grade {$to_grade_level}" : "same grade"), $promoted_by);
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

