<?php
/**
 * Toggle Facility Availability API (Admin)
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
    echo json_encode(['success' => false, 'message' => 'Only admins can change facility availability.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$facilityId = isset($input['facility_id']) ? (int) $input['facility_id'] : 0;
$requestedAvailability = filter_var($input['is_available'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($facilityId <= 0 || $requestedAvailability === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'facility_id and is_available are required.']);
    exit;
}

try {
    $updateStmt = $pdo->prepare(
        'UPDATE facilities
         SET is_available = :is_available
         WHERE facility_id = :facility_id'
    );
    $updateStmt->execute([
        ':is_available' => $requestedAvailability ? 1 : 0,
        ':facility_id' => $facilityId,
    ]);

    if ($updateStmt->rowCount() === 0) {
        $facilityStmt = $pdo->prepare('SELECT facility_id FROM facilities WHERE facility_id = :facility_id LIMIT 1');
        $facilityStmt->execute([':facility_id' => $facilityId]);

        if (!$facilityStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Facility not found.']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $requestedAvailability ? 'Facility is now available for booking.' : 'Facility has been set as unavailable.',
        'is_available' => $requestedAvailability,
    ]);
} catch (Throwable $e) {
    error_log('Toggle Facility Availability Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>