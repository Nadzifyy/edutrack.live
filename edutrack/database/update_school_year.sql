-- Update school year from 2024-2025 to 2025-2026
-- This script updates all school year references in the database

-- Update sections table
UPDATE sections 
SET school_year = '2025-2026' 
WHERE school_year = '2024-2025';

-- Update teacher_subject_sections table
UPDATE teacher_subject_sections 
SET school_year = '2025-2026' 
WHERE school_year = '2024-2025';

-- Update student_promotions table (if it exists)
-- Update from_school_year
UPDATE student_promotions 
SET from_school_year = '2025-2026' 
WHERE from_school_year = '2024-2025';

-- Update to_school_year
UPDATE student_promotions 
SET to_school_year = '2025-2026' 
WHERE to_school_year = '2024-2025';

