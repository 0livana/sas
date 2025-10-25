<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Only allow admin access
if ($_SESSION['user_type'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$error = '';
$success = '';

// Get all students, semesters, and sessions
$students = $pdo->query("SELECT s.*, f.name as faculty_name, d.name as department_name 
                        FROM students s 
                        JOIN faculties f ON s.faculty_id = f.id 
                        JOIN departments d ON s.department_id = d.id 
                        ORDER BY s.full_name")->fetchAll(PDO::FETCH_ASSOC);
$semesters = getSemesters();
$sessions = getSessions();

// Get search parameters
$matric_no = $_GET['matric_no'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$semester_id = $_GET['semester_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

// If matric number is provided, find the student
if (!empty($matric_no)) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE matric_no = ?");
    $stmt->execute([$matric_no]);
    $student_id = $stmt->fetchColumn();
    if (!$student_id) {
        $error = 'No student found with the provided matric number.';
    }
}

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

// Get registered courses
$registered_courses = [];
if ($student_id && $semester_name && $session_name) {
    $stmt = $pdo->prepare("SELECT c.* FROM courses c 
                          JOIN student_courses sc ON c.id = sc.course_id 
                          WHERE sc.student_id = ? AND sc.semester = ? AND sc.session = ?");
    $stmt->execute([$student_id, $semester_name, $session_name]);
    $registered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle individual course reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_courses'])) {
    if ($student_id && $semester_name && $session_name) {
        try {
            $pdo->beginTransaction();
            
            // Delete registered courses
            $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ? AND semester = ? AND session = ?");
            $stmt->execute([$student_id, $semester_name, $session_name]);
            
            $pdo->commit();
            $success = 'Student courses reset successfully!';
            
            // Refresh registered courses
            $registered_courses = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error resetting courses: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select a student, semester, and session!';
    }
}

// Handle bulk course reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_reset'])) {
    $bulk_semester_id = $_POST['bulk_semester_id'] ?? '';
    $bulk_session_id = $_POST['bulk_session_id'] ?? '';
    
    if ($bulk_semester_id && $bulk_session_id) {
        // Get semester and session names
        $stmt = $pdo->prepare("SELECT name FROM semesters WHERE id = ?");
        $stmt->execute([$bulk_semester_id]);
        $bulk_semester_name = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT name FROM sessions WHERE id = ?");
        $stmt->execute([$bulk_session_id]);
        $bulk_session_name = $stmt->fetchColumn();
        
        if ($bulk_semester_name && $bulk_session_name) {
            try {
                $pdo->beginTransaction();
                
                // Delete all registered courses for the selected semester and session
                $stmt = $pdo->prepare("DELETE FROM student_courses WHERE semester = ? AND session = ?");
                $stmt->execute([$bulk_semester_name, $bulk_session_name]);
                
                $pdo->commit();
                $success = 'All courses for ' . $bulk_semester_name . ' ' . $bulk_session_name . ' reset successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error resetting courses: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid semester or session selected for bulk reset!';
        }
    } else {
        $error = 'Please select a semester and session for bulk reset!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Course Reset System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b5998;
            --secondary-color: #8b9dc3;
            --accent-color: #dfe3ee;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-danger {
            background-color: #d9534f;
            border-color: #d9534f;
        }
        
        .btn-warning {
            background-color: #f0ad4e;
            border-color: #f0ad4e;
        }
        
        .btn-primary:hover, .btn-danger:hover, .btn-warning:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .search-section {
            background-color: var(--accent-color);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .highlight {
            background-color: #fff8e1;
        }
        
        .reset-confirm {
            background-color: #ffebee;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .student-info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .bulk-section {
            background-color: #e8f5e9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .icon {
            margin-right: 10px;
        }
        
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center mb-4"><i class="fas fa-sync-alt icon"></i>Reset Student Registered Courses</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-search icon"></i>Search Criteria</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="matric_no" class="form-label">Matriculation Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="matric_no" name="matric_no" 
                                       value="<?php echo htmlspecialchars($matric_no); ?>" 
                                       placeholder="Enter student matric number">
                            </div>
                        </div>
                    </div>
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
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search icon"></i>Search Courses</button>
            </form>
        </div>
    </div>
    
    <?php if ($student_id && $semester_id && $session_id): ?>
        <?php
        // Get student details
        $stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name 
                              FROM students s 
                              JOIN faculties f ON s.faculty_id = f.id 
                              JOIN departments d ON s.department_id = d.id 
                              WHERE s.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        
        <div class="student-info">
            <h4><i class="fas fa-user-graduate icon"></i>Student Information</h4>
            <div class="row">
                <div class="col-md-3"><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></div>
                <div class="col-md-3"><strong>Matric No:</strong> <?php echo htmlspecialchars($student['matric_no']); ?></div>
                <div class="col-md-3"><strong>Faculty:</strong> <?php echo htmlspecialchars($student['faculty_name']); ?></div>
                <div class="col-md-3"><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></div>
            </div>
        </div>
        
        <?php if (!empty($registered_courses)): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-book icon"></i>Registered Courses</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registered_courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                <td><?php echo $course['unit']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="reset-confirm text-center">
                    <h5 class="text-danger"><i class="fas fa-exclamation-triangle icon"></i>Reset Confirmation</h5>
                    <p>You are about to reset all courses for <span class="fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></span> 
                    for <span class="fw-bold"><?php echo htmlspecialchars($semester_name); ?></span> 
                    of <span class="fw-bold"><?php echo htmlspecialchars($session_name); ?></span> session.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="semester" value="<?php echo $semester_name; ?>">
                        <input type="hidden" name="session" value="<?php echo $session_name; ?>">
                        
                        <button type="submit" name="reset_courses" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all courses for this student in <?php echo htmlspecialchars($semester_name . ' ' . $session_name); ?>? This action cannot be undone.')">
                            <i class="fas fa-trash-alt icon"></i>Reset Student Courses
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">No registered courses found for the selected criteria.</div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Bulk Reset Section -->
    <div class="bulk-section">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users icon"></i>Bulk Reset for All Students</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_semester_id" class="form-label">Semester</label>
                                <select class="form-select" id="bulk_semester_id" name="bulk_semester_id" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>">
                                            <?php echo htmlspecialchars($semester['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_session_id" class="form-label">Session</label>
                                <select class="form-select" id="bulk_session_id" name="bulk_session_id" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>">
                                            <?php echo htmlspecialchars($session['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="bulk_reset" class="btn btn-warning" onclick="return confirm('Are you sure you want to reset ALL courses for the selected semester and session? This action cannot be undone and will affect ALL students.')">
                            <i class="fas fa-broom icon"></i>Reset All Courses for Selection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>