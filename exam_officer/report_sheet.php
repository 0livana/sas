<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

date_default_timezone_set('Africa/Lagos');

// Get exam officer details
$stmt = $pdo->prepare("SELECT eo.* FROM exam_officers eo WHERE eo.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student ID from URL
$student_id = $_GET['student_id'] ?? 0;

// Get student details
$stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name 
                      FROM students s 
                      JOIN faculties f ON s.faculty_id = f.id 
                      JOIN departments d ON s.department_id = d.id 
                      JOIN levels l ON s.level_id = l.id 
                      WHERE s.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get semesters and sessions
$semesters = getSemesters();
$sessions = getSessions();

// Get semester and session from query parameters or use defaults
$semester_id = $_GET['semester_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

// Get semester and session names
$semester_name = 'First Semester'; // Default
$session_name = date('Y'); // Default current year

if ($semester_id) {
    $stmt = $pdo->prepare("SELECT name FROM semesters WHERE id = ?");
    $stmt->execute([$semester_id]);
    $semester_name = $stmt->fetchColumn();
}

if ($session_id) {
    $stmt = $pdo->prepare("SELECT name FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session_name = $stmt->fetchColumn();
}

// Get student scores
$scores = [];
$gpa = 0;
$total_units = 0;
if ($student) {
    $stmt = $pdo->prepare("SELECT s.*, c.course_code, c.course_title, c.unit 
                          FROM scores s 
                          JOIN courses c ON s.course_id = c.id 
                          WHERE s.student_id = ? AND s.semester = ? AND s.session = ? AND s.status = 'accepted'");
    $stmt->execute([$student_id, $semester_name, $session_name]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate GPA
    $total_points = 0;
    
    foreach ($scores as $score) {
        $grade = calculateGrade($score['total']);
        $grade_point = 0;
        
        switch ($grade) {
            case 'A': $grade_point = 5; break;
            case 'B': $grade_point = 4; break;
            case 'C': $grade_point = 3; break;
            case 'D': $grade_point = 2; break;
            case 'E': $grade_point = 1; break;
            case 'F': $grade_point = 0; break;
        }
        
        $total_units += $score['unit'];
        $total_points += ($grade_point * $score['unit']);
    }
    
    $gpa = $total_units > 0 ? round($total_points / $total_units, 2) : 0;
}
?>

<div class="container mt-5">
    <?php if (!$student): ?>
        <div class="alert alert-danger">Student not found!</div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>View Options</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="semester_id" class="form-label">Semester</label>
                                <select class="form-select" id="semester_id" name="semester_id" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>" <?php echo $semester_id == $semester['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($semester['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="session_id" class="form-label">Session</label>
                                <select class="form-select" id="session_id" name="session_id" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">View Results</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="report-sheet">
            <div class="report-header text-center mb-4">
                <img src="../images/logo.png" alt="University Logo" height="80" class="mb-3">
                <h2>Dennis Osadebay University, Asaba</h2>
                <h3>Student Report Sheet</h3>
            </div>

            <div class="report-footer">
                <p>Generated by: <?php echo $exam_officer['name']; ?> (Exam Officer)</p>
                <p>Date: <?php echo date('l, F j, Y \a\t g:i A'); ?></p>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Student Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Matric No</th>
                                    <td><?php echo $student['matric_no']; ?></td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td><?php echo $student['full_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Faculty</th>
                                    <td><?php echo $student['faculty_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Department</th>
                                    <td><?php echo $student['department_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Level</th>
                                    <td><?php echo $student['level_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Semester</th>
                                    <td><?php echo $semester_name; ?></td>
                                </tr>
                                <tr>
                                    <th>Session</th>
                                    <td><?php echo $session_name; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Academic Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Total Units</th>
                                    <td><?php echo $total_units; ?></td>
                                </tr>
                                <tr>
                                    <th>GPA</th>
                                    <td><?php echo $gpa; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Course Results</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($scores)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Unit</th>
                                        <th>Test</th>
                                        <th>Assignment</th>
                                        <th>Attendance</th>
                                        <th>CA Total</th>
                                        <th>Exam</th>
                                        <th>Total</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scores as $score): 
                                        $ca_total = $score['test'] + $score['assignment'] + $score['attendance'];
                                        $total = $ca_total + $score['exam'];
                                        $grade = calculateGrade($total);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($score['course_code']); ?></td>
                                        <td><?php echo $score['unit']; ?></td>
                                        <td><?php echo $score['test']; ?></td>
                                        <td><?php echo $score['assignment']; ?></td>
                                        <td><?php echo $score['attendance']; ?></td>
                                        <td><?php echo $ca_total; ?></td>
                                        <td><?php echo $score['exam']; ?></td>
                                        <td><?php echo $total; ?></td>
                                        <td><strong><?php echo $grade; ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No results found for the selected semester and session.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- HOD Validation Notice -->
            <div class="alert alert-warning text-center mb-4">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Result Not Valid until stamped and signed by HOD</h5>
            </div>
            
            <div class="d-flex justify-content-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print me-1"></i>Print Report
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6 !important;
    }
    .report-header {
        margin-bottom: 20px;
    }
    .report-footer {
        margin-top: 30px;
        text-align: center;
        font-size: 14px;
    }
    .alert-warning {
        background-color: #fff3cd !important;
        border: 1px solid #ffeaa7 !important;
        color: #856404 !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>