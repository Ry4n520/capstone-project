<?php
/**
 * Student submits attendance code for active class session.
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

if ($_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only students can submit attendance codes']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
$code = isset($payload['code']) ? trim((string) $payload['code']) : (isset($_POST['code']) ? trim((string) $_POST['code']) : '');
$student_id = (int) $_SESSION['user_id'];

if (!preg_match('/^\d{3}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Attendance code must be 3 digits']);
    exit;
}

try {
    $session_sql = "
        SELECT
            s.session_id,
            s.code_expiry,
            t.section_id,
            c.course_name,
            cs.section_code
        FROM class_sessions s
        INNER JOIN timetables t ON s.timetable_id = t.timetable_id
        INNER JOIN course_sections cs ON t.section_id = cs.section_id
        INNER JOIN courses c ON cs.course_id = c.course_id
        WHERE s.attendance_code = :code
          AND s.code_expiry > NOW()
          AND s.session_date = CURDATE()
          AND t.status = 'released'
        ORDER BY s.code_expiry DESC
        LIMIT 1
    ";

    $session_stmt = $pdo->prepare($session_sql);
    $session_stmt->execute([':code' => $code]);
    $session_row = $session_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session_row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired attendance code']);
        exit;
    }

    $session_id = (int) $session_row['session_id'];
    $section_id = (int) $session_row['section_id'];

    $enrollment_sql = "
        SELECT enrollment_id
        FROM enrollments
        WHERE student_id = :student_id
          AND section_id = :section_id
          AND status = 'active'
        LIMIT 1
    ";
    $enrollment_stmt = $pdo->prepare($enrollment_sql);
    $enrollment_stmt->execute([
        ':student_id' => $student_id,
        ':section_id' => $section_id
    ]);
    $enrollment_row = $enrollment_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment_row) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this class']);
        exit;
    }

    $enrollment_id = (int) $enrollment_row['enrollment_id'];

    $check_sql = "
        SELECT attendance_id, status
        FROM attendance
        WHERE enrollment_id = :enrollment_id
          AND session_id = :session_id
        LIMIT 1
    ";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':enrollment_id' => $enrollment_id,
        ':session_id' => $session_id
    ]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'present' || $existing['status'] === 'late') {
            echo json_encode([
                'success' => true,
                'already_marked' => true,
                'message' => 'Attendance already recorded for this class',
                'course_name' => $session_row['course_name'],
                'section_code' => $session_row['section_code']
            ]);
            exit;
        }

        $update_sql = "
            UPDATE attendance
            SET status = 'present',
                marked_at = NOW()
            WHERE attendance_id = :attendance_id
        ";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([':attendance_id' => (int) $existing['attendance_id']]);
    } else {
        $insert_sql = "
            INSERT INTO attendance (enrollment_id, session_id, status, marked_at)
            VALUES (:enrollment_id, :session_id, 'present', NOW())
        ";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            ':enrollment_id' => $enrollment_id,
            ':session_id' => $session_id
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Attendance submitted successfully',
        'course_name' => $session_row['course_name'],
        'section_code' => $session_row['section_code'],
        'expires_at' => $session_row['code_expiry']
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit attendance code',
        'error' => $e->getMessage()
    ]);
}
