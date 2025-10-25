<?php
session_start();

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
exit;
?>