<?php
/**
 * Smart Campus Management System - Announcements
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Announcements</title>
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
        </div>

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

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/announcements.js?v=<?php echo time(); ?>"></script>
</body>
</html>
