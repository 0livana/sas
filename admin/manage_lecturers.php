<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Add lecturer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_lecturer'])) {
        $name = trim($_POST['name']);
        $gender = $_POST['gender'];
        $faculty_id = $_POST['faculty_id'];
        $department_id = $_POST['department_id'];
        $position = trim($_POST['position']);
        $email = trim($_POST['email']);
        $password = 'password123'; // Default password
        $courses = $_POST['courses'] ?? [];
        
        if (!empty($name) && !empty($gender) && !empty($faculty_id) && !empty($department_id) && 
            !empty($position) && !empty($email)) {
            
            try {
                $pdo->beginTransaction();
                
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM lecturers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception("Email already exists!");
                }
                
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'lecturer')");
                $stmt->execute([$email, $hashed_password]);
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
                
                // Create lecturer record
                $stmt = $pdo->prepare("INSERT INTO lecturers 
                                      (user_id, name, gender, faculty_id, department_id, position, email, passport_image) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $gender, $faculty_id, $department_id, $position, $email, $passport_image]);
                $lecturer_id = $pdo->lastInsertId();
                
                // Assign courses
                foreach ($courses as $course_id) {
                    $stmt = $pdo->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
                    $stmt->execute([$lecturer_id, $course_id]);
                }
                
                $pdo->commit();
                $success = 'Lecturer added successfully! Default password: ' . $password;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error adding lecturer: ' . $e->getMessage();
            }
        } else {
            $error = 'All fields are required!';
        }
    } elseif (isset($_POST['edit_lecturer'])) {
        // Edit lecturer
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $gender = $_POST['gender'];
        $faculty_id = $_POST['faculty_id'];
        $department_id = $_POST['department_id'];
        $position = trim($_POST['position']);
        $email = trim($_POST['email']);
        $courses = $_POST['courses'] ?? [];
        
        if (!empty($name) && !empty($gender) && !empty($faculty_id) && !empty($department_id) && 
            !empty($position) && !empty($email)) {
            
            try {
                $pdo->beginTransaction();
                
                // Check if email already exists (excluding current lecturer)
                $stmt = $pdo->prepare("SELECT id FROM lecturers WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    throw new Exception("Email already exists!");
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
                
                // Update lecturer record
                $stmt = $pdo->prepare("UPDATE lecturers 
                                      SET name = ?, gender = ?, faculty_id = ?, department_id = ?, 
                                      position = ?, email = ?, passport_image = ?
                                      WHERE id = ?");
                $stmt->execute([$name, $gender, $faculty_id, $department_id, $position, $email, $passport_image, $id]);
                
                // Update user account username if email changed
                $stmt = $pdo->prepare("SELECT user_id FROM lecturers WHERE id = ?");
                $stmt->execute([$id]);
                $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lecturer) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$email, $lecturer['user_id']]);
                }
                
                // Update assigned courses
                // First, remove all existing course assignments
                $stmt = $pdo->prepare("DELETE FROM lecturer_courses WHERE lecturer_id = ?");
                $stmt->execute([$id]);
                
                // Then add the new ones
                foreach ($courses as $course_id) {
                    $stmt = $pdo->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
                    $stmt->execute([$id, $course_id]);
                }
                
                $pdo->commit();
                $success = 'Lecturer updated successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error updating lecturer: ' . $e->getMessage();
            }
        } else {
            $error = 'All fields are required!';
        }
    }
}

// Delete lecturer
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id, passport_image FROM lecturers WHERE id = ?");
        $stmt->execute([$id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lecturer) {
            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$lecturer['user_id']]);
            
            // Delete passport image
            if (!empty($lecturer['passport_image'])) {
                deleteFile($lecturer['passport_image']);
            }
            
            // Delete lecturer courses
            $stmt = $pdo->prepare("DELETE FROM lecturer_courses WHERE lecturer_id = ?");
            $stmt->execute([$id]);
            
            // Delete lecturer record
            $stmt = $pdo->prepare("DELETE FROM lecturers WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        $pdo->commit();
        $success = 'Lecturer deleted successfully!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error deleting lecturer: ' . $e->getMessage();
    }
}

// Reset password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    
    try {
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM lecturers WHERE id = ?");
        $stmt->execute([$id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lecturer) {
            $new_password = 'password123'; // Default password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $lecturer['user_id']]);
            
            $success = 'Password reset successfully! New password: ' . $new_password;
        }
    } catch (Exception $e) {
        $error = 'Error resetting password: ' . $e->getMessage();
    }
}

// Get all lecturers
$lecturers = $pdo->query("SELECT l.*, f.name as faculty_name, d.name as department_name 
                         FROM lecturers l 
                         JOIN faculties f ON l.faculty_id = f.id 
                         JOIN departments d ON l.department_id = d.id 
                         ORDER BY l.name")->fetchAll(PDO::FETCH_ASSOC);

// Get all faculties and departments
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get lecturer data for editing if requested
$edit_lecturer = null;
$assigned_courses = [];
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT l.*, f.name as faculty_name, d.name as department_name 
                          FROM lecturers l 
                          JOIN faculties f ON l.faculty_id = f.id 
                          JOIN departments d ON l.department_id = d.id 
                          WHERE l.id = ?");
    $stmt->execute([$id]);
    $edit_lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get assigned courses for this lecturer
    if ($edit_lecturer) {
        $stmt = $pdo->prepare("SELECT course_id FROM lecturer_courses WHERE lecturer_id = ?");
        $stmt->execute([$id]);
        $assigned_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get all courses for initial load (will be filtered by JavaScript based on faculty selection)
$all_courses = $pdo->query("SELECT * FROM courses ORDER BY course_code")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Manage Lecturers</h2>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $edit_lecturer ? 'Edit Lecturer' : 'Add New Lecturer'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="lecturerForm">
                        <?php if ($edit_lecturer): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_lecturer['id']; ?>">
                            <input type="hidden" name="current_passport_image" value="<?php echo $edit_lecturer['passport_image']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?php echo $edit_lecturer ? htmlspecialchars($edit_lecturer['name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($edit_lecturer && $edit_lecturer['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($edit_lecturer && $edit_lecturer['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" 
                                        <?php echo ($edit_lecturer && $edit_lecturer['faculty_id'] == $faculty['id']) ? 'selected' : ''; ?>>
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
                                        <?php echo ($edit_lecturer && $edit_lecturer['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                value="<?php echo $edit_lecturer ? htmlspecialchars($edit_lecturer['position']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?php echo $edit_lecturer ? htmlspecialchars($edit_lecturer['email']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="passport_image" class="form-label">Passport Image</label>
                            <input type="file" class="form-control" id="passport_image" name="passport_image" accept="image/*">
                            <?php if ($edit_lecturer && !empty($edit_lecturer['passport_image'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Current image:</small>
                                    <img src="../uploads/passports/<?php echo $edit_lecturer['passport_image']; ?>" class="img-thumbnail mt-1" width="100" alt="Current passport">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="courses" class="form-label">Assign Courses</label>
                            <select class="form-select" id="courses" name="courses[]" multiple>
                                <!-- Courses will be populated dynamically by JavaScript -->
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple courses</small>
                        </div>
                        <button type="submit" name="<?php echo $edit_lecturer ? 'edit_lecturer' : 'add_lecturer'; ?>" class="btn btn-primary">
                            <?php echo $edit_lecturer ? 'Update Lecturer' : 'Add Lecturer'; ?>
                        </button>
                        <a href="reset_lecturer_scores.php?lecturer_id=<?php echo $lecturer['id']; ?>" class="btn btn-warning ">
                            <i class="fas fa-undo me-1"></i>Reset Uploaded Scores
                        </a>
                        <?php if ($edit_lecturer): ?>
                            <a href="manage_lecturers.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Existing Lecturers</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Faculty</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lecturers as $lecturer): 
                            // Get assigned courses
                            $stmt = $pdo->prepare("SELECT c.course_code, c.course_title 
                                                  FROM lecturer_courses lc 
                                                  JOIN courses c ON lc.course_id = c.id 
                                                  WHERE lc.lecturer_id = ?");
                            $stmt->execute([$lecturer['id']]);
                            $assigned_courses_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <tr>
                            <td><?php echo $lecturer['id']; ?></td>
                            <td>
                                <?php if (!empty($lecturer['passport_image'])): ?>
                                    <img src="../uploads/passports/<?php echo $lecturer['passport_image']; ?>" class="rounded-circle me-2" width="30" height="30" alt="Passport">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($lecturer['name']); ?>
                            </td>
                            <td><?php echo $lecturer['gender']; ?></td>
                            <td><?php echo htmlspecialchars($lecturer['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($lecturer['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($lecturer['position']); ?></td>
                            <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                            <td>
                                <a href="manage_lecturers.php?edit=<?php echo $lecturer['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#coursesModal<?php echo $lecturer['id']; ?>">
                                    <i class="fas fa-book me-1"></i>Courses
                                </button>
                                <a href="?reset_password=<?php echo $lecturer['id']; ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Reset password to default?')">
                                    <i class="fas fa-key me-1"></i>Reset Password
                                </a>
                                <a href="?delete=<?php echo $lecturer['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this lecturer?')">
                                    <i class="fas fa-trash me-1"></i>Delete
                                </a>
                            </td>
                        </tr>
                        
                        <!-- Courses Modal -->
                        <div class="modal fade" id="coursesModal<?php echo $lecturer['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Courses Assigned to <?php echo htmlspecialchars($lecturer['name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if (!empty($assigned_courses_list)): ?>
                                            <ul>
                                                <?php foreach ($assigned_courses_list as $course): ?>
                                                    <li><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p>No courses assigned.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Store all courses data
const allCourses = <?php echo json_encode($all_courses); ?>;
const assignedCourses = <?php echo json_encode($assigned_courses); ?>;

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
                    
                    // Select the department if it matches the lecturer's department (when editing)
                    <?php if ($edit_lecturer): ?>
                        if (department.id == <?php echo $edit_lecturer['department_id'] ?? 0; ?>) {
                            option.selected = true;
                        }
                    <?php endif; ?>
                    
                    departmentSelect.appendChild(option);
                });
            });
    }
    
    // Also update courses based on selected faculty
    updateCourses();
});

// Function to update courses based on selected faculty
function updateCourses() {
    const facultyId = document.getElementById('faculty_id').value;
    const coursesSelect = document.getElementById('courses');
    
    // Clear existing options
    coursesSelect.innerHTML = '';
    
    if (facultyId) {
        // Filter courses by faculty
        const facultyCourses = allCourses.filter(course => course.faculty_id == facultyId);
        
        facultyCourses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.course_code + ' - ' + course.course_title;
            
            // Select the course if it's assigned to the lecturer (when editing)
            if (assignedCourses.includes(parseInt(course.id))) {
                option.selected = true;
            }
            
            coursesSelect.appendChild(option);
        });
    }
}

// Initialize courses when page loads if faculty is already selected
document.addEventListener('DOMContentLoaded', function() {
    const facultyId = document.getElementById('faculty_id').value;
    if (facultyId) {
        updateCourses();
    }
    
    // Also update departments if faculty is selected
    if (facultyId) {
        const departmentSelect = document.getElementById('department_id');
        fetch('../includes/get_departments.php?faculty_id=' + facultyId)
            .then(response => response.json())
            .then(departments => {
                departments.forEach(department => {
                    const option = document.createElement('option');
                    option.value = department.id;
                    option.textContent = department.name;
                    
                    // Select the department if it matches the lecturer's department (when editing)
                    <?php if ($edit_lecturer): ?>
                        if (department.id == <?php echo $edit_lecturer['department_id'] ?? 0; ?>) {
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