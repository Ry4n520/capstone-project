<?php
/**
 * GPSchool Management System - Profile
 * 
 * Displays user profile information
 */

// Include session verification and authentication
include 'includes/check_session.php';

// Get current date and time
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date = $current_datetime->format('l, F d, Y');
$formatted_time = $current_datetime->format('g:i A');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPSchool - Profile</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/profile.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>My Profile</h1>
            <p>View and manage your profile information</p>
        </div>

        <!-- Profile Content -->
        <div class="card-grid row-1">
            <!-- Profile Information Card -->
            <div class="card">
                <div class="card-header">Personal Information</div>
                <div class="profile-section">
                    <div class="profile-avatar-large"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                    
                    <div class="profile-details">
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user_name); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">User ID:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user_id); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Role:</span>
                            <span class="detail-value"><?php echo ucfirst(htmlspecialchars($user_role)); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Settings Card -->
            <div class="card">
                <div class="card-header">Change Password</div>
                <div class="password-form">
                    <form id="password-form" onsubmit="changePassword(event)">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" id="current-password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" id="new-password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" id="confirm-password" required minlength="6">
                        </div>
                        <div id="password-message" class="message"></div>
                        <button type="submit" class="btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
    function changePassword(event) {
        event.preventDefault();
        
        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const messageEl = document.getElementById('password-message');
        
        // Validate new passwords match
        if (newPassword !== confirmPassword) {
            messageEl.textContent = 'New passwords do not match';
            messageEl.className = 'message error';
            return;
        }
        
        // Validate password length
        if (newPassword.length < 6) {
            messageEl.textContent = 'Password must be at least 6 characters';
            messageEl.className = 'message error';
            return;
        }
        
        // Submit password change
        fetch('api/change-password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageEl.textContent = data.message;
                messageEl.className = 'message success';
                document.getElementById('password-form').reset();
            } else {
                messageEl.textContent = data.message;
                messageEl.className = 'message error';
            }
        })
        .catch(() => {
            messageEl.textContent = 'Error updating password';
            messageEl.className = 'message error';
        });
    }
    </script>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
</body>
</html>
