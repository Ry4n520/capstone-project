<?php
/**
 * Create Facility API (Admin)
 *
 * Allows admins to add a new facility to the booking catalog.
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

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only admins can create facilities.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/facility_booking_utils.php';

function get_input_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

function normalize_text_value($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

$input = get_input_data();

$facilityName = normalize_text_value($input['facility_name'] ?? '');
$facilityType = facility_booking_normalize_type($input['facility_type'] ?? '');
$location = normalize_text_value($input['location'] ?? '');
$capacityRaw = trim((string) ($input['capacity'] ?? ''));

if ($facilityName === '' || $facilityType === null || $location === '' || $capacityRaw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'facility_name, facility_type, location, and capacity are required.']);
    exit;
}

if (strlen($facilityName) > 120 || strlen($location) > 120) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Facility name and location must be 120 characters or fewer.']);
    exit;
}

$capacity = filter_var($capacityRaw, FILTER_VALIDATE_INT);
if ($capacity === false || (int) $capacity < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Capacity must be a whole number greater than 0.']);
    exit;
}

try {
    $duplicateStmt = $pdo->prepare(
        'SELECT facility_id
         FROM facilities
         WHERE facility_type = :facility_type
           AND LOWER(facility_name) = LOWER(:facility_name)
         LIMIT 1'
    );
    $duplicateStmt->execute([
        ':facility_type' => $facilityType,
        ':facility_name' => $facilityName,
    ]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A facility with this name already exists in the selected category.']);
        exit;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO facilities (facility_name, location, capacity, facility_type, is_available)
         VALUES (:facility_name, :location, :capacity, :facility_type, :is_available)'
    );
    $insertStmt->execute([
        ':facility_name' => $facilityName,
        ':location' => $location,
        ':capacity' => (int) $capacity,
        ':facility_type' => $facilityType,
        ':is_available' => 1,
    ]);

    $facilityId = (int) $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Facility created successfully.',
        'facility' => [
            'facility_id' => $facilityId,
            'facility_name' => $facilityName,
            'location' => $location,
            'capacity' => (int) $capacity,
            'facility_type' => $facilityType,
            'is_available' => true,
        ],
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('Create Facility Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>