<?php
/**
 * Smart Campus Management System - Homepage
 * 
 * Real-time dashboard with dynamic data display
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
    <title>Smart Campus - Dashboard</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?></h1>
            <p id="welcome-datetime"><span id="current-day-date"><?php echo $formatted_date; ?></span> • <span id="current-time"><?php echo $formatted_time; ?></span></p>
        </div>

        <!-- Row 1: Time, Stats, Profile -->
        <div class="card-grid row-3">
            <!-- Current Time Card -->
            <div class="card">
                <div class="card-header">Current Time</div>
                <div class="time-display">
                    <div class="time" id="clock-time"><?php echo $formatted_time; ?></div>
                    <div class="date" id="clock-date"><?php echo $formatted_date; ?></div>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="card">
                <div class="card-header">Quick Stats</div>
                <div class="stat-item">
                    <div class="stat-label">Attendance Rate</div>
                    <div class="stat-value" id="attendance-rate">--</div>
                </div>
                <div class="stat-detail"><span id="active-bookings">0</span> Active Bookings • <span id="upcoming-classes">0</span> Upcoming Classes</div>
            </div>

            <!-- Profile Card -->
            <div class="card">
                <div class="card-header">Profile</div>
                <div class="profile-content">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_name); ?></h3>
                        <p>User ID: <?php echo htmlspecialchars($user_id); ?></p>
                        <p><?php echo ucfirst($user_role); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Today's Classes & Announcements -->
        <div class="card-grid row-2">
            <!-- Today's Classes Card -->
            <div class="card">
                <div class="card-header">Today's Classes</div>
                <div class="empty-state">
                    <p>No classes scheduled for today</p>
                </div>
            </div>

            <!-- Recent Announcements Card -->
            <div class="card">
                <div class="card-header">Recent Announcements</div>
                <div class="empty-state">
                    <p>No announcements at this time</p>
                </div>
            </div>
        </div>

        <!-- Row 3: Upcoming Facility Bookings -->
        <div class="card-grid row-1">
            <div class="card">
                <div class="card-header">Upcoming Facility Bookings</div>
                <div class="empty-state">
                    <p>No upcoming facility bookings</p>
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
