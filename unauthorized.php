<?php
session_start();
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .error-container {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="card shadow">
                <div class="card-body p-5">
                    <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                    <h1 class="h3 mb-3">Unauthorized Access</h1>
                    <p class="mb-4">You don't have permission to access this page.</p>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $_SESSION['user_type']; ?>/dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Return to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>