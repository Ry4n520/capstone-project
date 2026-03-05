<?php
/**
 * Smart Campus - Login Page
 * 
 * Authenticates user against database
 * Sets session variables: user_id, role, name
 * Redirects to homepage on success
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to homepage
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: /homepage.php');
    exit();
}

// Initialize error message
$error_message = '';
$success = false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            // Include database connection
            include('../config/db.php');

            // Prepared statement to prevent SQL injection
            // Join with roles table to get role_name
            $query = "SELECT u.*, r.role_name 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.role_id 
                      WHERE u.email = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Check if user exists and password is correct
            $is_valid_password = false;
            if ($user) {
                $stored_password = $user['password_hash'] ?? $user['password'] ?? '';
                $is_valid_password = password_verify($password, $stored_password) || hash_equals((string)$stored_password, (string)$password);
            }

            if ($is_valid_password) {
                // Login successful - set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['name'] = $user['name'];

                // Upgrade legacy plain-text password to bcrypt hash after successful login
                if (isset($stored_password) && !password_get_info($stored_password)['algo'] && array_key_exists('password_hash', $user)) {
                    $new_hash = password_hash($password, PASSWORD_BCRYPT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $update_stmt->execute([$new_hash, $user['user_id']]);
                }
                
                // Redirect to homepage
                header('Location: /homepage.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error_message = 'Server error. Please try again later.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1 class="login-title">🏫 Smart Campus</h1>
            <p class="login-subtitle">Management System</p>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your email" 
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                    >
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="login-info">
                <p><strong>Demo Credentials:</strong></p>
                <p>Student: student@campus.edu / password123</p>
                <p>Staff: staff@campus.edu / password123</p>
                <p>Admin: admin@campus.edu / password123</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>
