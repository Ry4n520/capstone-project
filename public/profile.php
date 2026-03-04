<?php
/**
 * Smart Campus Management System - Profile
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
    <title>Smart Campus - Profile</title>
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
                <div class="card-header">Account Settings</div>
                <div class="settings-list">
                    <div class="setting-item">
                        <span>Change Password</span>
                        <button class="btn-secondary">Update</button>
                    </div>
                    <div class="setting-item">
                        <span>Email Notifications</span>
                        <button class="btn-secondary">Manage</button>
                    </div>
                    <div class="setting-item">
                        <span>Privacy Settings</span>
                        <button class="btn-secondary">Configure</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
</body>
</html>
