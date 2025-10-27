<?php
session_start();
session_unset();
session_destroy();
session_write_close();

// Redirect to login page
header('Location: index.php');
exit;
?>