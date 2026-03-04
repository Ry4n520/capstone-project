<?php
/**
 * Smart Campus Management System - Timetable
 * 
 * Displays user's class timetable
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
    <title>Smart Campus - Timetable</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/timetable.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Class Timetable</h1>
            <p>View your schedule for the week</p>
        </div>

        <!-- Timetable Content -->
        <div class="card-grid row-1">
            <div class="card timetable-card">
                <div class="timetable-scroll">
                    <div class="timetable-day">
                        <div class="day-label">10/03</div>
                        <div class="timetable-row timetable-head">
                            <span>Subject Name</span>
                            <span>Venue</span>
                            <span>Lecturer Name</span>
                        </div>
                        <div class="timetable-row">
                            <div>
                                <div class="subject-title">Data Structures</div>
                                <div class="subject-code">CSC210</div>
                            </div>
                            <span>Block B-203</span>
                            <span>Dr. Lim</span>
                        </div>
                    </div>

                    <div class="timetable-day">
                        <div class="day-label">11/03</div>
                        <div class="timetable-row timetable-head">
                            <span>Subject Name</span>
                            <span>Venue</span>
                            <span>Lecturer Name</span>
                        </div>
                        <div class="timetable-row">
                            <div>
                                <div class="subject-title">Database Systems</div>
                                <div class="subject-code">CSC220</div>
                            </div>
                            <span>Lab C-105</span>
                            <span>Ms. Farah</span>
                        </div>
                        <div class="timetable-row">
                            <div>
                                <div class="subject-title">Software Engineering</div>
                                <div class="subject-code">CSC230</div>
                            </div>
                            <span>Room A-301</span>
                            <span>Mr. Kumar</span>
                        </div>
                    </div>

                    <div class="timetable-day">
                        <div class="day-label">12/03</div>
                        <div class="timetable-row timetable-head">
                            <span>Subject Name</span>
                            <span>Venue</span>
                            <span>Lecturer Name</span>
                        </div>
                        <div class="timetable-row">
                            <div>
                                <div class="subject-title">Computer Networks</div>
                                <div class="subject-code">CSC240</div>
                            </div>
                            <span>Block D-110</span>
                            <span>Dr. Tan</span>
                        </div>
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
