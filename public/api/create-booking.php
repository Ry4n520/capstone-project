<?php
/**
 * Creates facility booking with STRICT double-booking prevention.
 * 
 * Prevents ANY user from booking an already-booked time slot.
 * Uses transactions to ensure consistency.
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

error_log("Booking Request: facility_id=$facilityId, date=$bookingDate, start=$startTime, end=$endTime");

// Validate inputs
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

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid time format. Expected HH:MM:SS.']);
    exit;
}

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

// Check public holiday
$holiday_check = "SELECT holiday_name FROM public_holidays WHERE holiday_date = :date";
$holiday_stmt = $pdo->prepare($holiday_check);
$holiday_stmt->execute([':date' => $bookingDate]);
$holiday = $holiday_stmt->fetch(PDO::FETCH_ASSOC);

if ($holiday) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Cannot book on ' . $holiday['holiday_name']]);
    exit;
}

// Check if time is in the past (for same-day bookings)
if ($dateObj->format('Y-m-d') === $today->format('Y-m-d')) {
    $current_time = date('H:i:s');
    if ($startTime <= $current_time) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Cannot book past time slots']);
        exit;
    }
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
    // START TRANSACTION for atomicity
    $pdo->beginTransaction();

    // 1. Verify facility exists
    $facilityStmt = $pdo->prepare(
        'SELECT facility_id, is_available
         FROM facilities
         WHERE facility_id = :facility_id
         LIMIT 1'
    );
    $facilityStmt->execute([':facility_id' => $facilityId]);

    $facility = $facilityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$facility) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Facility not found.']);
        exit;
    }

    if (!facility_booking_is_available_value($facility['is_available'])) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This facility is currently unavailable for booking.']);
        exit;
    }

    // 2. CRITICAL: Check for STRICT conflicts - ANY user, ANY overlap
    // This is the key difference: we check if ANY user has booked this time
    $conflict_query = "
        SELECT b.booking_id 
        FROM bookings b
        WHERE b.facility_id = :facility_id 
        AND b.booking_date = :booking_date
        AND b.booking_status IN ('confirmed', 'pending')
        AND (
            -- Check for ANY time overlap
            (b.start_time < :end_time AND b.end_time > :start_time)
        )
        LIMIT 1
    ";
    
    $conflictStmt = $pdo->prepare($conflict_query);
    $conflictStmt->execute([
        ':facility_id' => $facilityId,
        ':booking_date' => $bookingDate,
        ':start_time' => $startTime,
        ':end_time' => $endTime
    ]);

    if ($conflictStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This time slot is already booked. Please select another slot.',
            'conflict' => true
        ]);
        exit;
    }

    // 3. Create the booking (only if NO conflicts exist)
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
        'message' => $bookingStatus === 'confirmed' ? 'Booking confirmed successfully!' : 'Booking submitted and pending confirmation.',
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
        'message' => 'Server error occurred. Please try again.'
    ]);
}
?>