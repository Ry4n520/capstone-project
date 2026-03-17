<?php
/**
 * Homepage Data API - DEBUGGED VERSION
 * 
 * Provides dynamic data for homepage dashboard:
 * - Today's classes / admin request changes
 * - Attendance rate
 * - Upcoming bookings
 * - Recent announcements
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'No user_id in session']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';
$today = date('Y-m-d');
$current_day = date('l'); // Monday, Tuesday, etc.

$response = [];
$debug = []; // For debugging

error_log("Homepage API called - user_id: $user_id, role: $role, today: $today, day: $current_day");

try {
    // 1. TODAY'S CLASSES
    if ($role === 'student') {
        $classes_query = "
            SELECT 
                c.course_name,
                cs.section_code,
                t.start_time,
                t.end_time,
                cl.room_name,
                cl.building,
                u.name as lecturer_name,
                CASE 
                    WHEN CURTIME() BETWEEN t.start_time AND t.end_time THEN 'ongoing'
                    WHEN CURTIME() < t.start_time THEN 'upcoming'
                    ELSE 'completed'
                END as class_status
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            JOIN enrollments e ON cs.section_id = e.section_id
            WHERE e.student_id = :user_id
            AND e.status = 'active'
            AND t.week_start_date <= :today_start
            AND t.week_end_date >= :today_end
            AND LOWER(t.day_of_week) = LOWER(:current_day)
            AND t.status = 'released'
            ORDER BY t.start_time ASC
        ";
        
        $stmt = $pdo->prepare($classes_query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':today_start' => $today,
            ':today_end' => $today,
            ':current_day' => $current_day
        ]);
        $response['todays_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debug['classes_query_params'] = [
            'user_id' => $user_id,
            'today' => $today,
            'current_day' => $current_day
        ];
        $debug['classes_count'] = count($response['todays_classes']);
        error_log("Today's classes: " . count($response['todays_classes']) . " classes found");
    } elseif ($role === 'staff') {
        $classes_query = "
            SELECT 
                c.course_name,
                cs.section_code,
                t.start_time,
                t.end_time,
                COALESCE(cl.room_name, 'TBA') AS room_name,
                COALESCE(cl.building, '') AS building,
                CONCAT('Section ', cs.section_code) AS lecturer_name,
                CASE 
                    WHEN CURTIME() BETWEEN t.start_time AND t.end_time THEN 'ongoing'
                    WHEN CURTIME() < t.start_time THEN 'upcoming'
                    ELSE 'completed'
                END as class_status
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            LEFT JOIN classrooms cl ON t.room_id = cl.room_id
            WHERE cs.lecturer_id = :user_id
            AND t.week_start_date <= :today_start
            AND t.week_end_date >= :today_end
            AND LOWER(t.day_of_week) = LOWER(:current_day)
            AND t.status = 'released'
            ORDER BY t.start_time ASC
        ";

        $stmt = $pdo->prepare($classes_query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':today_start' => $today,
            ':today_end' => $today,
            ':current_day' => $current_day
        ]);
        $response['todays_classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debug['classes_query_params'] = [
            'user_id' => $user_id,
            'today' => $today,
            'current_day' => $current_day,
            'mode' => 'lecturer'
        ];
        $debug['classes_count'] = count($response['todays_classes']);
        error_log("Today's lecturer classes: " . count($response['todays_classes']) . " classes found");
    } else {
        $response['todays_classes'] = [];
    }

} catch (Exception $e) {
    error_log("Today's classes query error: " . $e->getMessage());
    $response['todays_classes'] = [];
    $debug['classes_error'] = $e->getMessage();
}

try {
    // 1B. ADMIN REQUEST CHANGES
    if ($role === 'admin') {
        $requests_query = "
            SELECT
                sr.request_id,
                sr.day_of_week,
                sr.week_start_date,
                sr.week_end_date,
                sr.start_time,
                sr.end_time,
                sr.requested_at,
                c.course_name,
                cs.section_code,
                u.name AS lecturer_name,
                requested_room.room_name AS requested_room_name,
                requested_room.building AS requested_building,
                COALESCE(original_room.room_name, current_room.room_name, legacy_room.room_name) AS current_room_name,
                COALESCE(original_room.building, current_room.building, legacy_room.building) AS current_building,
                COALESCE(sr.original_day_of_week, t.day_of_week, legacy_t.day_of_week) AS current_day_of_week,
                COALESCE(sr.original_start_time, t.start_time, legacy_t.start_time) AS current_start_time,
                COALESCE(sr.original_end_time, t.end_time, legacy_t.end_time) AS current_end_time
            FROM schedule_requests sr
            INNER JOIN course_sections cs ON sr.section_id = cs.section_id
            INNER JOIN courses c ON cs.course_id = c.course_id
            INNER JOIN users u ON cs.lecturer_id = u.user_id
            INNER JOIN classrooms requested_room ON sr.room_id = requested_room.room_id
            LEFT JOIN classrooms original_room ON sr.original_room_id = original_room.room_id
            LEFT JOIN timetables t
                ON t.timetable_id = sr.source_timetable_id
            LEFT JOIN timetables legacy_t
                ON sr.source_timetable_id IS NULL
               AND legacy_t.section_id = sr.section_id
               AND legacy_t.week_start_date = sr.week_start_date
               AND legacy_t.start_time = sr.start_time
               AND legacy_t.end_time = sr.end_time
            LEFT JOIN classrooms current_room ON t.room_id = current_room.room_id
            LEFT JOIN classrooms legacy_room ON legacy_t.room_id = legacy_room.room_id
            WHERE sr.status = 'pending'
            ORDER BY sr.requested_at ASC
            LIMIT 3
        ";

        $stmt = $pdo->query($requests_query);
        $response['schedule_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debug['schedule_requests_count'] = count($response['schedule_requests']);
        error_log("Homepage schedule requests: " . count($response['schedule_requests']) . " pending requests found");
    } else {
        $response['schedule_requests'] = [];
    }
} catch (Exception $e) {
    error_log("Homepage schedule requests query error: " . $e->getMessage());
    $response['schedule_requests'] = [];
    $debug['schedule_requests_error'] = $e->getMessage();
}

try {
    // 2. ATTENDANCE RATE (for students)
    if ($role === 'student') {
        $attendance_query = "
            SELECT 
                COUNT(DISTINCT sess.session_id) as total_classes,
                SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_classes
            FROM enrollments e
            JOIN course_sections cs ON e.section_id = cs.section_id
            JOIN timetables t ON cs.section_id = t.section_id
            LEFT JOIN class_sessions sess ON t.timetable_id = sess.timetable_id 
                AND sess.session_date <= CURDATE()
            LEFT JOIN attendance a ON sess.session_id = a.session_id 
                AND a.enrollment_id = e.enrollment_id
            WHERE e.student_id = :user_id
            AND e.status = 'active'
        ";
        
        $stmt = $pdo->prepare($attendance_query);
        $stmt->execute([':user_id' => $user_id]);
        $attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = intval($attendance_data['total_classes'] ?? 0);
        $attended = intval($attendance_data['attended_classes'] ?? 0);
        
        $response['attendance_rate'] = $total > 0 ? round(($attended / $total) * 100, 1) : 0;
        
        $debug['attendance'] = [
            'total_classes' => $total,
            'attended_classes' => $attended,
            'rate' => $response['attendance_rate']
        ];
        error_log("Attendance: total=$total, attended=$attended, rate=" . $response['attendance_rate'] . "%");
    } else {
        $response['attendance_rate'] = 0;
    }

} catch (Exception $e) {
    error_log("Attendance rate query error: " . $e->getMessage());
    $response['attendance_rate'] = 0;
    $debug['attendance_error'] = $e->getMessage();
}

try {
    // 3. UPCOMING FACILITY BOOKINGS
    $bookings_query = "
        SELECT 
            f.facility_name,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.booking_status
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.facility_id
        WHERE b.user_id = ?
        AND b.booking_status IN ('confirmed', 'pending')
        AND (
            b.booking_date > CURDATE()
            OR (b.booking_date = CURDATE() AND b.start_time > CURTIME())
        )
        ORDER BY b.booking_date ASC, b.start_time ASC
        LIMIT 3
    ";

    $stmt = $pdo->prepare($bookings_query);
    $stmt->execute([$user_id]);
    $response['upcoming_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['bookings_count'] = count($response['upcoming_bookings']);
    error_log("Upcoming bookings: " . count($response['upcoming_bookings']) . " bookings found");

} catch (Exception $e) {
    error_log("Upcoming bookings query error: " . $e->getMessage());
    $response['upcoming_bookings'] = [];
    $debug['bookings_error'] = $e->getMessage();
}

try {
    // 4. ACTIVE BOOKINGS COUNT
    $active_bookings_query = "
        SELECT COUNT(*) FROM bookings 
        WHERE user_id = ?
        AND booking_status IN ('confirmed', 'pending')
        AND (
            booking_date > CURDATE()
            OR (booking_date = CURDATE() AND start_time > CURTIME())
        )
    ";
    $stmt = $pdo->prepare($active_bookings_query);
    $stmt->execute([$user_id]);
    $response['active_bookings_count'] = intval($stmt->fetchColumn());

} catch (Exception $e) {
    error_log("Active bookings count query error: " . $e->getMessage());
    $response['active_bookings_count'] = 0;
    $debug['active_bookings_error'] = $e->getMessage();
}

try {
    // 5. UPCOMING CLASSES COUNT
    if ($role === 'student') {
        $upcoming_classes_query = "
            SELECT COUNT(DISTINCT t.timetable_id) FROM timetables t
            JOIN enrollments e ON t.section_id = e.section_id
            WHERE e.student_id = ?
            AND e.status = 'active'
            AND (
                (t.week_start_date = CURDATE() AND t.day_of_week = ? AND t.start_time > CURTIME())
                OR t.week_start_date > CURDATE()
            )
            AND t.status = 'released'
        ";
        $stmt = $pdo->prepare($upcoming_classes_query);
        $stmt->execute([$user_id, $current_day]);
        $response['upcoming_classes_count'] = intval($stmt->fetchColumn());
    } else {
        $response['upcoming_classes_count'] = 0;
    }

} catch (Exception $e) {
    error_log("Upcoming classes count query error: " . $e->getMessage());
    $response['upcoming_classes_count'] = 0;
    $debug['upcoming_classes_error'] = $e->getMessage();
}

try {
    // 6. RECENT ANNOUNCEMENTS
    $announcements_query = "
        SELECT 
            title,
            content,
            created_date,
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, created_date, NOW()) < 24 THEN 'Today'
                WHEN TIMESTAMPDIFF(DAY, created_date, NOW()) = 1 THEN 'Yesterday'
                WHEN TIMESTAMPDIFF(DAY, created_date, NOW()) < 7 
                    THEN CONCAT(TIMESTAMPDIFF(DAY, created_date, NOW()), ' days ago')
                ELSE DATE_FORMAT(created_date, '%b %d, %Y')
            END as time_ago
        FROM announcements
        WHERE (target_role_id IS NULL OR target_role_id = :role_id)
        ORDER BY created_date DESC
        LIMIT 3
    ";

    $stmt = $pdo->prepare($announcements_query);
    $stmt->execute([':role_id' => $_SESSION['role_id'] ?? 2]); // Default to student role_id
    $response['recent_announcements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['announcements_count'] = count($response['recent_announcements']);
    error_log("Recent announcements: " . count($response['recent_announcements']) . " announcements found");

} catch (Exception $e) {
    error_log("Recent announcements query error: " . $e->getMessage());
    $response['recent_announcements'] = [];
    $debug['announcements_error'] = $e->getMessage();
}

// Add debug info in development - Remove this line in production
$response['_debug'] = $debug;

http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => $response
], JSON_PRETTY_PRINT);

?>
