<?php
/**
 * Return live attendance list for a lecturer session.
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
    echo json_encode(['success' => false, 'message' => 'Only lecturers can view class attendance']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$session_id = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$lecturer_id = (int) $_SESSION['user_id'];

if ($session_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid session_id is required']);
    exit;
}

try {
    $ownership_sql = "
        SELECT
            s.session_id,
            t.section_id
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
    $ownership = $ownership_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ownership) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot view this session']);
        exit;
    }

    $section_id = (int) $ownership['section_id'];

    $total_sql = "
        SELECT COUNT(*) AS total_students
        FROM enrollments
        WHERE section_id = :section_id
          AND status = 'active'
    ";
    $total_stmt = $pdo->prepare($total_sql);
    $total_stmt->execute([':section_id' => $section_id]);
    $total_students = (int) $total_stmt->fetchColumn();

    $attendance_sql = "
        SELECT
            u.user_id,
            u.name,
            u.email,
            a.status,
            a.marked_at
        FROM attendance a
        INNER JOIN enrollments e ON a.enrollment_id = e.enrollment_id
        INNER JOIN users u ON e.student_id = u.user_id
        WHERE a.session_id = :session_id
          AND a.status IN ('present', 'late')
        ORDER BY a.marked_at ASC
    ";
    $attendance_stmt = $pdo->prepare($attendance_sql);
    $attendance_stmt->execute([':session_id' => $session_id]);
    $attendees = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

    $present_students = array_map(function ($row) {
        return [
            'user_id' => (int) $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'status' => $row['status'],
            'marked_at' => $row['marked_at'],
            'marked_time' => date('g:i A', strtotime($row['marked_at']))
        ];
    }, $attendees);

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'present_count' => count($present_students),
        'total_students' => $total_students,
        'present_students' => $present_students
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load class attendance',
        'error' => $e->getMessage()
    ]);
}
