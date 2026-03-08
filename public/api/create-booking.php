<?php
/**
 * Creates facility booking with conflict prevention.
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

function get_input_data()
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$input = get_input_data();

$userId = (int) $_SESSION['user_id'];
$facilityId = isset($input['facility_id']) ? (int) $input['facility_id'] : 0;
$bookingDate = isset($input['booking_date']) ? trim((string) $input['booking_date']) : '';
$startTime = isset($input['start_time']) ? trim((string) $input['start_time']) : '';
$endTime = isset($input['end_time']) ? trim((string) $input['end_time']) : '';

// Log input for debugging
error_log("Booking Request: facility_id=$facilityId, date=$bookingDate, start=$startTime, end=$endTime");

$dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $bookingDate);
if ($facilityId <= 0 || !$dateObj || $dateObj->format('Y-m-d') !== $bookingDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid facility or date.']);
    exit;
}

if (!$startTime || !$endTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Time values are required.']);
    exit;
}

// Validate time format (HH:MM:SS)
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid time format. Expected HH:MM:SS.']);
    exit;
}

// Verify start time is before end time
if ($startTime >= $endTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start time must be before end time.']);
    exit;
}

$today = new DateTimeImmutable('today');
$maxDate = $today->modify('+30 days');
if ($dateObj < $today || $dateObj > $maxDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Bookings are allowed from today up to 30 days ahead.']);
    exit;
}

$allowedSlots = facility_booking_slots();
$requestedSlotAllowed = false;
foreach ($allowedSlots as $slot) {
    if ($slot['start'] === $startTime && $slot['end'] === $endTime) {
        $requestedSlotAllowed = true;
        break;
    }
}

if (!$requestedSlotAllowed) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requested time slot is not allowed.']);
    exit;
}

$bookingStatus = ($dateObj->format('Y-m-d') === $today->format('Y-m-d')) ? 'confirmed' : 'pending';

try {
    $pdo->beginTransaction();

    $facilityStmt = $pdo->prepare(
        'SELECT facility_id
         FROM facilities
         WHERE facility_id = :facility_id
         LIMIT 1'
    );
    $facilityStmt->execute([':facility_id' => $facilityId]);

    if (!$facilityStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Facility not found.']);
        exit;
    }

    $existingStmt = $pdo->prepare(
        'SELECT booking_id
         FROM bookings
         WHERE facility_id = :facility_id
           AND booking_date = :booking_date
           AND booking_status IN (\'confirmed\', \'pending\')
           AND (
               (start_time < :slot_end AND end_time > :slot_start)
           )'
    );

    $existingStmt->execute([
        ':facility_id' => $facilityId,
        ':booking_date' => $bookingDate,
        ':slot_start' => $startTime,
        ':slot_end' => $endTime
    ]);

    if ($existingStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Selected time slot is no longer available.']);
        exit;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO bookings (
            user_id,
            facility_id,
            booking_date,
            start_time,
            end_time,
            booking_status
         ) VALUES (
            :user_id,
            :facility_id,
            :booking_date,
            :start_time,
            :end_time,
            :booking_status
         )'
    );

    $insertStmt->execute([
        ':user_id' => $userId,
        ':facility_id' => $facilityId,
        ':booking_date' => $bookingDate,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':booking_status' => $bookingStatus
    ]);

    $bookingId = (int) $pdo->lastInsertId();
    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $bookingStatus === 'confirmed' ? 'Booking confirmed!' : 'Booking submitted and pending confirmation.',
        'booking_id' => $bookingId,
        'status' => $bookingStatus
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Booking Error: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")");

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>