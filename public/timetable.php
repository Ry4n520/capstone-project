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
        let currentWeekOffset = 0;
        let minWeekOffset = 0;  // Track earliest available week
        let maxWeekOffset = 0;  // Track latest available week
        let hasLoadedData = false;
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Format time helper
        function formatTime(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        // Update button states
        function updateButtonStates() {
            const prevBtn = document.getElementById('prevWeekBtn');
            const nextBtn = document.getElementById('nextWeekBtn');

            if (!hasLoadedData) {
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return;
            }

            prevBtn.disabled = currentWeekOffset <= minWeekOffset;
            nextBtn.disabled = currentWeekOffset >= maxWeekOffset;
        }

        // Load timetable data
        async function loadTimetable(weekOffset = 0) {
            try {
                const response = await fetch(`/api/get-timetable.php?week_offset=${weekOffset}`);
                
                if (!response.ok) {
                    throw new Error('Failed to load timetable');
                }

                const data = await response.json();

                if (!data.success) {
                    document.getElementById('timetableContainer').innerHTML = `<p style="text-align: center; color: #e53e3e; padding: 20px;">Error: ${data.error}</p>`;
                    updateButtonStates();
                    return;
                }

                // Check if this week has any classes
                const hasClasses = Object.keys(data.timetable).length > 0 && 
                                 Object.values(data.timetable).some(day => day.length > 0);

                // Track available weeks
                if (!hasLoadedData) {
                    minWeekOffset = weekOffset;
                    maxWeekOffset = weekOffset;
                    hasLoadedData = true;
                } else if (hasClasses) {
                    minWeekOffset = Math.min(minWeekOffset, weekOffset);
                    maxWeekOffset = Math.max(maxWeekOffset, weekOffset);
                }

                // Update week display
                const weekStart = new Date(data.week_start);
                const weekEnd = new Date(data.week_end);
                const weekDisplay = `${weekStart.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
                document.getElementById('weekDisplay').textContent = weekDisplay;

                // Build timetable HTML
                let html = '';
                
                days.forEach(day => {
                    if (!data.timetable[day] || data.timetable[day].length === 0) {
                        return; // Skip days with no classes
                    }

                    const dayDate = new Date(data.week_start);
                    dayDate.setDate(dayDate.getDate() + days.indexOf(day));
                    const formattedDate = dayDate.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit' });

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

                if (html === '') {
                    html = '<p style="text-align: center; padding: 20px; color: #a0aec0;">No classes scheduled for this week.</p>';
                }

                document.getElementById('timetableContainer').innerHTML = html;
                currentWeekOffset = weekOffset;
                updateButtonStates();

            } catch (error) {
                console.error('Error loading timetable:', error);
                document.getElementById('timetableContainer').innerHTML = `<p style="text-align: center; color: #e53e3e; padding: 20px; font-size: 0.95rem;">Error loading timetable: ${error.message}</p>`;
                updateButtonStates();
            }
        }

        // Week navigation handlers
        document.getElementById('prevWeekBtn').addEventListener('click', () => {
            if (currentWeekOffset > minWeekOffset) {
                loadTimetable(currentWeekOffset - 1);
            }
        });

        document.getElementById('nextWeekBtn').addEventListener('click', () => {
            if (currentWeekOffset < maxWeekOffset) {
                loadTimetable(currentWeekOffset + 1);
            } else if (!hasLoadedData) {
                // Try loading next week to find more data
                loadTimetable(currentWeekOffset + 1);
            }
        });

        // Load current week on page load
        loadTimetable(0);
    </script>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
</body>
</html>
