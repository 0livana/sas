<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';

// Get exam officer details
try {
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

// Get students with pending results
$students = [];
try {
    if (isset($exam_officer) && $exam_officer) {
        $stmt = $pdo->prepare("SELECT DISTINCT s.*, l.name as level_name 
                              FROM students s 
                              JOIN levels l ON s.level_id = l.id
                              JOIN scores sc ON s.id = sc.student_id
                              WHERE s.faculty_id = ? AND s.department_id = ? AND s.level_id = ?
                              AND sc.status = 'uploaded'");
        $stmt->execute([$exam_officer['faculty_id'], $exam_officer['department_id'], $exam_officer['level_assigned']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error retrieving students with pending results: " . $e->getMessage();
}

// Count pending results for each student
$pending_counts = [];
try {
    if (isset($exam_officer) && $exam_officer && !empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $stmt = $pdo->prepare("SELECT student_id, COUNT(*) as pending_count 
                              FROM scores 
                              WHERE student_id IN ($placeholders) 
                              AND status = 'uploaded'
                              GROUP BY student_id");
        $stmt->execute($student_ids);
        $pending_counts_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pending_counts_result as $row) {
            $pending_counts[$row['student_id']] = $row['pending_count'];
        }
    }
} catch (Exception $e) {
    $error = "Error counting pending results: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Students with Pending Results</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($exam_officer) && $exam_officer): ?>
    <div class="card mb-4">
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
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Students with Pending Results (<?php echo count($students); ?> students)</h5>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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
                            <th>Pending Results</th>
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
                                <span class="badge bg-warning">
                                    <?php echo $pending_counts[$student['id']] ?? 0; ?> pending
                                </span>
                            </td>
                            <td>
                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye me-1"></i>Review Results
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No students with pending results.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger">Exam officer information not available. Please contact administration.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>