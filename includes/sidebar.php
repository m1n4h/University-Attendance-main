<?php
// includes/sidebar.php
// Generates the fixed, collapsible left navigation menu based on the user's role.

// Assumes config.php is included and session is active.
$role = $_SESSION['role'] ?? 'guest'; 
$base_url = BASE_URL;

// Get current page filename to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if menu item is active
function isActive($page_name) {
    global $current_page;
    return ($current_page === $page_name) ? 'active' : '';
}
?>

<nav id="sidebar" class="bg-dark text-white vh-100 shadow-lg">
    <div class="sidebar-header">
        <h3><i class="fas fa-graduation-cap text-warning"></i> IFM-AS</h3>
    </div>
    <ul class="list-unstyled components">
        <li class="<?php echo isActive('dashboard.php'); ?>">
            <a href="<?php echo $base_url . $role; ?>/dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        
        <?php if ($role == 'admin'): ?>
            <li class="<?php echo isActive('manage-students.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/manage-students.php">
                    <i class="fas fa-user-graduate me-2"></i> Students
                </a>
            </li>
            <li class="<?php echo isActive('manage-teachers.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/manage-teachers.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                </a>
            </li>
            <li class="<?php echo isActive('manage-classes.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/manage-classes.php">
                    <i class="fas fa-school me-2"></i> Classes
                </a>
            </li>
            <li class="<?php echo isActive('manage-subjects.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/manage-subjects.php">
                    <i class="fas fa-book me-2"></i> Subjects
                </a>
            </li>
            <li class="<?php echo isActive('assign-class.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/assign-class.php">
                    <i class="fas fa-calendar-alt me-2"></i> Timetable
                </a>
            </li>
            <li class="<?php echo isActive('report-attendance.php'); ?>">
                <a href="<?php echo $base_url; ?>admin/report-attendance.php">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role == 'teacher'): ?>
            <li class="<?php echo isActive('take-attendance.php'); ?>">
                <a href="<?php echo $base_url; ?>teacher/take-attendance.php">
                    <i class="fas fa-qrcode me-2"></i> QR Attendance
                </a>
            </li>
            <li class="<?php echo isActive('manual-attendance.php'); ?>">
                <a href="<?php echo $base_url; ?>teacher/manual-attendance.php">
                    <i class="fas fa-user-edit me-2"></i> Manual Attendance
                </a>
            </li>
            <li class="<?php echo isActive('view-attendance.php'); ?>">
                <a href="<?php echo $base_url; ?>teacher/view-attendance.php">
                    <i class="fas fa-history me-2"></i> View History
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role == 'student'): ?>
            <li class="<?php echo isActive('sign-in.php'); ?>">
                <a href="<?php echo $base_url; ?>student/sign-in.php">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In Attendance
                </a>
            </li>
            <li class="<?php echo isActive('my-attendance.php'); ?>">
                <a href="<?php echo $base_url; ?>student/my-attendance.php">
                    <i class="fas fa-calendar-alt me-2"></i> My History
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
