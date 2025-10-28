<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get student details with level name
$stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name
                      FROM students s 
                      JOIN faculties f ON s.faculty_id = f.id 
                      JOIN departments d ON s.department_id = d.id 
                      JOIN levels l ON s.level_id = l.id
                      WHERE s.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get registered courses count
$stmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM student_courses WHERE student_id = ?");
$stmt->execute([$student['id']]);
$course_count = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];

// Get results count
$stmt = $pdo->prepare("SELECT COUNT(*) as result_count FROM scores WHERE student_id = ? AND status = 'accepted'");
$stmt->execute([$student['id']]);
$result_count = $stmt->fetch(PDO::FETCH_ASSOC)['result_count'];
?>

<head>
    <style>
        body {
  padding-top: 10vh;
}
@media (max-width: 768px) {
  body {
    padding-top: 13vh;
  }
}

    </style>
</head>
<div class="container">
    <h2 class="text-center mb-4">Student Dashboard</h2>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <?php if (!empty($student['passport_image'])): ?>
                                <img src="../uploads/passports/<?php echo $student['passport_image']; ?>" class="img-fluid rounded" alt="Passport">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 150px; width: 150px;">
                                    <span>No Image</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Matric No</th>
                                    <td><?php echo $student['matric_no']; ?></td>
                                    <th>Full Name</th>
                                    <td><?php echo $student['full_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Gender</th>
                                    <td><?php echo $student['gender']; ?></td>
                                    <th>Faculty</th>
                                    <td><?php echo $student['faculty_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Department</th>
                                    <td><?php echo $student['department_name']; ?></td>
                                    <th>Level</th>
                                    <td><?php echo $student['level_name']; ?></td> <!-- Changed from 'level' to 'level_name' -->
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td colspan="3"><?php echo $student['email']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Registered Courses</h5>
                    <h3 class="text-primary"><?php echo $course_count; ?></h3>
                    <p class="card-text">Total Courses registered</p>
                    <a href="register_courses.php" class="btn btn-primary">Register Courses</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Available Results</h5>
                    <h3 class="text-success"><?php echo $result_count; ?></h3>
                    <p class="card-text">Results ready for viewing</p>
                    <a href="view_report_sheet.php" class="btn btn-success">View Results</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Login Password</h5>
                    <p class="card-text">Always use Strong Password</p>
                    <a href="change_password.php" class="btn btn-warning">Change Password</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>