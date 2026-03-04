<?php
/**
 * Smart Campus Management System - Facility Booking
 * 
 * Allows users to book campus facilities
 */

// Include session verification and authentication
include 'includes/check_session.php';

// Get current date and time
date_default_timezone_set('Asia/Kuala_Lumpur');
$current_datetime = new DateTime();
$formatted_date = $current_datetime->format('l, F d, Y');
$formatted_time = $current_datetime->format('g:i A');

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
                    <div class="category-count">15 rooms available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- Meeting Rooms -->
                <div class="category-card meeting-accent" onclick="showFacilities('meeting')">
                    <div class="category-icon">👥</div>
                    <div class="category-name">Meeting Rooms</div>
                    <div class="category-count">8 rooms available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- Sport Facilities -->
                <div class="category-card sport-accent" onclick="showFacilities('sport')">
                    <div class="category-icon">⚽</div>
                    <div class="category-name">Sport Facilities</div>
                    <div class="category-count">6 facilities available</div>
                    <span class="category-badge">Browse Now</span>
                </div>

                <!-- View Bookings -->
                <div class="category-card booking-accent" onclick="showMyBookings()">
                    <div class="category-icon">📅</div>
                    <div class="category-name">View My Bookings</div>
                    <div class="category-count">3 active bookings</div>
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

            <div class="facility-grid">
                <!-- Facility Card 1 -->
                <div class="facility-card">
                    <div class="facility-header">
                        <div class="facility-name">Basketball Court A</div>
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="facility-info">
                        <span>👥 Max 20 people</span>
                        <span>📍 Sports Complex</span>
                    </div>
                    <div class="time-slots">
                        <div class="time-slots-label">Available Slots Today:</div>
                        <div class="slots">
                            <span class="time-slot booked">9:00 AM</span>
                            <span class="time-slot">11:00 AM</span>
                            <span class="time-slot">2:00 PM</span>
                            <span class="time-slot">4:00 PM</span>
                            <span class="time-slot">6:00 PM</span>
                        </div>
                    </div>
                    <button class="book-btn">Book Now</button>
                </div>

                <!-- Facility Card 2 -->
                <div class="facility-card">
                    <div class="facility-header">
                        <div class="facility-name">Tennis Court</div>
                        <span class="status-badge status-available">Available</span>
                    </div>
                    <div class="facility-info">
                        <span>👥 Max 4 people</span>
                        <span>📍 Sports Complex</span>
                    </div>
                    <div class="time-slots">
                        <div class="time-slots-label">Available Slots Today:</div>
                        <div class="slots">
                            <span class="time-slot">8:00 AM</span>
                            <span class="time-slot">10:00 AM</span>
                            <span class="time-slot booked">12:00 PM</span>
                            <span class="time-slot">3:00 PM</span>
                            <span class="time-slot">5:00 PM</span>
                        </div>
                    </div>
                    <button class="book-btn">Book Now</button>
                </div>

                <!-- Facility Card 3 -->
                <div class="facility-card">
                    <div class="facility-header">
                        <div class="facility-name">Badminton Hall</div>
                        <span class="status-badge status-occupied">Occupied</span>
                    </div>
                    <div class="facility-info">
                        <span>👥 Max 16 people</span>
                        <span>📍 Sports Complex</span>
                    </div>
                    <div class="time-slots">
                        <div class="time-slots-label">Available Slots Today:</div>
                        <div class="slots">
                            <span class="time-slot booked">9:00 AM</span>
                            <span class="time-slot booked">11:00 AM</span>
                            <span class="time-slot booked">2:00 PM</span>
                            <span class="time-slot">4:00 PM</span>
                            <span class="time-slot">6:00 PM</span>
                        </div>
                    </div>
                    <button class="book-btn">Book Now</button>
                </div>
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
                <button class="filter-tab active">Upcoming</button>
                <button class="filter-tab">Past</button>
                <button class="filter-tab">All</button>
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
                    <tr>
                        <td>Computer Lab A</td>
                        <td>March 5, 2026</td>
                        <td>3:00 PM - 5:00 PM</td>
                        <td><span class="status-badge status-available">Confirmed</span></td>
                        <td><button class="action-btn btn-cancel">Cancel</button></td>
                    </tr>
                    <tr>
                        <td>Meeting Room 3</td>
                        <td>March 7, 2026</td>
                        <td>10:00 AM - 12:00 PM</td>
                        <td><span class="category-badge pending-badge">Pending</span></td>
                        <td><button class="action-btn btn-cancel">Cancel</button></td>
                    </tr>
                    <tr>
                        <td>Basketball Court A</td>
                        <td>March 10, 2026</td>
                        <td>2:00 PM - 4:00 PM</td>
                        <td><span class="status-badge status-available">Confirmed</span></td>
                        <td><button class="action-btn btn-cancel">Cancel</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/header.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/homepage.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/facility-booking.js?v=<?php echo time(); ?>"></script>
</body>
</html>
