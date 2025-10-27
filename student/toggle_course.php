<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Start session
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// Handle course selection/deselection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Initialize selected courses in session if not exists
    if (!isset($_SESSION['selected_courses'])) {
        $_SESSION['selected_courses'] = [];
    }
    
    if ($_POST['action'] == 'add_course' && isset($_POST['course_id'])) {
        $course_id = $_POST['course_id'];
        
        // Add course to selection
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($course) {
            $_SESSION['selected_courses'][$course_id] = $course;
            echo json_encode(['status' => 'success', 'message' => 'Course added to selection']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Course not found']);
        }
    } 
    elseif ($_POST['action'] == 'remove_course' && isset($_POST['course_id'])) {
        $course_id = $_POST['course_id'];
        
        // Remove course from selection
        if (isset($_SESSION['selected_courses'][$course_id])) {
            unset($_SESSION['selected_courses'][$course_id]);
            echo json_encode(['status' => 'success', 'message' => 'Course removed from selection']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Course not found in selection']);
        }
    } 
    elseif ($_POST['action'] == 'clear_selection') {
        // Clear all selected courses
        $_SESSION['selected_courses'] = [];
        echo json_encode(['status' => 'success', 'message' => 'All courses removed from selection']);
    } 
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>