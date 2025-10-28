# Introduction

The Student Assessment System (SAS) is a web-based application designed to manage student assessments in Dennis Osadebay University (DOU). This system allows administrators to manage users, faculties, departments, and courses; lecturers to upload and export student scores; exam officers to review, accept, and prepare student report sheets; and students to register courses and view their results.

## The application is built using:
- MySQL for the database to store user data, faculties, departments, courses, scores, etc.
- HTML for the structure and UI, with CSS for styling (including a modern, responsive design with a fixed navbar and footer on all pages, university logo and name, background images especially on the login page, and simple navigation).
- PHP for the backend logic, handling user authentication, data manipulation, file uploads (e.g., passports), and interactions with the database.

## Key features include:
- Secure login for different user roles (Admin, Lecturer, Exam Officer, Student).
- Role-based dashboards with functionalities as specified.
- Password management (default passwords, changes, resets).
- File uploads for passports and exports in CSV format.
- Automated calculations for scores, grades, GPA, etc.
- Print-friendly report sheets.

## Database Schema
#### Key Tables:
- users - User authentication details
- students - Student information
- lecturers - Lecturer information
- courses - Course details
- student_courses - Course registration records
- scores - Academic scores
- faculties - Faculty information
- departments - Department information
- sessions - Academic sessions
- semesters - Semester information
- lecturer_courses - Courses assigned to lecturers
- Student Courses - Student registered courses
- levels - various Levels in the University

#### Relationships:
- One-to-many relationship between faculties and departments
- Many-to-many relationship between students and courses
- One-to-many relationship between lecturers and courses

## Setup Steps:
- Install XAMPP (or similar) for Apache, MySQL, PHP.
- Start Apache and MySQL.
- Create folder sas in htdocs.
- Create subfolders as in structure.
- Place logo.png and background.jpg in images/.
- Create uploads/ in sas, make writable (chmod 777).
- Run SQL in phpMyAdmin to create DB and tables.
- Use Vscode for writing ad editing the codes
- Access http://localhost/sas/index.php.
- Login as admin (username: admin@dou.edu, pass: admin123), create faculties, etc., then users.

## Free Hosted Link:
     https://dou-sarp.infy.uk

## Folder Arrangement:
#### student_assessment_system/
- ├── index.php
- ├── unauthorized.php
- ├── logout.php
- ├── config/
-        └── database.php
- ├── includes/
-        ├── header.php
-        ├── footer.php
-        ├── auth_check.php
-        └── functions.php
- ├── admin/
-        ├── dashboard.php
-        ├── manage_faculties.php
-        ├── manage_departments.php
-        ├── manage_courses.php
-        ├── manage_exam_officers.php
-        ├── manage_lecturers.php
-        ├── manage_students.php
-        └── change_password.php
- ├── lecturer/
-        ├── dashboard.php
-        ├── upload_scores.php
-        ├── export_scores.php
-        └── change_password.php
- ├── exam_officer/
-        ├── dashboard.php
-        ├── export_students.php
-        ├── view_student.php
-        ├── report_sheet.php
-        └── change_password.php
- ├── student/
-        ├── dashboard.php
-        ├── register_courses.php
-        ├── view_report_sheet.php
-        └── change_password.php
- ├── css/
-       └── style.css
- ├── js/
-       └── script.js
- ├── images/
-          └── dou_logo.png
- └── uploads/
-            └── passports/
