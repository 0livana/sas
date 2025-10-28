<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $_SESSION['user_type'] . '/dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT u.*, 
                              COALESCE(eo.name, l.name, s.full_name, 'Admin') as name 
                              FROM users u 
                              LEFT JOIN exam_officers eo ON u.id = eo.user_id AND u.user_type = 'exam_officer'
                              LEFT JOIN lecturers l ON u.id = l.user_id AND u.user_type = 'lecturer'
                              LEFT JOIN students s ON u.id = s.user_id AND u.user_type = 'student'
                              WHERE u.username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['name'] = $user['name'];
            
            header('Location: ' . $user['user_type'] . '/dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please enter both username and password';
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
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: url('images/bgg.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            margin: 0 auto;
        }
        .form-control {
            padding: 12px;
            border-radius: 5px;
        }
        .btn {
            padding: 12px;
            border-radius: 5px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Custom Header for Login Page -->
    <nav class="navbar navbar-expand-lg navbar-dark  fixed-top" style="background-color: rgba(135, 55, 178, 0.98);">
        <div class="container">
            <a class="navbar-brand" href="">
                <img src="images/logo.png" alt="DOU Logo" height="40" class="d-inline-block align-text-top me-2">
                Dennis Osadebay University, Asaba. 
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                         <a href="https://www.dou.edu.ng/" class="nav-link">DOU_PAGE</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://myportal.dou.edu.ng/">STUDENT_PORTAL</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container text-center">
                    <img src="images/logo.png" alt="DOU Logo" height="80" class="mb-4">
                    <h2 class="mb-3">Welcome To</h2>
                    <h4 class="mb-4 text-primary">Student Assessment Portal</h4>
                    <p class="text-center text-muted mb-4"><em>Access your academic records, manage student scores, and monitor progress with ease.</em></p>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php
                    // Add this where you display login messages
                    if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
                        echo '<div class="alert alert-warning">Your session has expired due to inactivity. Please log in again.</div>';
                    }
                    ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Login (Email for Staff, Matric_No for Student)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="username" placeholder="Username" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn  w-100" style="background-color: rgba(166, 80, 213, 0.98); color:white;">Login</button>
                    </form>
                    
                    
                    <p class="text-muted">Login Problem? visit admin office with your ID</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Footer for Login Page -->
    <footer class="footer mt-auto py-3 bg-light fixed-bottom">
        <div class="container">
            <span class="text-muted">&copy; <?php echo date('Y'); ?> DOU - Student Assessment Portal v.2. All rights reserved (CSC223 Group 16).</span>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>