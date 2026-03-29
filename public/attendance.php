<?php
/**
 * GPSchool Management System - Attendance
 *
 * Displays attendance based on user role
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

// Determine user role
$is_student = ($_SESSION['role'] === 'student');
$is_lecturer = ($_SESSION['role'] === 'staff');
$is_admin = ($_SESSION['role'] === 'admin');

$current_semester = 1;
$courses = [];
$semesters_data = [];
$overall_attended = 0;
$overall_total = 0;
$overall_percentage = 0;
$attendance_error = null;

$admin_error = null;
$admin_context = [
    'semester' => null,
    'year' => null,
    'label' => 'All Sections'
];
$admin_stats = [
    'total_sessions' => 0,
    'attendance_rate' => 0,
    'at_risk_count' => 0,
    'not_taken_this_week' => 0
];
$admin_sections = [];
$admin_sessions_by_section = [];
$admin_session_students = [];
$admin_students = [];
$admin_student_breakdown = [];
$admin_weeks = [];
$admin_week_sessions = [];
$admin_at_risk_report = [];
$admin_bootstrap = [];

if ($is_student) {
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
}

if ($is_admin) {
    try {
        $contextStmt = $pdo->query(
            "
            SELECT cs.semester, cs.year
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            WHERE sess.session_date <= CURDATE()
            ORDER BY sess.session_date DESC
            LIMIT 1
            "
        );

        $context = $contextStmt->fetch(PDO::FETCH_ASSOC);

        if (!$context) {
            $fallbackContextStmt = $pdo->query(
                "
                SELECT semester, year
                FROM course_sections
                ORDER BY year DESC, CAST(semester AS UNSIGNED) DESC
                LIMIT 1
                "
            );

            $context = $fallbackContextStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($context) {
            $admin_context['semester'] = $context['semester'];
            $admin_context['year'] = $context['year'] !== null ? (int) $context['year'] : null;

            if ($admin_context['semester'] !== null && $admin_context['year'] !== null) {
                $admin_context['label'] = "Semester {$admin_context['semester']} / {$admin_context['year']}";
            } elseif ($admin_context['semester'] !== null) {
                $admin_context['label'] = "Semester {$admin_context['semester']}";
            }
        }

        $adminFilterSql = '';
        $adminFilterParams = [];

        if ($admin_context['semester'] !== null && $admin_context['semester'] !== '') {
            $adminFilterSql .= ' AND cs.semester = :admin_semester';
            $adminFilterParams[':admin_semester'] = $admin_context['semester'];
        }

        if ($admin_context['year'] !== null) {
            $adminFilterSql .= ' AND cs.year = :admin_year';
            $adminFilterParams[':admin_year'] = $admin_context['year'];
        }

        $adminWeekStart = (clone $current_datetime);
        $adminWeekStart->modify('monday this week')->setTime(0, 0, 0);
        $adminWeekEnd = (clone $adminWeekStart);
        $adminWeekEnd->modify('+6 days');

        $admin_context['week_start'] = $adminWeekStart->format('Y-m-d');
        $admin_context['week_end'] = $adminWeekEnd->format('Y-m-d');

        $sessionsHeldQuery = "
            SELECT COUNT(*)
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            WHERE sess.session_date <= CURDATE()
              {$adminFilterSql}
        ";

        $sessionsHeldStmt = $pdo->prepare($sessionsHeldQuery);
        $sessionsHeldStmt->execute($adminFilterParams);
        $admin_stats['total_sessions'] = (int) $sessionsHeldStmt->fetchColumn();

        $attendanceRateQuery = "
            SELECT
                COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) AS attended_rows,
                COALESCE(COUNT(a.attendance_id), 0) AS total_rows
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            LEFT JOIN attendance a ON a.session_id = sess.session_id
            WHERE sess.session_date <= CURDATE()
              {$adminFilterSql}
        ";

        $attendanceRateStmt = $pdo->prepare($attendanceRateQuery);
        $attendanceRateStmt->execute($adminFilterParams);
        $attendanceRateRow = $attendanceRateStmt->fetch(PDO::FETCH_ASSOC) ?: ['attended_rows' => 0, 'total_rows' => 0];

        $attendedRows = (int) $attendanceRateRow['attended_rows'];
        $totalRows = (int) $attendanceRateRow['total_rows'];
        $admin_stats['attendance_rate'] = $totalRows > 0
            ? round(($attendedRows / $totalRows) * 100, 1)
            : 0;

        $atRiskCountQuery = "
            SELECT COUNT(DISTINCT risk.student_id)
            FROM (
                SELECT
                    e.student_id,
                    e.section_id,
                    COUNT(DISTINCT sess.session_id) AS total_sessions,
                    COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) AS attended_sessions
                FROM enrollments e
                JOIN course_sections cs ON e.section_id = cs.section_id
                LEFT JOIN timetables t ON cs.section_id = t.section_id
                LEFT JOIN class_sessions sess ON t.timetable_id = sess.timetable_id
                    AND sess.session_date <= CURDATE()
                LEFT JOIN attendance a ON a.session_id = sess.session_id
                    AND a.enrollment_id = e.enrollment_id
                WHERE e.status = 'active'
                  {$adminFilterSql}
                GROUP BY e.student_id, e.section_id
                HAVING total_sessions > 0
                   AND (attended_sessions / total_sessions) < 0.8
            ) risk
        ";

        $atRiskCountStmt = $pdo->prepare($atRiskCountQuery);
        $atRiskCountStmt->execute($adminFilterParams);
        $admin_stats['at_risk_count'] = (int) $atRiskCountStmt->fetchColumn();

        $notTakenQuery = "
            SELECT COUNT(*)
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            WHERE sess.session_date BETWEEN :week_start AND :week_end
              {$adminFilterSql}
              AND NOT EXISTS (
                  SELECT 1
                  FROM attendance a
                  WHERE a.session_id = sess.session_id
              )
        ";

        $notTakenParams = array_merge($adminFilterParams, [
            ':week_start' => $admin_context['week_start'],
            ':week_end' => $admin_context['week_end']
        ]);
        $notTakenStmt = $pdo->prepare($notTakenQuery);
        $notTakenStmt->execute($notTakenParams);
        $admin_stats['not_taken_this_week'] = (int) $notTakenStmt->fetchColumn();

        $sectionOptionsQuery = "
            SELECT
                cs.section_id,
                c.course_name,
                cs.section_code,
                cs.semester,
                cs.year,
                lecturer.name AS lecturer_name
            FROM course_sections cs
            JOIN courses c ON cs.course_id = c.course_id
            JOIN users lecturer ON cs.lecturer_id = lecturer.user_id
            WHERE 1 = 1
              {$adminFilterSql}
            ORDER BY c.course_name ASC, cs.section_code ASC
        ";

        $sectionOptionsStmt = $pdo->prepare($sectionOptionsQuery);
        $sectionOptionsStmt->execute($adminFilterParams);
        $admin_sections = $sectionOptionsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admin_sections as &$section) {
            $section['section_id'] = (int) $section['section_id'];
            $section['year'] = $section['year'] !== null ? (int) $section['year'] : null;
        }
        unset($section);

        $sessionSummariesQuery = "
            SELECT
                sess.session_id,
                cs.section_id,
                c.course_name,
                cs.section_code,
                lecturer.name AS lecturer_name,
                DATE_FORMAT(sess.session_date, '%Y-%m-%d') AS session_date,
                DAYNAME(sess.session_date) AS day_name,
                DATE_FORMAT(t.week_start_date, '%Y-%m-%d') AS week_start_date,
                DATE_FORMAT(t.week_end_date, '%Y-%m-%d') AS week_end_date,
                COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS present_count,
                COALESCE(SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END), 0) AS late_count,
                COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent_count,
                COUNT(DISTINCT e.enrollment_id) AS total_enrolled,
                COUNT(a.attendance_id) AS marked_count
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN users lecturer ON cs.lecturer_id = lecturer.user_id
            LEFT JOIN enrollments e ON e.section_id = cs.section_id
                AND e.status = 'active'
            LEFT JOIN attendance a ON a.session_id = sess.session_id
                AND a.enrollment_id = e.enrollment_id
            WHERE 1 = 1
              {$adminFilterSql}
            GROUP BY
                sess.session_id,
                cs.section_id,
                c.course_name,
                cs.section_code,
                lecturer.name,
                sess.session_date,
                t.week_start_date,
                t.week_end_date
            ORDER BY sess.session_date DESC, c.course_name ASC
        ";

        $sessionSummariesStmt = $pdo->prepare($sessionSummariesQuery);
        $sessionSummariesStmt->execute($adminFilterParams);
        $sessionSummaries = $sessionSummariesStmt->fetchAll(PDO::FETCH_ASSOC);

        $admin_week_lookup = [];

        foreach ($sessionSummaries as $session) {
            $sessionId = (int) $session['session_id'];
            $sectionId = (int) $session['section_id'];
            $presentCount = (int) $session['present_count'];
            $lateCount = (int) $session['late_count'];
            $absentCount = (int) $session['absent_count'];
            $totalEnrolled = (int) $session['total_enrolled'];
            $markedCount = (int) $session['marked_count'];

            $sessionDateObj = new DateTime($session['session_date']);
            $weekStartDate = $session['week_start_date'];
            $weekEndDate = $session['week_end_date'];

            if (!$weekStartDate || !$weekEndDate) {
                $dayIndex = (int) $sessionDateObj->format('N');
                $fallbackWeekStart = (clone $sessionDateObj)->modify('-' . ($dayIndex - 1) . ' days');
                $fallbackWeekEnd = (clone $fallbackWeekStart)->modify('+6 days');
                $weekStartDate = $fallbackWeekStart->format('Y-m-d');
                $weekEndDate = $fallbackWeekEnd->format('Y-m-d');
            }

            $rate = $totalEnrolled > 0
                ? round((($presentCount + $lateCount) / $totalEnrolled) * 100, 1)
                : 0;

            $sessionRow = [
                'session_id' => $sessionId,
                'section_id' => $sectionId,
                'course_name' => $session['course_name'],
                'section_code' => $session['section_code'],
                'lecturer_name' => $session['lecturer_name'],
                'session_date' => $session['session_date'],
                'day_name' => $session['day_name'],
                'week_start_date' => $weekStartDate,
                'week_end_date' => $weekEndDate,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'total_enrolled' => $totalEnrolled,
                'marked_count' => $markedCount,
                'attendance_rate' => $rate,
                'not_taken' => ($markedCount === 0)
            ];

            if (!isset($admin_sessions_by_section[$sectionId])) {
                $admin_sessions_by_section[$sectionId] = [];
            }
            $admin_sessions_by_section[$sectionId][] = $sessionRow;

            if (!isset($admin_week_lookup[$weekStartDate])) {
                $admin_week_lookup[$weekStartDate] = [
                    'week_start_date' => $weekStartDate,
                    'week_end_date' => $weekEndDate
                ];
            }

            if (!isset($admin_week_sessions[$weekStartDate])) {
                $admin_week_sessions[$weekStartDate] = [];
            }
            $admin_week_sessions[$weekStartDate][] = $sessionRow;
        }

        ksort($admin_week_lookup);
        ksort($admin_week_sessions);
        $admin_weeks = array_values($admin_week_lookup);

        $sessionStudentsQuery = "
            SELECT
                sess.session_id,
                u.user_id AS student_id,
                u.name AS student_name,
                u.email AS student_email,
                COALESCE(a.status, 'not_marked') AS status,
                a.marked_at
            FROM class_sessions sess
            JOIN timetables t ON sess.timetable_id = t.timetable_id
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN enrollments e ON e.section_id = cs.section_id
                AND e.status = 'active'
            JOIN users u ON e.student_id = u.user_id
            LEFT JOIN attendance a ON a.session_id = sess.session_id
                AND a.enrollment_id = e.enrollment_id
            WHERE 1 = 1
              {$adminFilterSql}
            ORDER BY sess.session_date DESC, u.name ASC
        ";

        $sessionStudentsStmt = $pdo->prepare($sessionStudentsQuery);
        $sessionStudentsStmt->execute($adminFilterParams);
        $sessionStudentsRows = $sessionStudentsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessionStudentsRows as $row) {
            $sessionId = (int) $row['session_id'];

            if (!isset($admin_session_students[$sessionId])) {
                $admin_session_students[$sessionId] = [];
            }

            $admin_session_students[$sessionId][] = [
                'student_id' => (int) $row['student_id'],
                'student_name' => $row['student_name'],
                'student_email' => $row['student_email'],
                'status' => $row['status'],
                'marked_at' => $row['marked_at'],
                'marked_time' => $row['marked_at'] ? date('g:i A', strtotime($row['marked_at'])) : null
            ];
        }

        $studentsQuery = "
            SELECT DISTINCT
                u.user_id,
                u.name,
                u.email
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            JOIN enrollments e ON u.user_id = e.student_id
                AND e.status = 'active'
            JOIN course_sections cs ON e.section_id = cs.section_id
            WHERE r.role_name = 'student'
              {$adminFilterSql}
            ORDER BY u.name ASC
        ";

        $studentsStmt = $pdo->prepare($studentsQuery);
        $studentsStmt->execute($adminFilterParams);
        $admin_students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admin_students as &$student) {
            $student['user_id'] = (int) $student['user_id'];
        }
        unset($student);

        $studentBreakdownQuery = "
            SELECT
                e.student_id,
                cs.section_id,
                c.course_name,
                cs.section_code,
                COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) AS attended_count,
                COUNT(DISTINCT sess.session_id) AS total_sessions
            FROM enrollments e
            JOIN course_sections cs ON e.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            LEFT JOIN timetables t ON cs.section_id = t.section_id
            LEFT JOIN class_sessions sess ON t.timetable_id = sess.timetable_id
                AND sess.session_date <= CURDATE()
            LEFT JOIN attendance a ON a.session_id = sess.session_id
                AND a.enrollment_id = e.enrollment_id
            WHERE e.status = 'active'
              {$adminFilterSql}
            GROUP BY e.student_id, cs.section_id, c.course_name, cs.section_code
            ORDER BY c.course_name ASC
        ";

        $studentBreakdownStmt = $pdo->prepare($studentBreakdownQuery);
        $studentBreakdownStmt->execute($adminFilterParams);
        $studentBreakdownRows = $studentBreakdownStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($studentBreakdownRows as $row) {
            $studentId = (int) $row['student_id'];
            $totalSessions = (int) $row['total_sessions'];
            $attendedCount = (int) $row['attended_count'];
            $rate = $totalSessions > 0
                ? round(($attendedCount / $totalSessions) * 100, 1)
                : 0;

            if (!isset($admin_student_breakdown[$studentId])) {
                $admin_student_breakdown[$studentId] = [];
            }

            $admin_student_breakdown[$studentId][] = [
                'section_id' => (int) $row['section_id'],
                'course_name' => $row['course_name'],
                'section_code' => $row['section_code'],
                'attended_count' => $attendedCount,
                'total_sessions' => $totalSessions,
                'attendance_rate' => $rate
            ];
        }

        $atRiskReportQuery = "
            SELECT
                u.user_id,
                u.name AS student_name,
                c.course_name,
                cs.section_code,
                COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) AS attended_count,
                COUNT(DISTINCT sess.session_id) AS total_sessions,
                CASE
                    WHEN COUNT(DISTINCT sess.session_id) = 0 THEN 0
                    ELSE ROUND((SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(DISTINCT sess.session_id)) * 100, 1)
                END AS attendance_rate
            FROM enrollments e
            JOIN users u ON e.student_id = u.user_id
            JOIN roles r ON u.role_id = r.role_id
                AND r.role_name = 'student'
            JOIN course_sections cs ON e.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            LEFT JOIN timetables t ON cs.section_id = t.section_id
            LEFT JOIN class_sessions sess ON t.timetable_id = sess.timetable_id
                AND sess.session_date <= CURDATE()
            LEFT JOIN attendance a ON a.session_id = sess.session_id
                AND a.enrollment_id = e.enrollment_id
            WHERE e.status = 'active'
              {$adminFilterSql}
            GROUP BY e.enrollment_id, u.user_id, u.name, c.course_name, cs.section_code
            HAVING total_sessions > 0
               AND attendance_rate < 80
            ORDER BY attendance_rate ASC, u.name ASC, c.course_name ASC
        ";

        $atRiskReportStmt = $pdo->prepare($atRiskReportQuery);
        $atRiskReportStmt->execute($adminFilterParams);
        $admin_at_risk_report = $atRiskReportStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admin_at_risk_report as &$row) {
            $row['user_id'] = (int) $row['user_id'];
            $row['attended_count'] = (int) $row['attended_count'];
            $row['total_sessions'] = (int) $row['total_sessions'];
            $row['attendance_rate'] = (float) $row['attendance_rate'];
        }
        unset($row);

        $admin_bootstrap = [
            'context' => $admin_context,
            'stats' => $admin_stats,
            'sections' => $admin_sections,
            'sectionSessions' => $admin_sessions_by_section,
            'sessionStudents' => $admin_session_students,
            'students' => $admin_students,
            'studentBreakdown' => $admin_student_breakdown,
            'weeks' => $admin_weeks,
            'weekSessions' => $admin_week_sessions,
            'atRiskReport' => $admin_at_risk_report
        ];
    } catch (PDOException $e) {
        $admin_error = 'Unable to load attendance administration data right now.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPSchool - Attendance</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/attendance.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="welcome-section">
            <h1>Attendance</h1>
            <?php if ($is_lecturer): ?>
                <p>Manage attendance for your classes</p>
            <?php elseif ($is_student): ?>
                <p>Track and manage your class attendance</p>
            <?php else: ?>
                <p>Attendance dashboard</p>
            <?php endif; ?>
        </div>

        <?php if ($is_lecturer): ?>
            <div class="card">
                <div class="card-header">This Week's Classes</div>
                <div id="lecturer-classes-container">
                    <div class="loading-state">Loading your classes...</div>
                </div>
            </div>

            <div id="active-session-card" class="card hidden">
                <div class="card-header">Active Class Session</div>
                <div class="active-session-content">
                    <div class="code-display">
                        <div class="code-label">Attendance Code</div>
                        <div class="code-value" id="attendance-code">---</div>
                        <div class="code-expiry" id="code-expiry">Expires in: --:--</div>
                    </div>

                    <div class="attendance-stats">
                        <div class="stat-box">
                            <div class="stat-number" id="present-count">0</div>
                            <div class="stat-label">Present</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number" id="total-students">0</div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>

                    <button class="btn-end-class" onclick="endClass()">End Class</button>
                </div>

                <div class="attendance-list-header">Students Present</div>
                <div id="attendance-list" class="attendance-list">
                    <div class="empty-state">No students have signed in yet</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_student): ?>
            <div class="attendance-container">
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

                <div class="right-sidebar">
                    <div class="card">
                        <div class="attendance-stats-right">
                            <div class="stat-label">Classes Present</div>
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

            <div id="attendance-modal" class="attendance-modal hidden">
                <div class="modal-backdrop" onclick="closeAttendanceModal()"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Enter Attendance Code</h3>
                        <button class="modal-close" onclick="closeAttendanceModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Enter the 3-digit code provided by your lecturer</p>
                        <div class="code-inputs">
                            <input type="text" maxlength="1" class="code-input" id="code1" oninput="moveToNext(this, 'code2')">
                            <input type="text" maxlength="1" class="code-input" id="code2" oninput="moveToNext(this, 'code3')">
                            <input type="text" maxlength="1" class="code-input" id="code3" oninput="moveToNext(this)">
                        </div>
                        <button class="btn-enter-code" onclick="submitAttendanceCode()">Submit</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <?php if ($admin_error !== null): ?>
                <div class="card">
                    <div class="card-header">Attendance Administration</div>
                    <div class="empty-state"><?php echo htmlspecialchars($admin_error); ?></div>
                </div>
            <?php else: ?>
                <div class="card admin-attendance-card">
                    <div class="card-header">Campus Attendance Stats (<?php echo htmlspecialchars($admin_context['label']); ?>)</div>
                    <div class="admin-stats-grid">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo (int) $admin_stats['total_sessions']; ?></div>
                            <div class="stat-label">Sessions Held</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo number_format((float) $admin_stats['attendance_rate'], 1); ?>%</div>
                            <div class="stat-label">Campus Attendance Rate</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo (int) $admin_stats['at_risk_count']; ?></div>
                            <div class="stat-label">Students Below 80%</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo (int) $admin_stats['not_taken_this_week']; ?></div>
                            <div class="stat-label">Sessions Not Taken This Week</div>
                        </div>
                    </div>
                </div>

                <div class="card admin-attendance-card">
                    <div class="card-header">Attendance Records</div>

                    <div class="admin-tabs" role="tablist" aria-label="Attendance records view">
                        <button type="button" class="admin-tab-btn active" data-tab="course">By Course</button>
                        <button type="button" class="admin-tab-btn" data-tab="student">By Student</button>
                        <button type="button" class="admin-tab-btn" data-tab="week">By Week</button>
                    </div>

                    <div class="admin-tab-panel" id="admin-tab-course">
                        <div class="admin-toolbar">
                            <label for="admin-section-select">Course Section</label>
                            <select id="admin-section-select" class="admin-select"></select>
                        </div>

                        <div class="admin-table-wrapper">
                            <table class="admin-table" id="admin-course-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Present</th>
                                        <th>Late</th>
                                        <th>Absent</th>
                                        <th>Total Enrolled</th>
                                        <th>Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-course-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="admin-tab-panel hidden" id="admin-tab-student">
                        <div class="admin-toolbar">
                            <label for="admin-student-search">Student Search</label>
                            <input
                                type="search"
                                id="admin-student-search"
                                class="admin-search"
                                placeholder="Search by student name or email"
                            >
                        </div>

                        <div id="admin-student-results" class="admin-student-results"></div>
                        <div id="admin-student-breakdown" class="admin-student-breakdown empty-state">Select a student to view attendance details.</div>
                    </div>

                    <div class="admin-tab-panel hidden" id="admin-tab-week">
                        <div class="admin-week-navigation">
                            <button type="button" id="admin-prev-week" class="btn">← Previous</button>
                            <span id="admin-week-display"></span>
                            <button type="button" id="admin-next-week" class="btn">Next →</button>
                        </div>

                        <div class="admin-filter-chips" role="group" aria-label="Week filters">
                            <button type="button" class="admin-chip active" data-filter="all">All</button>
                            <button type="button" class="admin-chip" data-filter="not-taken">Not Taken</button>
                            <button type="button" class="admin-chip" data-filter="low">Low Attendance (&lt;70%)</button>
                        </div>

                        <div class="admin-table-wrapper">
                            <table class="admin-table" id="admin-week-table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Section</th>
                                        <th>Lecturer</th>
                                        <th>Date</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-week-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card admin-attendance-card admin-report-card">
                    <button type="button" class="admin-report-toggle" id="admin-report-toggle" aria-expanded="false">
                        Generate Report
                    </button>

                    <div id="admin-report-panel" class="admin-report-panel hidden">
                        <div class="admin-report-types">
                            <label>
                                <input type="radio" name="admin-report-type" value="section" checked>
                                Section Report
                            </label>
                            <label>
                                <input type="radio" name="admin-report-type" value="risk">
                                At-Risk Students Report
                            </label>
                        </div>

                        <div id="admin-section-report-controls" class="admin-report-controls">
                            <label for="admin-report-section-select">Course Section</label>
                            <select id="admin-report-section-select" class="admin-select"></select>
                            <button type="button" id="admin-generate-section-report" class="btn">Generate</button>
                        </div>

                        <div id="admin-risk-report-controls" class="admin-report-controls hidden">
                            <button type="button" id="admin-generate-risk-report" class="btn">Generate At-Risk Report</button>
                        </div>

                        <div id="admin-report-output" class="admin-report-output empty-state">
                            Choose a report type and generate to view results.
                        </div>

                        <div class="admin-report-actions hidden" id="admin-report-actions">
                            <button type="button" id="admin-report-print-btn" class="btn">Print / Export</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>

    <?php if ($is_student): ?>
        <script src="assets/js/student-attendance.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <?php if ($is_lecturer): ?>
        <script src="assets/js/lecturer-attendance.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <?php if ($is_admin && $admin_error === null): ?>
        <script>
            window.adminAttendanceData = <?php echo json_encode($admin_bootstrap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
        <script src="assets/js/admin-attendance.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
</body>
</html>
