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
            section_id,
            room_id,
            day_of_week,
            week_start_date,
            week_end_date,
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
        ':day_of_week' => $request['day_of_week']
    ]);

    $existing_slot = $existing_slot_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_slot) {
        $target_status = ($existing_slot['status'] === 'released') ? 'released' : 'pending';

        $update_timetable_stmt = $pdo->prepare(
            'UPDATE timetables
             SET room_id = :room_id,
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
            ':status' => 'pending',
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
