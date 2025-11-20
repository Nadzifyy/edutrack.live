# EduTrack - Student Performance Monitoring System

A comprehensive web-based student performance monitoring system built with PHP and MySQL. This system allows Teachers and Administrators to manage student academic records, while Students and Parents can view read-only reports.

## Features

### Role-Based Access Control

- **Administrator**: Manage teacher/student accounts, parent-student assignments, subjects, sections, and view reports
- **Teacher**: Encode/update grades, attendance, and remarks; view class lists; generate per-student progress reports
- **Student**: View grades, attendance, teacher feedback, and performance trend charts (read-only)
- **Parent**: View academic records for all linked children in one account, with the ability to select individual children or view combined reports

### Core Features

- **Grade Management**: Teachers can add/update grades per subject and grading period (Q1–Q4)
- **Attendance Monitoring**: Teachers can mark daily attendance as Present, Absent, or Tardy
- **Teacher Remarks**: Teachers can add qualitative remarks for each student per grading period
- **Reporting & Visualization**: Web-based reports with Chart.js visualization and print-friendly CSS
- **Multi-Child Support**: Parents can access all linked children under a single account

## Technology Stack

- **Backend**: PHP 8.x (OOP)
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript (vanilla + Chart.js)
- **Server**: Apache (LAMP-compatible)

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- Modern web browser

## Installation

### 1. Database Setup

1. Create a MySQL database (or use phpMyAdmin)
2. Import the database schema:

```bash
mysql -u root -p < database/schema.sql
```

Or via phpMyAdmin:
- Create a new database named `edutrack`
- Import the `database/schema.sql` file

### 2. Configuration

Edit `config/config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edutrack');
```

Update the application URL if needed:

```php
define('APP_URL', 'http://localhost/edutrack');
```

### 3. File Permissions

Ensure the web server has read access to all files. No special write permissions are required for the application files.

### 4. Access the Application

1. Open your web browser
2. Navigate to: `http://localhost/edutrack`
3. Login with default admin credentials:
   - **Username**: `admin`
   - **Password**: `admin123`

**Important**: Change the default admin password after first login!

## Default Login Credentials

- **Administrator**:
  - Username: `admin`
  - Password: `admin123`

## Project Structure

```
edutrack/
├── admin/              # Administrator pages
│   ├── dashboard.php
│   ├── users.php
│   ├── students.php
│   ├── teachers.php
│   ├── subjects.php
│   ├── sections.php
│   └── assignments.php
├── teacher/            # Teacher pages
│   ├── dashboard.php
│   ├── classes.php
│   ├── grades.php
│   ├── attendance.php
│   └── remarks.php
├── student/            # Student pages
│   ├── dashboard.php
│   ├── grades.php
│   ├── attendance.php
│   ├── remarks.php
│   └── reports.php
├── parent/             # Parent pages
│   ├── dashboard.php
│   └── reports.php
├── assets/             # Static assets
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── config/             # Configuration files
│   ├── config.php
│   └── database.php
├── includes/           # Shared includes
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── database/           # Database files
│   └── schema.sql
├── login.php           # Login page
├── logout.php          # Logout handler
├── index.php           # Main router
└── README.md           # This file
```

## Usage Guide

### For Administrators

1. **Create Users**: Go to "Manage Users" to create new teacher, student, or parent accounts
2. **Manage Subjects**: Add subjects in "Subjects"
3. **Manage Sections**: Create sections with grade levels in "Sections"
4. **Assign Teachers**: Link teachers to subjects and sections in "Teachers"
5. **Link Parents**: Connect parent accounts to student accounts in "Parent-Student Links"

### For Teachers

1. **View Classes**: Check assigned classes in "My Classes"
2. **Encode Grades**: Go to "Manage Grades", select a class, and enter grades for Q1-Q4
3. **Mark Attendance**: Use "Attendance" to mark daily attendance
4. **Add Remarks**: Add qualitative feedback in "Remarks"

### For Students

1. **View Dashboard**: See summary of grades and attendance
2. **Check Grades**: View all grades by subject in "My Grades"
3. **View Attendance**: Check attendance records in "Attendance"
4. **Read Remarks**: See teacher feedback in "Teacher Remarks"
5. **Performance Report**: View comprehensive report with charts in "Performance Report"

### For Parents

1. **View Children**: See all linked children on the dashboard
2. **Select Child**: Choose a child to view their report
3. **View Reports**: Access grades, attendance, and remarks for selected child
4. **Print Reports**: Use the print button for printable reports

## Security Features

- Session-based authentication
- Password hashing using PHP's `password_hash()`
- Input validation and sanitization
- Prepared statements to prevent SQL injection
- Role-based access control
- Parent access restricted to linked children only

## Reporting Features

- **Grade Reports**: View grades by subject and grading period
- **Performance Charts**: Visual representation using Chart.js
- **Attendance Summary**: Track attendance patterns
- **Teacher Remarks**: View qualitative feedback
- **Print-Friendly**: CSS optimized for printing reports

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Troubleshooting

### Database Connection Error

- Verify database credentials in `config/config.php`
- Ensure MySQL service is running
- Check database name matches in config file

### Session Issues

- Ensure PHP sessions are enabled
- Check file permissions on session directory
- Verify `session_start()` is called before any output

### Page Not Found

- Verify `.htaccess` is configured (if using Apache)
- Check file paths match your server structure
- Ensure mod_rewrite is enabled (if using clean URLs)

## Development Notes

- All data entry requires an online connection
- Offline editing is not supported
- Optional: Latest viewed report can be cached using localStorage for read-only offline viewing (not implemented in MVP)

## License

This project is provided as-is for educational purposes.

## Support

For issues or questions, please contact your system administrator.

---

**Version**: 1.0.0  
**Last Updated**: 2024

