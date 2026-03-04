# Smart Campus Management System - Setup Guide

## File Structure Overview

```
public/
├── index.php                          # Home page
├── homepage.php                       # Dashboard (all roles)
├── includes/
│   ├── check_session.php             # Session verification (include in all protected pages)
│   ├── header.php                    # Header component
│   └── footer.php                    # Footer component
├── auth/
│   ├── login.php                     # Login page (with DB authentication)
│   ├── logout.php                    # Logout handler
│   ├── css/
│   │   └── login.css                 # Login page styling
│   └── js/
│       └── login.js                  # Login page JS
├── config/
│   └── db.php                        # Database connection (already exists)
├── css/
│   ├── header.css                    # Header styling (existing)
│   └── homepage.css                  # Homepage styling
├── js/
│   ├── header.js                     # Header JS (existing)
│   └── homepage.js                   # Homepage JS
└── assets/
    └── ...
```

## How It Works

### 1. **Session Management**

The system uses PHP sessions with three required variables:
- `$_SESSION['user_id']` - Unique user identifier
- `$_SESSION['role']` - User role: 'student', 'staff', or 'admin'
- `$_SESSION['name']` - User's full name

### 2. **Login Flow**

1. User visits `/auth/login.php`
2. Enters email and password
3. System queries the database to find the user
4. Verifies password using `password_verify()`
5. If valid, sets session variables and redirects to `/homepage.php`
6. If invalid, shows error message

### 3. **Protected Pages**

To protect any page, include this at the top:

```php
<?php
include 'includes/check_session.php';
?>
```

The `check_session.php` will:
- Start the session
- Check if user is logged in
- Redirect to login if not authenticated
- Store session values in `$user_id`, `$user_role`, `$user_name`

### 4. **Role-Based Content**

Use PHP conditionals to show different content for different roles:

```php
<?php if ($user_role == 'student'): ?>
    <!-- Show student content -->
<?php endif; ?>

<?php if ($user_role == 'staff'): ?>
    <!-- Show staff content -->
<?php endif; ?>

<?php if ($user_role == 'admin'): ?>
    <!-- Show admin content -->
<?php endif; ?>
```

## Database Setup

### Users Table Schema

Create a `users` table with the following structure:

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'staff', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Create Test Users

Run these SQL commands to create test accounts:

```sql
-- Student Test Account
INSERT INTO users (name, email, password, role) VALUES (
    'John Student',
    'student@campus.edu',
    '$2y$10$nOUIs5kJ7naTuTZkS6PO.OPST9/PgBkqquzi.Ss7KIUgO2t0jGMUi',
    'student'
);

-- Staff Test Account
INSERT INTO users (name, email, password, role) VALUES (
    'Prof. Smith',
    'staff@campus.edu',
    '$2y$10$nOUIs5kJ7naTuTZkS6PO.OPST9/PgBkqquzi.Ss7KIUgO2t0jGMUi',
    'staff'
);

-- Admin Test Account
INSERT INTO users (name, email, password, role) VALUES (
    'Admin User',
    'admin@campus.edu',
    '$2y$10$nOUIs5kJ7naTuTZkS6PO.OPST9/PgBkqquzi.Ss7KIUgO2t0jGMUi',
    'admin'
);
```

**Note:** The hashed password above is for `password123`. To create custom test accounts with different passwords, use PHP to generate the hash:

```php
<?php
echo password_hash('your_password_here', PASSWORD_BCRYPT);
?>
```

### Test Credentials

Use these credentials to test the system:

| Role    | Email             | Password   |
|---------|-------------------|------------|
| Student | student@campus.edu | password123|
| Staff   | staff@campus.edu  | password123|
| Admin   | admin@campus.edu  | password123|

## Testing the Login System

### Step 1: Create Users in Database

Run the SQL commands above to create test users.

### Step 2: Visit Login Page

Open your browser and go to: `http://localhost:8000/auth/login.php`

(Adjust the URL based on your server configuration)

### Step 3: Login with Test Credentials

- Email: `student@campus.edu`
- Password: `password123`

### Step 4: Verify Role-Based Dashboard

You should see the Student dashboard. Logout and try with different credentials.

## Key Files Explained

### check_session.php
- Includes at top of every protected page
- Verifies user is logged in
- Redirects to login if not authenticated
- Provides `$user_id`, `$user_role`, `$user_name` variables

### login.php
- Accepts POST requests with email and password
- Validates against database using prepared statements
- Creates session on successful login
- Shows error messages on failed login

### homepage.php
- Includes `check_session.php` (protected page)
- Shows different dashboards based on `$user_role`
- Has separate sections for student, staff, and admin

### logout.php
- Destroys the session
- Redirects back to login.php

## Security Features

✅ Password hashing using `password_hash()` and `password_verify()`
✅ Prepared statements prevent SQL injection
✅ Session validation on every protected page
✅ Role validation to prevent spoofing
✅ Input sanitization with `htmlspecialchars()`
✅ Logout functionality to clear sessions

## Troubleshooting

### "Invalid email or password" error

1. Check that user exists in database
2. Verify password is correct using: `password_hash('password123', PASSWORD_BCRYPT)`
3. Check database connection in `config/db.php`

### Stuck on login page after entering credentials

1. Check browser console for JavaScript errors
2. Verify database connection is working
3. Check that users table has the correct structure
4. Verify email and password fields in form match POST data

### "Session verification failed" error

1. Ensure `check_session.php` is included correctly
2. Verify session variables are set in login.php
3. Check browser cookies are enabled

## Creating New Protected Pages

To create a new protected page:

1. Create `pagename.php` with:
```php
<?php include 'includes/check_session.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/pagename.css">
</head>
<body>
    <?php if ($user_role == 'student'): ?>
        <!-- Student content -->
    <?php endif; ?>
    
    <?php if ($user_role == 'staff'): ?>
        <!-- Staff content -->
    <?php endif; ?>
    
    <?php if ($user_role == 'admin'): ?>
        <!-- Admin content -->
    <?php endif; ?>
    
    <script src="js/pagename.js"></script>
</body>
</html>
```

2. Create `css/pagename.css` with styling
3. Create `js/pagename.js` with JavaScript logic

## Next Steps

1. ✅ Set up database and users table
2. ✅ Create test accounts with SQL commands
3. ✅ Test login with different roles
4. ✅ Create additional role-specific pages
5. ✅ Add database queries for each role's features

---

**Last Updated:** March 4, 2026
**Version:** 1.0
