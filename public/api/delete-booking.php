<?php
/**
 * Delete Booking API (Admin)
 *
 * Allows admin to permanently delete any booking.
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
    echo json_encode(['success' => false, 'message' => 'Only admins can delete bookings.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($data['booking_id']) ? (int) $data['booking_id'] : 0;

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid booking ID required.']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM bookings WHERE booking_id = :booking_id');
    $stmt->execute([':booking_id' => $booking_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully.']);
} catch (Throwable $e) {
    error_log('Delete Booking Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>