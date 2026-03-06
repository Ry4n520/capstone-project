<?php
/**
 * Smart Campus Management System - Timetable API
 * 
 * Returns timetable data based on user role and week range
 * GET Parameters:
 *   - week_offset: Number of weeks from current week (0 = current week, 1 = next week, -1 = last week)
 */

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db.php';

$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role'];

// Get week offset from query parameter (default: current week)
$week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;

// Calculate date range for the week
// Week starts on Monday
$current_date = new DateTime();
$current_date->setTime(0, 0, 0);

// Find the Monday of current week
$day_of_week = $current_date->format('N'); // 1 = Monday, 7 = Sunday
if ($day_of_week != 1) {
    $current_date->modify('-' . ($day_of_week - 1) . ' days');
}

// Apply week offset
if ($week_offset != 0) {
    $current_date->modify(($week_offset * 7) . ' days');
}

$week_start = $current_date->format('Y-m-d');
$week_end_date = clone $current_date;
$week_end_date->modify('+6 days');
$week_end = $week_end_date->format('Y-m-d');

// Define days of week for Monday-Friday
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

$timetable_data = [];

try {
    if ($role_name === 'student') {
        // Get student's enrolled courses and their timetables
        $query = "
            SELECT 
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name as lecturer_name,
                t.session_code
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            JOIN enrollments e ON cs.section_id = e.section_id
            WHERE e.student_id = :user_id 
            AND e.status = 'active'
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";
        
    } else if ($role_name === 'staff') {
        // Get lecturer's taught courses and their timetables
        $query = "
            SELECT 
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name as lecturer_name,
                t.session_code,
                COUNT(e.enrollment_id) as enrolled_count
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'active'
            WHERE cs.lecturer_id = :user_id
            GROUP BY t.timetable_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";
        
    } else if ($role_name === 'admin') {
        // Admin sees all timetables
        $query = "
            SELECT 
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name as lecturer_name,
                t.session_code,
                COUNT(e.enrollment_id) as enrolled_count
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'active'
            GROUP BY t.timetable_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid user role']);
        exit;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group data by day
    $timetable_by_day = [];
    
    foreach ($results as $row) {
        $day = $row['day_of_week'];
        
        if (!isset($timetable_by_day[$day])) {
            $timetable_by_day[$day] = [];
        }
        
        $timetable_by_day[$day][] = [
            'timetable_id' => $row['timetable_id'],
            'course_name' => $row['course_name'],
            'course_id' => $row['course_id'],
            'section_code' => $row['section_code'],
            'month' => $row['month'],
            'week' => $row['week'],
            'venue' => $row['room_name'],
            'building' => $row['building'],
            'lecturer' => $row['lecturer_name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'session_code' => $row['session_code'],
            'enrolled_count' => isset($row['enrolled_count']) ? $row['enrolled_count'] : null
        ];
    }
    
    // Build response
    $response = [
        'success' => true,
        'user_id' => $user_id,
        'user_role' => $role_name,
        'week_offset' => $week_offset,
        'week_start' => $week_start,
        'week_end' => $week_end,
        'timetable' => $timetable_by_day
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>