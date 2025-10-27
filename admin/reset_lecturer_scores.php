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

// Get all lecturers, faculties, departments, courses, semesters, and sessions
$lecturers = $pdo->query("SELECT l.*, f.name as faculty_name, d.name as department_name 
                         FROM lecturers l 
                         JOIN faculties f ON l.faculty_id = f.id 
                         JOIN departments d ON l.department_id = d.id 
                         ORDER BY l.name")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_code")->fetchAll(PDO::FETCH_ASSOC);
$semesters = getSemesters();
$sessions = getSessions();

// Get search parameters
$lecturer_id = $_GET['lecturer_id'] ?? '';
$faculty_id = $_GET['faculty_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';
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

// Get uploaded scores
$scores = [];
if ($lecturer_id && $course_id && $semester_name && $session_name) {
    $stmt = $pdo->prepare("SELECT s.*, st.matric_no, st.full_name as student_name, c.course_code, c.course_title
                          FROM scores s 
                          JOIN students st ON s.student_id = st.id
                          JOIN courses c ON s.course_id = c.id
                          WHERE s.lecturer_id = ? AND s.course_id = ? AND s.semester = ? AND s.session = ?");
    $stmt->execute([$lecturer_id, $course_id, $semester_name, $session_name]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle bulk score reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_scores'])) {
    if ($lecturer_id && $course_id && $semester_name && $session_name) {
        try {
            $pdo->beginTransaction();
            
            // Delete uploaded scores
            $stmt = $pdo->prepare("DELETE FROM scores WHERE lecturer_id = ? AND course_id = ? AND semester = ? AND session = ?");
            $stmt->execute([$lecturer_id, $course_id, $semester_name, $session_name]);
            
            $pdo->commit();
            $success = 'All scores reset successfully!';
            
            // Refresh scores
            $scores = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error resetting scores: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select all required fields!';
    }
}

// Handle individual student score reset
if (isset($_GET['reset_student_score'])) {
    $score_id = $_GET['reset_student_score'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete the specific score
        $stmt = $pdo->prepare("DELETE FROM scores WHERE id = ?");
        $stmt->execute([$score_id]);
        
        $pdo->commit();
        $success = 'Student score reset successfully!';
        
        // Refresh scores
        if ($lecturer_id && $course_id && $semester_name && $session_name) {
            $stmt = $pdo->prepare("SELECT s.*, st.matric_no, st.full_name as student_name, c.course_code, c.course_title
                                  FROM scores s 
                                  JOIN students st ON s.student_id = st.id
                                  JOIN courses c ON s.course_id = c.id
                                  WHERE s.lecturer_id = ? AND s.course_id = ? AND s.semester = ? AND s.session = ?");
            $stmt->execute([$lecturer_id, $course_id, $semester_name, $session_name]);
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error resetting student score: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Lecturer Scores</title>
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
        
        .icon {
            margin-right: 10px;
        }
        
        .score-cell {
            text-align: center;
            font-weight: 500;
        }
        
        .total-score {
            font-weight: bold;
            background-color: #e8f5e9;
        }
        
        .status-cell {
            text-align: center;
        }
        
        .status-pass {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-fail {
            color: #dc3545;
            font-weight: bold;
        }
        
        .action-cell {
            text-align: center;
        }
    </style>
    <!-- my own style -->
    <style>
        body {
  padding-top: 10vh;
}
@media (max-width: 768px) {
  body {
    padding-top: 14vh;
  }
}

    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center mb-4"><i class="fas fa-sync-alt icon"></i>Reset Lecturer Uploaded/Accepted Scores</h2>
    
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
                            <label for="lecturer_id" class="form-label">Lecturer</label>
                            <select class="form-select" id="lecturer_id" name="lecturer_id" required>
                                <option value="">Select Lecturer</option>
                                <?php foreach ($lecturers as $lecturer): ?>
                                    <option value="<?php echo $lecturer['id']; ?>" <?php echo $lecturer_id == $lecturer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lecturer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id">
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" <?php echo $faculty_id == $faculty['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo $department_id == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $course_id == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                
                <button type="submit" class="btn btn-primary"><i class="fas fa-search icon"></i>Search Scores</button>
            </form>
        </div>
    </div>
    
    <?php if (!empty($scores)): ?>
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table icon"></i>Uploaded Scores</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Matric No</th>
                            <th>Student Name</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th class="score-cell">Test</th>
                            <th class="score-cell">Assignment</th>
                            <th class="score-cell">Attendance</th>
                            <th class="score-cell">Exam</th>
                            <th class="score-cell total-score">Total</th>
                            <th class="status-cell">Status</th>
                            <th class="action-cell">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scores as $score): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($score['matric_no']); ?></td>
                            <td><?php echo htmlspecialchars($score['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($score['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($score['course_title']); ?></td>
                            <td class="score-cell"><?php echo $score['test']; ?></td>
                            <td class="score-cell"><?php echo $score['assignment']; ?></td>
                            <td class="score-cell"><?php echo $score['attendance']; ?></td>
                            <td class="score-cell"><?php echo $score['exam']; ?></td>
                            <td class="score-cell total-score"><?php echo $score['total']; ?></td>
                            <td class="status-cell <?php echo ($score['status'] == 'Pass') ? 'status-pass' : 'status-fail'; ?>">
                                <?php echo $score['status']; ?>
                            </td>
                            <td class="action-cell">
                                <a href="?lecturer_id=<?php echo $lecturer_id; ?>&faculty_id=<?php echo $faculty_id; ?>&department_id=<?php echo $department_id; ?>&course_id=<?php echo $course_id; ?>&semester_id=<?php echo $semester_id; ?>&session_id=<?php echo $session_id; ?>&reset_student_score=<?php echo $score['id']; ?>" 
                                   class="btn btn-warning btn-sm" 
                                   onclick="return confirm('Are you sure you want to reset scores for <?php echo htmlspecialchars($score['student_name']); ?>?')">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="reset-confirm text-center">
                <h5 class="text-danger"><i class="fas fa-exclamation-triangle icon"></i>Bulk Reset Confirmation</h5>
                <p>You are about to reset ALL scores for <span class="fw-bold"><?php echo count($scores); ?> students</span> 
                in <span class="fw-bold"><?php echo htmlspecialchars($score['course_code']); ?></span> 
                for <span class="fw-bold"><?php echo htmlspecialchars($semester_name); ?></span> 
                of <span class="fw-bold"><?php echo htmlspecialchars($session_name); ?></span> session.</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="lecturer_id" value="<?php echo $lecturer_id; ?>">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    <input type="hidden" name="semester" value="<?php echo $semester_name; ?>">
                    <input type="hidden" name="session" value="<?php echo $session_name; ?>">
                    
                    <button type="submit" name="reset_scores" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset ALL scores for this course in <?php echo htmlspecialchars($semester_name . ' ' . $session_name); ?>? This action cannot be undone.')">
                        <i class="fas fa-broom icon"></i>Reset All Scores
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php elseif ($lecturer_id && $course_id && $semester_id && $session_id): ?>
    <div class="alert alert-info">No scores found for the selected criteria.</div>
    <?php endif; ?>
</div>

<script>
// Update departments based on selected faculty
document.getElementById('faculty_id').addEventListener('change', function() {
    const facultyId = this.value;
    const departmentSelect = document.getElementById('department_id');
    
    // Clear existing options
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    
    if (facultyId) {
        // Fetch departments for the selected faculty
        fetch('../includes/get_departments.php?faculty_id=' + facultyId)
            .then(response => response.json())
            .then(departments => {
                departments.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    departmentSelect.appendChild(option);
                });
            });
    }
});

// Initialize departments if faculty is already selected
document.addEventListener('DOMContentLoaded', function() {
    const facultyId = document.getElementById('faculty_id').value;
    if (facultyId) {
        const departmentSelect = document.getElementById('department_id');
        fetch('../includes/get_departments.php?faculty_id=' + facultyId)
            .then(response => response.json())
            .then(departments => {
                departments.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    departmentSelect.appendChild(option);
                });
            });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>