<?php
/**
 * Update Facility API (Admin)
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
    echo json_encode(['success' => false, 'message' => 'Only admins can update facilities.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/facility_booking_utils.php';

function update_facility_get_input_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

function update_facility_normalize_text($value)
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

$input = update_facility_get_input_data();

$facilityId = isset($input['facility_id']) ? (int) $input['facility_id'] : 0;
$facilityName = update_facility_normalize_text($input['facility_name'] ?? '');
$facilityType = facility_booking_normalize_type($input['facility_type'] ?? '');
$location = update_facility_normalize_text($input['location'] ?? '');
$capacityRaw = trim((string) ($input['capacity'] ?? ''));

if ($facilityId <= 0 || $facilityName === '' || $facilityType === null || $location === '' || $capacityRaw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'facility_id, facility_name, facility_type, location, and capacity are required.']);
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
    $facilityStmt = $pdo->prepare('SELECT facility_id FROM facilities WHERE facility_id = :facility_id LIMIT 1');
    $facilityStmt->execute([':facility_id' => $facilityId]);

    if (!$facilityStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Facility not found.']);
        exit;
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT facility_id
         FROM facilities
         WHERE facility_type = :facility_type
           AND LOWER(facility_name) = LOWER(:facility_name)
           AND facility_id <> :facility_id
         LIMIT 1'
    );
    $duplicateStmt->execute([
        ':facility_type' => $facilityType,
        ':facility_name' => $facilityName,
        ':facility_id' => $facilityId,
    ]);

    if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A facility with this name already exists in the selected category.']);
        exit;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE facilities
         SET facility_name = :facility_name,
             location = :location,
             capacity = :capacity,
             facility_type = :facility_type
         WHERE facility_id = :facility_id'
    );
    $updateStmt->execute([
        ':facility_name' => $facilityName,
        ':location' => $location,
        ':capacity' => (int) $capacity,
        ':facility_type' => $facilityType,
        ':facility_id' => $facilityId,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Facility updated successfully.',
        'facility' => [
            'facility_id' => $facilityId,
            'facility_name' => $facilityName,
            'location' => $location,
            'capacity' => (int) $capacity,
            'facility_type' => $facilityType,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Update Facility Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>