<?php
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
$filter = $_GET['filter'] ?? 'all'; // all, today, week, month

// Build date filter
$date_condition = '';
switch ($filter) {
    case 'today':
        $date_condition = "AND DATE(created_date) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    default:
        $date_condition = ""; // All announcements
}

$query = "
    SELECT 
        announcement_id,
        title,
        content,
        created_date,
        u.name as created_by,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, created_date, NOW()) < 1 
                THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_date, NOW()), ' minutes ago')
            WHEN TIMESTAMPDIFF(HOUR, created_date, NOW()) < 24 
                THEN CONCAT(TIMESTAMPDIFF(HOUR, created_date, NOW()), ' hours ago')
            WHEN TIMESTAMPDIFF(DAY, created_date, NOW()) = 1 
                THEN 'Yesterday'
            WHEN TIMESTAMPDIFF(DAY, created_date, NOW()) < 7 
                THEN CONCAT(TIMESTAMPDIFF(DAY, created_date, NOW()), ' days ago')
            ELSE DATE_FORMAT(created_date, '%b %d, %Y')
        END as time_ago,
        DATE_FORMAT(created_date, '%W, %M %d, %Y at %h:%i %p') as formatted_date
    FROM announcements a
    JOIN users u ON a.user_id = u.user_id
    WHERE (target_role_id IS NULL OR target_role_id = :role_id)
    $date_condition
    ORDER BY created_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([':role_id' => $role_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'announcements' => $announcements,
    'count' => count($announcements)
]);
?>
