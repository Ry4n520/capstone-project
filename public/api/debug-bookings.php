<?php
/**
 * Debug API - Check Database Bookings
 * 
 * Shows all bookings for the current user
 * Helps diagnose why bookings aren't showing
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

$user_id = $_SESSION['user_id'];

try {
    // Get ALL bookings for this user to see what's in database
    $all_query = "
        SELECT 
            b.booking_id,
            b.user_id,
            b.facility_id,
            f.facility_name,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.booking_status,
            b.created_at,
            DATEDIFF(b.booking_date, CURDATE()) as days_from_today,
            TIME_FORMAT(b.start_time, '%H:%i:%s') as start_time_formatted,
            TIME_FORMAT(CURTIME(), '%H:%i:%s') as current_time_db
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.facility_id
        WHERE b.user_id = :user_id
        ORDER BY b.booking_date DESC
    ";
    
    $stmt = $pdo->prepare($all_query);
    $stmt->execute([':user_id' => $user_id]);
    $user_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count by status
    $stats_query = "
        SELECT 
            booking_status,
            COUNT(*) as count
        FROM bookings
        WHERE user_id = :user_id
        GROUP BY booking_status
    ";
    
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([':user_id' => $user_id]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System info
    $system_query = "SELECT CURDATE() as today, CURTIME() as current_time";
    $stmt = $pdo->prepare($system_query);
    $stmt->execute();
    $system_time = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'system_time' => $system_time,
        'booking_stats' => $stats,
        'user_bookings' => $user_bookings,
        'total_bookings' => count($user_bookings),
        'debug_note' => 'Check days_from_today and current_time_db to see if query filters are working'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'user_id' => $user_id
    ], JSON_PRETTY_PRINT);
}

?>
