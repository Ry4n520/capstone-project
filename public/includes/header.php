<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Navigation Bar -->
<nav class="top-nav">
    <!-- Logo/Home -->
    <a href="homepage.php" class="logo-link">
        <div class="logo">Smart Campus</div>
    </a>

    <!-- Center Navigation Links -->
    <ul class="nav-center">
        <li><a href="timetable.php">Timetable</a></li>
        <li><a href="attendance.php">Attendance</a></li>
        <li><a href="facility-booking.php">Facility Booking</a></li>
        <li><a href="campus-map.php">Campus Map</a></li>
    </ul>

    <!-- Right Navigation Icons & Links -->
    <div class="nav-right">
        <!-- Notifications Bell Icon -->
        <button id="notification-btn" class="nav-icon" title="Notifications" onclick="toggleNotifications()">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
        </button>

        <!-- Profile Icon -->
        <a href="profile.php" class="nav-icon" title="Profile">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </a>

        <!-- Logout Link -->
        <a href="auth/logout.php" class="logout-link">Logout</a>
    </div>
</nav>

<!-- Notification Modal Popup -->
<div id="notification-modal" class="notification-modal">
    <div class="notification-popup">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="close-btn" onclick="closeNotifications()">&times;</button>
        </div>
        <div class="notification-content">
            <div class="notification-item">
                <div class="notification-item-title">Timetable Updated</div>
                <div class="notification-item-desc">Your Monday schedule has been updated</div>
                <div class="notification-item-time">2 hours ago</div>
            </div>
            <div class="notification-item">
                <div class="notification-item-title">Attendance Reminder</div>
                <div class="notification-item-desc">Don't forget to check in for your next class</div>
                <div class="notification-item-time">5 hours ago</div>
            </div>
            <div class="notification-item">
                <div class="notification-item-title">Facility Booking Confirmed</div>
                <div class="notification-item-desc">Your booking for the study room is confirmed</div>
                <div class="notification-item-time">1 day ago</div>
            </div>
            <div class="notification-item">
                <div class="notification-item-title">Campus Event</div>
                <div class="notification-item-desc">Join us for the upcoming tech expo next week</div>
                <div class="notification-item-time">2 days ago</div>
            </div>
        </div>
        <div class="notification-footer">
            <a href="notifications.php" class="view-all-link">View All Notifications →</a>
        </div>
    </div>
</div>

<!-- Notification Modal Backdrop -->
<div id="notification-backdrop" class="notification-backdrop" onclick="closeNotifications()"></div>