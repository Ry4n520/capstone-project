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

$user_role = strtolower((string) ($_SESSION['role'] ?? 'student'));
$is_admin = ($user_role === 'admin');
$filter = $_GET['filter'] ?? 'all'; // all, today, week, month

try {
    $role_stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1');
    $role_stmt->execute([':role_name' => $user_role]);
    $role_id = (int) ($role_stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'Unable to resolve role context.'
    ]);
    exit;
}

if (!$is_admin && $role_id <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Role unavailable',
        'message' => 'Role mapping not found.'
    ]);
    exit;
}

// Build date filter
$date_condition = '';
switch ($filter) {
    case 'today':
        $date_condition = "AND DATE(a.created_date) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND a.created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND a.created_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    default:
        $date_condition = ""; // All announcements
}

$visibility_condition = $is_admin
    ? '1=1'
    : '(a.target_role_id IS NULL OR a.target_role_id = :role_id)';

$query = "
    SELECT 
        a.announcement_id,
        a.title,
        a.content,
        a.created_date,
        u.name as created_by,
        tr.role_name as target_role_name,
        a.target_role_id,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, a.created_date, NOW()) < 1 
                THEN CONCAT(TIMESTAMPDIFF(MINUTE, a.created_date, NOW()), ' minutes ago')
            WHEN TIMESTAMPDIFF(HOUR, a.created_date, NOW()) < 24 
                THEN CONCAT(TIMESTAMPDIFF(HOUR, a.created_date, NOW()), ' hours ago')
            WHEN TIMESTAMPDIFF(DAY, a.created_date, NOW()) = 1 
                THEN 'Yesterday'
            WHEN TIMESTAMPDIFF(DAY, a.created_date, NOW()) < 7 
                THEN CONCAT(TIMESTAMPDIFF(DAY, a.created_date, NOW()), ' days ago')
            ELSE DATE_FORMAT(a.created_date, '%b %d, %Y')
        END as time_ago,
        DATE_FORMAT(a.created_date, '%W, %M %d, %Y at %h:%i %p') as formatted_date
    FROM announcements a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN roles tr ON a.target_role_id = tr.role_id
    WHERE $visibility_condition
    $date_condition
    ORDER BY a.created_date DESC
";

$stmt = $pdo->prepare($query);
$params = [];
if (!$is_admin) {
    $params[':role_id'] = $role_id;
}

$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'announcements' => $announcements,
    'count' => count($announcements)
]);
?>
