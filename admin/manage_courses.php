<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Get faculty_id from URL if specified
$faculty_id = isset($_GET['faculty_id']) ? $_GET['faculty_id'] : '';

// Add course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_title = trim($_POST['course_title']);
    $unit = $_POST['unit'];
    $faculty_id = $_POST['faculty_id'];
    
    if (!empty($course_code) && !empty($course_title) && !empty($unit) && !empty($faculty_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_title, unit, faculty_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$course_code, $course_title, $unit, $faculty_id]);
            $success = 'Course added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding course: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Edit course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_course'])) {
    $id = $_POST['id'];
    $course_code = trim($_POST['course_code']);
    $course_title = trim($_POST['course_title']);
    $unit = $_POST['unit'];
    $faculty_id = $_POST['faculty_id'];
    
    if (!empty($course_code) && !empty($course_title) && !empty($unit) && !empty($faculty_id)) {
        try {
            $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_title = ?, unit = ?, faculty_id = ? WHERE id = ?");
            $stmt->execute([$course_code, $course_title, $unit, $faculty_id, $id]);
            $success = 'Course updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating course: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required!';
    }
}

// Delete course
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if course has student registrations or scores
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ?");
    $stmt->execute([$id]);
    $student_courses_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE course_id = ?");
    $stmt->execute([$id]);
    $scores_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturer_courses WHERE course_id = ?");
    $stmt->execute([$id]);
    $lecturer_courses_count = $stmt->fetchColumn();
    
    if ($student_courses_count > 0 || $scores_count > 0 || $lecturer_courses_count > 0) {
        $error = 'Cannot delete course with associated records!';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Course deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting course: ' . $e->getMessage();
        }
    }
}

// Get all faculties
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get courses based on faculty filter
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT c.*, f.name as faculty_name FROM courses c 
                          JOIN faculties f ON c.faculty_id = f.id 
                          WHERE c.faculty_id = ? ORDER BY c.course_code");
    $stmt->execute([$faculty_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $courses = $pdo->query("SELECT c.*, f.name as faculty_name FROM courses c 
                           JOIN faculties f ON c.faculty_id = f.id 
                           ORDER BY f.name, c.course_code")->fetchAll(PDO::FETCH_ASSOC);
}

// Get course data for editing if requested
$edit_course = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT c.*, f.name as faculty_name FROM courses c 
                          JOIN faculties f ON c.faculty_id = f.id 
                          WHERE c.id = ?");
    $stmt->execute([$id]);
    $edit_course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set faculty_id to the course's faculty for the filter
    if ($edit_course) {
        $faculty_id = $edit_course['faculty_id'];
    }
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Manage Courses</h2>
    
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
                    <h5><?php echo $edit_course ? 'Edit Course' : 'Add New Course'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($edit_course): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_course['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo $faculty['id']; ?>" 
                                        <?php echo ($edit_course && $edit_course['faculty_id'] == $faculty['id']) || 
                                                 (!$edit_course && $faculty_id == $faculty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" 
                                value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_code']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="course_title" class="form-label">Course Title</label>
                            <input type="text" class="form-control" id="course_title" name="course_title" 
                                value="<?php echo $edit_course ? htmlspecialchars($edit_course['course_title']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="unit" class="form-label">Unit</label>
                            <input type="number" class="form-control" id="unit" name="unit" 
                                value="<?php echo $edit_course ? $edit_course['unit'] : ''; ?>" min="1" max="6" required>
                        </div>
                        <button type="submit" name="<?php echo $edit_course ? 'edit_course' : 'add_course'; ?>" class="btn btn-primary">
                            <?php echo $edit_course ? 'Update Course' : 'Add Course'; ?>
                        </button>
                        <?php if ($edit_course): ?>
                            <a href="manage_courses.php<?php echo $faculty_id ? '?faculty_id=' . $faculty_id : ''; ?>" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Filter Courses</h5>
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
                        <a href="manage_courses.php" class="btn btn-secondary">Clear Filter</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Existing Courses</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Unit</th>
                            <th>Faculty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo $course['id']; ?></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                            <td><?php echo $course['unit']; ?></td>
                            <td><?php echo htmlspecialchars($course['faculty_name']); ?></td>
                            <td>
                                <a href="manage_courses.php?edit=<?php echo $course['id']; ?><?php echo $faculty_id ? '&faculty_id=' . $faculty_id : ''; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="?delete=<?php echo $course['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this course?')">
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