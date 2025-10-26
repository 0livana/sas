<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get student details
$stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name 
                      FROM students s 
                      JOIN faculties f ON s.faculty_id = f.id 
                      JOIN departments d ON s.department_id = d.id 
                      WHERE s.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get semesters and sessions
$semesters = getSemesters();
$sessions = getSessions();

// Get semester and session from query parameters or use defaults
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

// Initialize checked courses in session if not exists
if (!isset($_SESSION['checked_courses'])) {
    $_SESSION['checked_courses'] = [];
}

// Clear selection if semester or session changes
if ((isset($_GET['semester_id']) && $_GET['semester_id'] != ($_SESSION['last_semester'] ?? '')) || 
    (isset($_GET['session_id']) && $_GET['session_id'] != ($_SESSION['last_session'] ?? ''))) {
    $_SESSION['checked_courses'] = [];
    $_SESSION['last_semester'] = $_GET['semester_id'] ?? '';
    $_SESSION['last_session'] = $_GET['session_id'] ?? '';
}

// Handle course search
$searched_course = null;
$search_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_course'])) {
    $course_code = trim($_POST['course_code'] ?? '');
    
    if (!empty($course_code)) {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_code LIKE ?");
        $stmt->execute(['%' . $course_code . '%']);
        $searched_course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$searched_course) {
            $search_error = "No course found with the code '{$course_code}'.";
        }
    } else {
        $search_error = "Please enter a course code to search.";
    }
}

// Handle course selection (add/remove from checked list)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_course'])) {
    $course_id = $_POST['course_id'];
    
    if (in_array($course_id, $_SESSION['checked_courses'])) {
        // Remove from checked courses
        $_SESSION['checked_courses'] = array_filter($_SESSION['checked_courses'], function($id) use ($course_id) {
            return $id != $course_id;
        });
        $_SESSION['checked_courses'] = array_values($_SESSION['checked_courses']); // Re-index
        $message = "Course removed from selection.";
    } else {
        // Add to checked courses
        $_SESSION['checked_courses'][] = $course_id;
        $message = "Course added to selection.";
    }
}

// Get checked course details
$checked_course_details = [];
if (!empty($_SESSION['checked_courses'])) {
    $placeholders = str_repeat('?,', count($_SESSION['checked_courses']) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id IN ($placeholders) ORDER BY course_code");
    $stmt->execute($_SESSION['checked_courses']);
    $checked_course_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get already registered courses for current semester and session
$registered_courses = [];
$total_units = 0;
if ($student && $semester_name && $session_name) {
    $stmt = $pdo->prepare("SELECT c.* FROM courses c 
                          JOIN student_courses sc ON c.id = sc.course_id 
                          WHERE sc.student_id = ? AND sc.semester = ? AND sc.session = ?");
    $stmt->execute([$student['id'], $semester_name, $session_name]);
    $registered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total units
    foreach ($registered_courses as $course) {
        $total_units += $course['unit'];
    }
}

// Get all registered courses for the current session (across all semesters)
$session_registered_courses = [];
$session_total_units = 0;
if ($student && $session_name) {
    $stmt = $pdo->prepare("SELECT c.*, sc.semester, s.name as semester_name FROM courses c 
                          JOIN student_courses sc ON c.id = sc.course_id 
                          LEFT JOIN semesters s ON s.name = sc.semester
                          WHERE sc.student_id = ? AND sc.session = ? 
                          ORDER BY sc.semester, c.course_code");
    $stmt->execute([$student['id'], $session_name]);
    $session_registered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total units for the session
    foreach ($session_registered_courses as $course) {
        $session_total_units += $course['unit'];
    }
}

// Handle course registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_courses'])) {
    if (!empty($_SESSION['checked_courses'])) {
        try {
            $pdo->beginTransaction();
            
            // Get already registered course IDs to prevent duplicate registration
            $registered_course_ids = array_column($registered_courses, 'id');
            
            $newly_registered = 0;
            // Only add new courses, don't remove existing ones
            foreach ($_SESSION['checked_courses'] as $course_id) {
                // Check if course is not already registered
                if (!in_array($course_id, $registered_course_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, semester, session) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$student['id'], $course_id, $semester_name, $session_name]);
                    $newly_registered++;
                }
            }
            
            $pdo->commit();
            $success = "Successfully registered {$newly_registered} new course(s)!";
            
            // Clear checked courses from session after successful registration
            $_SESSION['checked_courses'] = [];
            $checked_course_details = [];
            
            // Refresh registered courses after registration
            $stmt = $pdo->prepare("SELECT c.* FROM courses c 
                                  JOIN student_courses sc ON c.id = sc.course_id 
                                  WHERE sc.student_id = ? AND sc.semester = ? AND sc.session = ?");
            $stmt->execute([$student['id'], $semester_name, $session_name]);
            $registered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recalculate total units
            $total_units = 0;
            foreach ($registered_courses as $course) {
                $total_units += $course['unit'];
            }
            
            // Refresh session courses
            $stmt = $pdo->prepare("SELECT c.*, sc.semester, s.name as semester_name FROM courses c 
                                  JOIN student_courses sc ON c.id = sc.course_id 
                                  LEFT JOIN semesters s ON s.name = sc.semester
                                  WHERE sc.student_id = ? AND sc.session = ? 
                                  ORDER BY sc.semester, c.course_code");
            $stmt->execute([$student['id'], $session_name]);
            $session_registered_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recalculate session total units
            $session_total_units = 0;
            foreach ($session_registered_courses as $course) {
                $session_total_units += $course['unit'];
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error registering courses: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select at least one course!';
    }
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Course Registration</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Registration Details</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
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
            </form>
        </div>
    </div>
    
    <?php if ($student && $semester_name && $session_name): ?>
    <!-- Course Search Section -->
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Search Courses for <?php echo htmlspecialchars($semester_name . ' ' . $session_name); ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="course_code" class="form-label">Course Code</label>
                                    <input type="text" class="form-control" id="course_code" name="course_code" 
                                           placeholder="Enter course code (e.g., COS111, DOU_CSC121)" 
                                           value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" name="search_course" class="btn btn-primary form-control">
                                        <i class="fas fa-search me-1"></i> Search
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($search_error)): ?>
                        <div class="alert alert-warning"><?php echo $search_error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($searched_course): ?>
                        <?php
                        // Check if course is already registered
                        $is_already_registered = in_array($searched_course['id'], array_column($registered_courses, 'id'));
                        // Check if course is in checked list
                        $is_checked = in_array($searched_course['id'], $_SESSION['checked_courses']);
                        ?>
                        
                        <div class="card mt-3 border-primary">
                            <div class="card-header bg-light">
                                <h6 class="card-title mb-0">Search Result</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th width="25%">Course Code</th>
                                                <th width="55%">Course Title</th>
                                                <th width="10%">Unit</th>
                                                <th width="10%">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($searched_course['course_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($searched_course['course_title']); ?></td>
                                                <td><?php echo $searched_course['unit']; ?></td>
                                                <td>
                                                    <?php if ($is_already_registered): ?>
                                                        <span class="badge bg-success">Registered</span>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="course_id" value="<?php echo $searched_course['id']; ?>">
                                                            <button type="submit" name="toggle_course" class="btn btn-sm <?php echo $is_checked ? 'btn-warning' : 'btn-success'; ?>">
                                                                <?php if ($is_checked): ?>
                                                                    <i class="fas fa-times"></i> Remove
                                                                <?php else: ?>
                                                                    <i class="fas fa-plus"></i> Add
                                                                <?php endif; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Selected Courses Sidebar -->
        <div class="col-md-4">
            <?php if (!empty($checked_course_details)): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-list-check me-1"></i>
                        Selected Courses (<?php echo count($checked_course_details); ?>)
                    </h6>
                </div>
                <div class="card-body p-2">
                    <?php 
                    $checked_total_units = 0;
                    foreach ($checked_course_details as $course): 
                        $checked_total_units += $course['unit'];
                    ?>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <div>
                            <small class="fw-bold"><?php echo htmlspecialchars($course['course_code']); ?></small>
                            <small class="d-block text-muted"><?php echo $course['unit']; ?> units</small>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" name="toggle_course" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-2 pt-2 border-top">
                        <small><strong>Total Units: <?php echo $checked_total_units; ?></strong></small>
                    </div>
                    
                    <form method="POST" class="mt-2">
                        <button type="submit" name="register_courses" class="btn btn-success btn-sm w-100">
                            <i class="fas fa-check me-1"></i> Register All Courses
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-4 border-secondary">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-list-check me-1"></i>
                        Selected Courses (0)
                    </h6>
                </div>
                <div class="card-body text-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Search and select courses to add them here
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Already Registered Courses Section -->
    <?php if (!empty($registered_courses)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Already Registered Courses</h5>
            <p class="text-muted mb-0">These courses are already registered for <?php echo htmlspecialchars($semester_name . ' ' . $session_name); ?></p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registered_courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                            <td><?php echo $course['unit']; ?></td>
                            <td>
                                <span class="badge bg-success">Registered</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <th colspan="2">Total Registered Units:</th>
                            <th><?php echo $total_units; ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="button" class="btn btn-info me-2" onclick="printCourseRegistration()">
                    <i class="fas fa-print me-1"></i> Print Semester Registration
                </button>
                
                <?php if (!empty($session_registered_courses)): ?>
                <button type="button" class="btn btn-primary" onclick="printSessionRegistration()">
                    <i class="fas fa-print me-1"></i> Print Full Session Registration
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Session Summary (if session is selected) -->
    <?php if (!empty($session_registered_courses) && $session_name): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Session Summary</h5>
            <p class="text-muted mb-0">All registered courses for <?php echo htmlspecialchars($session_name); ?> session</p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_semester = '';
                        foreach ($session_registered_courses as $course): 
                            if ($current_semester !== $course['semester']) {
                                $current_semester = $course['semester'];
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['semester']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                            <td><?php echo $course['unit']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-warning">
                            <th colspan="3">Total Session Units:</th>
                            <th><?php echo $session_total_units; ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
  <?php
// ... [ALL YOUR EXISTING PHP CODE REMAINS EXACTLY THE SAME UNTIL THE PRINTABLE CONTENT SECTION] ...
?>

    <!-- Printable Content for Semester Registration (Hidden) -->
    <div id="printableContent" style="display: none;">
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="../images/logo.png" alt="University Logo" style="height: 60px;">
            <h2 style="margin: 5px 0; font-size: 18px;">Dennis Osadebay University</h2>
            <h3 style="margin: 5px 0; font-size: 16px;">Course Registration Form - Semester</h3>
            <div style="font-size: 12px;">Date: <?php echo date('d/m/Y'); ?></div>
        </div>
        <hr style="margin: 10px 0;">
        
        <div style="margin-bottom: 15px;">
            <h4 style="margin: 10px 0; font-size: 14px;">Student Information | Undergraduate</h4>
            <div style="display: flex; align-items: flex-start; margin-bottom: 10px;">
                <div style="margin-right: 15px; flex-shrink: 0;">
                    <?php 
                    $passportPath = '../uploads/passports/' . $student['passport_image'];
                    if (!empty($student['passport_image']) && file_exists($passportPath)): 
                    ?>
                        <img src="<?php echo $passportPath; ?>" 
                             alt="Student Passport" style="width: 80px; height: 100px; object-fit: cover; border: 1px solid #ddd;">
                    <?php else: ?>
                        <div style="width: 80px; height: 100px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 10px;">No Photo</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1; font-size: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 20%; padding: 3px;"><strong>Full Name:</strong></td>
                            <td style="width: 30%; padding: 3px;"><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td style="width: 20%; padding: 3px;"><strong>Matriculation Number:</strong></td>
                            <td style="width: 30%; padding: 3px;"><?php echo htmlspecialchars($student['matric_no']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Faculty:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($student['faculty_name']); ?></td>
                            <td style="padding: 3px;"><strong>Department:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($student['department_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Level:</strong></td>
                            <td style="padding: 3px;">
                                <?php 
                                // Get student level name
                                $level_stmt = $pdo->prepare("SELECT name FROM levels WHERE id = ?");
                                $level_stmt->execute([$student['level_id']]);
                                $level_name = $level_stmt->fetchColumn();
                                echo htmlspecialchars($level_name); 
                                ?>
                            </td>
                            <td style="padding: 3px;"><strong>Semester:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($semester_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Session:</strong></td>
                            <td style="padding: 3px;" colspan="3"><?php echo htmlspecialchars($session_name); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <h4 style="margin: 10px 0; font-size: 14px;">Registered Courses</h4>
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 11px;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">S/N</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Course Code</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Course Title</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sn = 1; foreach ($registered_courses as $course): ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px;"><?php echo $sn++; ?></td>
                        <td style="border: 1px solid #000; padding: 4px;"><?php echo htmlspecialchars($course['course_code']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($course['course_title']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo $course['unit']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #e9ecef;">
                        <td colspan="3" style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: bold;">Total Units:</td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;"><?php echo $total_units; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 100px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px;">
                <div style="text-align: center; width: 48%;">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 3px;">
                        Course Adviser Signature
                    </div>
                </div>
                <div style="text-align: center; width: 48%;">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 3px;">
                        HOD Signature & Stamp
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Printable Content for Session Registration (Hidden) -->
    <div id="printableSessionContent" style="display: none;">
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="../images/logo.png" alt="University Logo" style="height: 60px;">
            <h2 style="margin: 5px 0; font-size: 18px;">Dennis Osadebay University</h2>
            <h3 style="margin: 5px 0; font-size: 16px;">Course Registration Form - Full Session</h3>
            <div style="font-size: 12px;">Date: <?php echo date('d/m/Y'); ?></div>
        </div>
        <hr style="margin: 10px 0;">
        
        <div style="margin-bottom: 15px;">
            <h4 style="margin: 10px 0; font-size: 14px;">Student Information | Undergraduate</h4>
            <div style="display: flex; align-items: flex-start; margin-bottom: 10px;">
                <div style="margin-right: 15px; flex-shrink: 0;">
                    <?php 
                    $passportPath = '../uploads/passports/' . $student['passport_image'];
                    if (!empty($student['passport_image']) && file_exists($passportPath)): 
                    ?>
                        <img src="<?php echo $passportPath; ?>" 
                             alt="Student Passport" style="width: 80px; height: 100px; object-fit: cover; border: 1px solid #ddd;">
                    <?php else: ?>
                        <div style="width: 80px; height: 100px; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 10px;">No Photo</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1; font-size: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 20%; padding: 3px;"><strong>Full Name:</strong></td>
                            <td style="width: 30%; padding: 3px;"><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td style="width: 20%; padding: 3px;"><strong>Matriculation Number:</strong></td>
                            <td style="width: 30%; padding: 3px;"><?php echo htmlspecialchars($student['matric_no']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Faculty:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($student['faculty_name']); ?></td>
                            <td style="padding: 3px;"><strong>Department:</strong></td>
                            <td style="padding: 3px;"><?php echo htmlspecialchars($student['department_name']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Level:</strong></td>
                            <td style="padding: 3px;" colspan="3">
                                <?php 
                                // Get student level name
                                $level_stmt = $pdo->prepare("SELECT name FROM levels WHERE id = ?");
                                $level_stmt->execute([$student['level_id']]);
                                $level_name = $level_stmt->fetchColumn();
                                echo htmlspecialchars($level_name); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px;"><strong>Session:</strong></td>
                            <td style="padding: 3px;" colspan="3"><?php echo htmlspecialchars($session_name); ?> (All Semesters)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <h4 style="margin: 10px 0; font-size: 14px;">Registered Courses</h4>
            <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 11px;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">S/N</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Semester</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Course Code</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Course Title</th>
                        <th style="border: 1px solid #000; padding: 4px; background-color: #f0f0f0;">Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sn = 1; 
                    $current_semester = '';
                    $semester_units = [];
                    
                    foreach ($session_registered_courses as $course): 
                        if (!isset($semester_units[$course['semester']])) {
                            $semester_units[$course['semester']] = 0;
                        }
                        $semester_units[$course['semester']] += $course['unit'];
                    ?>
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px;"><?php echo $sn++; ?></td>
                        <td style="border: 1px solid #000; padding: 4px;"><?php echo htmlspecialchars($course['semester']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px;"><?php echo htmlspecialchars($course['course_code']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; font-size: 10px;"><?php echo htmlspecialchars($course['course_title']); ?></td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center;"><?php echo $course['unit']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php foreach ($semester_units as $semester => $units): ?>
                    <tr style="background-color: #f8f9fa;">
                        <td colspan="3" style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: bold;"><?php echo htmlspecialchars($semester); ?> Subtotal:</td>
                        <td style="border: 1px solid #000; padding: 4px; font-weight: bold;"><?php echo $units; ?> units</td>
                        <td style="border: 1px solid #000; padding: 4px;"></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: #e9ecef;">
                        <td colspan="4" style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: bold;">Total Session Units:</td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;"><?php echo $session_total_units; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 100px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px;">
                <div style="text-align: center; width: 48%;">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 3px;">
                        Course Adviser Signature
                    </div>
                </div>
                <div style="text-align: center; width: 48%;">
                    <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 3px;">
                        HOD Signature & Stamp
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function printCourseRegistration() {
        var content = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Course Registration - Semester</title>
                <style>
                    @media print {
                        body { 
                            margin: 10mm !important;
                            font-family: Arial, sans-serif;
                            font-size: 12px;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse;
                            page-break-inside: avoid;
                        }
                        th, td { 
                            padding: 3px 4px;
                        }
                        .no-print { 
                            display: none !important; 
                        }
                        h2, h3, h4 {
                            margin: 5px 0;
                        }
                        h2 { font-size: 16px; }
                        h3 { font-size: 14px; }
                        h4 { font-size: 13px; }
                    }
                </style>
            </head>
            <body>
                ${document.getElementById('printableContent').innerHTML}
            </body>
            </html>
        `;
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(content);
        printWindow.document.close();
        
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };
    }
    
    function printSessionRegistration() {
        var content = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Course Registration - Session</title>
                <style>
                    @media print {
                        body { 
                            margin: 10mm !important;
                            font-family: Arial, sans-serif;
                            font-size: 12px;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse;
                            page-break-inside: avoid;
                        }
                        th, td { 
                            padding: 3px 4px;
                        }
                        .no-print { 
                            display: none !important; 
                        }
                        h2, h3, h4 {
                            margin: 5px 0;
                        }
                        h2 { font-size: 16px; }
                        h3 { font-size: 14px; }
                        h4 { font-size: 13px; }
                    }
                </style>
            </head>
            <body>
                ${document.getElementById('printableSessionContent').innerHTML}
            </body>
            </html>
        `;
        
        var printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(content);
        printWindow.document.close();
        
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };
    }
    </script>
    
<?php
// ... [THE REST OF YOUR ORIGINAL CODE REMAINS EXACTLY THE SAME] ...
?>
    
    <?php elseif ($student && (!$semester_name || !$session_name)): ?>
    <div class="alert alert-info">Please select both semester and session to search for courses.</div>
    <?php else: ?>
    <div class="alert alert-info">No courses available for your faculty.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>