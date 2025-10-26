<?php
// Start clean output buffering
ob_start();

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

// Get faculties
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get departments based on selected faculty
$departments = [];
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE faculty_id = ? ORDER BY name");
    $stmt->execute([$faculty_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Get accepted scores based on search criteria
$scores = [];
$course_details = null;

if ($faculty_id && $department_id && $level_id && $course_id && $semester_id && $session_id) {
    // Get course details
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get accepted scores only
    $stmt = $pdo->prepare("SELECT s.*, st.matric_no, st.full_name as student_name, c.course_code, c.course_title
                          FROM scores s 
                          JOIN students st ON s.student_id = st.id
                          JOIN courses c ON s.course_id = c.id
                          WHERE s.lecturer_id = ? AND s.course_id = ? AND s.semester = ? AND s.session = ?
                          AND st.faculty_id = ? AND st.department_id = ? AND st.level_id = ? 
                          AND s.status = 'accepted'");
    $stmt->execute([$lecturer['id'], $course_id, $semester_name, $session_name, $faculty_id, $department_id, $level_id]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Accepted scores query returned " . count($scores) . " rows for lecturer_id={$lecturer['id']}, course_id=$course_id, semester=$semester_name, session=$session_name");
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
    
    if ($all_params_present && !empty($scores)) {
        ob_end_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scores_' . ($course_details['course_code'] ?? 'unknown') . '_' . $semester_name . '_' . $session_name . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Dennis Osadebay University - Accepted Scores Report']);
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
        foreach ($scores as $score) {
            fputcsv($output, [
                $sn++,
                $score['matric_no'],
                $score['student_name'],
                $score['test'],
                $score['assignment'],
                $score['attendance'],
                $score['exam'],
                $score['total'],
                'Accepted'
            ]);
        }
        
        fclose($output);
        exit();
    } else {
        ob_end_clean();
        header('Location: view_uploaded_scores.php?error=missing_params');
        exit();
    }
}

// Log logo file existence
$logo_file_path = $_SERVER['DOCUMENT_ROOT'] . '/images/logo.png';
if (!file_exists($logo_file_path)) {
    error_log("Logo file not found: $logo_file_path");
} else {
    error_log("Logo file found: $logo_file_path");
}

// Flush output buffer before HTML
ob_end_flush();
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">View Accepted Scores</h2>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'missing_params'): ?>
        <div class="alert alert-danger">Please select all search criteria before exporting or printing.</div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Search Accepted Scores</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="searchForm">
                <input type="hidden" name="export" value="0">
                
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
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" <?php echo $department_id == $department['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
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
                
                <button type="submit" class="btn btn-primary" name="search" value="1">Search Scores</button>
                <a href="view_uploaded_scores.php" class="btn btn-secondary">Reset</a>
                
                <?php if (!empty($scores)): ?>
                <button type="submit" class="btn btn-success" name="export" value="1">
                    <i class="fas fa-download me-1"></i> Export to CSV
                </button>
                <button type="button" class="btn btn-info" onclick="printScores()">
                    <i class="fas fa-print me-1"></i> Print Scores
                </button>
                <?php else: ?>
                <div class="alert alert-warning mt-3">No accepted scores available to export or print.</div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if ($course_details): ?>
    <div class="card">
        <div class="card-header">
            <h5>Accepted Scores for: <?php echo htmlspecialchars($course_details['course_code'] . ' - ' . $course_details['course_title']); ?></h5>
            <p class="mb-0">Lecturer: <strong><?php echo htmlspecialchars($lecturer_name); ?></strong></p>
        </div>
        <div class="card-body">
            <?php if (!empty($scores)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="scoresTable">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Matric No</th>
                            <th>Student Name</th>
                            <th>Test</th>
                            <th>Assignment</th>
                            <th>Attendance</th>
                            <th>Exam</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($scores as $score): ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td><?php echo htmlspecialchars($score['matric_no']); ?></td>
                            <td><?php echo htmlspecialchars($score['student_name']); ?></td>
                            <td><?php echo $score['test']; ?></td>
                            <td><?php echo $score['assignment']; ?></td>
                            <td><?php echo $score['attendance']; ?></td>
                            <td><?php echo $score['exam']; ?></td>
                            <td><?php echo $score['total']; ?></td>
                            <td>
                                <span class="badge bg-success">Accepted</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Printable Content (Hidden) -->
            <div id="printableContent" style="display: none;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="../images/logo.png" alt="University Logo" style="height: 80px; margin-bottom: 10px;">
                    <h2 style="margin: 5px 0; font-size: 18px;">Dennis Osadebay University</h2>
                    <h3 style="margin: 5px 0; font-size: 16px;">Accepted Scores Report</h3>
                    <p style="margin: 0;">Date: <?php echo date('d/m/Y'); ?></p>
                </div>
                
                <div style="margin-bottom: 20px; font-size: 14px;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 30%; padding: 3px;"><strong>Lecturer Name:</strong></td>
                            <td style="width: 70%; padding: 3px;"><?php echo htmlspecialchars($lecturer_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Course:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($course_details['course_code'] . ' - ' . $course_details['course_title']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Faculty:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($faculties[array_search($faculty_id, array_column($faculties, 'id'))]['name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Department:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($departments[array_search($department_id, array_column($departments, 'id'))]['name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Level:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($level_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Semester:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($semester_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Session:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($session_name); ?></td>
                        </tr>
                    </table>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">S/N</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Matric No</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Student Name</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Test</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Assignment</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Attendance</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Exam</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Total</th>
                            <th style="border: 1px solid #000; padding: 6px; background-color: #f0f0f0;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($scores as $score): ?>
                        <tr>
                            <td style="border: 1px solid #000; padding: 6px;"><?php echo $sn++; ?></td>
                            <td style="border: 1px solid #000; padding: 6px;"><?php echo htmlspecialchars($score['matric_no']); ?></td>
                            <td style="border: 1px solid #000; padding: 6px;"><?php echo htmlspecialchars($score['student_name']); ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?php echo $score['test']; ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?php echo $score['assignment']; ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?php echo $score['attendance']; ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?php echo $score['exam']; ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?php echo $score['total']; ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;">Accepted</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="signature-area" style="margin-top: 60px; text-align: center;">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="border: none; text-align: center;">
                                <div style="width: 70%; margin: 0 auto; padding-top: 5px;">
                                    Lecturer Signature
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php else: ?>
                <div class="alert alert-info">No accepted scores found for the selected criteria. Scores may be pending or not yet uploaded.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printableContent, #printableContent * {
        visibility: visible;
    }
    #printableContent {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        background: white;
        z-index: 9999;
    }
}
</style>

<script>
// Update departments based on selected faculty
document.getElementById('faculty_id').addEventListener('change', function() {
    const facultyId = this.value;
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
                    departmentSelect.appendChild(option);
                });
            });
    }
});

// Initialize departments when page loads if faculty is already selected
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
                    <?php if ($department_id): ?>
                        if (department.id == <?php echo $department_id; ?>) {
                            option.selected = true;
                        }
                    <?php endif; ?>
                    departmentSelect.appendChild(option);
                });
            });
    }
});

function printScores() {
    var printContent = document.getElementById('printableContent').innerHTML;
    var printWindow = window.open('', '_blank', 'width=900,height=600');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Accepted Scores Report - <?php echo htmlspecialchars($lecturer_name); ?></title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    font-size: 12px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse;
                }
                th, td { 
                    padding: 6px; 
                    text-align: left;
                }
                .signature-area table {
                    border: none;
                }
                .signature-area td {
                    border: none;
                }
                @media print {
                    body { margin: 15mm; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>${printContent}</body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for images to load before printing
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}
</script>

<?php require_once '../includes/footer.php'; ?>