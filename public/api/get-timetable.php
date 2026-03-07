<?php
/**
 * Smart Campus Management System - Timetable API
 *
 * Week-release aware timetable endpoint.
 * - Students only see released weeks from current week onward.
 * - Staff can see pending and released weeks for their own sections.
 * - Admin can see all statuses for any section.
 *
 * GET Parameters:
 *   - week_offset (optional): Number of weeks from current week.
 *   - week_start (optional): Exact week start date in Y-m-d format. Overrides week_offset.
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

/**
 * Parse strict Y-m-d dates from query/body values.
 */
function parse_iso_date($value)
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
    if (!$date || $date->format('Y-m-d') !== (string) $value) {
        return null;
    }

    return $date;
}

// Calculate current week Monday (used as system boundary for past-week hiding).
$today = new DateTimeImmutable('today');
$current_week_start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');

$requested_week_start = null;

if (isset($_GET['week_start']) && $_GET['week_start'] !== '') {
    $requested_week_start = parse_iso_date($_GET['week_start']);
    if ($requested_week_start === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid week_start. Expected format Y-m-d.']);
        exit;
    }
} else {
    $week_offset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;
    $requested_week_start = $current_week_start->modify(($week_offset * 7) . ' days');
}

$week_start = $requested_week_start->format('Y-m-d');
$week_end = $requested_week_start->modify('+6 days')->format('Y-m-d');

// Keep week_offset in response even when caller uses week_start.
$week_offset = (int) (($requested_week_start->getTimestamp() - $current_week_start->getTimestamp()) / 604800);

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

try {
    // Past weeks are hidden from normal timetable navigation.
    if ($requested_week_start < $current_week_start) {
        $empty_response = [
            'success' => true,
            'user_id' => $user_id,
            'user_role' => $role_name,
            'week_offset' => $week_offset,
            'week_start' => $week_start,
            'week_end' => $week_end,
            'is_released' => false,
            'message' => 'Past weeks are hidden from timetable view.',
            'timetable' => [],
            'available_weeks' => [
                'min_week' => null,
                'max_week' => null
            ]
        ];

        http_response_code(200);
        echo json_encode($empty_response, JSON_PRETTY_PRINT);
        exit;
    }

    if ($role_name === 'student') {
        $query = "
            SELECT
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                t.week_start_date,
                t.week_end_date,
                t.status,
                t.session_code,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name AS lecturer_name
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            JOIN enrollments e ON cs.section_id = e.section_id
            WHERE e.student_id = :user_id
              AND e.status = 'active'
              AND t.week_start_date = :week_start_date
              AND t.status = 'released'
            ORDER BY
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";

        $available_weeks_query = "
            SELECT
                MIN(t.week_start_date) AS min_week,
                MAX(t.week_start_date) AS max_week
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN enrollments e ON cs.section_id = e.section_id
            WHERE e.student_id = :user_id
              AND e.status = 'active'
              AND t.status = 'released'
              AND t.week_start_date >= :current_week_start
        ";

        $params = [
            ':user_id' => $user_id,
            ':week_start_date' => $week_start
        ];

        $available_params = [
            ':user_id' => $user_id,
            ':current_week_start' => $current_week_start->format('Y-m-d')
        ];
    } elseif ($role_name === 'staff') {
        $query = "
            SELECT
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                t.week_start_date,
                t.week_end_date,
                t.status,
                t.session_code,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name AS lecturer_name,
                (
                    SELECT COUNT(*)
                    FROM enrollments e
                    WHERE e.section_id = cs.section_id AND e.status = 'active'
                ) AS enrolled_count
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            WHERE cs.lecturer_id = :user_id
              AND t.week_start_date = :week_start_date
            ORDER BY
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";

        $available_weeks_query = "
            SELECT
                MIN(t.week_start_date) AS min_week,
                MAX(t.week_start_date) AS max_week
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            WHERE cs.lecturer_id = :user_id
              AND t.week_start_date >= :current_week_start
        ";

        $params = [
            ':user_id' => $user_id,
            ':week_start_date' => $week_start
        ];

        $available_params = [
            ':user_id' => $user_id,
            ':current_week_start' => $current_week_start->format('Y-m-d')
        ];
    } elseif ($role_name === 'admin') {
        $query = "
            SELECT
                t.timetable_id,
                t.month,
                t.week,
                t.day_of_week,
                t.start_time,
                t.end_time,
                t.week_start_date,
                t.week_end_date,
                t.status,
                t.session_code,
                c.course_name,
                c.course_id,
                cs.section_code,
                cl.room_name,
                cl.building,
                u.name AS lecturer_name,
                (
                    SELECT COUNT(*)
                    FROM enrollments e
                    WHERE e.section_id = cs.section_id AND e.status = 'active'
                ) AS enrolled_count
            FROM timetables t
            JOIN course_sections cs ON t.section_id = cs.section_id
            JOIN courses c ON cs.course_id = c.course_id
            JOIN classrooms cl ON t.room_id = cl.room_id
            JOIN users u ON cs.lecturer_id = u.user_id
            WHERE t.week_start_date = :week_start_date
            ORDER BY
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.start_time
        ";

        $available_weeks_query = "
            SELECT
                MIN(t.week_start_date) AS min_week,
                MAX(t.week_start_date) AS max_week
            FROM timetables t
            WHERE t.week_start_date >= :current_week_start
        ";

        $params = [
            ':week_start_date' => $week_start
        ];

        $available_params = [
            ':current_week_start' => $current_week_start->format('Y-m-d')
        ];
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid user role']);
        exit;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $release_check_stmt = $pdo->prepare(
        "SELECT COUNT(*) AS released_count
         FROM timetables
         WHERE week_start_date = :week_start_date
           AND status = 'released'"
    );
    $release_check_stmt->execute([':week_start_date' => $week_start]);
    $released_count = (int) $release_check_stmt->fetchColumn();
    $is_released = $released_count > 0;

    $available_stmt = $pdo->prepare($available_weeks_query);
    $available_stmt->execute($available_params);
    $available_weeks = $available_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$available_weeks) {
        $available_weeks = ['min_week' => null, 'max_week' => null];
    }

    // Group data by day
    $timetable_by_day = [];

    foreach ($results as $row) {
        $day = $row['day_of_week'];

        // Convert weekday slots into concrete class dates for the selected week.
        $class_date = new DateTimeImmutable($row['week_start_date']);
        $day_offset = array_search($day, $days_of_week, true);
        if ($day_offset !== false) {
            $class_date = $class_date->modify('+' . $day_offset . ' days');
        }

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
            'week_start_date' => $row['week_start_date'],
            'week_end_date' => $row['week_end_date'],
            'actual_date' => $class_date->format('Y-m-d'),
            'day_of_week' => $day,
            'venue' => $row['room_name'],
            'building' => $row['building'],
            'lecturer' => $row['lecturer_name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'session_code' => $row['session_code'],
            'status' => $row['status'],
            'enrolled_count' => isset($row['enrolled_count']) ? $row['enrolled_count'] : null
        ];
    }

    $message = null;
    if (empty($results) && $role_name === 'student' && !$is_released) {
        $message = "This week's timetable has not been released yet.";
    } elseif (empty($results)) {
        $message = 'No classes scheduled for this week.';
    }

    // Build response
    $response = [
        'success' => true,
        'user_id' => $user_id,
        'user_role' => $role_name,
        'week_offset' => $week_offset,
        'week_start' => $week_start,
        'week_end' => $week_end,
        'is_released' => $is_released,
        'message' => $message,
        'available_weeks' => [
            'min_week' => $available_weeks['min_week'] ?? null,
            'max_week' => $available_weeks['max_week'] ?? null
        ],
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