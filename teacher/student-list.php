<?php
// teacher/student-list.php
// Displays a list of all students belonging to the teacher's assigned classes and subjects.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Teacher role access
check_access('teacher'); 

$teacherId = $_SESSION['user_id'];
$error = '';
$student_roster = [];
$assignments_filter = [];

// -----------------------------------------------------------
// 2. Fetch Assigned Students Roster
// -----------------------------------------------------------
try {
    // Fetch unique assignments (Class/Subject combinations) for the filter
    $sql_assignments = "
        SELECT 
            tsc.classId, 
            tsc.subjectId, 
            s.subjectCode, 
            c.className, 
            c.yearLevel
        FROM tblteacher_subject_class tsc
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        JOIN tblclass c ON tsc.classId = c.classId
        WHERE tsc.teacherId = :tid
        ORDER BY c.yearLevel, c.className, s.subjectCode";
        
    $stmt_assignments = $pdo->prepare($sql_assignments);
    $stmt_assignments->execute([':tid' => $teacherId]);
    $assignments_filter = $stmt_assignments->fetchAll();

    // Fetch the detailed list of unique students associated with ALL of this teacher's assignments
    $sql_roster = "
        SELECT DISTINCT
            st.studentId, 
            st.admissionNo, 
            st.firstName, 
            st.lastName, 
            st.email,
            c.className,
            c.yearLevel
        FROM tblstudent st
        JOIN tblclass c ON st.classId = c.classId
        WHERE st.classId IN (
            -- Find all classes the teacher is assigned to
            SELECT DISTINCT classId FROM tblteacher_subject_class WHERE teacherId = :tid
        )
        ORDER BY c.yearLevel, c.className, st.lastName";
        
    $stmt_roster = $pdo->prepare($sql_roster);
    $stmt_roster->execute([':tid' => $teacherId]);
    $student_roster = $stmt_roster->fetchAll();

} catch (PDOException $e) {
    error_log("Teacher Student Roster DB Error: " . $e->getMessage());
    $error = "A database error occurred while loading the student list.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>My Student Roster | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-users"></i> My Student Roster</h1>
                <p class="lead">List of all students enrolled in the classes you are assigned to teach.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="alert alert-info shadow-sm" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Your Assigned Courses</h4>
                    <p class="mb-0">You are currently assigned to **<?php echo count($assignments_filter); ?>** unique lecture assignments across your classes.</p>
                    <small>The list below includes all unique students in those classes.</small>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-list-ul me-2"></i> Enrolled Students (Total: <?php echo count($student_roster); ?>)</div>
                    <div class="card-body">
                        <?php if (empty($student_roster)): ?>
                            <div class="alert alert-warning text-center">
                                No students found in your assigned classes.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="rosterTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Adm No.</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Class/Year</th>
                                            <th>View History</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($student_roster as $student): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($student['admissionNo']); ?></td>
                                                <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['className'] . ' (Y' . $student['yearLevel'] . ')'); ?></td>
                                                <td>
                                                    <a href="view-attendance.php?studentId=<?php echo htmlspecialchars($student['studentId']); ?>" class="btn btn-sm btn-outline-info" title="View Student Attendance History">
                                                        <i class="fas fa-search"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    </body>
</html>