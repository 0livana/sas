<?php
function getFaculties() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM faculties ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFaculty($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM faculties WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getDepartments($faculty_id = null) {
    global $pdo;
    $sql = "SELECT d.*, f.name as faculty_name FROM departments d 
            JOIN faculties f ON d.faculty_id = f.id";
    if ($faculty_id) {
        $sql .= " WHERE d.faculty_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$faculty_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDepartment($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT d.*, f.name as faculty_name FROM departments d 
                          JOIN faculties f ON d.faculty_id = f.id 
                          WHERE d.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCourses($faculty_id = null) {
    global $pdo;
    $sql = "SELECT c.*, f.name as faculty_name FROM courses c 
            JOIN faculties f ON c.faculty_id = f.id";
    if ($faculty_id) {
        $sql .= " WHERE c.faculty_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$faculty_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourse($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.*, f.name as faculty_name FROM courses c 
                          JOIN faculties f ON c.faculty_id = f.id 
                          WHERE c.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateGrade($score) {
    if ($score >= 70) return 'A';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    if ($score >= 40) return 'E';
    return 'F';
}

function calculateGPA($student_id, $semester, $session) {
    global $pdo;
    
    $sql = "SELECT c.unit, s.total 
            FROM scores s 
            JOIN courses c ON s.course_id = c.id 
            WHERE s.student_id = ? AND s.semester = ? AND s.session = ? AND s.status = 'accepted'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $semester, $session]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_units = 0;
    $total_points = 0;
    
    foreach ($results as $result) {
        $grade = calculateGrade($result['total']);
        $grade_point = 0;
        
        switch ($grade) {
            case 'A': $grade_point = 5; break;
            case 'B': $grade_point = 4; break;
            case 'C': $grade_point = 3; break;
            case 'D': $grade_point = 2; break;
            case 'E': $grade_point = 1; break;
            case 'F': $grade_point = 0; break;
        }
        
        $total_units += $result['unit'];
        $total_points += ($grade_point * $result['unit']);
    }
    
    return $total_units > 0 ? round($total_points / $total_units, 2) : 0;
}

function uploadPassport($file) {
    $target_dir = "../uploads/passports/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (max 2MB)
    if ($file["size"] > 2000000) {
        return ["success" => false, "message" => "Sorry, your file is too large."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        return ["success" => false, "message" => "Sorry, only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "filename" => $new_filename];
    } else {
        return ["success" => false, "message" => "Sorry, there was an error uploading your file."];
    }
}

function deleteFile($filename) {
    $file_path = "../uploads/passports/" . $filename;
    if (file_exists($file_path)) {
        unlink($file_path);
        return true;
    }
    return false;
}

function getExamOfficers($faculty_id = null) {
    global $pdo;
    $sql = "SELECT eo.*, f.name as faculty_name, d.name as department_name 
            FROM exam_officers eo 
            JOIN faculties f ON eo.faculty_id = f.id 
            JOIN departments d ON eo.department_id = d.id";
    if ($faculty_id) {
        $sql .= " WHERE eo.faculty_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$faculty_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLecturers($faculty_id = null) {
    global $pdo;
    $sql = "SELECT l.*, f.name as faculty_name, d.name as department_name 
            FROM lecturers l 
            JOIN faculties f ON l.faculty_id = f.id 
            JOIN departments d ON l.department_id = d.id";
    if ($faculty_id) {
        $sql .= " WHERE l.faculty_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$faculty_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudents($faculty_id = null, $department_id = null, $level = null) {
    global $pdo;
    $sql = "SELECT s.*, f.name as faculty_name, d.name as department_name 
            FROM students s 
            JOIN faculties f ON s.faculty_id = f.id 
            JOIN departments d ON s.department_id = d.id WHERE 1=1";
    $params = [];
    
    if ($faculty_id) {
        $sql .= " AND s.faculty_id = ?";
        $params[] = $faculty_id;
    }
    
    if ($department_id) {
        $sql .= " AND s.department_id = ?";
        $params[] = $department_id;
    }
    
    if ($level) {
        $sql .= " AND s.level = ?";
        $params[] = $level;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLecturerCourses($lecturer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.* FROM courses c 
                          JOIN lecturer_courses lc ON c.id = lc.course_id 
                          WHERE lc.lecturer_id = ?");
    $stmt->execute([$lecturer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentCourses($student_id, $semester = null, $session = null) {
    global $pdo;
    $sql = "SELECT c.*, sc.semester, sc.session FROM courses c 
            JOIN student_courses sc ON c.id = sc.course_id 
            WHERE sc.student_id = ?";
    $params = [$student_id];
    
    if ($semester) {
        $sql .= " AND sc.semester = ?";
        $params[] = $semester;
    }
    
    if ($session) {
        $sql .= " AND sc.session = ?";
        $params[] = $session;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourseStudents($course_id, $faculty_id = null, $department_id = null, $level = null, $semester = null, $session = null) {
    global $pdo;
    $sql = "SELECT s.*, sc.semester, sc.session FROM students s 
            JOIN student_courses sc ON s.id = sc.student_id 
            WHERE sc.course_id = ?";
    $params = [$course_id];
    
    if ($faculty_id) {
        $sql .= " AND s.faculty_id = ?";
        $params[] = $faculty_id;
    }
    
    if ($department_id) {
        $sql .= " AND s.department_id = ?";
        $params[] = $department_id;
    }
    
    if ($level) {
        $sql .= " AND s.level = ?";
        $params[] = $level;
    }
    
    if ($semester) {
        $sql .= " AND sc.semester = ?";
        $params[] = $semester;
    }
    
    if ($session) {
        $sql .= " AND sc.session = ?";
        $params[] = $session;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getScores($student_id = null, $course_id = null, $lecturer_id = null, $status = null) {
    global $pdo;
    $sql = "SELECT s.*, st.matric_no, st.full_name as student_name, c.course_code, c.course_title, 
            l.name as lecturer_name, eo.name as accepted_by_name
            FROM scores s 
            JOIN students st ON s.student_id = st.id 
            JOIN courses c ON s.course_id = c.id 
            JOIN lecturers l ON s.lecturer_id = l.id 
            LEFT JOIN exam_officers eo ON s.accepted_by = eo.id WHERE 1=1";
    $params = [];
    
    if ($student_id) {
        $sql .= " AND s.student_id = ?";
        $params[] = $student_id;
    }
    
    if ($course_id) {
        $sql .= " AND s.course_id = ?";
        $params[] = $course_id;
    }
    
    if ($lecturer_id) {
        $sql .= " AND s.lecturer_id = ?";
        $params[] = $lecturer_id;
    }
    
    if ($status) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExamOfficerStudents($exam_officer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT s.* FROM students s 
                          JOIN exam_officers eo ON s.faculty_id = eo.faculty_id 
                          AND s.department_id = eo.department_id 
                          AND s.level = eo.level_assigned 
                          WHERE eo.id = ?");
    $stmt->execute([$exam_officer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSemesters() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM semesters ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLevels() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM levels ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSessions() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM sessions ORDER BY name DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLevelName($level_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM levels WHERE id = ?");
    $stmt->execute([$level_id]);
    return $stmt->fetchColumn();
}
?>

