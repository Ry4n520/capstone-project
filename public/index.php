<?php
$host = 'db'; // This matches the service name in your yml file
$user = 'root';
$pass = 'root_password';
$db   = 'capstone_db';

// 1. Create Connection
$conn = new mysqli($host, $user, $pass, $db);

// 2. Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Create Table automatically if it's not there
$setup_sql = "CREATE TABLE IF NOT EXISTS test_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($setup_sql);

// 4. Handle Form Submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['username'])) {
    $name = $conn->real_escape_string($_POST['username']);
    $sql = "INSERT INTO test_users (name) VALUES ('$name')";
    
    if ($conn->query($sql) === TRUE) {
        $message = "✅ Data saved to Docker MySQL successfully!";
    } else {
        $message = "❌ Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Docker SQL Test</title></head>
<body>
    <h2>Capstone Docker Test Form</h2>
    <p><?php echo $message; ?></p>

    <form method="post">
        <input type="text" name="username" placeholder="Enter your name" required>
        <button type="submit">Save to Database</button>
    </form>

    <hr>
    <h3>Data currently in Docker:</h3>
    <ul>
        <?php
        $result = $conn->query("SELECT name FROM test_users ORDER BY id DESC");
        while($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['name']) . "</li>";
        }
        ?>
    </ul>
</body>
</html>