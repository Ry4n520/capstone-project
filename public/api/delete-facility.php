<?php
/**
 * Delete Facility API (Admin)
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
    echo json_encode(['success' => false, 'message' => 'Only admins can delete facilities.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$facilityId = isset($input['facility_id']) ? (int) $input['facility_id'] : 0;

if ($facilityId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid facility ID required.']);
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

    $bookingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE facility_id = :facility_id');
    $bookingCountStmt->execute([':facility_id' => $facilityId]);
    $bookingCount = (int) $bookingCountStmt->fetchColumn();

    if ($bookingCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This facility has booking records and cannot be deleted. Set it unavailable instead.'
        ]);
        exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM facilities WHERE facility_id = :facility_id');
    $deleteStmt->execute([':facility_id' => $facilityId]);

    echo json_encode(['success' => true, 'message' => 'Facility deleted successfully.']);
} catch (Throwable $e) {
    error_log('Delete Facility Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>