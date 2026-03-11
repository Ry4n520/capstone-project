<?php
/**
 * Check Holiday API
 * 
 * Checks if a given date is a public holiday
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$date = isset($_GET['date']) ? trim($_GET['date']) : '';

if (!$date) {
    http_response_code(400);
    echo json_encode([
        'is_holiday' => false,
        'error' => 'Date parameter required'
    ]);
    exit;
}

// Validate date format
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode([
        'is_holiday' => false,
        'error' => 'Invalid date format. Use Y-m-d format.'
    ]);
    exit;
}

try {
    $query = "SELECT holiday_name, description FROM public_holidays WHERE holiday_date = :date";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':date' => $date]);
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($holiday) {
        http_response_code(200);
        echo json_encode([
            'is_holiday' => true,
            'holiday_name' => $holiday['holiday_name'],
            'description' => $holiday['description'] ?? ''
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'is_holiday' => false
        ]);
    }
} catch (Exception $e) {
    error_log("Check Holiday Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'is_holiday' => false,
        'error' => 'Server error occurred'
    ]);
}
?>
