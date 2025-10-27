<?php
require_once 'db.php';

header('Content-Type: application/json');

if (isset($_GET['faculty_id'])) {
    $faculty_id = $_GET['faculty_id'];
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code");
    $stmt->execute([$faculty_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($courses);
} else {
    echo json_encode([]);
}