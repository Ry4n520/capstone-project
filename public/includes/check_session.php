<?php
/**
 * Session Check & Authentication Verification
 * 
 * This file verifies user login status and ensures required session variables are set.
 * Include at the top of any page that requires authentication:
 * <?php include 'includes/check_session.php'; ?>
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (required session variables)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['name'])) {
    // Redirect to login page if not authenticated
    header('Location: /auth/login.php');
    exit();
}

// Store session values in readable variables
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'];

// Validate role is one of allowed roles
$allowed_roles = ['student', 'staff', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    // Invalid role - redirect to login
    session_destroy();
    header('Location: /auth/login.php');
    exit();
}

// Set header for AJAX/API requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
}

?>
