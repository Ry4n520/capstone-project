<?php
/**
 * Smart Campus Management System - Attendance
 * 
 * Displays user's attendance records
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
    <title>Smart Campus - Attendance</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/attendance.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Attendance</h1>
            <p>Track and manage your class attendance</p>
        </div>

        <!-- Attendance Layout: Left = Semesters, Right = Stats + Button -->
        <div class="attendance-container">
            <!-- Left Side: Semester Sections -->
            <div class="card semesters-card">
                <!-- Semester 1 -->
                <div class="semester-section">
                    <div class="semester-header" onclick="toggleSemester(this)">
                        <div class="semester-info">
                            <span class="semester-name">Semester 1</span>
                            <span class="semester-percentage" data-attended="70" data-total="80"></span>
                        </div>
                        <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="semester-content">
                        <div class="subject-item">
                            <div class="subject-name">Subject 1</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 18/20</span>
                                <span class="subject-percentage" data-attended="18" data-total="20"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 2</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 16/20</span>
                                <span class="subject-percentage" data-attended="16" data-total="20"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 3</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 19/20</span>
                                <span class="subject-percentage" data-attended="19" data-total="20"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 4</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 17/20</span>
                                <span class="subject-percentage" data-attended="17" data-total="20"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester 2 -->
                <div class="semester-section">
                    <div class="semester-header" onclick="toggleSemester(this)">
                        <div class="semester-info">
                            <span class="semester-name">Semester 2</span>
                            <span class="semester-percentage" data-attended="62" data-total="72"></span>
                        </div>
                        <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="semester-content">
                        <div class="subject-item">
                            <div class="subject-name">Subject 1</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 15/18</span>
                                <span class="subject-percentage" data-attended="15" data-total="18"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 2</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 14/18</span>
                                <span class="subject-percentage" data-attended="14" data-total="18"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 3</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 16/18</span>
                                <span class="subject-percentage" data-attended="16" data-total="18"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 4</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 17/18</span>
                                <span class="subject-percentage" data-attended="17" data-total="18"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester 3 -->
                <div class="semester-section">
                    <div class="semester-header" onclick="toggleSemester(this)">
                        <div class="semester-info">
                            <span class="semester-name">Semester 3</span>
                            <span class="semester-percentage" data-attended="50" data-total="60"></span>
                        </div>
                        <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="semester-content">
                        <div class="subject-item">
                            <div class="subject-name">Subject 1</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 12/15</span>
                                <span class="subject-percentage" data-attended="12" data-total="15"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 2</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 13/15</span>
                                <span class="subject-percentage" data-attended="13" data-total="15"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 3</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 14/15</span>
                                <span class="subject-percentage" data-attended="14" data-total="15"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 4</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 11/15</span>
                                <span class="subject-percentage" data-attended="11" data-total="15"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester 4 -->
                <div class="semester-section">
                    <div class="semester-header" onclick="toggleSemester(this)">
                        <div class="semester-info">
                            <span class="semester-name">Semester 4</span>
                            <span class="semester-percentage" data-attended="34" data-total="40"></span>
                        </div>
                        <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                    <div class="semester-content">
                        <div class="subject-item">
                            <div class="subject-name">Subject 1</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 8/10</span>
                                <span class="subject-percentage" data-attended="8" data-total="10"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 2</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 9/10</span>
                                <span class="subject-percentage" data-attended="9" data-total="10"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 3</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 7/10</span>
                                <span class="subject-percentage" data-attended="7" data-total="10"></span>
                            </div>
                        </div>
                        <div class="subject-item">
                            <div class="subject-name">Subject 4</div>
                            <div class="subject-attendance">
                                <span>Classes Attended: 10/10</span>
                                <span class="subject-percentage" data-attended="10" data-total="10"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Stats + Sign Button -->
            <div class="right-sidebar">
                <!-- Combined Card: Classes Present + Sign Attendance -->
                <div class="card">
                    <div class="attendance-stats-right">
                        <div class="stat-label">Classes Present</div>
                        <div class="attendance-percentage" id="overall-percentage">85.5%</div>
                        <button class="btn-sign-attendance" onclick="openAttendanceModal()">Sign Attendance</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Code Modal -->
    <div id="attendance-modal" class="attendance-modal">
        <div class="modal-backdrop" onclick="closeAttendanceModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Enter Code</h3>
                <button class="modal-close" onclick="closeAttendanceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="code-inputs">
                    <input type="text" maxlength="1" class="code-input" id="code1" oninput="moveToNext(this, 'code2')">
                    <input type="text" maxlength="1" class="code-input" id="code2" oninput="moveToNext(this, 'code3')">
                    <input type="text" maxlength="1" class="code-input" id="code3">
                </div>
                <button class="btn-enter-code" onclick="submitAttendanceCode()">Enter</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/attendance.js?v=<?php echo time(); ?>"></script>
</body>
</html>
