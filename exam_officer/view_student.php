<?php
// Start output buffering to prevent header issues
ob_start();

require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get exam officer details
$stmt = $pdo->prepare("SELECT eo.* FROM exam_officers eo WHERE eo.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student ID from URL
$student_id = $_GET['id'] ?? 0;

// Handle score acceptance BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_score'])) {
    $score_id = $_POST['score_id'];
    $semester_id = $_POST['semester_id'] ?? '';
    $session_id = $_POST['session_id'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE scores SET status = 'accepted', accepted_by = ?, accepted_at = NOW() WHERE id = ?");
        $stmt->execute([$exam_officer['id'], $score_id]);
        
        // Build redirect URL
        $redirect_url = "view_student.php?id=$student_id&success=1";
        if ($semester_id) $redirect_url .= "&semester_id=$semester_id";
        if ($session_id) $redirect_url .= "&session_id=$session_id";
        
        // Clean output buffer and redirect
        ob_end_clean();
        header("Location: $redirect_url");
        exit();
    } catch (Exception $e) {
        // Build error redirect URL
        $redirect_url = "view_student.php?id=$student_id&error=1";
        if ($semester_id) $redirect_url .= "&semester_id=$semester_id";
        if ($session_id) $redirect_url .= "&session_id=$session_id";
        
        // Clean output buffer and redirect
        ob_end_clean();
        header("Location: $redirect_url");
        exit();
    }
}

// Handle bulk acceptance BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_all_scores'])) {
    $semester_id = $_POST['semester_id'] ?? '';
    $session_id = $_POST['session_id'] ?? '';
    
    // Get semester and session names for the query
    $semester_name = '';
    $session_name = '';
    
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
    
    try {
        if ($semester_id && $session_id) {
            // Accept all scores for the selected semester and session
            $stmt = $pdo->prepare("UPDATE scores SET status = 'accepted', accepted_by = ?, accepted_at = NOW() 
                                  WHERE student_id = ? AND status = 'uploaded' AND semester = ? AND session = ?");
            $stmt->execute([$exam_officer['id'], $student_id, $semester_name, $session_name]);
        } else {
            // Accept all scores for all semesters and sessions
            $stmt = $pdo->prepare("UPDATE scores SET status = 'accepted', accepted_by = ?, accepted_at = NOW() 
                                  WHERE student_id = ? AND status = 'uploaded'");
            $stmt->execute([$exam_officer['id'], $student_id]);
        }
        
        // Build redirect URL
        $redirect_url = "view_student.php?id=$student_id&success=2";
        if ($semester_id) $redirect_url .= "&semester_id=$semester_id";
        if ($session_id) $redirect_url .= "&session_id=$session_id";
        
        // Clean output buffer and redirect
        ob_end_clean();
        header("Location: $redirect_url");
        exit();
    } catch (Exception $e) {
        // Build error redirect URL
        $redirect_url = "view_student.php?id=$student_id&error=2";
        if ($semester_id) $redirect_url .= "&semester_id=$semester_id";
        if ($session_id) $redirect_url .= "&session_id=$session_id";
        
        // Clean output buffer and redirect
        ob_end_clean();
        header("Location: $redirect_url");
        exit();
    }
}

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

// Get semester and session from query parameters
$semester_id = $_GET['semester_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

// Get semester and session names
$semester_name = '';
$session_name = '';

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

// Get student scores with semester and session filter
$scores = [];
if ($student) {
    if ($semester_id && $session_id) {
        // Filter by selected semester and session
        $stmt = $pdo->prepare("SELECT s.*, c.course_code, c.course_title, c.unit, l.name as lecturer_name 
                              FROM scores s 
                              JOIN courses c ON s.course_id = c.id 
                              JOIN lecturers l ON s.lecturer_id = l.id 
                              WHERE s.student_id = ? AND s.semester = ? AND s.session = ? 
                              AND (s.status = 'uploaded' OR s.status = 'accepted')
                              ORDER BY s.status, c.course_code");
        $stmt->execute([$student_id, $semester_name, $session_name]);
    } else {
        // Get all scores if no filter is selected
        $stmt = $pdo->prepare("SELECT s.*, c.course_code, c.course_title, c.unit, l.name as lecturer_name 
                              FROM scores s 
                              JOIN courses c ON s.course_id = c.id 
                              JOIN lecturers l ON s.lecturer_id = l.id 
                              WHERE s.student_id = ? AND (s.status = 'uploaded' OR s.status = 'accepted')
                              ORDER BY s.status, c.course_code");
        $stmt->execute([$student_id]);
    }
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check for success or error messages
$success = $_GET['success'] ?? 0;
$error = $_GET['error'] ?? 0;

// End output buffering and send content
ob_end_flush();
?>

<div class="container mt-5">
    <?php if (!$student): ?>
        <div class="alert alert-danger">Student not found!</div>
    <?php else: ?>
        <h2 class="text-center mb-4">Student Results: <?php echo htmlspecialchars($student['full_name']); ?></h2>
        
        <?php if ($success == 1): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i>Score accepted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($success == 2): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i>All scores accepted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-1"></i>Error accepting score! Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error == 2): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-1"></i>Error accepting all scores! Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Semester and Session Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Results</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="id" value="<?php echo $student_id; ?>">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="semester_id" class="form-label">Semester</label>
                                <select class="form-select" id="semester_id" name="semester_id">
                                    <option value="">All Semesters</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>" <?php echo $semester_id == $semester['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($semester['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="session_id" class="form-label">Session</label>
                                <select class="form-select" id="session_id" name="session_id">
                                    <option value="">All Sessions</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">Filter</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
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
                                <th>Faculty</th>
                                <td><?php echo $student['faculty_name']; ?></td>
                                <th>Department</th>
                                <td><?php echo $student['department_name']; ?></td>
                            </tr>
                            <tr>
                                <th>Level</th>
                                <td><?php echo $student['level_name']; ?></td>
                                <th>Email</th>
                                <td><?php echo $student['email']; ?></td>
                            </tr>
                            <?php if ($semester_id && $session_id): ?>
                            <tr>
                                <th>Semester</th>
                                <td><?php echo $semester_name; ?></td>
                                <th>Session</th>
                                <td><?php echo $session_name; ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Course Results</h5>
                <?php 
                // Check if there are any pending scores
                $has_pending_scores = false;
                foreach ($scores as $score) {
                    if ($score['status'] === 'uploaded') {
                        $has_pending_scores = true;
                        break;
                    }
                }
                
                if ($has_pending_scores): ?>
                <form method="POST" action="">
                    <input type="hidden" name="semester_id" value="<?php echo $semester_id; ?>">
                    <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                    <button type="submit" name="accept_all_scores" class="btn btn-success" onclick="return confirm('Accept all pending scores for this student?')">
                        <i class="fas fa-check-circle me-1"></i>Accept All Pending Scores
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($scores)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Unit</th>
                                    <th>Lecturer</th>
                                    <th>Test</th>
                                    <th>Assignment</th>
                                    <th>Attendance</th>
                                    <th>CA Total</th>
                                    <th>Exam</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scores as $score): 
                                    $ca_total = $score['test'] + $score['assignment'] + $score['attendance'];
                                    $total = $ca_total + $score['exam'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($score['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($score['course_title']); ?></td>
                                    <td><?php echo $score['unit']; ?></td>
                                    <td><?php echo htmlspecialchars($score['lecturer_name']); ?></td>
                                    <td><?php echo $score['test']; ?></td>
                                    <td><?php echo $score['assignment']; ?></td>
                                    <td><?php echo $score['attendance']; ?></td>
                                    <td><?php echo $ca_total; ?></td>
                                    <td><?php echo $score['exam']; ?></td>
                                    <td><?php echo $total; ?></td>
                                    <td>
                                        <?php if ($score['status'] === 'uploaded'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Accepted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($score['status'] === 'uploaded'): ?>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="score_id" value="<?php echo $score['id']; ?>">
                                                <input type="hidden" name="semester_id" value="<?php echo $semester_id; ?>">
                                                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                                <button type="submit" name="accept_score" class="btn btn-success btn-sm" onclick="return confirm('Accept this score?')">
                                                    <i class="fas fa-check me-1"></i>Accept
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Accepted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <?php
                        $report_sheet_url = "report_sheet.php?student_id=$student_id";
                        if ($semester_id) $report_sheet_url .= "&semester_id=$semester_id";
                        if ($session_id) $report_sheet_url .= "&session_id=$session_id";
                        ?>
                        <a href="<?php echo $report_sheet_url; ?>" class="btn btn-primary">
                            <i class="fas fa-file-alt me-1"></i>View Report Sheet
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <?php if ($semester_id && $session_id): ?>
                            No results found for the selected semester and session.
                        <?php else: ?>
                            No results found for this student.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>