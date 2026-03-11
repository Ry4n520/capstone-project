<?php
/**
 * Notifications API - Aggregates from existing tables
 * 
 * Combines announcements, bookings, and upcoming classes
 * No separate notifications table needed - uses existing data
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

require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$user_id = $_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? 'student');

$notifications = [];
$errors = [];

try {
    // 1. RECENT ANNOUNCEMENTS (from announcements table)
    try {
        $announcements_query = "
            SELECT 
                announcement_id as id,
                'announcement' as type,
                title,
                CONCAT('New announcement: ', LEFT(content, 50), '...') as message,
                created_date as created_at,
                CASE 
                    WHEN TIMESTAMPDIFF(MINUTE, created_date, NOW()) < 60 
                        THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_date, NOW()), ' min ago')
                    WHEN TIMESTAMPDIFF(HOUR, created_date, NOW()) < 24 
                        THEN CONCAT(TIMESTAMPDIFF(HOUR, created_date, NOW()), 'h ago')
                    WHEN TIMESTAMPDIFF(DAY, created_date, NOW()) < 7 
                        THEN CONCAT(TIMESTAMPDIFF(DAY, created_date, NOW()), 'd ago')
                    ELSE DATE_FORMAT(created_date, '%b %d')
                END as time_ago
            FROM announcements
            WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_date DESC
            LIMIT 5
        ";

        $stmt = $pdo->prepare($announcements_query);
        $stmt->execute();
        $announcement_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $notifications = array_merge($notifications, $announcement_notifications);
        error_log("Announcements found: " . count($announcement_notifications));

    } catch (Exception $e) {
        error_log("Announcements query error: " . $e->getMessage());
        $errors[] = "Announcements: " . $e->getMessage();
    }

} catch (Exception $e) {
    error_log("Announcements outer error: " . $e->getMessage());
}

try {
    // 2. RECENT BOOKINGS (last 7 days)
    try {
        $bookings_query = "
            SELECT 
                b.booking_id as id,
                CASE 
                    WHEN b.booking_status = 'confirmed' THEN 'booking_confirmed'
                    WHEN b.booking_status = 'cancelled' THEN 'booking_cancelled'
                    ELSE 'booking_pending'
                END as type,
                CASE 
                    WHEN b.booking_status = 'confirmed' THEN 'Booking Confirmed'
                    WHEN b.booking_status = 'cancelled' THEN 'Booking Cancelled'
                    ELSE 'Booking Pending'
                END as title,
                CONCAT(f.facility_name, ' on ', DATE_FORMAT(b.booking_date, '%b %d'), 
                       ' at ', TIME_FORMAT(b.start_time, '%h:%i %p')) as message,
                b.booking_date as created_at,
                CASE 
                    WHEN TIMESTAMPDIFF(HOUR, b.booking_date, NOW()) < 24 
                        THEN CONCAT(TIMESTAMPDIFF(HOUR, b.booking_date, NOW()), 'h ago')
                    WHEN TIMESTAMPDIFF(DAY, b.booking_date, NOW()) < 7 
                        THEN CONCAT(TIMESTAMPDIFF(DAY, b.booking_date, NOW()), 'd ago')
                    ELSE DATE_FORMAT(b.booking_date, '%b %d')
                END as time_ago
            FROM bookings b
            JOIN facilities f ON b.facility_id = f.facility_id
            WHERE b.user_id = :user_id
            AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY b.booking_date DESC
            LIMIT 5
        ";

        $stmt = $pdo->prepare($bookings_query);
        $stmt->execute([':user_id' => $user_id]);
        $booking_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $notifications = array_merge($notifications, $booking_notifications);
        error_log("Bookings found: " . count($booking_notifications));

    } catch (Exception $e) {
        error_log("Bookings query error: " . $e->getMessage());
        $errors[] = "Bookings: " . $e->getMessage();
    }

} catch (Exception $e) {
    error_log("Bookings outer error: " . $e->getMessage());
}

try {
    // 3. UPCOMING CLASSES TODAY (for students) - DISABLED FOR NOW
    // This query has parameter binding issues that need to be debugged
    // For now, we'll skip class notifications and focus on announcements and bookings
    
    /*
    if ($role === 'student') {
        try {
            // Complex query disabled due to parameter binding errors
        } catch (Exception $e) {
            error_log("Class notifications query error: " . $e->getMessage());
            $errors[] = "Class notifications: " . $e->getMessage();
        }
    }
    */
} catch (Exception $e) {
    error_log("Class notifications outer error: " . $e->getMessage());
}

// Sort by created_at descending
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to 10 most recent
$notifications = array_slice($notifications, 0, 10);

// Count "unread" - from last 24 hours
$unread_count = 0;
foreach ($notifications as $notif) {
    $hours_ago = (time() - strtotime($notif['created_at'])) / 3600;
    if ($hours_ago < 24) {
        $unread_count++;
    }
}

error_log("Total notifications: " . count($notifications) . ", Unread: " . $unread_count);

http_response_code(200);
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count,
    'debug_errors' => $errors
], JSON_PRETTY_PRINT);

?>
