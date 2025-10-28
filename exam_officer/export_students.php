<?php
// Start output buffering
ob_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/Applications/XAMPP/xamppfiles/logs/php_errors.log');

require_once '../includes/header.php';
require_once '../includes/auth_check.php';

// Get exam officer details
$stmt = $pdo->prepare("SELECT eo.*, f.name as faculty_name, d.name as department_name, l.name as level_name
                      FROM exam_officers eo 
                      JOIN faculties f ON eo.faculty_id = f.id 
                      JOIN departments d ON eo.department_id = d.id 
                      JOIN levels l ON eo.level_assigned = l.id
                      WHERE eo.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$exam_officer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam_officer) {
    error_log("Exam officer not found for user_id: " . $_SESSION['user_id']);
    ob_end_clean();
    die("Exam officer not found.");
}

// Get assigned students
$stmt = $pdo->prepare("SELECT s.*, l.name as level_name 
                      FROM students s 
                      JOIN levels l ON s.level_id = l.id
                      WHERE s.faculty_id = ? AND s.department_id = ? AND s.level_id = ?");
$stmt->execute([$exam_officer['faculty_id'], $exam_officer['department_id'], $exam_officer['level_assigned']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Students query returned " . count($students) . " rows for faculty_id={$exam_officer['faculty_id']}, department_id={$exam_officer['department_id']}, level_id={$exam_officer['level_assigned']}");

// Handle CSV export
if (isset($_GET['export'])) {
    // Clear output buffer
    ob_end_clean();
    
    // Buffer CSV content
    $output = fopen('php://memory', 'w+');
    
    // CSV header
    fputcsv($output, ['Matric No', 'Full Name', 'Gender', 'Faculty', 'Department', 'Level', 'Email']);
    
    // CSV data
    foreach ($students as $student) {
        fputcsv($output, [
            $student['matric_no'],
            $student['full_name'],
            $student['gender'],
            $exam_officer['faculty_name'],
            $exam_officer['department_name'],
            $student['level_name'],
            $student['email']
        ]);
    }
    
    // Get CSV content
    rewind($output);
    $csv_content = stream_get_contents($output);
    fclose($output);
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_list.csv"');
    header('Content-Length: ' . strlen($csv_content));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    echo $csv_content;
    error_log("CSV export completed for " . count($students) . " students");
    exit();
}

// University details
$university_name = "Dennis Osadebay University, Asaba";
$university_logo = "../images/logo.png"; // Update with your actual logo path

// Flush buffer for HTML output
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
    <h2 class="text-center mb-4">Export Students List</h2>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Export Options</h5>
        </div>
        <div class="card-body">
            <div class="d-flex gap-2">
                <a href="?export=1" class="btn btn-success">
                    <i class="fas fa-download me-1"></i>Export CSV file
                </a>
                
                <button type="button" class="btn btn-primary" onclick="printStudentList()">
                    <i class="fas fa-print me-1"></i>Print List
                </button>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Assigned Students (<?php echo count($students); ?> students)</h5>
        </div>
        <div class="card-body">
            <?php if (count($students) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Matric_No</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Faculty</th>
                            <th>Department</th>
                            <th>Level</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['matric_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['gender']); ?></td>
                            <td><?php echo htmlspecialchars($exam_officer['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($exam_officer['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No students assigned to you yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function printStudentList() {
    // Create a new window for printing
    var printWindow = window.open('', '_blank');
    
    // Get current date and time
    var now = new Date();
    var timestamp = now.toLocaleString();
    
    // Create the print content
    var printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Students List</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .university-info { margin-bottom: 15px; }
                .logo { max-height: 80px; }
                .officer-info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; }
                @media print {
                    .no-print { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="university-info">
                    <img src="<?php echo $university_logo; ?>" alt="University Logo" class="logo">
                    <h2><?php echo $university_name; ?></h2>
                </div>
                <h3>Students List</h3>
                <h4><?php echo htmlspecialchars($exam_officer['faculty_name']); ?> - <?php echo htmlspecialchars($exam_officer['department_name']); ?></h4>
            </div>
            
            <div class="officer-info">
                <h4>Exam Officer Details:</h4>
                <table border="0" cellpadding="5" style="width: 100%;">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['name']); ?></td>
                        <td><strong>Gender:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['gender']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Faculty:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['faculty_name']); ?></td>
                        <td><strong>Department:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['department_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Position:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['position']); ?></td>
                        <td><strong>Level Assigned:</strong></td>
                        <td><?php echo htmlspecialchars($exam_officer['level_name']); ?></td>
                    </tr>
                </table>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Matric_No</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Level</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['matric_no']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['gender']); ?></td>
                        <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Generated on: ${timestamp}</p>
            </div>
            
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `;
    
    // Write the content to the new window
    printWindow.document.open();
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Focus on the new window
    printWindow.focus();
}
</script>

<?php require_once '../includes/footer.php'; ?>