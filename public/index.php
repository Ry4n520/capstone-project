<?php
require_once 'config/db.php';

$inputUser = 'testuser';
$inputPass = 'password123'; // The plain text password you're testing

try {
    // We search by username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$inputUser]);
    $user = $stmt->fetch();

    if ($user) {
        // Simple plain text comparison
        if ($inputPass === $user['password']) {
            echo "✅ Login Success! Welcome, " . $user['username'];
            echo "<br>Role: " . $user['role'];
        } else {
            echo "❌ Login Failed: Incorrect password.";
        }
    } else {
        echo "❌ Login Failed: User not found.";
    }
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>