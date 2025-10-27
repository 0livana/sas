<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Get levels for dropdown
$levels = getLevels();

// Get sessions for dropdown
$sessions = getSessions();

// Check if admitted_session_id column exists in students table
$column_exists = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'admitted_session_id'");
    $column_exists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $error = 'Error checking database structure: ' . $e->getMessage();
}

// Add student
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_student'])) {
        $matric_no = trim($_POST['matric_no']);
        $full_name = trim($_POST['full_name']);
        $gender = $_POST['gender'];
        $faculty_id = $_POST['faculty_id'];
        $department_id = $_POST['department_id'];
        $level_id = $_POST['level_id'];
        $email = trim($_POST['email']);
        $admitted_session_id = $_POST['admitted_session_id'];
        $password = 'password123'; // Default password
        
        if (!empty($matric_no) && !empty($full_name) && !empty($gender) && !empty($faculty_id) && 
            !empty($department_id) && !empty($level_id) && !empty($email)) {
            
            try {
                $pdo->beginTransaction();
                
                // Check if matric number or email already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE matric_no = ? OR email = ?");
                $stmt->execute([$matric_no, $email]);
                if ($stmt->fetch()) {
                    throw new Exception("Matric number or email already exists!");
                }
                
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
                $stmt->execute([$matric_no, $hashed_password]);
                $user_id = $pdo->lastInsertId();
                
                // Handle file upload
                $passport_image = '';
                if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] == UPLOAD_ERR_OK) {
                    $upload_result = uploadPassport($_FILES['passport_image']);
                    if (!$upload_result['success']) {
                        throw new Exception($upload_result['message']);
                    }
                    $passport_image = $upload_result['filename'];
                }
                
                // Create student record
                if ($column_exists) {
                    $stmt = $pdo->prepare("INSERT INTO students 
                                        (user_id, matric_no, full_name, gender, faculty_id, department_id, level_id, email, passport_image, admitted_session_id) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $matric_no, $full_name, $gender, $faculty_id, $department_id, $level_id, $email, $passport_image, $admitted_session_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO students 
                                        (user_id, matric_no, full_name, gender, faculty_id, department_id, level_id, email, passport_image) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $matric_no, $full_name, $gender, $faculty_id, $department_id, $level_id, $email, $passport_image]);
                }
                
                $pdo->commit();
                $success = 'Student added successfully! Default password: ' . $password;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error adding student: ' . $e->getMessage();
            }
        } else {
            $error = 'All fields are required!';
        }
    } elseif (isset($_POST['edit_student'])) {
        // Edit student
        $id = $_POST['id'];
        $matric_no = trim($_POST['matric_no']);
        $full_name = trim($_POST['full_name']);
        $gender = $_POST['gender'];
        $faculty_id = $_POST['faculty_id'];
        $department_id = $_POST['department_id'];
        $level_id = $_POST['level_id'];
        $email = trim($_POST['email']);
        $admitted_session_id = $_POST['admitted_session_id'];
        
        if (!empty($matric_no) && !empty($full_name) && !empty($gender) && !empty($faculty_id) && 
            !empty($department_id) && !empty($level_id) && !empty($email)) {
            
            try {
                $pdo->beginTransaction();
                
                // Check if matric number or email already exists (excluding current student)
                $stmt = $pdo->prepare("SELECT id FROM students WHERE (matric_no = ? OR email = ?) AND id != ?");
                $stmt->execute([$matric_no, $email, $id]);
                if ($stmt->fetch()) {
                    throw new Exception("Matric number or email already exists!");
                }
                
                // Handle file upload
                $passport_image = $_POST['current_passport_image'];
                if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] == UPLOAD_ERR_OK) {
                    // Delete old image if exists
                    if (!empty($passport_image)) {
                        deleteFile($passport_image);
                    }
                    
                    $upload_result = uploadPassport($_FILES['passport_image']);
                    if (!$upload_result['success']) {
                        throw new Exception($upload_result['message']);
                    }
                    $passport_image = $upload_result['filename'];
                }
                
                // Update student record
                if ($column_exists) {
                    $stmt = $pdo->prepare("UPDATE students 
                                        SET matric_no = ?, full_name = ?, gender = ?, faculty_id = ?, 
                                        department_id = ?, level_id = ?, email = ?, passport_image = ?, admitted_session_id = ?
                                        WHERE id = ?");
                    $stmt->execute([$matric_no, $full_name, $gender, $faculty_id, $department_id, $level_id, $email, $passport_image, $admitted_session_id, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE students 
                                        SET matric_no = ?, full_name = ?, gender = ?, faculty_id = ?, 
                                        department_id = ?, level_id = ?, email = ?, passport_image = ?
                                        WHERE id = ?");
                    $stmt->execute([$matric_no, $full_name, $gender, $faculty_id, $department_id, $level_id, $email, $passport_image, $id]);
                }
                
                // Update user account username if matric number changed
                $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$matric_no, $student['user_id']]);
                }
                
                $pdo->commit();
                $success = 'Student updated successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error updating student: ' . $e->getMessage();
            }
        } else {
            $error = 'All fields are required!';
        }
    }
}

// Delete student
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id, passport_image FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            
            // Delete passport image
            if (!empty($student['passport_image'])) {
                deleteFile($student['passport_image']);
            }
            
            // Delete student courses
            $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ?");
            $stmt->execute([$id]);
            
            // Delete student scores
            $stmt = $pdo->prepare("DELETE FROM scores WHERE student_id = ?");
            $stmt->execute([$id]);
            
            // Delete student record
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        $pdo->commit();
        $success = 'Student deleted successfully!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error deleting student: ' . $e->getMessage();
    }
}

// Reset password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    
    try {
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $new_password = 'password123'; // Default password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $student['user_id']]);
            
            $success = 'Password reset successfully! New password: ' . $new_password;
        }
    } catch (Exception $e) {
        $error = 'Error resetting password: ' . $e->getMessage();
    }
}

// Get search parameter if exists
$search_matric = isset($_GET['search_matric']) ? trim($_GET['search_matric']) : '';

// Build query for getting students
if ($column_exists) {
    $query = "SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name, 
                     sess.name as admitted_session_name
              FROM students s 
              JOIN faculties f ON s.faculty_id = f.id 
              JOIN departments d ON s.department_id = d.id 
              JOIN levels l ON s.level_id = l.id
              LEFT JOIN sessions sess ON s.admitted_session_id = sess.id";
} else {
    $query = "SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name
              FROM students s 
              JOIN faculties f ON s.faculty_id = f.id 
              JOIN departments d ON s.department_id = d.id 
              JOIN levels l ON s.level_id = l.id";
}

// Add search condition if search parameter is provided
if (!empty($search_matric)) {
    $query .= " WHERE s.matric_no LIKE :matric_no";
}

$query .= " ORDER BY s.matric_no";

// Prepare and execute the query
$stmt = $pdo->prepare($query);

if (!empty($search_matric)) {
    $search_term = '%' . $search_matric . '%';
    $stmt->bindParam(':matric_no', $search_term, PDO::PARAM_STR);
}

$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all faculties and departments
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER by name")->fetchAll(PDO::FETCH_ASSOC);

// Get student data for editing if requested
$edit_student = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    if ($column_exists) {
        $stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name, 
                                      sess.name as admitted_session_name
                              FROM students s 
                              JOIN faculties f ON s.faculty_id = f.id 
                              JOIN departments d ON s.department_id = d.id 
                              JOIN levels l ON s.level_id = l.id 
                              LEFT JOIN sessions sess ON s.admitted_session_id = sess.id
                              WHERE s.id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT s.*, f.name as faculty_name, d.name as department_name, l.name as level_name
                              FROM students s 
                              JOIN faculties f ON s.faculty_id = f.id 
                              JOIN departments d ON s.department_id = d.id 
                              JOIN levels l ON s.level_id = l.id 
                              WHERE s.id = ?");
    }
    $stmt->execute([$id]);
    $edit_student = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
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
        
        .btn-info {
            background-color: #5bc0de;
            border-color: #5bc0de;
        }
        
        .btn-primary:hover, .btn-danger:hover, .btn-warning:hover, .btn-info:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .search-section {
            background-color: var(--accent-color);
            padding: 15px;
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
        
        .student-info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .icon {
            margin-right: 10px;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .search-highlight {
            background-color: yellow;
            font-weight: bold;
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
    <h2 class="text-center mb-4"><i class="fas fa-users icon"></i>Manage Students</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!$column_exists): ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> The admitted session feature is not available yet. Please run the database migration to add the admitted_session_id column to the students table.
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $edit_student ? 'Edit Student' : 'Add New Student'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if ($edit_student): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_student['id']; ?>">
                            <input type="hidden" name="current_passport_image" value="<?php echo $edit_student['passport_image']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="matric_no" class="form-label">Matric Number</label>
                            <input type="text" class="form-control" id="matric_no" name="matric_no" 
                                value="<?php echo $edit_student ? htmlspecialchars($edit_student['matric_no']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                value="<?php echo $edit_student ? htmlspecialchars($edit_student['full_name']) : ''; ?>" placeholder="(Surname first and all in uppercase)" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($edit_student && $edit_student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($edit_student && $edit_student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" 
                                        <?php echo ($edit_student && $edit_student['faculty_id'] == $faculty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['id']; ?>" 
                                        <?php echo ($edit_student && $edit_student['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="level_id" class="form-label">Level</label>
                            <select class="form-select" id="level_id" name="level_id" required>
                                <option value="">Select Level</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>" 
                                        <?php echo ($edit_student && $edit_student['level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($column_exists): ?>
                        <div class="mb-3">
                            <label for="admitted_session_id" class="form-label">Admitted Session (Year)</label>
                            <select class="form-select" id="admitted_session_id" name="admitted_session_id" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>" 
                                        <?php echo ($edit_student && $edit_student['admitted_session_id'] == $session['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?php echo $edit_student ? htmlspecialchars($edit_student['email']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="passport_image" class="form-label">Passport Image</label>
                            <input type="file" class="form-control" id="passport_image" name="passport_image" accept="image/*">
                            <?php if ($edit_student && !empty($edit_student['passport_image'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Current image:</small>
                                    <img src="../uploads/passports/<?php echo $edit_student['passport_image']; ?>" class="img-thumbnail mt-1" width="100" alt="Current passport">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="<?php echo $edit_student ? 'edit_student' : 'add_student'; ?>" class="btn btn-primary">
                            <?php echo $edit_student ? 'Update Student' : 'Add Student'; ?>
                        </button>
                        <a href="reset_student_courses.php" class="btn btn-warning">
                            <i class="fas fa-undo me-1"></i>Reset Student Registered Courses
                        </a>
                        <?php if ($edit_student): ?>
                            <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Existing Students</h5>
            <form method="GET" action="" class="d-flex">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search by matric number" name="search_matric" value="<?php echo htmlspecialchars($search_matric); ?>">
                    <button class="btn btn-info" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search_matric)): ?>
                        <a href="manage_students.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (!empty($search_matric)): ?>
                <div class="alert alert-info">
                    Showing results for matric number: <strong><?php echo htmlspecialchars($search_matric); ?></strong>
                    <?php if (count($students) === 0): ?>
                        - No students found
                    <?php else: ?>
                        - Found <?php echo count($students); ?> student(s)
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Matric No</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Faculty</th>
                            <th>Department</th>
                            <th>Level</th>
                            <?php if ($column_exists): ?>
                                <th>Admitted</th>
                            <?php endif; ?>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td>
                                <?php 
                                if (!empty($search_matric)) {
                                    $highlighted = preg_replace("/(" . preg_quote($search_matric, '/') . ")/i", "<span class='search-highlight'>$1</span>", $student['matric_no']);
                                    echo $highlighted;
                                } else {
                                    echo htmlspecialchars($student['matric_no']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($student['passport_image'])): ?>
                                    <img src="../uploads/passports/<?php echo $student['passport_image']; ?>" class="rounded-circle me-2" width="30" height="30" alt="Passport">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </td>
                            <td><?php echo $student['gender']; ?></td>
                            <td><?php echo htmlspecialchars($student['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                            <?php if ($column_exists): ?>
                                <td><?php echo htmlspecialchars($student['admitted_session_name'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td>
                                <a href="manage_students.php?edit=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="?reset_password=<?php echo $student['id']; ?>" class="btn btn-info btn-sm" onclick="return confirm('Reset password to default?')">
                                    <i class="fas fa-key me-1"></i>Reset Password
                                </a>
                                <a href="?delete=<?php echo $student['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this student?')">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
                    
                    // Select the department if it matches the student's department (when editing)
                    <?php if ($edit_student): ?>
                        if (department.id == <?php echo $edit_student['department_id'] ?? 0; ?>) {
                            option.selected = true;
                        }
                    <?php endif; ?>
                    
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
                    
                    // Select the department if it matches the student's department (when editing)
                    <?php if ($edit_student): ?>
                        if (department.id == <?php echo $edit_student['department_id'] ?? 0; ?>) {
                            option.selected = true;
                        }
                    <?php endif; ?>
                    
                    departmentSelect.appendChild(option);
                });
            });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>