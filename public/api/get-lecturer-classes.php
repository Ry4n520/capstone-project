<?php
/**
 * Get the current week's lecturer classes for attendance management.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only lecturers can access this endpoint']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$lecturer_id = (int) $_SESSION['user_id'];
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

try {
    $query = "
        SELECT
            t.timetable_id,
            t.day_of_week,
            t.week_start_date,
            t.week_end_date,
            c.course_name,
            cs.section_code,
            t.start_time,
            t.end_time,
            COALESCE(cr.room_name, 'TBA') AS room_name,
            COALESCE(cr.building, '') AS building,
            s.session_id AS active_session_id,
            s.attendance_code,
            s.code_expiry,
            CASE
                WHEN s.attendance_code IS NOT NULL AND s.code_expiry > NOW() THEN 1
                ELSE 0
            END AS has_active_session
        FROM timetables t
        INNER JOIN course_sections cs ON t.section_id = cs.section_id
        INNER JOIN courses c ON cs.course_id = c.course_id
        LEFT JOIN classrooms cr ON t.room_id = cr.room_id
        LEFT JOIN class_sessions s
            ON s.timetable_id = t.timetable_id
           AND s.session_date = DATE_ADD(
                t.week_start_date,
                INTERVAL (
                    FIELD(
                        t.day_of_week,
                        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
                    ) - 1
                ) DAY
           )
        WHERE cs.lecturer_id = :lecturer_id
          AND t.status = 'released'
          AND CURDATE() BETWEEN t.week_start_date AND t.week_end_date
        ORDER BY
            FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            t.start_time ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':lecturer_id' => $lecturer_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = new DateTimeImmutable('today');

    $classes = array_values(array_filter(array_map(function ($row) use ($days_of_week, $today) {
        $day_offset = array_search($row['day_of_week'], $days_of_week, true);
        if ($day_offset === false) {
            return null;
        }

        $class_date = (new DateTimeImmutable($row['week_start_date']))
            ->modify('+' . $day_offset . ' days');
        $is_today = $class_date->format('Y-m-d') === $today->format('Y-m-d');

        return [
            'timetable_id' => (int) $row['timetable_id'],
            'day_of_week' => $row['day_of_week'],
            'week_start_date' => $row['week_start_date'],
            'week_end_date' => $row['week_end_date'],
            'class_date' => $class_date->format('Y-m-d'),
            'course_name' => $row['course_name'],
            'section_code' => $row['section_code'],
            'start_time' => date('g:i A', strtotime($row['start_time'])),
            'end_time' => date('g:i A', strtotime($row['end_time'])),
            'room_name' => $row['room_name'],
            'building' => $row['building'],
            'active_session_id' => $row['active_session_id'] !== null ? (int) $row['active_session_id'] : null,
            'attendance_code' => $row['attendance_code'],
            'code_expiry' => $row['code_expiry'],
            'has_active_session' => ((int) $row['has_active_session']) === 1,
            'is_today' => $is_today,
            'can_start_attendance' => $is_today
        ];
    }, $rows)));

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'week_start' => $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->format('Y-m-d'),
        'week_end' => $today->modify('+' . (7 - (int) $today->format('N')) . ' days')->format('Y-m-d'),
        'today' => date('Y-m-d')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load lecturer classes',
        'error' => $e->getMessage()
    ]);
}
