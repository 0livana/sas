<?php
// Start output buffering at the very beginning
ob_start();

// Enable error reporting for debugging (log errors, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/Applications/XAMPP/xamppfiles/logs/php_errors.log');

require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get lecturer details
$stmt = $pdo->prepare("SELECT l.* FROM lecturers l WHERE l.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    error_log("Lecturer not found for user_id: " . $_SESSION['user_id']);
    die("Lecturer not found.");
}

// Ensure lecturer name
$lecturer_name = $lecturer['name'] ?? 'Unknown Lecturer';
error_log("Lecturer name: $lecturer_name");

$error = '';
$success = '';

// Get semesters, levels, and sessions
$semesters = getSemesters();
$levels = getLevels();
$sessions = getSessions();

// Get search parameters
$faculty_id = $_GET['faculty_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$level_id = $_GET['level_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';
$semester_id = $_GET['semester_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

// Get semester, level, and session names for display
$semester_name = '';
$level_name = '';
$session_name = '';

if ($semester_id) {
    $stmt = $pdo->prepare("SELECT name FROM semesters WHERE id = ?");
    $stmt->execute([$semester_id]);
    $semester_name = $stmt->fetchColumn();
}

if ($level_id) {
    $stmt = $pdo->prepare("SELECT name FROM levels WHERE id = ?");
    $stmt->execute([$level_id]);
    $level_name = $stmt->fetchColumn();
}

if ($session_id) {
    $stmt = $pdo->prepare("SELECT name FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session_name = $stmt->fetchColumn();
}

// Get assigned courses
$stmt = $pdo->prepare("SELECT c.* FROM courses c 
                      JOIN lecturer_courses lc ON c.id = lc.course_id 
                      WHERE lc.lecturer_id = ?");
$stmt->execute([$lecturer['id']]);
$assigned_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get faculties and departments
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get students based on search criteria, excluding accepted scores
$students = [];
$course_details = null;
$has_accepted_scores = false;

if ($faculty_id && $department_id && $level_id && $course_id && $semester_id && $session_id) {
    // Get course details
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get students registered for this course with no scores or non-accepted scores
    $stmt = $pdo->prepare("SELECT s.* FROM students s 
                          JOIN student_courses sc ON s.id = sc.student_id 
                          LEFT JOIN scores scs ON s.id = scs.student_id 
                          AND scs.course_id = ? AND scs.semester = ? AND scs.session = ?
                          WHERE s.faculty_id = ? AND s.department_id = ? AND s.level_id = ? 
                          AND sc.course_id = ? AND sc.semester = ? AND sc.session = ?
                          AND (scs.status IS NULL OR scs.status IN ('saved', 'uploaded'))");
    $stmt->execute([$course_id, $semester_name, $session_name, $faculty_id, $department_id, $level_id, $course_id, $semester_name, $session_name]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Students query returned " . count($students) . " rows for course_id=$course_id, semester=$semester_name, session=$session_name");
    
    // Get existing scores (for display and CSV export)
    $scores = [];
    if (!empty($students)) {
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $stmt = $pdo->prepare("SELECT * FROM scores 
                              WHERE student_id IN ($placeholders) 
                              AND course_id = ? AND semester = ? AND session = ?");
        $params = array_merge($student_ids, [$course_id, $semester_name, $session_name]);
        $stmt->execute($params);
        
        $scores_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($scores_result as $score) {
            $scores[$score['student_id']] = $score;
        }
        
        // Check for accepted scores (for CSV export and warning)
        $stmt = $pdo->prepare("SELECT COUNT(*) as accepted_count FROM scores 
                              WHERE course_id = ? AND semester = ? AND session = ? 
                              AND lecturer_id = ? AND status = 'accepted'");
        $stmt->execute([$course_id, $semester_name, $session_name, $lecturer['id']]);
        $has_accepted_scores = $stmt->fetch(PDO::FETCH_ASSOC)['accepted_count'] > 0;
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $required_params = ['faculty_id', 'department_id', 'level_id', 'course_id', 'semester_id', 'session_id'];
    $all_params_present = true;
    
    foreach ($required_params as $param) {
        if (empty($_GET[$param])) {
            $all_params_present = false;
            break;
        }
    }
    
    if ($all_params_present && !empty($students)) {
        ob_end_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scores_' . ($course_details['course_code'] ?? 'unknown') . '_' . $semester_name . '_' . $session_name . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Dennis Osadebay University - Uploaded Scores Report']);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['Lecturer Name:', $lecturer_name]);
        fputcsv($output, ['Course:', $course_details['course_code'] . ' - ' . $course_details['course_title']]);
        fputcsv($output, ['Semester:', $semester_name]);
        fputcsv($output, ['Session:', $session_name]);
        fputcsv($output, ['Level:', $level_name]);
        fputcsv($output, ['Faculty:', $faculties[array_search($faculty_id, array_column($faculties, 'id'))]['name'] ?? 'N/A']);
        fputcsv($output, ['Department:', $departments[array_search($department_id, array_column($departments, 'id'))]['name'] ?? 'N/A']);
        fputcsv($output, []);
        
        fputcsv($output, ['S/N', 'Matric No', 'Student Name', 'Test', 'Assignment', 'Attendance', 'Exam', 'Total', 'Status']);
        
        $sn = 1;
        foreach ($students as $student) {
            $score = $scores[$student['id']] ?? [];
            fputcsv($output, [
                $sn++,
                $student['matric_no'],
                $student['full_name'],
                $score['test'] ?? 0,
                $score['assignment'] ?? 0,
                $score['attendance'] ?? 0,
                $score['exam'] ?? 0,
                $score['total'] ?? 0,
                $score['status'] ?? 'Not Entered'
            ]);
        }
        
        fclose($output);
        exit();
    } else {
        ob_end_clean();
        header('Location: upload_scores.php?error=missing_params');
        exit();
    }
}

// Handle score submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_scores'])) {
    $student_ids = $_POST['student_id'] ?? [];
    $test_scores = $_POST['test'] ?? [];
    $assignment_scores = $_POST['assignment'] ?? [];
    $attendance_scores = $_POST['attendance'] ?? [];
    $exam_scores = $_POST['exam'] ?? [];
    
    try {
        $pdo->beginTransaction();
        $updated_count = 0;
        $skipped_count = 0;
        
        foreach ($student_ids as $index => $student_id) {
            $test = $test_scores[$index] ?? 0;
            $assignment = $assignment_scores[$index] ?? 0;
            $attendance = $attendance_scores[$index] ?? 0;
            $exam = $exam_scores[$index] ?? 0;
            
            $ca_total = $test + $assignment + $attendance;
            $total = $ca_total + $exam;
            
            $stmt = $pdo->prepare("SELECT id, status FROM scores 
                                  WHERE student_id = ? AND course_id = ? 
                                  AND semester = ? AND session = ?");
            $stmt->execute([$student_id, $course_id, $semester_name, $session_name]);
            $existing_score = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_score) {
                if ($existing_score['status'] === 'accepted') {
                    $skipped_count++;
                    continue;
                }
                
                $stmt = $pdo->prepare("UPDATE scores 
                                      SET test = ?, assignment = ?, attendance = ?, 
                                      exam = ?, total = ?, status = 'saved'
                                      WHERE id = ?");
                $stmt->execute([$test, $assignment, $attendance, $exam, $total, $existing_score['id']]);
                $updated_count++;
            } else {
                $stmt = $pdo->prepare("INSERT INTO scores 
                                      (student_id, course_id, lecturer_id, test, assignment, 
                                      attendance, exam, total, status, semester, session) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'saved', ?, ?)");
                $stmt->execute([$student_id, $course_id, $lecturer['id'], $test, $assignment, 
                               $attendance, $exam, $total, $semester_name, $session_name]);
                $updated_count++;
            }
        }
        
        $pdo->commit();
        if ($updated_count > 0) {
            $success = "Scores saved successfully for $updated_count students!";
            if ($skipped_count > 0) {
                $success .= " $skipped_count students with accepted scores were not modified.";
            }
        } else {
            $success = "No scores were saved. All scores have already been accepted by the exam officer.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error saving scores: ' . $e->getMessage();
        error_log("Error saving scores: " . $e->getMessage());
    }
}

// Handle upload to exam officer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_scores'])) {
    try {
        $stmt = $pdo->prepare("UPDATE scores 
                              SET status = 'uploaded' 
                              WHERE course_id = ? AND semester = ? AND session = ? 
                              AND lecturer_id = ? AND status != 'accepted'");
        $stmt->execute([$course_id, $semester_name, $session_name, $lecturer['id']]);
        $updated_count = $stmt->rowCount();
        
        if ($updated_count > 0) {
            $success = "Scores uploaded to exam officer successfully for $updated_count students!";
            if ($has_accepted_scores) {
                $success .= " Some already accepted scores were not modified.";
            }
        } else {
            $success = "No scores were uploaded. All scores have already been accepted by the exam officer.";
        }
    } catch (Exception $e) {
        $error = 'Error uploading scores: ' . $e->getMessage();
        error_log("Error uploading scores: " . $e->getMessage());
    }
}

// Flush output buffer before HTML
ob_end_flush();
?>

<head>
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
<div class="container">
    <h2 class="text-center mb-4">Upload Scores</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'missing_params'): ?>
        <div class="alert alert-danger">Please select all search criteria before exporting.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Search Students</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="searchForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required onchange="this.form.submit()">
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
                            <select class="form-select" id="department_id" name="department_id" required onchange="this.form.submit()">
                                <option value="">Select Department</option>
                                <?php 
                                if ($faculty_id) {
                                    $stmt = $pdo->prepare("SELECT * FROM departments WHERE faculty_id = ? ORDER BY name");
                                    $stmt->execute([$faculty_id]);
                                    $faculty_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($faculty_departments as $department): ?>
                                        <option value="<?php echo $department['id']; ?>" <?php echo $department_id == $department['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['name']); ?>
                                        </option>
                                    <?php endforeach;
                                } else {
                                    foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['id']; ?>" <?php echo $department_id == $department['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['name']); ?>
                                        </option>
                                    <?php endforeach;
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="level_id" class="form-label">Level</label>
                            <select class="form-select" id="level_id" name="level_id" required onchange="this.form.submit()">
                                <option value="">Select Level</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>" <?php echo $level_id == $level['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
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
                            <select class="form-select" id="course_id" name="course_id" required onchange="this.form.submit()">
                                <option value="">Select Course</option>
                                <?php foreach ($assigned_courses as $course): ?>
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
                            <select class="form-select" id="semester_id" name="semester_id" required onchange="this.form.submit()">
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
                            <select class="form-select" id="session_id" name="session_id" required onchange="this.form.submit()">
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
                
                <button type="submit" class="btn btn-primary">Search Students</button>
                <a href="upload_scores.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>
    </div>
    
    <?php if ($course_details): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Course: <?php echo htmlspecialchars($course_details['course_code'] . ' - ' . $course_details['course_title']); ?> (<?php echo $course_details['unit']; ?> Units)</h5>
            <p class="mb-0">Lecturer: <strong><?php echo htmlspecialchars($lecturer_name); ?></strong></p>
            <?php if ($has_accepted_scores): ?>
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-exclamation-triangle"></i> Some scores have been accepted by the exam officer and are not shown here. View them in the "View Uploaded Scores" page.
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($students)): ?>
            <form method="POST" action="">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Matric No</th>
                                <th>Name</th>
                                <th>Test (10)</th>
                                <th>Assignment (10)</th>
                                <th>Attendance (10)</th>
                                <th>CA Total (30)</th>
                                <th>Exam (70)</th>
                                <th>Total (100)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): 
                                $score = $scores[$student['id']] ?? [];
                                $is_editable = !isset($score['status']) || in_array($score['status'], ['saved', 'uploaded']);
                            ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($student['matric_no']); ?>
                                    <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td>
                                    <?php if ($is_editable): ?>
                                        <input type="number" class="form-control form-control-sm ca-input" name="test[]" 
                                               value="<?php echo $score['test'] ?? 0; ?>" min="0" max="10" step="0.5" required>
                                    <?php else: ?>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="<?php echo $score['test'] ?? 0; ?>" readonly>
                                        <input type="hidden" name="test[]" value="<?php echo $score['test'] ?? 0; ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_editable): ?>
                                        <input type="number" class="form-control form-control-sm ca-input" name="assignment[]" 
                                               value="<?php echo $score['assignment'] ?? 0; ?>" min="0" max="10" step="0.5" required>
                                    <?php else: ?>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="<?php echo $score['assignment'] ?? 0; ?>" readonly>
                                        <input type="hidden" name="assignment[]" value="<?php echo $score['assignment'] ?? 0; ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_editable): ?>
                                        <input type="number" class="form-control form-control-sm ca-input" name="attendance[]" 
                                               value="<?php echo $score['attendance'] ?? 0; ?>" min="0" max="10" step="0.5" required>
                                    <?php else: ?>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="<?php echo $score['attendance'] ?? 0; ?>" readonly>
                                        <input type="hidden" name="attendance[]" value="<?php echo $score['attendance'] ?? 0; ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" id="ca_total_<?php echo $index; ?>" 
                                           value="<?php echo ($score['test'] ?? 0) + ($score['assignment'] ?? 0) + ($score['attendance'] ?? 0); ?>" readonly>
                                </td>
                                <td>
                                    <?php if ($is_editable): ?>
                                        <input type="number" class="form-control form-control-sm exam-input" name="exam[]" 
                                               value="<?php echo $score['exam'] ?? 0; ?>" min="0" max="70" step="0.5" required>
                                    <?php else: ?>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="<?php echo $score['exam'] ?? 0; ?>" readonly>
                                        <input type="hidden" name="exam[]" value="<?php echo $score['exam'] ?? 0; ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" id="total_<?php echo $index; ?>" 
                                           value="<?php echo $score['total'] ?? 0; ?>" readonly>
                                </td>
                                <td>
                                    <?php if (isset($score['status']) && $score['status'] === 'uploaded'): ?>
                                        <span class="badge bg-info">Uploaded</span>
                                    <?php elseif (isset($score['status']) && $score['status'] === 'saved'): ?>
                                        <span class="badge bg-warning">Saved</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Entered</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button type="submit" name="save_scores" class="btn btn-primary">Save Scores</button>
                    <button type="submit" name="upload_scores" class="btn btn-success">Upload to Exam Officer</button>
                    <?php if (!empty($scores)): ?>
                        <a href="?export=1&faculty_id=<?php echo htmlspecialchars($faculty_id); ?>&department_id=<?php echo htmlspecialchars($department_id); ?>&level_id=<?php echo htmlspecialchars($level_id); ?>&course_id=<?php echo htmlspecialchars($course_id); ?>&semester_id=<?php echo htmlspecialchars($semester_id); ?>&session_id=<?php echo htmlspecialchars($session_id); ?>" class="btn btn-info">
                            <i class="fas fa-download me-1"></i> Export to CSV
                        </a>
                    <?php endif; ?>
                    <?php if ($has_accepted_scores): ?>
                        <span class="text-muted align-self-center">Note: Accepted Scores by Exam officer can't be edited</span>
                    <?php endif; ?>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-info">No students with editable scores (saved or uploaded) found for the selected criteria. All scores may have been accepted by the exam officer.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Function to update departments based on selected faculty
function updateDepartments() {
    const facultyId = document.getElementById('faculty_id').value;
    const departmentSelect = document.getElementById('department_id');
    
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    
    if (facultyId) {
        fetch('../includes/get_departments.php?faculty_id=' + facultyId)
            .then(response => response.json())
            .then(departments => {
                departments.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    <?php if ($department_id): ?>
                        if (department.id == <?php echo $department_id; ?>) {
                            option.selected = true;
                        }
                    <?php endif; ?>
                    departmentSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error fetching departments:', error);
            });
    }
}

// Add event listener to faculty dropdown
document.getElementById('faculty_id').addEventListener('change', updateDepartments);

// Calculate CA total and overall total
document.addEventListener('DOMContentLoaded', function() {
    const caInputs = document.querySelectorAll('.ca-input');
    const examInputs = document.querySelectorAll('.exam-input');
    
    function calculateScores() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            const test = parseFloat(row.querySelector('input[name="test[]"]').value) || 0;
            const assignment = parseFloat(row.querySelector('input[name="assignment[]"]').value) || 0;
            const attendance = parseFloat(row.querySelector('input[name="attendance[]"]').value) || 0;
            const exam = parseFloat(row.querySelector('input[name="exam[]"]').value) || 0;
            
            const caTotal = test + assignment + attendance;
            const total = caTotal + exam;
            
            document.getElementById('ca_total_' + index).value = caTotal.toFixed(1);
            document.getElementById('total_' + index).value = total.toFixed(1);
        });
    }
    
    caInputs.forEach(input => {
        input.addEventListener('input', calculateScores);
    });
    
    examInputs.forEach(input => {
        input.addEventListener('input', calculateScores);
    });
    
    // Update departments on page load if faculty is already selected
    const facultyId = document.getElementById('faculty_id').value;
    if (facultyId) {
        updateDepartments();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>