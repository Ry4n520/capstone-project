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
$is_admin_user = ($user_role === 'admin');

// Category count = facilities with at least one available slot today.
$category_counts = [
    'classroom' => facility_booking_available_facility_count($pdo, 'classroom', $today_iso),
    'meeting_room' => facility_booking_available_facility_count($pdo, 'meeting_room', $today_iso),
    'sport_facility' => facility_booking_available_facility_count($pdo, 'sport_facility', $today_iso)
];

$bookings_query = '
    SELECT
        b.booking_id,
        b.user_id,
        b.facility_id,
        f.facility_name,
        f.location,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.booking_status,
        u.name AS booked_by_name,
        u.email AS booked_by_email
    FROM bookings b
    JOIN facilities f ON b.facility_id = f.facility_id
    JOIN users u ON b.user_id = u.user_id
';

if ($is_admin_user) {
    $bookings_stmt = $pdo->query($bookings_query . ' ORDER BY b.booking_date ASC, b.start_time ASC');
} else {
    $bookings_stmt = $pdo->prepare(
        $bookings_query . '
         WHERE b.user_id = :user_id
           AND b.booking_status != :cancelled_status
         ORDER BY b.booking_date ASC, b.start_time ASC'
    );

    $bookings_stmt->execute([
        ':user_id' => $user_id,
        ':cancelled_status' => 'cancelled'
    ]);
}

$bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
$bookings_count = count($bookings);

$booking_card_title = $is_admin_user ? 'View All Bookings' : 'View My Bookings';
$booking_card_count_label = $is_admin_user ? 'bookings in system' : 'active bookings';
$bookings_section_title = $is_admin_user ? 'All Bookings' : 'My Bookings';
$bookings_empty_message = $is_admin_user ? 'No bookings found in the system.' : 'No bookings found.';

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
                    <div class="category-name"><?php echo e($booking_card_title); ?></div>
                    <div class="category-count"><?php echo e($bookings_count); ?> <?php echo e($booking_card_count_label); ?></div>
                    <span class="category-badge">View All</span>
                </div>
            </div>
        </div>

        <!-- Facility List View (Hidden by default) -->
        <div id="facilityView" class="facility-section hidden">
            <div class="section-header">
                <h2 class="section-title">Sport Facilities</h2>
                <div class="section-header-actions">
                    <?php if ($is_admin_user): ?>
                        <button type="button" class="admin-add-btn" onclick="openAdminFacilityModal()">+ Add Facility</button>
                    <?php endif; ?>
                    <button class="back-btn" onclick="showCategories()">← Back to Categories</button>
                </div>
            </div>

            <div id="facilityGrid" class="facility-grid">
                <div class="facility-empty-state">Select a category to load facilities.</div>
            </div>
        </div>

        <!-- My Bookings View (Hidden by default) -->
        <div id="bookingsView" class="facility-section hidden">
            <div class="section-header">
                <h2 class="section-title"><?php echo e($bookings_section_title); ?></h2>
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
                        <?php if ($is_admin_user): ?>
                            <th>Booked By</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) === 0): ?>
                        <tr class="bookings-empty-row">
                            <td colspan="<?php echo $is_admin_user ? '6' : '5'; ?>" class="bookings-empty"><?php echo e($bookings_empty_message); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $booking_date_iso = $booking['booking_date'];
                            $booking_date_readable = date('F d, Y', strtotime($booking_date_iso));
                            $time_range = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            
                            // Check if booking is in the future
                            $booking_datetime = strtotime($booking_date_iso . ' ' . $booking['start_time']);
                            $is_future_booking = $booking_datetime >= time();
                            $is_cancelled = strtolower((string) $booking['booking_status']) === 'cancelled';
                            ?>
                            <tr
                                id="booking-row-<?php echo e($booking['booking_id']); ?>"
                                data-booking-id="<?php echo e($booking['booking_id']); ?>"
                                data-facility-id="<?php echo e($booking['facility_id']); ?>"
                                data-facility-name="<?php echo e($booking['facility_name']); ?>"
                                data-booking-date="<?php echo e($booking_date_iso); ?>"
                                data-start-time="<?php echo e($booking['start_time']); ?>"
                                data-end-time="<?php echo e($booking['end_time']); ?>"
                                data-booking-status="<?php echo e($booking['booking_status']); ?>"
                            >
                                <td><?php echo e($booking['facility_name']); ?></td>
                                <?php if ($is_admin_user): ?>
                                    <td>
                                        <div class="booking-user-name"><?php echo e($booking['booked_by_name']); ?></div>
                                        <div class="booking-user-email"><?php echo e($booking['booked_by_email']); ?></div>
                                    </td>
                                <?php endif; ?>
                                <td><?php echo e($booking_date_readable); ?></td>
                                <td><?php echo e($time_range); ?></td>
                                <td><?php echo booking_status_badge($booking['booking_status']); ?></td>
                                <td>
                                    <?php if ($is_admin_user): ?>
                                        <div class="bookings-action-group">
                                            <button
                                                class="action-btn btn-cancel"
                                                onclick="cancelBooking(<?php echo e($booking['booking_id']); ?>)"
                                                <?php echo (!$is_future_booking || $is_cancelled) ? 'disabled' : ''; ?>
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                class="action-btn btn-edit"
                                                onclick="openEditBookingModal(<?php echo e($booking['booking_id']); ?>)"
                                                <?php echo $is_cancelled ? 'disabled' : ''; ?>
                                            >
                                                Edit
                                            </button>
                                            <button
                                                class="action-btn btn-delete"
                                                onclick="deleteBooking(<?php echo e($booking['booking_id']); ?>)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($is_future_booking): ?>
                                            <button class="action-btn btn-cancel" 
                                                    onclick="cancelBooking(<?php echo e($booking['booking_id']); ?>)">
                                                Cancel
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn btn-cancel" disabled>Cancel</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($is_admin_user): ?>
    <div id="editBookingModal" class="booking-modal hidden">
        <div class="modal-backdrop" onclick="closeEditBookingModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Booking</h3>
                <button class="modal-close" onclick="closeEditBookingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="booking-summary">
                    <p><strong>Facility:</strong> <span id="editBookingFacilityName">-</span></p>
                </div>

                <label for="edit-booking-date">Date</label>
                <input type="date" id="edit-booking-date" min="<?php echo e($today_iso); ?>" max="<?php echo e($max_advance_iso); ?>">

                <div class="edit-booking-time-grid">
                    <div>
                        <label for="edit-booking-start">Start Time</label>
                        <input type="time" id="edit-booking-start" step="60">
                    </div>
                    <div>
                        <label for="edit-booking-end">End Time</label>
                        <input type="time" id="edit-booking-end" step="60">
                    </div>
                </div>

                <div class="admin-facility-actions">
                    <button type="button" class="admin-cancel-btn" onclick="closeEditBookingModal()">Cancel</button>
                    <button type="button" class="admin-post-btn" onclick="submitBookingEdit()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    <?php if ($is_admin_user): ?>
    <div id="adminFacilityModal" class="admin-facility-modal hidden">
        <div class="admin-facility-backdrop" onclick="closeAdminFacilityModal()"></div>
        <div class="admin-facility-content">
            <div class="admin-facility-header">
                <h3>Add New Facility</h3>
                <button type="button" class="admin-facility-close" onclick="closeAdminFacilityModal()">&times;</button>
            </div>
            <form id="adminFacilityForm" class="admin-facility-form" onsubmit="return false;">
                <div class="admin-facility-grid">
                    <label>
                        Facility Name
                        <input type="text" placeholder="e.g. Innovation Lab A" />
                    </label>
                    <label>
                        Category
                        <select>
                            <option value="classroom">Classroom</option>
                            <option value="meeting_room">Meeting Room</option>
                            <option value="sport_facility">Sport Facility</option>
                        </select>
                    </label>
                    <label>
                        Location
                        <input type="text" placeholder="e.g. Block B, Level 2" />
                    </label>
                    <label>
                        Capacity
                        <input type="number" min="1" placeholder="e.g. 30" />
                    </label>
                </div>
                <label class="admin-facility-full-width">
                    Notes
                    <textarea rows="3" placeholder="Optional notes for this facility."></textarea>
                </label>
                <div class="admin-facility-checkbox-row">
                    <label class="admin-facility-checkbox">
                        <input type="checkbox" />
                        <span>Set this facility as unavailable for student booking</span>
                    </label>
                </div>
                <div class="admin-facility-actions">
                    <button type="button" class="admin-cancel-btn" onclick="closeAdminFacilityModal()">Cancel</button>
                    <button type="button" class="admin-post-btn">Post</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
            maxDate: '<?php echo e($max_advance_iso); ?>',
            isAdmin: <?php echo $is_admin_user ? 'true' : 'false'; ?>
        };
    </script>
    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/facility-booking.js?v=<?php echo time(); ?>"></script>
</body>
</html>
