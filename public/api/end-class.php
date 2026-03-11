<?php
/**
 * End active attendance session.
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
    echo json_encode(['success' => false, 'message' => 'Only lecturers can end class attendance']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
$session_id = isset($payload['session_id']) ? (int) $payload['session_id'] : (isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0);
$lecturer_id = (int) $_SESSION['user_id'];

if ($session_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid session_id is required']);
    exit;
}

try {
    $ownership_sql = "
        SELECT s.session_id
        FROM class_sessions s
        INNER JOIN timetables t ON s.timetable_id = t.timetable_id
        INNER JOIN course_sections cs ON t.section_id = cs.section_id
        WHERE s.session_id = :session_id
          AND cs.lecturer_id = :lecturer_id
        LIMIT 1
    ";
    $ownership_stmt = $pdo->prepare($ownership_sql);
    $ownership_stmt->execute([
        ':session_id' => $session_id,
        ':lecturer_id' => $lecturer_id
    ]);

    if (!$ownership_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot end this session']);
        exit;
    }

    $end_sql = "
        UPDATE class_sessions
        SET attendance_code = NULL,
            code_expiry = NOW()
        WHERE session_id = :session_id
    ";
    $end_stmt = $pdo->prepare($end_sql);
    $end_stmt->execute([':session_id' => $session_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Class attendance has been ended'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to end class attendance',
        'error' => $e->getMessage()
    ]);
}
