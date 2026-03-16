<?php
/**
 * Cancel Booking API
 * 
 * Allows users to cancel their own bookings (only future bookings can be cancelled)
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

require_once __DIR__ . '/../config/db.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($data['booking_id']) ? (int) $data['booking_id'] : 0;

if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid booking ID required']);
    exit;
}

try {
    // Verify booking exists (admins can manage all; non-admins can only manage their own).
    $check_query = "SELECT booking_id, user_id, booking_date, start_time, booking_status
                    FROM bookings
                    WHERE booking_id = :booking_id";

    if (!$is_admin) {
        $check_query .= " AND user_id = :user_id";
    }

    $stmt = $pdo->prepare($check_query);

    $params = [':booking_id' => $booking_id];
    if (!$is_admin) {
        $params[':user_id'] = $_SESSION['user_id'];
    }

    $stmt->execute($params);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found or unauthorized.']);
        exit;
    }

    // Check if booking is already cancelled
    if ($booking['booking_status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This booking is already cancelled']);
        exit;
    }

    // Non-admin users can only cancel future bookings.
    $booking_datetime = $booking['booking_date'] . ' ' . $booking['start_time'];
    if (!$is_admin && strtotime($booking_datetime) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot cancel past bookings.']);
        exit;
    }

    // Update status to cancelled
    $update_query = "UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = :booking_id";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([':booking_id' => $booking_id]);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);

} catch (Exception $e) {
    error_log("Cancel Booking Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
