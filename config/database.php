<?php
$host = 'localhost';
$dbname = 'student_assessment_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create default admin if not exists
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
if ($stmt->fetchColumn() == 0) {
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, user_type) VALUES ('admin', '$hashedPassword', 'admin')");
}
?>
