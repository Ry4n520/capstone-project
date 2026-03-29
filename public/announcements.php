<?php
/**
 * GPSchool Management System - Announcements
 * 
 * Displays all announcements with filtering options
 */

// Include session verification and authentication
include 'includes/check_session.php';

// Get current date and time
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date = $current_datetime->format('l, F d, Y');
$formatted_time = $current_datetime->format('g:i A');
$is_admin_user = ($user_role === 'admin');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPSchool - Announcements</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/announcements.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Announcements</h1>
            <p>Stay updated with the latest campus news and announcements</p>

            <?php if ($is_admin_user): ?>
                <div class="page-header-actions">
                    <button type="button" class="admin-post-btn" onclick="openAnnouncementFormModal('create')">+ Post Announcement</button>
                </div>
            <?php endif; ?>
        </div>

        <div id="announcement-action-feedback" class="announcement-action-feedback hidden"></div>

        <!-- Filter Options -->
        <div class="filter-section">
            <button class="filter-btn active" onclick="filterAnnouncements('all', event)">All</button>
            <button class="filter-btn" onclick="filterAnnouncements('today', event)">Today</button>
            <button class="filter-btn" onclick="filterAnnouncements('week', event)">This Week</button>
            <button class="filter-btn" onclick="filterAnnouncements('month', event)">This Month</button>
        </div>

        <!-- Announcements List -->
        <div class="announcements-container" id="announcements-container">
            <div class="loading-state">Loading announcements...</div>
        </div>
    </div>

    <!-- Announcement Detail Modal -->
    <div id="announcement-modal" class="announcement-modal hidden">
        <div class="modal-backdrop" onclick="closeAnnouncementModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Announcement Title</h2>
                <button class="modal-close" onclick="closeAnnouncementModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-meta">
                    <span id="modal-date"></span>
                </div>
                <div class="modal-text" id="modal-content"></div>
            </div>
        </div>
    </div>

    <?php if ($is_admin_user): ?>
    <div id="announcement-form-modal" class="announcement-modal hidden">
        <div class="modal-backdrop" onclick="closeAnnouncementFormModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="announcement-form-title">Post Announcement</h2>
                <button type="button" class="modal-close" onclick="closeAnnouncementFormModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="announcement-form" class="announcement-form" onsubmit="return false;">
                    <div class="announcement-form-row">
                        <label for="announcement-title-input">Title</label>
                        <input type="text" id="announcement-title-input" maxlength="150" placeholder="Enter announcement title">
                    </div>
                    <div class="announcement-form-row">
                        <label for="announcement-target-input">Target Audience</label>
                        <select id="announcement-target-input">
                            <option value="all">All Users</option>
                            <option value="student">Students</option>
                            <option value="staff">Lecturers</option>
                            <option value="admin">Admins</option>
                        </select>
                    </div>
                    <div class="announcement-form-row">
                        <label for="announcement-content-input">Content</label>
                        <textarea id="announcement-content-input" rows="6" maxlength="3000" placeholder="Write the announcement details"></textarea>
                    </div>
                    <div class="announcement-form-actions">
                        <button type="button" class="announcement-cancel-btn" onclick="closeAnnouncementFormModal()">Cancel</button>
                        <button type="button" id="announcement-submit-btn" class="announcement-submit-btn" onclick="submitAnnouncementForm()">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script>
        window.ANNOUNCEMENTS_CONFIG = {
            isAdmin: <?php echo $is_admin_user ? 'true' : 'false'; ?>
        };
    </script>
    <script src="assets/js/announcements.js?v=<?php echo time(); ?>"></script>
</body>
</html>
