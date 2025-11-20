# Installation Guide - EduTrack

## Quick Start Guide

### Step 1: Database Setup

1. **Create Database**
   - Open phpMyAdmin or MySQL command line
   - Create a new database named `edutrack`
   - Or use the command: `CREATE DATABASE edutrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

2. **Import Schema**
   - In phpMyAdmin: Select the `edutrack` database, go to Import tab, choose `database/schema.sql`
   - Or via command line: `mysql -u root -p edutrack < database/schema.sql`

### Step 2: Configure Database Connection

Edit `config/config.php`:

```php
define('DB_HOST', 'localhost');      // Your MySQL host
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
define('DB_NAME', 'edutrack');      // Database name
define('APP_URL', 'http://localhost/edutrack');  // Your application URL
```

### Step 3: Set Up Web Server

**For WAMP/XAMPP:**
- Place the `edutrack` folder in `C:\wamp64\www\` (or `C:\xampp\htdocs\`)
- Access via: `http://localhost/edutrack`

**For Linux/Apache:**
- Place in `/var/www/html/edutrack/` or your web root
- Ensure Apache has read permissions
- Access via: `http://localhost/edutrack`

### Step 4: Verify Installation

1. Open browser: `http://localhost/edutrack`
2. You should see the login page
3. Login with:
   - **Username**: `admin`
   - **Password**: `admin123`

### Step 5: Initial Setup (Recommended)

1. **Change Admin Password**
   - After first login, go to "Manage Users"
   - Delete and recreate admin account with new password
   - Or manually update in database

2. **Create Subjects**
   - Go to Admin → Subjects
   - Add subjects (e.g., Math, Science, English)

3. **Create Sections**
   - Go to Admin → Sections
   - Add sections (e.g., Section A, Grade 7, 2024-2025)

4. **Create Users**
   - Create Teacher accounts
   - Create Student accounts
   - Create Parent accounts

5. **Link Parents to Students**
   - Go to Admin → Parent-Student Links
   - Connect parent accounts to student accounts

6. **Assign Teachers**
   - Go to Admin → Teachers
   - Assign teachers to subjects and sections

## Troubleshooting

### Database Connection Failed

**Error**: "Database connection error"

**Solutions**:
- Verify MySQL service is running
- Check database credentials in `config/config.php`
- Ensure database `edutrack` exists
- Check MySQL user has proper permissions

### Session Errors

**Error**: "Headers already sent" or session issues

**Solutions**:
- Ensure no whitespace before `<?php` tags
- Check `session_start()` is called before any output
- Verify PHP sessions are enabled
- Clear browser cookies

### Page Not Found (404)

**Solutions**:
- Verify file paths match your server structure
- Check `.htaccess` file exists (for Apache)
- Ensure mod_rewrite is enabled (for clean URLs)
- Try accessing files directly: `http://localhost/edutrack/login.php`

### Permission Denied

**Solutions**:
- Ensure web server has read access to all files
- Check file permissions (Linux: `chmod 644` for files, `chmod 755` for directories)
- Verify PHP can write to session directory

### Blank Page

**Solutions**:
- Enable error display in `config/config.php` (already enabled by default)
- Check PHP error logs
- Verify PHP version is 7.4 or higher
- Check browser console for JavaScript errors

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Apache**: 2.4+ (or Nginx with PHP-FPM)
- **Browser**: Modern browser with JavaScript enabled

## PHP Extensions Required

- `mysqli` - MySQL database connection
- `session` - Session management
- `mbstring` - String handling (usually enabled by default)

## Security Checklist

After installation:

- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Set proper file permissions
- [ ] Enable HTTPS (for production)
- [ ] Review `.htaccess` security settings
- [ ] Disable error display in production (set `display_errors` to 0)

## Next Steps

1. Create your first teacher account
2. Create student accounts
3. Create parent accounts and link them
4. Assign teachers to classes
5. Start entering grades and attendance!

## Support

For additional help, refer to `README.md` or contact your system administrator.

