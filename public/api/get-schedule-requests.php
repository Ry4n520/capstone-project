<?php
/**
 * Return pending schedule change requests for admin review.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only admins can view schedule requests.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $query = "
        SELECT
            sr.request_id,
            sr.section_id,
            sr.room_id,
            sr.day_of_week,
            sr.week_start_date,
            sr.week_end_date,
            sr.start_time,
            sr.end_time,
            sr.status,
            sr.requested_at,
            c.course_name,
            cs.section_code,
            u.name AS lecturer_name,
            requested_room.room_name AS requested_room_name,
            requested_room.building AS requested_building,
            current_room.room_name AS current_room_name,
            current_room.building AS current_building,
            t.start_time AS current_start_time,
            t.end_time AS current_end_time,
            t.status AS current_status
        FROM schedule_requests sr
        INNER JOIN course_sections cs ON sr.section_id = cs.section_id
        INNER JOIN courses c ON cs.course_id = c.course_id
        INNER JOIN users u ON cs.lecturer_id = u.user_id
        INNER JOIN classrooms requested_room ON sr.room_id = requested_room.room_id
        LEFT JOIN timetables t
            ON t.section_id = sr.section_id
           AND t.week_start_date = sr.week_start_date
           AND t.day_of_week = sr.day_of_week
        LEFT JOIN classrooms current_room ON t.room_id = current_room.room_id
        WHERE sr.status = 'pending'
        ORDER BY sr.requested_at ASC
    ";

    $stmt = $pdo->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $requests = array_map(function ($row) {
        return [
            'request_id' => (int) $row['request_id'],
            'section_id' => (int) $row['section_id'],
            'room_id' => (int) $row['room_id'],
            'day_of_week' => $row['day_of_week'],
            'week_start_date' => $row['week_start_date'],
            'week_end_date' => $row['week_end_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'status' => $row['status'],
            'requested_at' => $row['requested_at'],
            'course_name' => $row['course_name'],
            'section_code' => $row['section_code'],
            'lecturer_name' => $row['lecturer_name'],
            'requested_room_name' => $row['requested_room_name'],
            'requested_building' => $row['requested_building'],
            'current_room_name' => $row['current_room_name'],
            'current_building' => $row['current_building'],
            'current_start_time' => $row['current_start_time'],
            'current_end_time' => $row['current_end_time'],
            'current_status' => $row['current_status']
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load schedule requests',
        'message' => $e->getMessage()
    ]);
}
?>