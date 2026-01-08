<?php
// teacher/dashboard.php
// Main dashboard for the Teacher Panel.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Teacher role access
check_access('teacher'); 

// Get Teacher ID from session
$teacherId = $_SESSION['user_id'];
$teacherName = $_SESSION['user_name'];

// Initialize variables
$dashboard_stats = [
    'assigned_classes_count' => 0,
    'assigned_subjects_count' => 0,
    'total_students_in_classes' => 0,
    'recent_attendance_count' => 0,
];

$assignments_list = [];

// -----------------------------------------------------------
// 2. Dashboard Data Fetching (Secure Prepared Statements)
// -----------------------------------------------------------
try {
    // --- A. Count Unique Assigned Classes and Subjects ---
    $sql_counts = "
        SELECT 
            COUNT(DISTINCT tsc.classId) AS assigned_classes_count,
            COUNT(DISTINCT tsc.subjectId) AS assigned_subjects_count
        FROM tblteacher_subject_class tsc
        WHERE tsc.teacherId = :tid";
        
    $stmt_counts = $pdo->prepare($sql_counts);
    $stmt_counts->execute([':tid' => $teacherId]);
    $counts = $stmt_counts->fetch();

    $dashboard_stats['assigned_classes_count'] = $counts['assigned_classes_count'];
    $dashboard_stats['assigned_subjects_count'] = $counts['assigned_subjects_count'];
    
    // --- B. Calculate Total Unique Students Across All Assigned Classes ---
    // Note: This query calculates the count of unique students enrolled in the classes the teacher is assigned to.
    $sql_students = "
        SELECT COUNT(DISTINCT s.studentId) AS total_students_in_classes
        FROM tblstudent s
        WHERE s.classId IN (
            SELECT DISTINCT classId FROM tblteacher_subject_class WHERE teacherId = :tid
        )";
        
    $stmt_students = $pdo->prepare($sql_students);
    $stmt_students->execute([':tid' => $teacherId]);
    $dashboard_stats['total_students_in_classes'] = $stmt_students->fetchColumn();


    // --- C. Count Recent Attendance Taken (e.g., in the last 7 days) ---
    $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
    $sql_attendance = "
        SELECT COUNT(DISTINCT attendanceId)
        FROM tblattendance
        WHERE teacherId = :tid AND dateTaken >= :date_limit";
        
    $stmt_attendance = $pdo->prepare($sql_attendance);
    $stmt_attendance->execute([':tid' => $teacherId, ':date_limit' => $seven_days_ago]);
    $dashboard_stats['recent_attendance_count'] = $stmt_attendance->fetchColumn();


    // --- D. Fetch Current Assignments (for quick list) ---
    $sql_assignments = "
        SELECT 
            s.subjectCode, s.subjectName,
            c.className, c.yearLevel,
            tsc.scheduleTime, tsc.topic
        FROM tblteacher_subject_class tsc
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        JOIN tblclass c ON tsc.classId = c.classId
        WHERE tsc.teacherId = :tid
        ORDER BY c.yearLevel, s.subjectCode";
        
    $stmt_assignments = $pdo->prepare($sql_assignments);
    $stmt_assignments->execute([':tid' => $teacherId]);
    $assignments_list = $stmt_assignments->fetchAll();

} catch (PDOException $e) {
    error_log("Teacher Dashboard DB Error: " . $e->getMessage());
    $error_message = "A database error occurred while loading dashboard data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Teacher Dashboard | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-chalkboard-teacher"></i> Teacher Dashboard</h1>
                <p class="lead">Welcome, **<?php echo htmlspecialchars($teacherName); ?>**! Here is an overview of your teaching load and recent activity.</p>
                
                <hr>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="row g-3">
                    
                    <!-- Assigned Subjects Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(102,126,234,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-book" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Subjects</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['assigned_subjects_count']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Assigned Subjects</div>
                            </div>
                            <a href="take-attendance.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                Take Attendance <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Classes Assigned Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(245,87,108,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-chalkboard" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Classes</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['assigned_classes_count']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Classes Assigned</div>
                            </div>
                            <a href="student-list.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View Students <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Total Students Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(17,153,142,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user-graduate" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Students</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['total_students_in_classes']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Total Students</div>
                            </div>
                            <a href="view-attendance.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View History <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Attendance Taken Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(79,172,254,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-clipboard-check" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">7 Days</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['recent_attendance_count']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Attendance Taken</div>
                            </div>
                            <a href="take-attendance.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                Mark Today <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                </div>
                
                <hr class="mt-5">

                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-list me-2"></i> My Current Lecture Assignments</div>
                    <div class="card-body">
                        <?php if (empty($assignments_list)): ?>
                            <div class="alert alert-warning text-center">
                                You are not currently assigned to any classes or subjects. Please contact the administrator.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Subject</th>
                                            <th>Class/Year</th>
                                            <th>Scheduled Time</th>
                                            <th>Lecture Topic/Info</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments_list as $assignment): ?>
                                            <tr>
                                                <td>**<?php echo htmlspecialchars($assignment['subjectCode']); ?>** - <?php echo htmlspecialchars($assignment['subjectName']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['className']); ?> (Y<?php echo htmlspecialchars($assignment['yearLevel']); ?>)</td>
                                                <td><span class="badge bg-secondary"><?php echo date('h:i A', strtotime($assignment['scheduleTime'])); ?></span></td>
                                                <td><?php echo htmlspecialchars($assignment['topic'] ?? 'N/A'); ?></td>
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