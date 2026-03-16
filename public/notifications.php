<?php
/**
 * Smart Campus Management System - Notifications
 * 
 * Displays all user notifications
 */

// Include session verification and authentication
include 'includes/check_session.php';

$is_admin_user = ($user_role === 'admin');

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
    <title>Smart Campus - Notifications</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/notifications.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="notifications-page-header">
            <div>
                <h1>All Notifications</h1>
                <p>Stay updated with all your notifications</p>
            </div>
            <?php if ($is_admin_user): ?>
                <button type="button" class="add-announcement-btn" onclick="openAddAnnouncementModal()">+ Add Announcement</button>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <div class="notifications-panel">
            <div class="notifications-panel-title">Notification History</div>
            <div id="notifications-container" class="notifications-container">
                <div class="notifications-empty-state">Loading notifications...</div>
            </div>
        </div>
    </div>

    <?php if ($is_admin_user): ?>
    <div id="addAnnouncementModal" class="add-announcement-modal hidden">
        <div class="add-announcement-backdrop" onclick="closeAddAnnouncementModal()"></div>
        <div class="add-announcement-dialog">
            <div class="add-announcement-header">
                <h2>Post Announcement</h2>
                <button type="button" class="add-announcement-close" onclick="closeAddAnnouncementModal()">&times;</button>
            </div>
            <form class="add-announcement-form" onsubmit="return false;">
                <label>
                    Title
                    <input id="announcementTitleInput" type="text" placeholder="Announcement title" />
                </label>
                <label>
                    Content
                    <textarea id="announcementContentInput" rows="5" placeholder="Write announcement details..."></textarea>
                </label>
                <div class="add-announcement-actions">
                    <button type="button" class="announcement-cancel-btn" onclick="closeAddAnnouncementModal()">Cancel</button>
                    <button type="button" class="announcement-post-btn" onclick="submitAnnouncementPlaceholder()">Post</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        window.NOTIFICATIONS_CONFIG = {
            isAdmin: <?php echo $is_admin_user ? 'true' : 'false'; ?>
        };
    </script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/notifications.js?v=<?php echo time(); ?>"></script>
</body>
</html>
