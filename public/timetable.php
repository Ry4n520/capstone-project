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
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        let availableWeeks = [];
        let currentWeekIndex = -1;
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        function formatTime(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours, 10);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        function toIsoDate(dateObj) {
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function getCurrentMondayIso() {
            const now = new Date();
            const day = now.getDay(); // Sunday=0 ... Saturday=6
            const diff = day === 0 ? -6 : 1 - day;
            now.setDate(now.getDate() + diff);
            now.setHours(0, 0, 0, 0);
            return toIsoDate(now);
        }

        function updateButtonStates() {
            const prevBtn = document.getElementById('prevWeekBtn');
            const nextBtn = document.getElementById('nextWeekBtn');

            const noWeeks = availableWeeks.length === 0 || currentWeekIndex < 0;
            prevBtn.disabled = noWeeks || currentWeekIndex === 0;
            nextBtn.disabled = noWeeks || currentWeekIndex >= availableWeeks.length - 1;
        }

        async function fetchAvailableWeeks() {
            const response = await fetch('/api/get-available-weeks.php');
            if (!response.ok) {
                throw new Error('Failed to load available weeks.');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to load available weeks.');
            }

            availableWeeks = Array.isArray(data.weeks) ? data.weeks : [];
        }

        function getInitialWeekIndex() {
            if (availableWeeks.length === 0) {
                return -1;
            }

            const currentMonday = getCurrentMondayIso();
            const exactMatch = availableWeeks.findIndex(week => week.week_start_date === currentMonday || week.week_start === currentMonday);
            if (exactMatch >= 0) {
                return exactMatch;
            }

            return 0;
        }

        function renderWeekHeader(weekStart, weekEnd, isReleased) {
            const weekStartObj = new Date(weekStart);
            const weekEndObj = new Date(weekEnd);
            const weekText = `${weekStartObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEndObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            const releaseText = isReleased ? 'Released' : 'Pending Release';
            document.getElementById('weekDisplay').textContent = `${weekText} (${releaseText})`;
        }

        function buildEmptyState(message) {
            const safeMessage = message || 'No classes scheduled for this week.';
            return `<p style="text-align: center; padding: 20px; color: #a0aec0;">${safeMessage}</p>`;
        }

        function buildTimetableHtml(data) {
            let html = '';

            days.forEach(day => {
                if (!data.timetable[day] || data.timetable[day].length === 0) {
                    return;
                }

                const dayDate = data.timetable[day][0].actual_date || data.week_start;
                const formattedDate = new Date(dayDate).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit' });

                html += `<div class="timetable-day">
                    <div class="day-label">${day} (${formattedDate})</div>
                    <div class="timetable-row timetable-head">
                        <span>Subject Name</span>
                        <span>Venue</span>
                        <span>Time</span>
                        <span>Lecturer Name</span>
                    </div>`;

                data.timetable[day].forEach(course => {
                    html += `<div class="timetable-row">
                        <div>
                            <div class="subject-title">${course.course_name}</div>
                            <div class="subject-code">${course.section_code}</div>
                        </div>
                        <span>${course.venue}</span>
                        <span>${formatTime(course.start_time)} - ${formatTime(course.end_time)}</span>
                        <span>${course.lecturer}</span>
                    </div>`;
                });

                html += `</div>`;
            });

            return html || buildEmptyState(data.message);
        }

        async function loadTimetableByIndex(index) {
            if (index < 0 || index >= availableWeeks.length) {
                return;
            }

            const week = availableWeeks[index];
            const weekStart = week.week_start_date || week.week_start;

            try {
                const response = await fetch(`/api/get-timetable.php?week_start=${encodeURIComponent(weekStart)}`);
                if (!response.ok) {
                    throw new Error('Failed to load timetable.');
                }

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load timetable.');
                }

                currentWeekIndex = index;
                renderWeekHeader(data.week_start, data.week_end, data.is_released);
                document.getElementById('timetableContainer').innerHTML = buildTimetableHtml(data);
                updateButtonStates();
            } catch (error) {
                console.error('Error loading timetable:', error);
                document.getElementById('timetableContainer').innerHTML = `<p style="text-align: center; color: #e53e3e; padding: 20px; font-size: 0.95rem;">Error loading timetable: ${error.message}</p>`;
                updateButtonStates();
            }
        }

        async function initializeTimetable() {
            try {
                await fetchAvailableWeeks();

                if (availableWeeks.length === 0) {
                    document.getElementById('weekDisplay').textContent = 'No Available Weeks';
                    document.getElementById('timetableContainer').innerHTML = buildEmptyState('No released timetable weeks are available yet.');
                    updateButtonStates();
                    return;
                }

                const initialIndex = getInitialWeekIndex();
                await loadTimetableByIndex(initialIndex);
            } catch (error) {
                console.error('Initialization error:', error);
                document.getElementById('weekDisplay').textContent = 'Error';
                document.getElementById('timetableContainer').innerHTML = `<p style="text-align: center; color: #e53e3e; padding: 20px;">${error.message}</p>`;
                updateButtonStates();
            }
        }

        document.getElementById('prevWeekBtn').addEventListener('click', () => {
            if (currentWeekIndex > 0) {
                loadTimetableByIndex(currentWeekIndex - 1);
            }
        });

        document.getElementById('nextWeekBtn').addEventListener('click', () => {
            if (currentWeekIndex < availableWeeks.length - 1) {
                loadTimetableByIndex(currentWeekIndex + 1);
            }
        });

        initializeTimetable();
    </script>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
</body>
</html>
