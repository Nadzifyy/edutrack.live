# Profile Pictures Migration Guide

## Quick Setup

To add profile picture support to your system, follow these steps:

### Step 1: Run the Migration

Open your web browser and navigate to:
```
http://localhost/edutrack/database/migrate_profile_pictures.php
```

This will automatically:
- Check if the column already exists
- Add the `profile_picture` column to the users table
- Show you a success message

### Step 2: Verify Uploads Directory

The system will automatically create the `uploads/profiles/` directory when needed, but you can manually create it if you prefer:

**Windows:**
```
mkdir uploads\profiles
```

**Linux/Mac:**
```
mkdir -p uploads/profiles
```

Make sure the web server has write permissions to this directory.

### Step 3: Test Profile Pictures

1. Log in as administrator
2. Go to "Manage Users"
3. Click "Edit" on any user
4. Upload a profile picture (optional)
5. Save the changes
6. The profile picture will appear in the header

## Features Added

✅ Profile picture upload for all users (students, teachers, administrators, parents)
✅ Profile pictures displayed in the header
✅ Default avatar when no picture is uploaded
✅ Automatic cleanup of old pictures when uploading new ones
✅ File validation (JPEG, PNG, GIF, max 2MB)

## Security

- Uploaded files are stored in `uploads/profiles/`
- File types are validated (only images allowed)
- File size is limited to 2MB
- Unique filenames prevent conflicts
- Old files are automatically deleted when replaced

## Troubleshooting

**If the migration fails:**
- Check your database connection in `config/config.php`
- Ensure you have proper database permissions
- Check the error message for specific issues

**If uploads don't work:**
- Check that `uploads/profiles/` directory exists
- Verify web server has write permissions
- Check PHP upload settings in `php.ini`:
  - `upload_max_filesize` should be at least 2M
  - `post_max_size` should be at least 2M

