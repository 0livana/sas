<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Get faculty_id from URL if specified
$faculty_id = isset($_GET['faculty_id']) ? $_GET['faculty_id'] : '';

// Add department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    $name = trim($_POST['name']);
    $faculty_id = $_POST['faculty_id'];
    $hod_name = trim($_POST['hod_name']);
    
    if (!empty($name) && !empty($faculty_id) && !empty($hod_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (name, faculty_id, hod_name) VALUES (?, ?, ?)");
            $stmt->execute([$name, $faculty_id, $hod_name]);
            $success = 'Department added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding department: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Edit department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_department'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $faculty_id = $_POST['faculty_id'];
    $hod_name = trim($_POST['hod_name']);
    
    if (!empty($name) && !empty($faculty_id) && !empty($hod_name)) {
        try {
            $stmt = $pdo->prepare("UPDATE departments SET name = ?, faculty_id = ?, hod_name = ? WHERE id = ?");
            $stmt->execute([$name, $faculty_id, $hod_name, $id]);
            $success = 'Department updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating department: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Delete department
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if department has students, lecturers, or exam officers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = ?");
    $stmt->execute([$id]);
    $students_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE department_id = ?");
    $stmt->execute([$id]);
    $lecturers_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_officers WHERE department_id = ?");
    $stmt->execute([$id]);
    $exam_officers_count = $stmt->fetchColumn();
    
    if ($students_count > 0 || $lecturers_count > 0 || $exam_officers_count > 0) {
        $error = 'Cannot delete department with associated records!';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Department deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting department: ' . $e->getMessage();
        }
    }
}

// Get all faculties
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get departments based on faculty filter
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT d.*, f.name as faculty_name FROM departments d 
                          JOIN faculties f ON d.faculty_id = f.id 
                          WHERE d.faculty_id = ? ORDER BY d.name");
    $stmt->execute([$faculty_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departments = $pdo->query("SELECT d.*, f.name as faculty_name FROM departments d 
                               JOIN faculties f ON d.faculty_id = f.id 
                               ORDER BY f.name, d.name")->fetchAll(PDO::FETCH_ASSOC);
}

// Get department data for editing if requested
$edit_department = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT d.*, f.name as faculty_name FROM departments d 
                          JOIN faculties f ON d.faculty_id = f.id 
                          WHERE d.id = ?");
    $stmt->execute([$id]);
    $edit_department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set faculty_id to the department's faculty for the filter
    if ($edit_department) {
        $faculty_id = $edit_department['faculty_id'];
    }
}
?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Manage Departments</h2>
    
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
                    <h5><?php echo $edit_department ? 'Edit Department' : 'Add New Department'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($edit_department): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_department['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" 
                                        <?php echo ($edit_department && $edit_department['faculty_id'] == $faculty['id']) || 
                                                 (!$edit_department && $faculty_id == $faculty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?php echo $edit_department ? htmlspecialchars($edit_department['name']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="hod_name" class="form-label">Head of Department (HOD) Name</label>
                            <input type="text" class="form-control" id="hod_name" name="hod_name" 
                                value="<?php echo $edit_department ? htmlspecialchars($edit_department['hod_name']) : ''; ?>" required>
                        </div>
                        <button type="submit" name="<?php echo $edit_department ? 'edit_department' : 'add_department'; ?>" class="btn btn-primary">
                            <?php echo $edit_department ? 'Update Department' : 'Add Department'; ?>
                        </button>
                        <?php if ($edit_department): ?>
                            <a href="manage_departments.php<?php echo $faculty_id ? '?faculty_id=' . $faculty_id : ''; ?>" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Filter Departments</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label for="filter_faculty" class="form-label">Filter by Faculty</label>
                            <select class="form-select" id="filter_faculty" name="faculty_id" onchange="this.form.submit()">
                                <option value="">All Faculties</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" <?php echo $faculty_id == $faculty['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a href="manage_departments.php" class="btn btn-secondary">Clear Filter</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Existing Departments</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Faculty</th>
                            <th>HOD Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><?php echo $department['id']; ?></td>
                            <td><?php echo htmlspecialchars($department['name']); ?></td>
                            <td><?php echo htmlspecialchars($department['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($department['hod_name']); ?></td>
                            <td>
                                <a href="manage_departments.php?edit=<?php echo $department['id']; ?><?php echo $faculty_id ? '&faculty_id=' . $faculty_id : ''; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="?delete=<?php echo $department['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this department?')">
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

<?php require_once '../includes/footer.php'; ?>