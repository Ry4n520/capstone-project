<?php
/**
 * Smart Campus Management System - Facility Booking
 * 
 * Allows users to book campus facilities
 */

// Include session verification and authentication
include 'includes/check_session.php';
require_once 'config/db.php';
require_once 'includes/facility_booking_utils.php';

// Get current date and time
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date = $current_datetime->format('l, F d, Y');
$formatted_time = $current_datetime->format('g:i A');

$today_iso = $current_datetime->format('Y-m-d');
$tomorrow_iso = (clone $current_datetime)->modify('+1 day')->format('Y-m-d');
$max_advance_iso = (clone $current_datetime)->modify('+30 days')->format('Y-m-d');

// Category count = facilities with at least one available slot today.
$category_counts = [
    'classroom' => facility_booking_available_facility_count($pdo, 'classroom', $today_iso),
    'meeting_room' => facility_booking_available_facility_count($pdo, 'meeting_room', $today_iso),
    'sport_facility' => facility_booking_available_facility_count($pdo, 'sport_facility', $today_iso)
];

$my_bookings_stmt = $pdo->prepare(
    'SELECT
        b.booking_id,
        f.facility_name,
        f.location,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.booking_status
     FROM bookings b
     JOIN facilities f ON b.facility_id = f.facility_id
     WHERE b.user_id = :user_id
       AND b.booking_status != :cancelled_status
     ORDER BY b.booking_date ASC, b.start_time ASC'
);

$my_bookings_stmt->execute([
    ':user_id' => $user_id,
    ':cancelled_status' => 'cancelled'
]);

$my_bookings = $my_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
$active_bookings_count = count($my_bookings);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function booking_status_badge($status)
{
    $status = strtolower((string) $status);

    if ($status === 'confirmed') {
        return '<span class="status-badge status-available">Confirmed</span>';
    }

    if ($status === 'pending') {
        return '<span class="category-badge pending-badge">Pending</span>';
    }

    return '<span class="status-badge">' . e(ucfirst($status)) . '</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Facility Booking</title>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/homepage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/facility-booking.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Facility Booking</h1>
            <p>Book classrooms, meeting rooms, and sports facilities</p>
        </div>

        <!-- Category Selection (Main View) -->
        <div id="categoryView">
            <div class="category-grid">
                <!-- Classrooms -->
                <div class="category-card classroom-accent" onclick="showFacilities('classroom')">
                    <div class="category-icon">🏫</div>
                    <div class="category-name">Classrooms</div>
                    <div class="category-count"><?php echo e($category_counts['classroom']); ?> rooms available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- Meeting Rooms -->
                <div class="category-card meeting-accent" onclick="showFacilities('meeting_room')">
                    <div class="category-icon">👥</div>
                    <div class="category-name">Meeting Rooms</div>
                    <div class="category-count"><?php echo e($category_counts['meeting_room']); ?> rooms available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- Sport Facilities -->
                <div class="category-card sport-accent" onclick="showFacilities('sport_facility')">
                    <div class="category-icon">⚽</div>
                    <div class="category-name">Sport Facilities</div>
                    <div class="category-count"><?php echo e($category_counts['sport_facility']); ?> facilities available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- View Bookings -->
                <div class="category-card booking-accent" onclick="showMyBookings()">
                    <div class="category-icon">📅</div>
                    <div class="category-name">View My Bookings</div>
                    <div class="category-count"><?php echo e($active_bookings_count); ?> active bookings</div>
                    <span class="category-badge">View All</span>
                </div>
            </div>
        </div>

        <!-- Facility List View (Hidden by default) -->
        <div id="facilityView" class="facility-section hidden">
            <div class="section-header">
                <h2 class="section-title">Sport Facilities</h2>
                <button class="back-btn" onclick="showCategories()">← Back to Categories</button>
            </div>

            <div id="facilityGrid" class="facility-grid">
                <div class="facility-empty-state">Select a category to load facilities.</div>
            </div>
        </div>

        <!-- My Bookings View (Hidden by default) -->
        <div id="bookingsView" class="facility-section hidden">
            <div class="section-header">
                <h2 class="section-title">My Bookings</h2>
                <button class="back-btn" onclick="showCategories()">← Back to Categories</button>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="upcoming">Upcoming</button>
                <button class="filter-tab" data-filter="past">Past</button>
                <button class="filter-tab" data-filter="all">All</button>
            </div>

            <!-- Bookings Table -->
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($my_bookings) === 0): ?>
                        <tr class="bookings-empty-row">
                            <td colspan="5" class="bookings-empty">No bookings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_bookings as $booking): ?>
                            <?php
                            $booking_date_iso = $booking['booking_date'];
                            $booking_date_readable = date('F d, Y', strtotime($booking_date_iso));
                            $time_range = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            ?>
                            <tr data-booking-date="<?php echo e($booking_date_iso); ?>">
                                <td><?php echo e($booking['facility_name']); ?></td>
                                <td><?php echo e($booking_date_readable); ?></td>
                                <td><?php echo e($time_range); ?></td>
                                <td><?php echo booking_status_badge($booking['booking_status']); ?></td>
                                <td><button class="action-btn btn-cancel" disabled>Cancel</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="bookingModal" class="booking-modal hidden">
        <div class="modal-backdrop" onclick="closeBookingModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Book <span id="modal-facility-name"></span></h3>
                <button class="modal-close" onclick="closeBookingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="booking-mode-indicator" class="booking-mode-indicator"></div>

                <div id="date-selection-section" class="hidden">
                    <label for="booking-date-picker">Select Date:</label>
                    <input
                        type="date"
                        id="booking-date-picker"
                        min="<?php echo e($tomorrow_iso); ?>"
                        max="<?php echo e($max_advance_iso); ?>"
                    >
                </div>

                <div id="today-date-display" class="hidden">
                    <label>Date:</label>
                    <p><?php echo e($formatted_date); ?></p>
                </div>

                <div class="time-slots-selection">
                    <label>Select Time Slot:</label>
                    <div id="time-slots-container" class="slots"></div>
                </div>

                <div class="booking-summary">
                    <p><strong>Facility:</strong> <span id="summary-facility">-</span></p>
                    <p><strong>Date:</strong> <span id="summary-date">-</span></p>
                    <p><strong>Time:</strong> <span id="summary-time">-</span></p>
                </div>

                <button id="confirmBookingBtn" class="btn-confirm-booking" onclick="submitBooking()">
                    Confirm Booking
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Success Popup Modal -->
    <div id="successPopup" class="success-popup hidden">
        <div class="success-popup-backdrop" onclick="closeSuccessPopup()"></div>
        <div class="success-popup-content">
            <div class="success-popup-icon">✓</div>
            <h2 id="successPopupTitle">Booking Confirmed!</h2>
            <p id="successPopupMessage">Your facility booking has been created successfully.</p>
            <div id="successPopupDetails" class="success-popup-details"></div>
            <button class="success-popup-btn" onclick="handleSuccessPopupClose()">Continue</button>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        window.FACILITY_BOOKING_CONFIG = {
            today: '<?php echo e($today_iso); ?>',
            todayLabel: '<?php echo e($formatted_date); ?>',
            tomorrow: '<?php echo e($tomorrow_iso); ?>',
            maxDate: '<?php echo e($max_advance_iso); ?>'
        };
    </script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/facility-booking.js?v=<?php echo time(); ?>"></script>
</body>
</html>
