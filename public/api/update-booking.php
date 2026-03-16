<?php
/**
 * Update Booking API (Admin)
 *
 * Allows admin to edit booking date/time for any booking.
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
    echo json_encode(['success' => false, 'message' => 'Only admins can update bookings.']);
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

function normalize_time_value($value)
{
    $text = trim((string) $value);
    if (preg_match('/^\d{2}:\d{2}$/', $text)) {
        return $text . ':00';
    }
    return $text;
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$input = get_input_data();

$booking_id = isset($input['booking_id']) ? (int) $input['booking_id'] : 0;
$booking_date = isset($input['booking_date']) ? trim((string) $input['booking_date']) : '';
$start_time = normalize_time_value($input['start_time'] ?? '');
$end_time = normalize_time_value($input['end_time'] ?? '');

if ($booking_id <= 0 || $booking_date === '' || $start_time === '' || $end_time === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'booking_id, booking_date, start_time and end_time are required.']);
    exit;
}

$dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $booking_date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $booking_date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking date.']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid time format. Expected HH:MM or HH:MM:SS.']);
    exit;
}

if ($start_time >= $end_time) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start time must be before end time.']);
    exit;
}

$allowedSlots = facility_booking_slots();
$requestedSlotAllowed = false;
foreach ($allowedSlots as $slot) {
    if ($slot['start'] === $start_time && $slot['end'] === $end_time) {
        $requestedSlotAllowed = true;
        break;
    }
}

if (!$requestedSlotAllowed) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requested time slot is not allowed.']);
    exit;
}

$today = new DateTimeImmutable('today');
$maxDate = $today->modify('+30 days');
if ($dateObj < $today || $dateObj > $maxDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Bookings can only be set between today and 30 days ahead.']);
    exit;
}

if ($dateObj->format('Y-m-d') === $today->format('Y-m-d')) {
    $current_time = date('H:i:s');
    if ($start_time <= $current_time) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cannot set booking to a past time slot.']);
        exit;
    }
}

try {
    $holiday_stmt = $pdo->prepare('SELECT holiday_name FROM public_holidays WHERE holiday_date = :date LIMIT 1');
    $holiday_stmt->execute([':date' => $booking_date]);
    $holiday = $holiday_stmt->fetch(PDO::FETCH_ASSOC);

    if ($holiday) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cannot book on ' . $holiday['holiday_name'] . '.']);
        exit;
    }

    $pdo->beginTransaction();

    $booking_stmt = $pdo->prepare(
        'SELECT booking_id, facility_id, booking_status
         FROM bookings
         WHERE booking_id = :booking_id
         LIMIT 1
         FOR UPDATE'
    );
    $booking_stmt->execute([':booking_id' => $booking_id]);
    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    if (strtolower((string) $booking['booking_status']) === 'cancelled') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cancelled bookings cannot be edited.']);
        exit;
    }

    $conflict_stmt = $pdo->prepare(
        'SELECT booking_id
         FROM bookings
         WHERE facility_id = :facility_id
           AND booking_date = :booking_date
           AND booking_status IN (\'confirmed\', \'pending\')
           AND booking_id <> :booking_id
           AND start_time < :end_time
           AND end_time > :start_time
         LIMIT 1'
    );
    $conflict_stmt->execute([
        ':facility_id' => (int) $booking['facility_id'],
        ':booking_date' => $booking_date,
        ':booking_id' => $booking_id,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
    ]);

    if ($conflict_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked.']);
        exit;
    }

    $target_status = ($dateObj->format('Y-m-d') === $today->format('Y-m-d')) ? 'confirmed' : 'pending';

    $update_stmt = $pdo->prepare(
        'UPDATE bookings
         SET booking_date = :booking_date,
             start_time = :start_time,
             end_time = :end_time,
             booking_status = :booking_status
         WHERE booking_id = :booking_id'
    );
    $update_stmt->execute([
        ':booking_date' => $booking_date,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':booking_status' => $target_status,
        ':booking_id' => $booking_id,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Booking updated successfully.',
        'status' => $target_status,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Update Booking Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>