<?php
/**
 * Smart Campus Management System - Attendance
 *
 * Displays user's attendance records
 */

// Include session verification and authentication
include 'includes/check_session.php';

// Include database connection
require_once __DIR__ . '/config/db.php';

// Get current date and time
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date = $current_datetime->format('l, F d, Y');
$formatted_time = $current_datetime->format('g:i A');

$current_semester = 1;
$courses = [];
$semesters_data = [];
$overall_attended = 0;
$overall_total = 0;
$overall_percentage = 0;
$attendance_error = null;

try {
    // Determine highest semester this student is enrolled in.
    $current_sem_query = "
        SELECT MAX(CAST(cs.semester AS UNSIGNED)) AS current_semester
        FROM course_sections cs
        JOIN enrollments e ON cs.section_id = e.section_id
        WHERE e.student_id = :user_id
          AND e.status = 'active'
    ";

    $stmt = $pdo->prepare($current_sem_query);
    $stmt->execute([':user_id' => $user_id]);
    $db_current_semester = $stmt->fetchColumn();

    if ($db_current_semester !== false && $db_current_semester !== null) {
        $current_semester = max(1, (int) $db_current_semester);
    }

    // Attendance summary per enrolled section.
    $courses_query = "
        SELECT
            c.course_name,
            cs.semester,
            cs.section_id,
            COUNT(DISTINCT sess.session_id) AS total_classes,
            COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) AS attended_classes,
            COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS present_count,
            COALESCE(SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END), 0) AS late_count,
            COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent_count
        FROM enrollments e
        JOIN course_sections cs ON e.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.course_id
        LEFT JOIN timetables t ON cs.section_id = t.section_id AND t.status = 'released'
        LEFT JOIN class_sessions sess ON t.timetable_id = sess.timetable_id
            AND sess.session_date <= CURDATE()
        LEFT JOIN attendance a ON sess.session_id = a.session_id
            AND a.enrollment_id = e.enrollment_id
        WHERE e.student_id = :user_id
          AND e.status = 'active'
        GROUP BY cs.section_id, c.course_name, cs.semester
        ORDER BY CAST(cs.semester AS UNSIGNED), c.course_name
    ";

    $stmt = $pdo->prepare($courses_query);
    $stmt->execute([':user_id' => $user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    for ($sem = 1; $sem <= $current_semester; $sem++) {
        $semesters_data[$sem] = [
            'courses' => [],
            'total_attended' => 0,
            'total_classes' => 0
        ];
    }

    foreach ($courses as $course) {
        $semester_num = max(1, (int) $course['semester']);

        if (!isset($semesters_data[$semester_num])) {
            $semesters_data[$semester_num] = [
                'courses' => [],
                'total_attended' => 0,
                'total_classes' => 0
            ];
        }

        $total_classes = (int) $course['total_classes'];
        $attended_classes = (int) $course['attended_classes'];

        $course['semester'] = (string) $semester_num;
        $course['total_classes'] = $total_classes;
        $course['attended_classes'] = $attended_classes;
        $course['present_count'] = (int) $course['present_count'];
        $course['late_count'] = (int) $course['late_count'];
        $course['absent_count'] = (int) $course['absent_count'];

        $semesters_data[$semester_num]['courses'][] = $course;
        $semesters_data[$semester_num]['total_attended'] += $attended_classes;
        $semesters_data[$semester_num]['total_classes'] += $total_classes;
    }

    foreach ($semesters_data as $sem_data) {
        $overall_attended += (int) $sem_data['total_attended'];
        $overall_total += (int) $sem_data['total_classes'];
    }

    $overall_percentage = ($overall_total > 0)
        ? round(($overall_attended / $overall_total) * 100, 1)
        : 0;
} catch (PDOException $e) {
    $attendance_error = 'Unable to load attendance data right now.';
}

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
                <?php if ($attendance_error !== null): ?>
                    <p style="padding: 1.5rem; color: #e53e3e;"><?php echo htmlspecialchars($attendance_error); ?></p>
                <?php else: ?>
                    <?php
                    $rendered_semesters = 0;
                    foreach ($semesters_data as $sem_num => $sem_data):
                        if (empty($sem_data['courses'])) {
                            continue;
                        }
                        $rendered_semesters++;
                    ?>
                        <div class="semester-section">
                            <div class="semester-header" onclick="toggleSemester(this)">
                                <div class="semester-info">
                                    <span class="semester-name">Semester <?php echo (int) $sem_num; ?></span>
                                    <span
                                        class="semester-percentage"
                                        data-attended="<?php echo (int) $sem_data['total_attended']; ?>"
                                        data-total="<?php echo (int) $sem_data['total_classes']; ?>"
                                    ></span>
                                </div>
                                <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                            <div class="semester-content">
                                <?php foreach ($sem_data['courses'] as $course): ?>
                                    <?php
                                    $course_total = (int) $course['total_classes'];
                                    $course_attended = (int) $course['attended_classes'];
                                    $attendance_text = ($course_total > 0)
                                        ? "Classes Attended: {$course_attended}/{$course_total}"
                                        : 'Classes Attended: 0/0 - No classes yet';
                                    ?>
                                    <div class="subject-item">
                                        <div class="subject-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                        <div class="subject-attendance">
                                            <span><?php echo htmlspecialchars($attendance_text); ?></span>
                                            <span
                                                class="subject-percentage"
                                                data-attended="<?php echo $course_attended; ?>"
                                                data-total="<?php echo $course_total; ?>"
                                            ></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($rendered_semesters === 0): ?>
                        <p style="padding: 1.5rem; color: #718096;">No enrollment attendance data available yet.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Right Side: Stats + Sign Button -->
            <div class="right-sidebar">
                <!-- Combined Card: Classes Present + Sign Attendance -->
                <div class="card">
                    <div class="attendance-stats-right">
                        <div class="stat-label">
                            <?php if ($overall_total > 0): ?>
                                Classes Attended: <?php echo (int) $overall_attended; ?>/<?php echo (int) $overall_total; ?>
                            <?php else: ?>
                                Classes Attended: 0/0 - No classes yet
                            <?php endif; ?>
                        </div>
                        <div
                            class="attendance-percentage"
                            id="overall-percentage"
                            data-attended="<?php echo (int) $overall_attended; ?>"
                            data-total="<?php echo (int) $overall_total; ?>"
                        >
                            <?php if ($overall_total > 0): ?>
                                <?php echo $overall_percentage; ?>%
                            <?php else: ?>
                                0/0 - No classes yet
                            <?php endif; ?>
                        </div>
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
