<?php
/**
 * Smart Campus Management System - Create Schedule Request API
 *
 * Lecturer request workflow:
 * 1) Lecturer submits section/date/time/room request.
 * 2) Request must be submitted at least 7 days before target week start.
 * 3) Request is stored as pending for admin approval.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Only lecturers can create schedule requests.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

function parse_iso_date($value)
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
    if (!$date || $date->format('Y-m-d') !== (string) $value) {
        return null;
    }
    return $date;
}

function get_input_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

$input = get_input_data();

$section_id = isset($input['section_id']) ? (int) $input['section_id'] : 0;
$room_id = isset($input['room_id']) ? (int) $input['room_id'] : 0;
$class_date_raw = isset($input['class_date']) ? trim((string) $input['class_date']) : '';
$day_of_week = isset($input['day_of_week']) ? trim((string) $input['day_of_week']) : '';
$start_time = isset($input['start_time']) ? trim((string) $input['start_time']) : '';
$end_time = isset($input['end_time']) ? trim((string) $input['end_time']) : '';
$week_start_raw = isset($input['week_start_date']) ? trim((string) $input['week_start_date']) : '';
$week_end_raw = isset($input['week_end_date']) ? trim((string) $input['week_end_date']) : '';

$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($section_id <= 0 || $room_id <= 0 || $start_time === '' || $end_time === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields.']);
    exit;
}

if ($class_date_raw === '' && ($day_of_week === '' || $week_start_raw === '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing class_date or week/day details.']);
    exit;
}


$week_start_date = null;
$week_end_date = null;

if ($class_date_raw !== '') {
    $class_date = parse_iso_date($class_date_raw);
    if ($class_date === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid class_date. Expected Y-m-d.']);
        exit;
    }

    $day_of_week = $class_date->format('l');
    if (!in_array($day_of_week, $valid_days, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Class date must be between Monday and Friday.']);
        exit;
    }

    $week_start_date = $class_date->modify('-' . ((int) $class_date->format('N') - 1) . ' days');
    $week_end_date = $week_start_date->modify('+6 days');
} else {
    if (!in_array($day_of_week, $valid_days, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid day_of_week.']);
        exit;
    }

    $week_start_date = parse_iso_date($week_start_raw);
    if ($week_start_date === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid week_start_date. Expected Y-m-d.']);
        exit;
    }

    if ($week_end_raw !== '') {
        $week_end_date = parse_iso_date($week_end_raw);
        if ($week_end_date === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid week_end_date. Expected Y-m-d.']);
            exit;
        }
    } else {
        $week_end_date = $week_start_date->modify('+6 days');
    }
}

if (strtotime($start_time) === false || strtotime($end_time) === false || strtotime($start_time) >= strtotime($end_time)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time range.']);
    exit;
}

$request_date = new DateTimeImmutable('today');
$deadline = $week_start_date->modify('-7 days');

if ($request_date > $deadline) {
    http_response_code(422);
    echo json_encode([
        'error' => 'Too late! Requests must be submitted at least 1 week before the target week.'
    ]);
    exit;
}

try {
    $lecturer_id = (int) $_SESSION['user_id'];

    $room_stmt = $pdo->prepare(
        'SELECT room_id FROM classrooms WHERE room_id = :room_id LIMIT 1'
    );
    $room_stmt->execute([':room_id' => $room_id]);

    if (!$room_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Selected classroom was not found.']);
        exit;
    }

    // Ensure lecturer owns this section.
    $section_stmt = $pdo->prepare(
        'SELECT section_id FROM course_sections WHERE section_id = :section_id AND lecturer_id = :lecturer_id'
    );
    $section_stmt->execute([
        ':section_id' => $section_id,
        ':lecturer_id' => $lecturer_id
    ]);

    if (!$section_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this section.']);
        exit;
    }

    // Week must exist in timetable planning horizon.
    $week_exists_stmt = $pdo->prepare(
        'SELECT timetable_id FROM timetables WHERE section_id = :section_id AND week_start_date = :week_start_date LIMIT 1'
    );
    $week_exists_stmt->execute([
        ':section_id' => $section_id,
        ':week_start_date' => $week_start_date->format('Y-m-d')
    ]);

    if (!$week_exists_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Target week does not exist in timetable.']);
        exit;
    }

    $pending_request_stmt = $pdo->prepare(
        'SELECT request_id
         FROM schedule_requests
         WHERE section_id = :section_id
           AND week_start_date = :week_start_date
           AND day_of_week = :day_of_week
           AND status = :status
         LIMIT 1'
    );
    $pending_request_stmt->execute([
        ':section_id' => $section_id,
        ':week_start_date' => $week_start_date->format('Y-m-d'),
        ':day_of_week' => $day_of_week,
        ':status' => 'pending'
    ]);

    if ($pending_request_stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'error' => 'A pending schedule request already exists for this class week and day.'
        ]);
        exit;
    }

    $insert_stmt = $pdo->prepare(
        'INSERT INTO schedule_requests (
            section_id,
            room_id,
            day_of_week,
            week_start_date,
            week_end_date,
            start_time,
            end_time,
            status,
            requested_at
        ) VALUES (
            :section_id,
            :room_id,
            :day_of_week,
            :week_start_date,
            :week_end_date,
            :start_time,
            :end_time,
            :status,
            NOW()
        )'
    );

    $insert_stmt->execute([
        ':section_id' => $section_id,
        ':room_id' => $room_id,
        ':day_of_week' => $day_of_week,
        ':week_start_date' => $week_start_date->format('Y-m-d'),
        ':week_end_date' => $week_end_date->format('Y-m-d'),
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':status' => 'pending'
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Schedule request submitted and pending admin approval.',
        'request_id' => (int) $pdo->lastInsertId()
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
