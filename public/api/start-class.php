<?php
/**
 * Start (or refresh) attendance for a lecturer class.
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
    echo json_encode(['success' => false, 'message' => 'Only lecturers can start classes']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
$timetable_id = isset($payload['timetable_id']) ? (int) $payload['timetable_id'] : (isset($_POST['timetable_id']) ? (int) $_POST['timetable_id'] : 0);
$lecturer_id = (int) $_SESSION['user_id'];

if ($timetable_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid timetable_id is required']);
    exit;
}

try {
    $ownership_sql = "
        SELECT t.timetable_id
        FROM timetables t
        INNER JOIN course_sections cs ON t.section_id = cs.section_id
        WHERE t.timetable_id = :timetable_id
          AND cs.lecturer_id = :lecturer_id
          AND t.status = 'released'
          AND CURDATE() BETWEEN t.week_start_date AND t.week_end_date
          AND LOWER(t.day_of_week) = LOWER(DAYNAME(CURDATE()))
        LIMIT 1
    ";

    $ownership_stmt = $pdo->prepare($ownership_sql);
    $ownership_stmt->execute([
        ':timetable_id' => $timetable_id,
        ':lecturer_id' => $lecturer_id
    ]);

    if (!$ownership_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot start attendance for this class']);
        exit;
    }

    $pdo->beginTransaction();

    $find_session_sql = "
        SELECT session_id
        FROM class_sessions
        WHERE timetable_id = :timetable_id
          AND session_date = CURDATE()
        LIMIT 1
        FOR UPDATE
    ";
    $find_session_stmt = $pdo->prepare($find_session_sql);
    $find_session_stmt->execute([':timetable_id' => $timetable_id]);
    $existing = $find_session_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $session_id = (int) $existing['session_id'];
    } else {
        $insert_sql = "
            INSERT INTO class_sessions (timetable_id, session_date, created_at)
            VALUES (:timetable_id, CURDATE(), NOW())
        ";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([':timetable_id' => $timetable_id]);
        $session_id = (int) $pdo->lastInsertId();
    }

    $attendance_code = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $expiry_datetime = date('Y-m-d H:i:s', time() + (15 * 60));

    $update_sql = "
        UPDATE class_sessions
        SET attendance_code = :attendance_code,
            code_expiry = :code_expiry
        WHERE session_id = :session_id
    ";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':attendance_code' => $attendance_code,
        ':code_expiry' => $expiry_datetime,
        ':session_id' => $session_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance started',
        'session_id' => $session_id,
        'attendance_code' => $attendance_code,
        'expires_at' => $expiry_datetime
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to start class attendance',
        'error' => $e->getMessage()
    ]);
}
