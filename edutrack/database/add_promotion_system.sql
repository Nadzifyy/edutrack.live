-- Add promotion system to students table
-- This migration adds promotion tracking capabilities

-- Add promotion status and grade level tracking to students table
ALTER TABLE students 
ADD COLUMN promotion_status ENUM('Active', 'Promoted', 'Retained', 'Transferred', 'Graduated', 'Dropped') DEFAULT 'Active' AFTER section_id,
ADD COLUMN current_grade_level INT NULL AFTER promotion_status,
ADD INDEX idx_promotion_status (promotion_status),
ADD INDEX idx_grade_level (current_grade_level);

-- Create promotion history table
CREATE TABLE IF NOT EXISTS student_promotions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    promotion_type ENUM('Promoted', 'Retained', 'Transferred', 'Graduated', 'Dropped') NOT NULL,
    from_grade_level INT NOT NULL,
    to_grade_level INT NULL,
    from_section_id INT,
    to_section_id INT,
    from_school_year VARCHAR(20) NOT NULL,
    to_school_year VARCHAR(20) NOT NULL,
    reason TEXT,
    promoted_by INT NOT NULL,
    promotion_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (from_section_id) REFERENCES sections(id) ON DELETE SET NULL,
    FOREIGN KEY (to_section_id) REFERENCES sections(id) ON DELETE SET NULL,
    FOREIGN KEY (promoted_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_type (promotion_type),
    INDEX idx_school_year (to_school_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

