<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get counts for dashboard
$faculties_count = $pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
$departments_count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$courses_count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$exam_officers_count = $pdo->query("SELECT COUNT(*) FROM exam_officers")->fetchColumn();
$lecturers_count = $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn();
$students_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
?>

<div class="container mt-4">
    <h2 class="text-center page-title">Admin Dashboard</h2>
    
    <div class="row mb-4">
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-school fa-2x mb-3"></i></h5>
                    <h3><?php echo $faculties_count; ?></h3>
                    <p class="card-text">Faculties</p>
                    <a href="manage_faculties.php" class="btn btn-light">Manage Faculties</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-building fa-2x mb-3"></i></h5>
                    <h3><?php echo $departments_count; ?></h3>
                    <p class="card-text">Departments</p>
                    <a href="manage_departments.php" class="btn btn-light">Manage Departments</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book fa-2x mb-3"></i></h5>
                    <h3><?php echo $courses_count; ?></h3>
                    <p class="card-text">Courses</p>
                    <a href="manage_courses.php" class="btn btn-light">Manage Courses</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-user-tie fa-2x mb-3"></i></h5>
                    <h3><?php echo $exam_officers_count; ?></h3>
                    <p class="card-text">Exam Officers</p>
                    <a href="manage_exam_officers.php" class="btn btn-dark">Manage Exam Officers</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-chalkboard-teacher fa-2x mb-3"></i></h5>
                    <h3><?php echo $lecturers_count; ?></h3>
                    <p class="card-text">Lecturers</p>
                    <a href="manage_lecturers.php" class="btn btn-light">Manage Lecturers</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-user-graduate fa-2x mb-3"></i></h5>
                    <h3><?php echo $students_count; ?></h3>
                    <p class="card-text">Students</p>
                    <a href="manage_students.php" class="btn btn-light">Manage Students</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12 text-center">
            <a href="change_password.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-key me-2"></i>Change Password
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>