<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['faculty_id'])) {
    $faculty_id = $_GET['faculty_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE faculty_id = ? ORDER BY name");
    $stmt->execute([$faculty_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departments);
} else {
    echo json_encode([]);
}
?>