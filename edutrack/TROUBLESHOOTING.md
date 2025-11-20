# Troubleshooting Internal Server Error

## Quick Fixes

### 1. Check Database Connection

The most common cause is a missing database. Make sure you've:

1. Created the database `edutrack` in MySQL
2. Imported the schema from `database/schema.sql`

**To check:**
- Open phpMyAdmin: `http://localhost/phpmyadmin`
- Look for database named `edutrack`
- If missing, create it and import `database/schema.sql`

### 2. Temporarily Disable .htaccess

If `.htaccess` is causing issues, temporarily rename it:

1. Rename `.htaccess` to `.htaccess.bak`
2. Try accessing the site again
3. If it works, the issue is with `.htaccess` configuration

### 3. Check PHP Error Logs

**WAMP Error Log Location:**
- `C:\wamp64\logs\php_error.log`
- `C:\wamp64\logs\apache_error.log`

**To view:**
1. Click WAMP icon → Logs → PHP error log
2. Look for the most recent error
3. Share the error message for further help

### 4. Run Diagnostic Test

1. Navigate to: `http://localhost/edutrack/test.php`
2. This will show:
   - PHP version
   - Database connection status
   - Missing files
   - Required extensions

### 5. Common Issues and Solutions

#### Issue: "Database connection failed"
**Solution:**
- Verify database exists: `edutrack`
- Check credentials in `config/config.php`
- Ensure MySQL service is running (green WAMP icon)

#### Issue: "Call to undefined function"
**Solution:**
- Check if all files are in correct locations
- Verify file permissions
- Ensure PHP extensions are enabled (mysqli, session)

#### Issue: "Headers already sent"
**Solution:**
- Check for whitespace before `<?php` tags
- Ensure no output before `session_start()`
- Check for BOM (Byte Order Mark) in files

#### Issue: ".htaccess causing 500 error"
**Solution:**
- Rename `.htaccess` to `.htaccess.bak`
- Check if mod_rewrite is enabled in Apache
- Verify Apache version (2.4 uses different syntax)

### 6. Step-by-Step Debugging

1. **Test basic PHP:**
   - Create `info.php` with: `<?php phpinfo(); ?>`
   - Access: `http://localhost/edutrack/info.php`
   - If this fails, PHP is not configured correctly

2. **Test database:**
   - Access: `http://localhost/edutrack/test.php`
   - Check database connection status

3. **Test login page:**
   - Access: `http://localhost/edutrack/login.php`
   - If this fails, check file paths and includes

4. **Check file structure:**
   - Ensure all directories exist
   - Verify file permissions (readable by web server)

### 7. WAMP-Specific Issues

**If WAMP icon is orange/red:**
- Right-click WAMP icon → Tools → Check services
- Ensure MySQL and Apache are running (green)

**If mod_rewrite not working:**
- Click WAMP icon → Apache → Apache modules
- Check `rewrite_module` is enabled

**If PHP extensions missing:**
- Click WAMP icon → PHP → PHP extensions
- Enable: `mysqli`, `session`, `mbstring`

### 8. Verify Configuration

Check `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Usually empty for WAMP
define('DB_NAME', 'edutrack');
define('APP_URL', 'http://localhost/edutrack');
```

### 9. Still Having Issues?

1. Check WAMP error logs
2. Run `test.php` diagnostic
3. Verify database exists and is imported
4. Try accessing `login.php` directly
5. Check browser console for JavaScript errors

### 10. Emergency Fix

If nothing works, try this minimal setup:

1. **Disable .htaccess:**
   - Rename `.htaccess` to `.htaccess.disabled`

2. **Create minimal test:**
   - Create `test2.php`:
   ```php
   <?php
   echo "PHP is working!<br>";
   $conn = new mysqli('localhost', 'root', '', 'edutrack');
   if ($conn->connect_error) {
       echo "DB Error: " . $conn->connect_error;
   } else {
       echo "Database connected!";
   }
   ?>
   ```

3. **Access:** `http://localhost/edutrack/test2.php`

This will tell you if the issue is PHP, database, or application code.

---

**Need More Help?**
- Check error logs
- Run diagnostic test (`test.php`)
- Verify all files are in place
- Check WAMP services are running

