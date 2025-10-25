<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user has access to this specific section
$allowed_types = [];
$user_type = $_SESSION['user_type'];

// Define allowed user types for each section
$section = basename(dirname($_SERVER['PHP_SELF']));

switch ($section) {
    case 'admin':
        $allowed_types = ['admin'];
        break;
    case 'lecturer':
        $allowed_types = ['lecturer'];
        break;
    case 'exam_officer':
        $allowed_types = ['exam_officer'];
        break;
    case 'student':
        $allowed_types = ['student'];
        break;
    default:
        $allowed_types = ['admin', 'exam_officer', 'lecturer', 'student'];
}

if (!in_array($user_type, $allowed_types)) {
    header('Location: ../unauthorized.php');
    exit();
}
?>


<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set session timeout to 5 minutes (300 seconds)
$session_timeout = 300;

// Check if session is expired
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout)) {
    // Last request was more than 5 minutes ago
    session_unset();     // Unset $_SESSION variable
    session_destroy();   // Destroy session data
    header('Location: ../login.php?timeout=1');
    exit;
}

// Update last activity time stamp
$_SESSION['LAST_ACTIVITY'] = time();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Regenerate session ID periodically to prevent session fixation attacks
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 1800) {
    // Change every 30 minutes
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}
?>