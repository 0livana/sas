<?php
session_start();
require_once '../config/database.php';
require_once 'functions.php';

// Check if user is logged in and redirect if trying to access protected pages
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'index.php' && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: ../index.php');
    exit();
}

// Determine dashboard URL based on user type
$dashboard_url = '';
if (isset($_SESSION['user_type'])) {
    $dashboard_url = $_SESSION['user_type'] . '/dashboard.php';
}

// Store current page URL for back button functionality
$current_url = $_SERVER['REQUEST_URI'];
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Store the previous page in session if it's different from current page
if (!isset($_SESSION['previous_pages'])) {
    $_SESSION['previous_pages'] = [];
}

// Add current page to history if it's different from the last one
$last_page = end($_SESSION['previous_pages']);
if ($last_page !== $current_url) {
    $_SESSION['previous_pages'][] = $current_url;
    
    // Keep only the last 10 pages in history
    if (count($_SESSION['previous_pages']) > 10) {
        array_shift($_SESSION['previous_pages']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOU - Student Assessment Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .navbar-brand {
            display: flex;
            align-items: center;
        }
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        .nav-button {
            display: flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .nav-button:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .nav-button i {
            margin-right: 0.3rem;
        }
        @media (max-width: 991.98px) {
            .nav-buttons {
                margin-top: 10px;
                gap: 5px;
            }
            .nav-button {
                padding: 0.3rem 0.6rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../images/logo.png" alt="DOU Logo" height="40" class="d-inline-block align-text-top me-2">
                DOU - Student Assessment Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                        </li>
                        <div class="nav-buttons">
                            <?php if (!empty($dashboard_url)): ?>
                                <a class="nav-link nav-button text-white" href="../<?php echo $dashboard_url; ?>">
                                    <i class="fas fa-tachometer-alt"></i>Dashboard
                                </a>
                            <?php endif; ?>
                            <a class="nav-link nav-button text-white" href="#" onclick="goBack(event)">
                                <i class="fas fa-arrow-left"></i>Back
                            </a>
                            <a class="nav-link nav-button text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid main-container">
    
    <script>
    function goBack(event) {
        event.preventDefault();
        
        // Get the page history from session
        const pageHistory = <?php echo json_encode($_SESSION['previous_pages'] ?? []); ?>;
        
        if (pageHistory.length > 1) {
            // Get the previous page (second to last in the array)
            const previousPage = pageHistory[pageHistory.length - 2];
            
            // Remove the current page from history
            pageHistory.pop();
            
            // Update session via AJAX
            fetch('../includes/update_history.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({history: pageHistory})
            }).then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = previousPage;
                } else {
                    fallbackRedirect();
                }
            }).catch(error => {
                console.error('Error updating history:', error);
                fallbackRedirect();
            });
        } else {
            fallbackRedirect();
        }
    }
    
    function fallbackRedirect() {
        // If no history or error, redirect to dashboard
        <?php if (!empty($dashboard_url)): ?>
            window.location.href = '../<?php echo $dashboard_url; ?>';
        <?php else: ?>
            window.location.href = '../index.php';
        <?php endif; ?>
    }
    
    // Alternative method using browser history
    function goBackAlternative() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            fallbackRedirect();
        }
    }
    </script>

<!-- Add this script for session timeout handling -->
<script>
// Set timeout variables
var timeoutInterval = 5 * 60 * 1000; // 5 minutes in milliseconds
var warningTimeout = 4.5 * 60 * 1000; // 4.5 minutes (30 seconds before timeout)
var logoutUrl = '../logout.php';
var warningTimer;
var timeoutTimer;

function startTimers() {
    // Clear existing timers
    clearTimeout(warningTimer);
    clearTimeout(timeoutTimer);
    
    // Set warning timer
    warningTimer = setTimeout(showTimeoutWarning, warningTimeout);
    
    // Set logout timer
    timeoutTimer = setTimeout(performLogout, timeoutInterval);
}

function resetTimers() {
    clearTimeout(warningTimer);
    clearTimeout(timeoutTimer);
    startTimers();
}

function showTimeoutWarning() {
    // Create warning modal if it doesn't exist
    if (!document.getElementById('timeout-warning')) {
        const modal = document.createElement('div');
        modal.id = 'timeout-warning';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Session Expiring</h5>
                    </div>
                    <div class="modal-body">
                        <p>Your session will expire in <span id="countdown">30</span> seconds due to inactivity.</p>
                        <p>Would you like to continue your session?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="logout-now">Log Out Now</button>
                        <button type="button" class="btn btn-primary" id="continue-session">Continue Session</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add event listeners
        document.getElementById('continue-session').addEventListener('click', function() {
            $('#timeout-warning').modal('hide');
            resetTimers();
            // Send keep-alive request to server
            fetch('../includes/keepalive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        console.log('Session extended');
                    }
                });
        });
        
        document.getElementById('logout-now').addEventListener('click', function() {
            performLogout();
        });
    }
    
    // Show the modal
    $('#timeout-warning').modal('show');
    
    // Start countdown
    let seconds = 30;
    const countdownEl = document.getElementById('countdown');
    const countdownInterval = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(countdownInterval);
            $('#timeout-warning').modal('hide');
            performLogout();
        }
    }, 1000);
}

function performLogout() {
    window.location.href = logoutUrl;
}

// Reset timers on user activity
document.addEventListener('mousemove', resetTimers);
document.addEventListener('keypress', resetTimers);
document.addEventListener('click', resetTimers);
document.addEventListener('scroll', resetTimers);
document.addEventListener('touchstart', resetTimers);

// Start timers when page loads
document.addEventListener('DOMContentLoaded', startTimers);
</script>

<?php
// Rest of your header code
?>