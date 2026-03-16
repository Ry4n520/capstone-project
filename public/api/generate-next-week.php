<?php
/**
 * Generate next week's timetable by copying entries from the source week.
 *
 * POST body (JSON): { "source_week_start": "Y-m-d" }
 *
 * Response:
 *   { success: true, generated: N }           — entries created
 *   { success: false, already_exists: true }  — next week already has data
 *   { error: "..." }                          — validation / server error
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
    echo json_encode(['error' => 'Admin only.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}

$source_week = isset($raw['source_week_start']) ? trim((string) $raw['source_week_start']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $source_week)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing source_week_start (expected Y-m-d).']);
    exit;
}

try {
    $src_date   = new DateTimeImmutable($source_week);
    $next_start = $src_date->modify('+7 days');
    $next_end   = $src_date->modify('+13 days');

    $next_week_start = $next_start->format('Y-m-d');
    $next_week_end   = $next_end->format('Y-m-d');
    $next_month      = $next_start->format('F');
    $next_week_label = $next_start->format('M j') . ' - ' . $next_end->format('M j');

    // Check if next week already has timetable entries
    $check = $pdo->prepare('SELECT COUNT(*) FROM timetables WHERE week_start_date = :ws');
    $check->execute([':ws' => $next_week_start]);

    if ((int) $check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'already_exists' => true]);
        exit;
    }

    // Copy all non-cancelled entries from source week to next week
    $insert = $pdo->prepare("
        INSERT INTO timetables
            (section_id, room_id, month, week, week_start_date, week_end_date,
             day_of_week, start_time, end_time, session_code, status, created_by, released_at)
        SELECT
            section_id,
            room_id,
            :next_month      AS month,
            :next_week_label AS week,
            :next_week_start AS week_start_date,
            :next_week_end   AS week_end_date,
            day_of_week,
            start_time,
            end_time,
            session_code,
            status,
            :admin_id        AS created_by,
            CASE WHEN status = 'released' THEN NOW() ELSE NULL END AS released_at
        FROM timetables
        WHERE week_start_date = :src_week
          AND status != 'cancelled'
    ");

    $insert->execute([
        ':next_month'      => $next_month,
        ':next_week_label' => $next_week_label,
        ':next_week_start' => $next_week_start,
        ':next_week_end'   => $next_week_end,
        ':admin_id'        => (int) $_SESSION['user_id'],
        ':src_week'        => $source_week,
    ]);

    echo json_encode([
        'success'   => true,
        'generated' => $insert->rowCount(),
        'week_start' => $next_week_start,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>
