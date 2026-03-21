<?php
/**
 * Admin timetable form options endpoint.
 * Returns courses, sections (with lecturer), and classrooms.
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

try {
    $coursesStmt = $pdo->query(
        'SELECT DISTINCT c.course_id, c.course_name
         FROM course_sections cs
         INNER JOIN courses c ON cs.course_id = c.course_id
         ORDER BY c.course_name ASC'
    );

    $sectionsStmt = $pdo->query(
        'SELECT
            cs.section_id,
            cs.course_id,
            cs.section_code,
            cs.semester,
            cs.year,
            c.course_name,
            u.user_id AS lecturer_id,
            u.name AS lecturer_name
         FROM course_sections cs
         INNER JOIN courses c ON cs.course_id = c.course_id
         INNER JOIN users u ON cs.lecturer_id = u.user_id
         ORDER BY c.course_name ASC, cs.section_code ASC'
    );

    $classroomsStmt = $pdo->query(
        'SELECT room_id, room_name, building, capacity, room_type
         FROM classrooms
         ORDER BY building ASC, room_name ASC'
    );

    $courses = array_map(function ($row) {
        return [
            'course_id' => (int) $row['course_id'],
            'course_name' => $row['course_name']
        ];
    }, $coursesStmt->fetchAll(PDO::FETCH_ASSOC));

    $sections = array_map(function ($row) {
        return [
            'section_id' => (int) $row['section_id'],
            'course_id' => (int) $row['course_id'],
            'section_code' => $row['section_code'],
            'semester' => $row['semester'],
            'year' => $row['year'] !== null ? (int) $row['year'] : null,
            'course_name' => $row['course_name'],
            'lecturer_id' => (int) $row['lecturer_id'],
            'lecturer_name' => $row['lecturer_name']
        ];
    }, $sectionsStmt->fetchAll(PDO::FETCH_ASSOC));

    $classrooms = array_map(function ($row) {
        return [
            'room_id' => (int) $row['room_id'],
            'room_name' => $row['room_name'],
            'building' => $row['building'],
            'capacity' => $row['capacity'] !== null ? (int) $row['capacity'] : null,
            'room_type' => $row['room_type']
        ];
    }, $classroomsStmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'sections' => $sections,
        'classrooms' => $classrooms
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load timetable form options',
        'message' => $e->getMessage()
    ]);
}
?>