<?php
/**
 * Return classrooms for lecturer schedule change requests.
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

if (!in_array($_SESSION['role'], ['staff', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only staff and admins can access classrooms.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->query(
        'SELECT room_id, room_name, building, capacity, room_type
         FROM classrooms
         ORDER BY building ASC, room_name ASC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $classrooms = array_map(function ($row) {
        return [
            'room_id' => (int) $row['room_id'],
            'room_name' => $row['room_name'],
            'building' => $row['building'],
            'capacity' => $row['capacity'] !== null ? (int) $row['capacity'] : null,
            'room_type' => $row['room_type']
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'classrooms' => $classrooms
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load classrooms',
        'message' => $e->getMessage()
    ]);
}
?>