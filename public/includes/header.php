<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_role = $user_role ?? ($_SESSION['role'] ?? '');
$is_admin_nav = ($current_role === 'admin');

$default_nav_links = [
    ['href' => 'timetable.php', 'label' => 'Timetable'],
    ['href' => 'attendance.php', 'label' => 'Attendance'],
    ['href' => 'facility-booking.php', 'label' => 'Facility Booking'],
    ['href' => 'announcements.php', 'label' => 'Announcements']
];

$admin_nav_links = [
    ['href' => 'user-management.php', 'label' => 'User Management'],
    ['href' => 'class-scheduling.php', 'label' => 'Class Scheduling'],
    ['href' => 'attendance.php', 'label' => 'Attendance'],
    ['href' => 'facility-booking.php', 'label' => 'Facility Booking'],
    ['href' => 'announcements.php', 'label' => 'Announcements']
];

$nav_links = $is_admin_nav ? $admin_nav_links : $default_nav_links;
?>

<!-- Navigation Bar -->
<nav class="top-nav">
    <!-- Logo/Home -->
    <a href="homepage.php" class="logo-link">
        <div class="logo">Smart Campus</div>
    </a>

    <!-- Center Navigation Links -->
    <ul class="nav-center<?php echo $is_admin_nav ? ' nav-center-admin' : ''; ?>">
        <?php foreach ($nav_links as $link): ?>
            <li>
                <a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Right Navigation Icons & Links -->
    <div class="nav-right">
        <!-- Announcements Bell -->
        <div class="nav-item announcements-container">
            <a href="#" class="nav-link" onclick="toggleAnnouncements(event)">
                <span class="bell-icon">🔔</span>
                <span class="announcement-badge" id="announcement-badge" style="display: none;">0</span>
            </a>
            
            <!-- Announcements Dropdown -->
            <div class="announcements-dropdown hidden" id="announcements-dropdown">
                <div class="dropdown-header">
                    <h3>Announcements</h3>
                </div>
                <div class="announcements-list" id="announcements-list">
                    <div class="loading-state">Loading...</div>
                </div>
                <div class="view-all-container">
                    <a href="announcements.php" class="view-all-announcements-btn">View All Announcements</a>
                </div>
            </div>
        </div>

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