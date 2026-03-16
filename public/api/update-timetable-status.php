<?php
/**
 * Update or delete a timetable entry. Admin only.
 *
 * POST body (JSON):
 *   { "timetable_id": N, "action": "cancel" | "cancel_rest" | "delete" }
 *
 * Actions:
 *   cancel      — set this entry's status to 'cancelled'
 *   cancel_rest — set this entry and all future entries for the same
 *                 section to 'cancelled'
 *   delete      — permanently remove this single entry
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

$timetable_id = isset($raw['timetable_id']) ? (int) $raw['timetable_id'] : 0;
$action       = isset($raw['action']) ? strtolower(trim((string) $raw['action'])) : '';

if ($timetable_id <= 0 || !in_array($action, ['cancel', 'cancel_rest', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid timetable_id or action.']);
    exit;
}

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM timetables WHERE timetable_id = :id');
        $stmt->execute([':id' => $timetable_id]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);

    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE timetables SET status = 'cancelled' WHERE timetable_id = :id");
        $stmt->execute([':id' => $timetable_id]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);

    } elseif ($action === 'cancel_rest') {
        // Look up the section and week for this entry
        $get = $pdo->prepare('SELECT section_id, week_start_date FROM timetables WHERE timetable_id = :id');
        $get->execute([':id' => $timetable_id]);
        $row = $get->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Timetable entry not found.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE timetables
               SET status = 'cancelled'
             WHERE section_id       = :section_id
               AND week_start_date >= :week_start
        ");
        $stmt->execute([
            ':section_id' => $row['section_id'],
            ':week_start' => $row['week_start_date'],
        ]);

        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
?>
