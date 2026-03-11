<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if ($current_password === '' || $new_password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get user's current password
$query = "SELECT password_hash FROM users WHERE user_id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// For now, passwords are stored as plain text (password123)
// In production, you'd use password_hash() and password_verify()
if ($user['password_hash'] !== $current_password) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// Update password
$update_query = "UPDATE users SET password_hash = :new_password WHERE user_id = :user_id";
$stmt = $pdo->prepare($update_query);
$stmt->execute([
    ':new_password' => $new_password,
    ':user_id' => $_SESSION['user_id']
]);

echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
?>
