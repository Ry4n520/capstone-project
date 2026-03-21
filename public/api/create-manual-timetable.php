<?php
/**
 * Create a manual timetable entry (admin only).
 *
 * POST body (JSON):
 * {
 *   "section_id": 1,
 *   "room_id": 1,
 *   "week_start_date": "2026-03-16",
 *   "day_of_week": "Monday",
 *   "start_time": "09:00",
 *   "end_time": "10:30",
 *   "release_now": true
 * }
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
    echo json_encode(['success' => false, 'error' => 'Admin only.']);
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

function normalize_time($value)
{
    $value = trim((string) $value);

    $dt = DateTimeImmutable::createFromFormat('H:i:s', $value);
    if ($dt) {
        return $dt->format('H:i:s');
    }

    $dt = DateTimeImmutable::createFromFormat('H:i', $value);
    if ($dt) {
        return $dt->format('H:i:s');
    }

    return null;
}

function monday_of_week(DateTimeImmutable $date)
{
    $dayNumber = (int) $date->format('N');
    return $date->modify('-' . ($dayNumber - 1) . ' days');
}

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}

$section_id = isset($raw['section_id']) ? (int) $raw['section_id'] : 0;
$room_id = isset($raw['room_id']) ? (int) $raw['room_id'] : 0;
$week_start_raw = isset($raw['week_start_date']) ? trim((string) $raw['week_start_date']) : '';
$day_of_week = isset($raw['day_of_week']) ? trim((string) $raw['day_of_week']) : '';
$start_time = normalize_time($raw['start_time'] ?? '');
$end_time = normalize_time($raw['end_time'] ?? '');
$release_now = !empty($raw['release_now']);

$valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($section_id <= 0 || $room_id <= 0 || $week_start_raw === '' || $day_of_week === '' || !$start_time || !$end_time) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields.']);
    exit;
}

if (!in_array($day_of_week, $valid_days, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid day_of_week.']);
    exit;
}

if ($start_time >= $end_time) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'End time must be after start time.']);
    exit;
}

$week_start_date = parse_iso_date($week_start_raw);
if ($week_start_date === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid week_start_date. Expected Y-m-d.']);
    exit;
}

$week_start_date = monday_of_week($week_start_date);
$week_end_date = $week_start_date->modify('+6 days');
$week_start = $week_start_date->format('Y-m-d');
$week_end = $week_end_date->format('Y-m-d');
$month_label = $week_start_date->format('F');
$week_label = $week_start_date->format('M j') . ' - ' . $week_end_date->format('M j');

try {
    $sectionStmt = $pdo->prepare(
        'SELECT
            cs.section_id,
            cs.section_code,
            cs.lecturer_id,
            c.course_name,
            u.name AS lecturer_name,
            COALESCE(en.enrolled_count, 0) AS enrolled_count
         FROM course_sections cs
         INNER JOIN courses c ON cs.course_id = c.course_id
         INNER JOIN users u ON cs.lecturer_id = u.user_id
         LEFT JOIN (
            SELECT section_id, COUNT(*) AS enrolled_count
            FROM enrollments
            WHERE status = "active"
            GROUP BY section_id
         ) en ON en.section_id = cs.section_id
         WHERE cs.section_id = :section_id
         LIMIT 1'
    );
    $sectionStmt->execute([':section_id' => $section_id]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Selected class/section not found.']);
        exit;
    }

    $roomStmt = $pdo->prepare(
        'SELECT room_id, room_name, building, capacity
         FROM classrooms
         WHERE room_id = :room_id
         LIMIT 1'
    );
    $roomStmt->execute([':room_id' => $room_id]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Selected classroom not found.']);
        exit;
    }

    $enrolled_count = (int) $section['enrolled_count'];
    if ($room['capacity'] !== null && (int) $room['capacity'] < $enrolled_count) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Selected room capacity (' . (int) $room['capacity'] . ') is lower than enrolled students (' . $enrolled_count . ').'
        ]);
        exit;
    }

    $sectionConflictStmt = $pdo->prepare(
        'SELECT t.timetable_id
         FROM timetables t
         WHERE t.section_id = :section_id
           AND t.week_start_date = :week_start
           AND t.day_of_week = :day_of_week
           AND t.status <> "cancelled"
           AND t.start_time < :end_time
           AND t.end_time > :start_time
         LIMIT 1'
    );
    $sectionConflictStmt->execute([
        ':section_id' => $section_id,
        ':week_start' => $week_start,
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
    ]);

    if ($sectionConflictStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'This class/section already has an overlapping timetable slot in the selected week.'
        ]);
        exit;
    }

    $roomConflictStmt = $pdo->prepare(
        'SELECT c.course_name, cs.section_code
         FROM timetables t
         INNER JOIN course_sections cs ON t.section_id = cs.section_id
         INNER JOIN courses c ON cs.course_id = c.course_id
         WHERE t.room_id = :room_id
           AND t.week_start_date = :week_start
           AND t.day_of_week = :day_of_week
           AND t.status <> "cancelled"
           AND t.start_time < :end_time
           AND t.end_time > :start_time
         LIMIT 1'
    );
    $roomConflictStmt->execute([
        ':room_id' => $room_id,
        ':week_start' => $week_start,
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
    ]);
    $roomConflict = $roomConflictStmt->fetch(PDO::FETCH_ASSOC);

    if ($roomConflict) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Room conflict: ' . $roomConflict['course_name'] . ' (' . $roomConflict['section_code'] . ') is already in this room at that time.'
        ]);
        exit;
    }

    $lecturerConflictStmt = $pdo->prepare(
        'SELECT c.course_name, cs.section_code
         FROM timetables t
         INNER JOIN course_sections cs ON t.section_id = cs.section_id
         INNER JOIN courses c ON cs.course_id = c.course_id
         WHERE cs.lecturer_id = :lecturer_id
           AND t.week_start_date = :week_start
           AND t.day_of_week = :day_of_week
           AND t.status <> "cancelled"
           AND t.start_time < :end_time
           AND t.end_time > :start_time
         LIMIT 1'
    );
    $lecturerConflictStmt->execute([
        ':lecturer_id' => (int) $section['lecturer_id'],
        ':week_start' => $week_start,
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
    ]);
    $lecturerConflict = $lecturerConflictStmt->fetch(PDO::FETCH_ASSOC);

    if ($lecturerConflict) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Lecturer conflict: ' . $lecturerConflict['course_name'] . ' (' . $lecturerConflict['section_code'] . ') overlaps this time.'
        ]);
        exit;
    }

    $day_code = strtoupper(substr($day_of_week, 0, 3));
    $time_code = str_replace(':', '', substr($start_time, 0, 5));
    $clean_section = preg_replace('/[^A-Za-z0-9]/', '', (string) $section['section_code']);
    if ($clean_section === '') {
        $clean_section = 'SEC' . $section_id;
    }
    $session_code = substr($clean_section . '-' . $day_code . '-' . $time_code . '-' . $week_start_date->format('md'), 0, 50);

    $status = $release_now ? 'released' : 'pending';

    $insertStmt = $pdo->prepare(
        'INSERT INTO timetables (
            section_id,
            room_id,
            month,
            week,
            week_start_date,
            week_end_date,
            day_of_week,
            start_time,
            end_time,
            session_code,
            status,
            created_by,
            released_at
        ) VALUES (
            :section_id,
            :room_id,
            :month,
            :week,
            :week_start_date,
            :week_end_date,
            :day_of_week,
            :start_time,
            :end_time,
            :session_code,
            :status,
            :created_by,
            :released_at
        )'
    );

    $insertStmt->execute([
        ':section_id' => $section_id,
        ':room_id' => $room_id,
        ':month' => $month_label,
        ':week' => $week_label,
        ':week_start_date' => $week_start,
        ':week_end_date' => $week_end,
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':session_code' => $session_code,
        ':status' => $status,
        ':created_by' => (int) $_SESSION['user_id'],
        ':released_at' => $release_now ? date('Y-m-d H:i:s') : null,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Timetable class created successfully.',
        'timetable_id' => (int) $pdo->lastInsertId(),
        'week_start' => $week_start,
        'week_end' => $week_end,
        'status' => $status
    ]);
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Duplicate timetable slot detected for this section, week, and time.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>