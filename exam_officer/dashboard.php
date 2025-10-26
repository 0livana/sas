<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';

// Get exam officer details with error handling
try {
    // FIXED: Added join to levels table to get level name
    $stmt = $pdo->prepare("SELECT eo.*, f.name as faculty_name, d.name as department_name, l.name as level_name 
                          FROM exam_officers eo 
                          JOIN faculties f ON eo.faculty_id = f.id 
                          JOIN departments d ON eo.department_id = d.id 
                          JOIN levels l ON eo.level_assigned = l.id 
                          WHERE eo.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam_officer) {
        throw new Exception("Exam officer details not found!");
    }
} catch (Exception $e) {
    $error = "Error retrieving exam officer details: " . $e->getMessage();
}

// Get assigned students with error handling
$students = [];
try {
    if (isset($exam_officer) && $exam_officer) {
        $stmt = $pdo->prepare("SELECT s.*, l.name as level_name 
                              FROM students s 
                              JOIN levels l ON s.level_id = l.id
                              WHERE s.faculty_id = ? AND s.department_id = ? AND s.level_id = ?");
        $stmt->execute([$exam_officer['faculty_id'], $exam_officer['department_id'], $exam_officer['level_assigned']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error retrieving students: " . $e->getMessage();
}

// Count uploaded scores pending acceptance with error handling
$pending_count = 0;
try {
    if (isset($exam_officer) && $exam_officer) {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.student_id) as pending_count 
                              FROM scores s 
                              JOIN students st ON s.student_id = st.id 
                              WHERE st.faculty_id = ? AND st.department_id = ? AND st.level_id = ? 
                              AND s.status = 'uploaded'");
        $stmt->execute([$exam_officer['faculty_id'], $exam_officer['department_id'], $exam_officer['level_assigned']]);
        $pending_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $pending_count = $pending_count_result ? $pending_count_result['pending_count'] : 0;
    }
} catch (Exception $e) {
    $error = "Error counting pending results: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Exam Officer Dashboard</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($exam_officer) && $exam_officer): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Exam Officer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <?php if (!empty($exam_officer['passport_image'])): ?>
                                <img src="../uploads/passports/<?php echo $exam_officer['passport_image']; ?>" class="img-fluid rounded" alt="Passport">
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
                                    <td><?php echo $exam_officer['name']; ?></td>
                                    <th>Gender</th>
                                    <td><?php echo $exam_officer['gender']; ?></td>
                                </tr>
                                <tr>
                                    <th>Faculty</th>
                                    <td><?php echo $exam_officer['faculty_name']; ?></td>
                                    <th>Department</th>
                                    <td><?php echo $exam_officer['department_name']; ?></td>
                                </tr>
                                <tr>
                                    <!-- FIXED: Display level_name instead of level_assigned -->
                                    <th>Level Assigned</th>
                                    <td><?php echo $exam_officer['level_name']; ?></td>
                                    <th>Email</th>
                                    <td><?php echo $exam_officer['email']; ?></td>
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
                    <h5 class="card-title">Assigned Students</h5>
                    <h3 class="text-primary"><?php echo count($students); ?></h3>
                    <p class="card-text">Students in your care</p>
                    <a href="export_students.php" class="btn btn-primary">Export Students List</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Pending Results</h5>
                    <h3 class="text-warning"><?php echo $pending_count; ?></h3>
                    <p class="card-text">Results awaiting acceptance</p>
                    <a href="pending_results.php" class="btn btn-warning">View Pending Results</a>
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
    
    <div class="card mt-4" id="students-list">
        <div class="card-header">
            <h5>Assigned Students</h5>
        </div>
        <div class="card-body">
            <?php if (count($students) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Matric No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['matric_no']); ?></td>
                            <td>
                                <?php if (!empty($student['passport_image'])): ?>
                                    <img src="../uploads/passports/<?php echo $student['passport_image']; ?>" class="rounded-circle me-2" width="30" height="30" alt="Passport">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                            <td>
                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Results
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No students assigned to you yet.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger">Exam officer information not available. Please contact administration.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>