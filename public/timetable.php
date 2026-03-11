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
$is_lecturer = ($_SESSION['role'] === 'staff');
$is_admin = ($_SESSION['role'] === 'admin');

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

        <!-- Week Navigation -->
        <div class="week-navigation">
            <button id="prevWeekBtn" class="btn">← Previous Week</button>
            <span id="weekDisplay"></span>
            <button id="nextWeekBtn" class="btn">Next Week →</button>
        </div>

        <!-- Timetable Content -->
        <div class="card-grid row-1">
            <div class="card timetable-card">
                <div id="timetableContainer" class="timetable-scroll">
                    <p style="text-align: center; padding: 20px; color: #a0aec0;">Loading timetable...</p>
                </div>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <div class="card schedule-request-panel">
                <div class="card-header">Schedule Change Requests</div>
                <div id="scheduleRequestsContainer" class="schedule-request-list">
                    <div class="schedule-empty-state">Loading schedule requests...</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($is_lecturer): ?>
        <div id="scheduleRequestModal" class="schedule-request-modal hidden">
            <div class="schedule-request-backdrop" onclick="closeScheduleRequestModal()"></div>
            <div class="schedule-request-dialog">
                <div class="schedule-request-header">
                    <h3>Request Timetable Change</h3>
                    <button type="button" class="schedule-request-close" onclick="closeScheduleRequestModal()">&times;</button>
                </div>
                <div class="schedule-request-body">
                    <form id="scheduleRequestForm">
                        <input type="hidden" id="requestSectionId" value="">
                        <input type="hidden" id="requestDayOfWeek" value="">
                        <input type="hidden" id="requestWeekStartDate" value="">
                        <input type="hidden" id="requestWeekEndDate" value="">

                        <div id="scheduleRequestSummary" class="schedule-request-summary"></div>
                        <p class="schedule-request-note">Requests must be submitted at least 1 week before the target week.</p>

                        <div class="schedule-request-grid">
                            <div class="schedule-request-field" style="grid-column: 1 / -1;">
                                <label for="requestClassDate">Requested Class Date</label>
                                <input type="date" id="requestClassDate" required>
                            </div>
                            <div class="schedule-request-field">
                                <label for="requestStartTime">Requested Start Time</label>
                                <input type="time" id="requestStartTime" required>
                            </div>
                            <div class="schedule-request-field">
                                <label for="requestEndTime">Requested End Time</label>
                                <input type="time" id="requestEndTime" required>
                            </div>
                            <div class="schedule-request-field" style="grid-column: 1 / -1;">
                                <label for="requestRoomId">Requested Classroom</label>
                                <select id="requestRoomId" required></select>
                            </div>
                        </div>

                        <div id="scheduleRequestMessage" class="schedule-request-message"></div>

                        <div class="schedule-request-footer">
                            <button type="button" class="btn-cancel-request" onclick="closeScheduleRequestModal()">Cancel</button>
                            <button type="submit" id="submitScheduleRequestBtn" class="btn-submit-request">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        window.timetableConfig = {
            isLecturer: <?php echo $is_lecturer ? 'true' : 'false'; ?>,
            isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>
        };
    </script>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/timetable.js?v=<?php echo time(); ?>"></script>
</body>
</html>
