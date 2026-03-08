<?php
/**
 * Returns availability of fixed booking slots for a specific facility/date.
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

$facilityId = isset($_GET['facility_id']) ? (int) $_GET['facility_id'] : 0;
$dateRaw = isset($_GET['date']) ? trim((string) $_GET['date']) : '';

$date = DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
if ($facilityId <= 0 || !$date || $date->format('Y-m-d') !== $dateRaw) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid facility_id or date.']);
    exit;
}

$today = new DateTimeImmutable('today');
$maxDate = $today->modify('+30 days');
if ($date < $today || $date > $maxDate) {
    http_response_code(422);
    echo json_encode(['error' => 'Date must be between today and 30 days ahead.']);
    exit;
}

try {
    $facilityStmt = $pdo->prepare(
        'SELECT facility_id, facility_name, location, facility_type
         FROM facilities
         WHERE facility_id = :facility_id
         LIMIT 1'
    );
    $facilityStmt->execute([':facility_id' => $facilityId]);
    $facility = $facilityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$facility) {
        http_response_code(404);
        echo json_encode(['error' => 'Facility not found.']);
        exit;
    }

    $availability = facility_booking_get_slots_for_date($pdo, $facilityId, $dateRaw);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'facility' => $facility,
        'date' => $dateRaw,
        'available_slots_count' => $availability['available_slots_count'],
        'slots' => $availability['slots']
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>