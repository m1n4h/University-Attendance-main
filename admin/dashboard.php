<?php
// admin/dashboard.php
// Main file for the Admin Panel Dashboard

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Admin role access
check_access('admin'); 

// -----------------------------------------------------------
// 2. Dashboard Data Fetching (Secure Prepared Statements)
// -----------------------------------------------------------
$dashboard_stats = [
    'students' => 0,
    'teachers' => 0,
    'classes' => 0,
    'subjects' => 0
];

try {
    // Total Students
    $stmt_students = $pdo->query("SELECT COUNT(*) AS total FROM tblstudent");
    $dashboard_stats['students'] = $stmt_students->fetchColumn();

    // Total Teachers
    $stmt_teachers = $pdo->query("SELECT COUNT(*) AS total FROM tblteacher");
    $dashboard_stats['teachers'] = $stmt_teachers->fetchColumn();
    
    // Total Classes (Courses/Years)
    $stmt_classes = $pdo->query("SELECT COUNT(*) AS total FROM tblclass");
    $dashboard_stats['classes'] = $stmt_classes->fetchColumn();

    // Total Subjects
    $stmt_subjects = $pdo->query("SELECT COUNT(*) AS total FROM tblsubject");
    $dashboard_stats['subjects'] = $stmt_subjects->fetchColumn();

} catch (PDOException $e) {
    // Log the error but continue execution for UI to load
    error_log("Database Error on Dashboard Stats: " . $e->getMessage());
    $error_message = "A database error occurred while fetching statistics.";
}
?>
<?php include_once '../includes/header.php'; ?>
<title>Admin Dashboard | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>
            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p class="lead">Welcome back, <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>! Here is a summary of the system.</p>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <hr>

                <div class="row g-3">
                    
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
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['students']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Total Students</div>
                            </div>
                            <a href="manage-students.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View Details <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Total Teachers Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(102,126,234,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-chalkboard-teacher" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Teachers</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['teachers']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Total Teachers</div>
                            </div>
                            <a href="manage-teachers.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View Details <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Total Classes Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(245,87,108,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-school" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Classes</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['classes']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Total Classes</div>
                            </div>
                            <a href="manage-classes.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View Details <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Total Subjects Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(79,172,254,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-book" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Subjects</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($dashboard_stats['subjects']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Total Subjects</div>
                            </div>
                            <a href="manage-subjects.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View Details <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                </div>
                <hr class="mt-5">
                
                <div class="row mt-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-secondary text-white"><i class="fas fa-clipboard-list me-2"></i> Quick Actions</div>
                            <div class="list-group list-group-flush">
                                <a href="manage-students.php" class="list-group-item list-group-item-action"><i class="fas fa-plus me-2 text-success"></i> Register New Student</a>
                                <a href="manage-teachers.php" class="list-group-item list-group-item-action"><i class="fas fa-user-plus me-2 text-primary"></i> Add New Teacher</a>
                                <a href="assign-class.php" class="list-group-item list-group-item-action"><i class="fas fa-link me-2 text-warning"></i> Assign Teacher to Class/Subject</a>
                                <a href="report-attendance.php" class="list-group-item list-group-item-action"><i class="fas fa-download me-2 text-danger"></i> Generate Attendance Report</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white"><i class="fas fa-cogs me-2"></i> System Status</div>
                            <div class="card-body">
                                <p><strong>System Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>Database Status:</strong> Connected (PDO)</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>