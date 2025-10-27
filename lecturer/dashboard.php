<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get lecturer details
$stmt = $pdo->prepare("SELECT l.*, f.name as faculty_name, d.name as department_name 
                      FROM lecturers l 
                      JOIN faculties f ON l.faculty_id = f.id 
                      JOIN departments d ON l.department_id = d.id 
                      WHERE l.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get assigned courses
$stmt = $pdo->prepare("SELECT c.* FROM courses c 
                      JOIN lecturer_courses lc ON c.id = lc.course_id 
                      JOIN lecturers l ON lc.lecturer_id = l.id 
                      WHERE l.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Lecturer Dashboard</h2>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Lecturer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <?php if (!empty($lecturer['passport_image'])): ?>
                                <img src="../uploads/passports/<?php echo $lecturer['passport_image']; ?>" class="img-fluid rounded" alt="Passport">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 150px; width: 150px;">
                                    <span>No Image</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Name</th>
                                    <td><?php echo $lecturer['name']; ?></td>
                                    <th>Gender</th>
                                    <td><?php echo $lecturer['gender']; ?></td>
                                </tr>
                                <tr>
                                    <th>Faculty</th>
                                    <td><?php echo $lecturer['faculty_name']; ?></td>
                                    <th>Department</th>
                                    <td><?php echo $lecturer['department_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Position</th>
                                    <td><?php echo $lecturer['position']; ?></td>
                                    <th>Email</th>
                                    <td><?php echo $lecturer['email']; ?></td>
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
                    <h5 class="card-title">Upload Scores</h5>
                    <p class="card-text">Upload student scores for courses</p>
                    <a href="upload_scores.php" class="btn btn-primary">Upload Scores</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">View Uploaded Scores</h5>
                    <p class="card-text">View and export already uploaded scores</p>
                    <a href="view_uploaded_scores.php" class="btn btn-info">View Scores</a>
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
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Assigned Courses</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo $course['course_code']; ?></td>
                                <td><?php echo $course['course_title']; ?></td>
                                <td><?php echo $course['unit']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>