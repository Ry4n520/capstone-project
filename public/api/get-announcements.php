<?php
/**
 * Announcements API - For notifications dropdown
 * 
 * Returns recent announcements (last 30 days) filtered by user's role
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

$role_id = $_SESSION['role_id'] ?? 2;

try {
    // Get recent announcements (last 30 days)
    $query = "
        SELECT 
            announcement_id,
            title,
            content,
            created_date,
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
        WHERE (target_role_id IS NULL OR target_role_id = :role_id)
        AND created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_date DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':role_id' => $role_id]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count announcements from last 24 hours as "new"
    $new_count = 0;
    foreach ($announcements as $ann) {
        $hours_ago = (time() - strtotime($ann['created_date'])) / 3600;
        if ($hours_ago < 24) {
            $new_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
        'new_count' => $new_count
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
