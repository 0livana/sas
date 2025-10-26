<?php
require_once '../includes/header.php';
require_once '../includes/auth_check.php';

$error = '';
$success = '';

// Add faculty
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_faculty'])) {
    $name = trim($_POST['name']);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO faculties (name) VALUES (?)");
            $stmt->execute([$name]);
            $success = 'Faculty added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding faculty: ' . $e->getMessage();
        }
    } else {
        $error = 'Faculty name is required!';
    }
}

// Edit faculty
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_faculty'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE faculties SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $success = 'Faculty updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating faculty: ' . $e->getMessage();
        }
    } else {
        $error = 'Faculty name is required!';
    }
}

// Delete faculty
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if faculty has departments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE faculty_id = ?");
    $stmt->execute([$id]);
    $departments_count = $stmt->fetchColumn();
    
    if ($departments_count > 0) {
        $error = 'Cannot delete faculty with departments!';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM faculties WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Faculty deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting faculty: ' . $e->getMessage();
        }
    }
}

// Get all faculties
$stmt = $pdo->query("SELECT * FROM faculties ORDER BY name");
$faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get faculty data for editing if requested
$edit_faculty = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM faculties WHERE id = ?");
    $stmt->execute([$id]);
    $edit_faculty = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">Manage Faculties</h2>
    
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
                    <h5><?php echo $edit_faculty ? 'Edit Faculty' : 'Add New Faculty'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($edit_faculty): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_faculty['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Faculty Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?php echo $edit_faculty ? htmlspecialchars($edit_faculty['name']) : ''; ?>" required>
                        </div>
                        <button type="submit" name="<?php echo $edit_faculty ? 'edit_faculty' : 'add_faculty'; ?>" class="btn btn-primary">
                            <?php echo $edit_faculty ? 'Update Faculty' : 'Add Faculty'; ?>
                        </button>
                        <?php if ($edit_faculty): ?>
                            <a href="manage_faculties.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Existing Faculties</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faculties as $faculty): ?>
                        <tr>
                            <td><?php echo $faculty['id']; ?></td>
                            <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($faculty['created_at'])); ?></td>
                            <td>
                                <a href="manage_faculties.php?edit=<?php echo $faculty['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <a href="manage_departments.php?faculty_id=<?php echo $faculty['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-building me-1"></i>Departments
                                </a>
                                <a href="?delete=<?php echo $faculty['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this faculty?')">
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