<?php
/**
 * Smart Campus Management System - Approve Schedule Request API
 *
 * Admin actions:
 * - approve: apply request to the specific week timetable slot.
 * - reject: keep timetable unchanged and record rejection reason.
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

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Only admins can approve schedule requests.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

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
$request_id = isset($input['request_id']) ? (int) $input['request_id'] : 0;
$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
$rejection_reason = isset($input['rejection_reason']) ? trim((string) $input['rejection_reason']) : null;

if ($request_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request_id or action.']);
    exit;
}

$admin_user_id = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $request_stmt = $pdo->prepare(
        'SELECT
            request_id,
            source_timetable_id,
            section_id,
            room_id,
            original_day_of_week,
            day_of_week,
            week_start_date,
            week_end_date,
            original_start_time,
            original_end_time,
            start_time,
            end_time,
            status
         FROM schedule_requests
         WHERE request_id = :request_id
         FOR UPDATE'
    );
    $request_stmt->execute([':request_id' => $request_id]);
    $request = $request_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Schedule request not found.']);
        exit;
    }

    if ($request['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Only pending requests can be processed.']);
        exit;
    }

    if ($action === 'reject') {
        $reject_stmt = $pdo->prepare(
            'UPDATE schedule_requests
             SET status = :status,
                 approved_by = :approved_by,
                 approved_at = NOW(),
                 rejection_reason = :rejection_reason
             WHERE request_id = :request_id'
        );

        $reject_stmt->execute([
            ':status' => 'rejected',
            ':approved_by' => $admin_user_id,
            ':rejection_reason' => $rejection_reason,
            ':request_id' => $request_id
        ]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Schedule request rejected.'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $week_start = new DateTimeImmutable($request['week_start_date']);
    $week_end = new DateTimeImmutable($request['week_end_date']);
    $month_label = $week_start->format('F');
    $week_label = $week_start->format('M j') . ' - ' . $week_end->format('M j');

    $section_stmt = $pdo->prepare(
        'SELECT lecturer_id FROM course_sections WHERE section_id = :section_id LIMIT 1'
    );
    $section_stmt->execute([':section_id' => $request['section_id']]);
    $lecturer_id = $section_stmt->fetchColumn();

    if ($lecturer_id === false) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'The request section no longer exists.']);
        exit;
    }

    $existing_slot = null;

    if (!empty($request['source_timetable_id'])) {
        $existing_slot_stmt = $pdo->prepare(
            'SELECT timetable_id, status
             FROM timetables
             WHERE timetable_id = :timetable_id
               AND section_id = :section_id
               AND week_start_date = :week_start_date
             LIMIT 1
             FOR UPDATE'
        );
        $existing_slot_stmt->execute([
            ':timetable_id' => $request['source_timetable_id'],
            ':section_id' => $request['section_id'],
            ':week_start_date' => $request['week_start_date']
        ]);
        $existing_slot = $existing_slot_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$existing_slot) {
        $same_time_slot_stmt = $pdo->prepare(
            'SELECT timetable_id, status
             FROM timetables
             WHERE section_id = :section_id
               AND week_start_date = :week_start_date
               AND start_time = :start_time
               AND end_time = :end_time
             LIMIT 1
             FOR UPDATE'
        );
        $same_time_slot_stmt->execute([
            ':section_id' => $request['section_id'],
            ':week_start_date' => $request['week_start_date'],
            ':start_time' => $request['start_time'],
            ':end_time' => $request['end_time']
        ]);
        $existing_slot = $same_time_slot_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$existing_slot) {
        $fallback_day = $request['original_day_of_week'] ?: $request['day_of_week'];
        $existing_slot_stmt = $pdo->prepare(
            'SELECT timetable_id, status
             FROM timetables
             WHERE section_id = :section_id
               AND week_start_date = :week_start_date
               AND day_of_week = :day_of_week
             LIMIT 1
             FOR UPDATE'
        );
        $existing_slot_stmt->execute([
            ':section_id' => $request['section_id'],
            ':week_start_date' => $request['week_start_date'],
            ':day_of_week' => $fallback_day
        ]);
        $existing_slot = $existing_slot_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$existing_slot) {
        $single_slot_stmt = $pdo->prepare(
            'SELECT timetable_id, status
             FROM timetables
             WHERE section_id = :section_id
               AND week_start_date = :week_start_date
             ORDER BY timetable_id ASC
             LIMIT 2
             FOR UPDATE'
        );
        $single_slot_stmt->execute([
            ':section_id' => $request['section_id'],
            ':week_start_date' => $request['week_start_date']
        ]);
        $candidate_slots = $single_slot_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($candidate_slots) === 1) {
            $existing_slot = $candidate_slots[0];
        }
    }

    $room_conflict_stmt = $pdo->prepare(
        'SELECT c.course_name, cs.section_code
         FROM timetables t
         INNER JOIN course_sections cs ON t.section_id = cs.section_id
         INNER JOIN courses c ON cs.course_id = c.course_id
         WHERE t.room_id = :room_id
           AND t.week_start_date = :week_start_date
           AND t.day_of_week = :day_of_week
           AND t.section_id <> :section_id
           AND t.start_time < :end_time
           AND t.end_time > :start_time
         LIMIT 1'
    );
    $room_conflict_stmt->execute([
        ':room_id' => $request['room_id'],
        ':week_start_date' => $request['week_start_date'],
        ':day_of_week' => $request['day_of_week'],
        ':section_id' => $request['section_id'],
        ':start_time' => $request['start_time'],
        ':end_time' => $request['end_time']
    ]);
    $room_conflict = $room_conflict_stmt->fetch(PDO::FETCH_ASSOC);

    if ($room_conflict) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'error' => 'The requested classroom is already occupied during that time by ' . $room_conflict['course_name'] . ' (' . $room_conflict['section_code'] . ').'
        ]);
        exit;
    }

    $lecturer_conflict_stmt = $pdo->prepare(
        'SELECT c.course_name, cs.section_code
         FROM timetables t
         INNER JOIN course_sections cs ON t.section_id = cs.section_id
         INNER JOIN courses c ON cs.course_id = c.course_id
         WHERE cs.lecturer_id = :lecturer_id
           AND t.week_start_date = :week_start_date
           AND t.day_of_week = :day_of_week
           AND t.section_id <> :section_id
           AND t.start_time < :end_time
           AND t.end_time > :start_time
         LIMIT 1'
    );
    $lecturer_conflict_stmt->execute([
        ':lecturer_id' => (int) $lecturer_id,
        ':week_start_date' => $request['week_start_date'],
        ':day_of_week' => $request['day_of_week'],
        ':section_id' => $request['section_id'],
        ':start_time' => $request['start_time'],
        ':end_time' => $request['end_time']
    ]);
    $lecturer_conflict = $lecturer_conflict_stmt->fetch(PDO::FETCH_ASSOC);

    if ($lecturer_conflict) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'error' => 'The lecturer already has another class at that time: ' . $lecturer_conflict['course_name'] . ' (' . $lecturer_conflict['section_code'] . ').'
        ]);
        exit;
    }

    $week_release_stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM timetables
         WHERE week_start_date = :week_start_date
           AND status = :status'
    );
    $week_release_stmt->execute([
        ':week_start_date' => $request['week_start_date'],
        ':status' => 'released'
    ]);
    $week_has_released_rows = ((int) $week_release_stmt->fetchColumn()) > 0;

    if ($existing_slot) {
        $target_status = ($existing_slot['status'] === 'released') ? 'released' : 'pending';

        $update_timetable_stmt = $pdo->prepare(
            'UPDATE timetables
             SET room_id = :room_id,
                 day_of_week = :day_of_week,
                 month = :month,
                 week = :week,
                 week_end_date = :week_end_date,
                 start_time = :start_time,
                 end_time = :end_time,
                 status = :status
             WHERE timetable_id = :timetable_id'
        );

        $update_timetable_stmt->execute([
            ':room_id' => $request['room_id'],
            ':day_of_week' => $request['day_of_week'],
            ':month' => $month_label,
            ':week' => $week_label,
            ':week_end_date' => $request['week_end_date'],
            ':start_time' => $request['start_time'],
            ':end_time' => $request['end_time'],
            ':status' => $target_status,
            ':timetable_id' => $existing_slot['timetable_id']
        ]);
    } else {
        $insert_timetable_stmt = $pdo->prepare(
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
                NULL,
                :status,
                :created_by,
                NULL
             )'
        );

        $insert_timetable_stmt->execute([
            ':section_id' => $request['section_id'],
            ':room_id' => $request['room_id'],
            ':month' => $month_label,
            ':week' => $week_label,
            ':week_start_date' => $request['week_start_date'],
            ':week_end_date' => $request['week_end_date'],
            ':day_of_week' => $request['day_of_week'],
            ':start_time' => $request['start_time'],
            ':end_time' => $request['end_time'],
            ':status' => $week_has_released_rows ? 'released' : 'pending',
            ':created_by' => $admin_user_id
        ]);
    }

    $approve_stmt = $pdo->prepare(
        'UPDATE schedule_requests
         SET status = :status,
             approved_by = :approved_by,
             approved_at = NOW(),
             rejection_reason = NULL
         WHERE request_id = :request_id'
    );

    $approve_stmt->execute([
        ':status' => 'approved',
        ':approved_by' => $admin_user_id,
        ':request_id' => $request_id
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Schedule request approved and applied to the target week.'
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
