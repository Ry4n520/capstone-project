# Code Structure & Architecture

## File Organization

```
Smart Campus Management System
├── public/
│   ├── index.php                          
│   ├── homepage.php                       <- Protected dashboard (all roles)
│   ├── auth/
│   │   ├── login.php                      <- Login form + authentication
│   │   ├── logout.php                     <- Session destroyer
│   │   ├── css/
│   │   │   └── login.css                  <- Login styling
│   │   └── js/
│   │       └── login.js                   <- Login validation
│   ├── css/
│   │   ├── header.css                     (existing)
│   │   └── homepage.css                   <- Dashboard styling
│   ├── js/
│   │   ├── header.js                      (existing)
│   │   └── homepage.js                    <- Dashboard interactions
│   ├── includes/
│   │   ├── check_session.php              <- Session verification (REQUIRED ON ALL PROTECTED PAGES)
│   │   ├── header.php                     (existing)
│   │   └── footer.php                     (existing)
│   ├── config/
│   │   └── db.php                         (existing - PDO connection)
│   └── assets/
│       └── ...
```

## Login Flow Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     USER VISITS WEBSITE                      │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │   Check if session exists?    │
         │  (check_session.php included) │
         └──────────┬────────┬───────────┘
                    │        │
              YES   │        │ NO
                    │        │
              ┌─────▼─┐   ┌──▼────────────────────┐
              │ HOME  │   │ Redirect to login.php │
              │ PAGE  │   │                       │
              └───────┘   └──────────┬────────────┘
                                     │
                      ┌──────────────▼──────────────┐
                      │   Display Login Form        │
                      │   (email + password)        │
                      └──────────────┬──────────────┘
                                     │
                          User enters credentials
                                     │
                      ┌──────────────▼──────────────┐
                      │ Verify in database          │
                      │ password_verify() check     │
                      └──┬───────────────┬──────────┘
                         │               │
                      VALID           INVALID
                         │               │
                    ┌────▼───┐     ┌────▼─────────┐
                    │ Set    │     │ Show error   │
                    │SESSION │     │ message      │
                    └────┬───┘     └──────────────┘
                         │
         ┌───────────────▼────────────────┐
         │ Redirect to homepage.php       │
         │ (with session variables)       │
         └───────────────┬────────────────┘
                         │
         ┌───────────────▼────────────────┐
         │  Check session in homepage.php │
         └───────────────┬────────────────┘
                         │
         ┌───────────────▼────────────────┐
         │ Show role-based dashboard      │
         │ - Student dashboard            │
         │ - Staff dashboard              │
         │ - Admin dashboard              │
         └────────────────────────────────┘
```

## Session Variables Flow

```
┌──────────────────────────────────────────┐
│ login.php validates credentials          │
│ Extracts user data from database         │
└──────────┬───────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│ Sets PHP Session Variables:              │
│ - $_SESSION['user_id'] = "123"           │
│ - $_SESSION['role'] = "student"          │
│ - $_SESSION['name'] = "John Doe"         │
└──────────┬───────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│ Redirects to homepage.php                │
└──────────┬───────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│ homepage.php includes check_session.php  │
│ check_session.php reads session vars     │
│ Stores in: $user_id, $user_role, $user_ │
│           name (available to page)       │
└──────────┬───────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────┐
│ homepage.php uses conditionals:          │
│ if($user_role == 'student')              │
│   -> Show student dashboard              │
│ if($user_role == 'staff')                │
│   -> Show staff dashboard                │
│ if($user_role == 'admin')                │
│   -> Show admin dashboard                │
└──────────────────────────────────────────┘
```

## File Content Structure

### login.php (HTML + PHP)
```php
<?php
// 1. Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /homepage.php');
    exit();
}

// 2. If form submitted
if ($_POST['email'] && $_POST['password']) {
    // 3. Query database
    // 4. Verify password
    // 5. Set session variables
    // 6. Redirect to homepage
}
?>
<!DOCTYPE html>
<!-- Display login form -->
<form method="POST">
    <input name="email" required>
    <input name="password" required>
    <button type="submit">Login</button>
</form>
<script src="js/login.js"></script>
```

### css/login.css (Styling ONLY)
```css
/* No PHP, no JavaScript */
/* Just pure CSS styling */
.login-box { ... }
.form-group { ... }
```

### js/login.js (JavaScript ONLY)
```javascript
// No PHP, no CSS in here
// Just pure JavaScript logic
document.getElementById('email')...
```

### homepage.php (HTML + PHP conditionals)
```php
<?php include 'includes/check_session.php'; ?>
<!DOCTYPE html>
<!-- Session variables now available:
     $user_id, $user_role, $user_name -->

<?php if ($user_role == 'student'): ?>
    <!-- Student HTML -->
<?php endif; ?>

<?php if ($user_role == 'staff'): ?>
    <!-- Staff HTML -->
<?php endif; ?>

<?php if ($user_role == 'admin'): ?>
    <!-- Admin HTML -->
<?php endif; ?>

<script src="js/homepage.js"></script>
```

## Key Rules

✅ **DO:**
- Separate CSS into `.css` files
- Separate JavaScript into `.js` files
- Include check_session.php on protected pages
- Use PHP conditionals for role-based content
- Use prepared statements for database queries

❌ **DON'T:**
- Put `<style>` tags in `.php` files
- Put `<script>` code in `.php` files
- Mix HTML and CSS in same file
- Mix HTML and JavaScript in same file
- Use direct SQL queries (use prepared statements)

## How to Create a New Protected Page

1. Create `pagename.php`:
```php
<?php
include 'includes/check_session.php';
// $user_id, $user_role, $user_name now available
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/pagename.css">
</head>
<body>
    <?php if ($user_role == 'student'): ?>
        Student content here
    <?php endif; ?>
    
    <script src="js/pagename.js"></script>
</body>
</html>
```

2. Create `css/pagename.css` (styling)
3. Create `js/pagename.js` (JavaScript)

## Testing Flow

```
1. Go to http://localhost:8000/auth/login.php
2. Enter email: student@campus.edu
3. Enter password: password123
4. Click Login
5. Should see Student dashboard
6. Try logging out
7. Try other roles (staff, admin)
8. Test that you can't access homepage without login
```

---

*This structure ensures clean separation of concerns,*
*easy maintenance, and secure role-based access control.*
