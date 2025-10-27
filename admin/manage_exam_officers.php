<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Get levels for dropdown
$levels = getLevels();

// Add exam officer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_exam_officer'])) {
    $name = trim($_POST['name']);
    $gender = $_POST['gender'];
    $faculty_id = $_POST['faculty_id'];
    $department_id = $_POST['department_id'];
    $level_id = $_POST['level_id']; // Changed from level_assigned to level_id
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    $password = 'password123'; // Default password
    
    if (!empty($name) && !empty($gender) && !empty($faculty_id) && !empty($department_id) && 
        !empty($level_id) && !empty($position) && !empty($email)) {
        
        try {
            $pdo->beginTransaction();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM exam_officers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists!");
            }
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'exam_officer')");
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
            
            // Create exam officer record - FIXED: Use level_id instead of level name
            $stmt = $pdo->prepare("INSERT INTO exam_officers 
                                  (user_id, name, gender, faculty_id, department_id, level_assigned, position, email, passport_image) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $gender, $faculty_id, $department_id, $level_id, $position, $email, $passport_image]);
            
            $pdo->commit();
            $success = 'Exam officer added successfully! Default password: ' . $password;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adding exam officer: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Edit exam officer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_exam_officer'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $gender = $_POST['gender'];
    $faculty_id = $_POST['faculty_id'];
    $department_id = $_POST['department_id'];
    $level_id = $_POST['level_id'];
    $position = trim($_POST['position']);
    $email = trim($_POST['email']);
    
    if (!empty($name) && !empty($gender) && !empty($faculty_id) && !empty($department_id) && 
        !empty($level_id) && !empty($position) && !empty($email)) {
        
        try {
            $pdo->beginTransaction();
            
            // Check if email already exists (excluding current exam officer)
            $stmt = $pdo->prepare("SELECT id FROM exam_officers WHERE email = ? AND id != ?");
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
            
            // Update exam officer record
            $stmt = $pdo->prepare("UPDATE exam_officers 
                                  SET name = ?, gender = ?, faculty_id = ?, department_id = ?, 
                                  level_assigned = ?, position = ?, email = ?, passport_image = ?
                                  WHERE id = ?");
            $stmt->execute([$name, $gender, $faculty_id, $department_id, $level_id, $position, $email, $passport_image, $id]);
            
            // Update user account username if email changed
            $stmt = $pdo->prepare("SELECT user_id FROM exam_officers WHERE id = ?");
            $stmt->execute([$id]);
            $exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exam_officer) {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$email, $exam_officer['user_id']]);
            }
            
            $pdo->commit();
            $success = 'Exam officer updated successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating exam officer: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Delete exam officer
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id, passport_image FROM exam_officers WHERE id = ?");
        $stmt->execute([$id]);
        $exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam_officer) {
            // Delete user account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$exam_officer['user_id']]);
            
            // Delete passport image
            if (!empty($exam_officer['passport_image'])) {
                deleteFile($exam_officer['passport_image']);
            }
            
            // Delete exam officer record
            $stmt = $pdo->prepare("DELETE FROM exam_officers WHERE id = ?");
            $stmt->execute([$id]);
        }
        
        $pdo->commit();
        $success = 'Exam officer deleted successfully!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error deleting exam officer: ' . $e->getMessage();
    }
}

// Reset password
if (isset($_GET['reset_password'])) {
    $id = $_GET['reset_password'];
    
    try {
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM exam_officers WHERE id = ?");
        $stmt->execute([$id]);
        $exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam_officer) {
            $new_password = 'password123'; // Default password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $exam_officer['user_id']]);
            
            $success = 'Password reset successfully! New password: ' . $new_password;
        }
    } catch (Exception $e) {
        $error = 'Error resetting password: ' . $e->getMessage();
    }
}

// Get all exam officers with level name
$exam_officers = $pdo->query("SELECT eo.*, f.name as faculty_name, d.name as department_name, l.name as level_name 
                             FROM exam_officers eo 
                             JOIN faculties f ON eo.faculty_id = f.id 
                             JOIN departments d ON eo.department_id = d.id 
                             JOIN levels l ON eo.level_assigned = l.id 
                             ORDER BY eo.name")->fetchAll(PDO::FETCH_ASSOC);

// Get all faculties and departments
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get exam officer data for editing if requested
$edit_officer = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT eo.*, f.name as faculty_name, d.name as department_name, l.name as level_name 
                          FROM exam_officers eo 
                          JOIN faculties f ON eo.faculty_id = f.id 
                          JOIN departments d ON eo.department_id = d.id 
                          JOIN levels l ON eo.level_assigned = l.id 
                          WHERE eo.id = ?");
    $stmt->execute([$id]);
    $edit_officer = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<head>
    <style>
        body {
  padding-top: 10vh;
}
@media (max-width: 768px) {
  body {
    padding-top: 13vh;
  }
}

    </style>
</head>
<div class="container">
    <h2 class="text-center mb-4">Manage Exam Officers</h2>
    
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
                    <h5><?php echo $edit_officer ? 'Edit Exam Officer' : 'Add New Exam Officer'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if ($edit_officer): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_officer['id']; ?>">
                            <input type="hidden" name="current_passport_image" value="<?php echo $edit_officer['passport_image']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?php echo $edit_officer ? htmlspecialchars($edit_officer['name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($edit_officer && $edit_officer['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($edit_officer && $edit_officer['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" 
                                        <?php echo ($edit_officer && $edit_officer['faculty_id'] == $faculty['id']) ? 'selected' : ''; ?>>
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
                                        <?php echo ($edit_officer && $edit_officer['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="level_id" class="form-label">Level Assigned</label>
                            <select class="form-select" id="level_id" name="level_id" required>
                                <option value="">Select Level</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>" 
                                        <?php echo ($edit_officer && $edit_officer['level_assigned'] == $level['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                value="<?php echo $edit_officer ? htmlspecialchars($edit_officer['position']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?php echo $edit_officer ? htmlspecialchars($edit_officer['email']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="passport_image" class="form-label">Passport Image</label>
                            <input type="file" class="form-control" id="passport_image" name="passport_image" accept="image/*">
                            <?php if ($edit_officer && !empty($edit_officer['passport_image'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Current image:</small>
                                    <img src="../uploads/passports/<?php echo $edit_officer['passport_image']; ?>" class="img-thumbnail mt-1" width="100" alt="Current passport">
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="<?php echo $edit_officer ? 'edit_exam_officer' : 'add_exam_officer'; ?>" class="btn btn-primary">
                            <?php echo $edit_officer ? 'Update Exam Officer' : 'Add Exam Officer'; ?>
                        </button>
                        <?php if ($edit_officer): ?>
                            <a href="manage_exam_officers.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Existing Exam Officers</h5>
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
                            <th>Level Assigned</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exam_officers as $officer): ?>
                        <tr>
                            <td><?php echo $officer['id']; ?></td>
                            <td>
                                <?php if (!empty($officer['passport_image'])): ?>
                                    <img src="../uploads/passports/<?php echo $officer['passport_image']; ?>" class="rounded-circle me-2" width="30" height="30" alt="Passport">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($officer['name']); ?>
                            </td>
                            <td><?php echo $officer['gender']; ?></td>
                            <td><?php echo htmlspecialchars($officer['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($officer['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($officer['level_name']); ?></td>
                            <td><?php echo htmlspecialchars($officer['position']); ?></td>
                            <td><?php echo htmlspecialchars($officer['email']); ?></td>
                            <td>
                                <a href="manage_exam_officers.php?edit=<?php echo $officer['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="?reset_password=<?php echo $officer['id']; ?>" class="btn btn-info btn-sm" onclick="return confirm('Reset password to default?')">
                                    <i class="fas fa-key me-1"></i>Reset Password
                                </a>
                                <a href="?delete=<?php echo $officer['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this exam officer?')">
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
                    departmentSelect.appendChild(option);
                });
            });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>