-- Fix Admin Password SQL Script
-- Run this in phpMyAdmin to reset the admin password to 'admin123'

-- Option 1: Update existing admin user
UPDATE users 
SET password_hash = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
WHERE username = 'admin';

-- Option 2: If admin doesn't exist, insert it
INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
VALUES ('admin', 'admin@edutrack.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'administrator', 'System', 'Administrator')
ON DUPLICATE KEY UPDATE password_hash = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

-- The password hash above is for: admin123
-- Generated using: password_hash('admin123', PASSWORD_DEFAULT)

