<?php
/**
 * Returns facilities by type with today's slot availability.
 * Availability is computed slot-by-slot for today and sorted by available count.
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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/facility_booking_utils.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
$normalizedType = facility_booking_normalize_type($type);

if ($normalizedType === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid facility type.']);
    exit;
}

try {
    $facilities = facility_booking_get_facilities_with_availability($pdo, $normalizedType, $today);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'type' => $normalizedType,
        'title' => facility_booking_type_title($normalizedType),
        'date' => $today,
        'facilities' => $facilities
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>