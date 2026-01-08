<?php
// student/dashboard.php
// Main dashboard for the Student Panel.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Student role access
check_access('student'); 

// Get Student ID from session
$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['user_name'];

// Initialize variables
$student_info = [];
$todays_lectures = [];
$attendance_summary = [
    'total_lectures_attended' => 0,
    'total_lectures_absent' => 0,
    'total_lectures_taken' => 0,
    'attendance_percentage' => 0.00
];
$error_message = '';

// -----------------------------------------------------------
// 2. Dashboard Data Fetching (Secure Prepared Statements)
// -----------------------------------------------------------
try {
    // --- A. Fetch Student Profile and Class Info ---
    $sql_profile = "
        SELECT 
            st.admissionNo, st.email, 
            c.className, c.yearLevel, c.semester
        FROM tblstudent st
        JOIN tblclass c ON st.classId = c.classId
        WHERE st.studentId = :sid";
        
    $stmt_profile = $pdo->prepare($sql_profile);
    $stmt_profile->execute([':sid' => $studentId]);
    $student_info = $stmt_profile->fetch();

    if (!$student_info) {
        throw new Exception("Student profile data not found.");
    }

    // --- Get student's assigned subjects ---
    $sql_subjects = "SELECT subjectId FROM tblstudent_subject WHERE studentId = :sid";
    $stmt_subjects = $pdo->prepare($sql_subjects);
    $stmt_subjects->execute([':sid' => $studentId]);
    $subjects = $stmt_subjects->fetchAll(PDO::FETCH_COLUMN);


    // --- B. Calculate Attendance Summary (based on student's class) ---
    
    // 1. Total Lectures where student has attendance record
    $sql_total = "
        SELECT COUNT(ar.recordId) AS total_lectures_taken
        FROM tblattendance_record ar
        WHERE ar.studentId = :sid";
        
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([':sid' => $studentId]);
    $attendance_summary['total_lectures_taken'] = (int)$stmt_total->fetchColumn();

    // 2. Total Present/Late/Excused Lectures
    $sql_attended = "
        SELECT COUNT(ar.recordId) AS total_attended
        FROM tblattendance_record ar
        WHERE ar.studentId = :sid AND ar.status IN ('Present', 'Late', 'Excused')";
        
    $stmt_attended = $pdo->prepare($sql_attended);
    $stmt_attended->execute([':sid' => $studentId]);
    $attendance_summary['total_lectures_attended'] = (int)$stmt_attended->fetchColumn();
    
    // 3. Calculate Percentage
    if ($attendance_summary['total_lectures_taken'] > 0) {
        $attendance_summary['attendance_percentage'] = round(
            ($attendance_summary['total_lectures_attended'] / $attendance_summary['total_lectures_taken']) * 100, 
            2
        );
    }
    
    // 4. Calculate Absent (for the card)
    $attendance_summary['total_lectures_absent'] = $attendance_summary['total_lectures_taken'] - $attendance_summary['total_lectures_attended'];

    // --- C. Get Today's Lectures for this student (ONLY subjects assigned to student) ---
    $todayDayOfWeek = date('l');
    $currentDate = date('Y-m-d');
    
    // Get student's classId
    $sql_class = "SELECT classId FROM tblstudent WHERE studentId = :sid";
    $stmt_class = $pdo->prepare($sql_class);
    $stmt_class->execute([':sid' => $studentId]);
    $studentClassId = $stmt_class->fetchColumn();
    
    // Only proceed if student has subjects assigned
    if (!empty($subjects) && $studentClassId) {
        // Get today's lectures - ONLY for subjects student is assigned to via tblstudent_subject
        $sql_today = "
            SELECT 
                tsc.id AS assignmentId,
                tsc.scheduleTime,
                tsc.endTime,
                s.subjectCode, s.subjectName,
                t.firstName AS teacher_fn, t.lastName AS teacher_ln,
                a.attendanceId AS teacher_started,
                ar.status AS attendance_status
            FROM tblteacher_subject_class tsc
            JOIN tblsubject s ON tsc.subjectId = s.subjectId
            JOIN tblteacher t ON tsc.teacherId = t.teacherId
            JOIN tblstudent_subject ss ON ss.subjectId = tsc.subjectId AND ss.studentId = :sid
            LEFT JOIN tblattendance a ON a.assignmentId = tsc.id AND a.dateTaken = :today
            LEFT JOIN tblattendance_record ar ON ar.attendanceId = a.attendanceId AND ar.studentId = :sid2
            WHERE tsc.classId = :classId 
            AND (tsc.dayOfWeek = :dayOfWeek OR tsc.dayOfWeek IS NULL)
            ORDER BY tsc.scheduleTime
        ";
        $stmt_today = $pdo->prepare($sql_today);
        $stmt_today->execute([
            ':today' => $currentDate,
            ':sid' => $studentId,
            ':sid2' => $studentId,
            ':classId' => $studentClassId,
            ':dayOfWeek' => $todayDayOfWeek
        ]);
        $todays_lectures = $stmt_today->fetchAll();
    }

} catch (Exception $e) {
    error_log("Student Dashboard Error: " . $e->getMessage());
    $error_message = "Could not load essential student data. Please contact the administration.";
} catch (PDOException $e) {
    error_log("Student Dashboard DB Error: " . $e->getMessage());
    $error_message = "A database error occurred while loading dashboard data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Student Dashboard | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-user-graduate"></i> My Dashboard</h1>
                <p class="lead">Welcome, <b><?php echo htmlspecialchars($studentName); ?></b>! Your academic overview is below.</p>
                
                <hr>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white"><i class="fas fa-address-card me-2"></i> Personal & Academic Details</div>
                    <div class="card-body">
                        <?php if ($student_info): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <p class="mb-0"><strong>Name:</strong> <?php echo htmlspecialchars($studentName); ?></p>
                                    <p class="mb-0"><strong>Admission No.:</strong> <?php echo htmlspecialchars($student_info['admissionNo']); ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <p class="mb-0"><strong>Class/Level:</strong> <?php echo htmlspecialchars("{$student_info['className']} - Year {$student_info['yearLevel']}"); ?></p>
                                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($student_info['email']); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning m-0">Could not retrieve full profile details.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <h2 class="mt-4 mb-3 text-secondary"><i class="fas fa-chart-pie me-2"></i> Attendance Summary</h2>
                <div class="row g-3">
                    
                    <!-- Overall Percentage Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(102,126,234,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-chart-pie" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Rate</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo htmlspecialchars($attendance_summary['attendance_percentage']); ?>%</div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Overall Percentage</div>
                            </div>
                            <a href="my-attendance.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                View <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Present / Attended Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(17,153,142,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user-check" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Good</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($attendance_summary['total_lectures_attended']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Present</div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Absent Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(235,51,73,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user-xmark" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Alert</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($attendance_summary['total_lectures_absent']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Absent</div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Lectures Card -->
                    <div class="col-6 col-lg-3">
                        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 14px; overflow: hidden; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 25px rgba(79,172,254,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <div class="card-body text-white p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-graduation-cap" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <span class="badge ms-auto" style="background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.65rem;">Total</span>
                                </div>
                                <div style="font-size: 1.75rem; font-weight: 700; line-height: 1.2;"><?php echo number_format($attendance_summary['total_lectures_taken']); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.85;">Lectures</div>
                            </div>
                            <a href="sign-in.php" class="d-block text-white text-decoration-none text-center" style="background: rgba(0,0,0,0.12); padding: 10px; font-size: 0.75rem; font-weight: 500;">
                                Sign In <i class="fas fa-arrow-right ms-1" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Today's Lectures Section -->
                <?php if (empty($subjects) || empty($todays_lectures)): ?>
                    <?php if (empty($subjects)): ?>
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>You are not enrolled in any subjects!</strong>
                        <br>Please contact the Admin to assign your subjects.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i> No lectures scheduled for today (<?php echo date('l'); ?>) for your enrolled subjects.
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <h2 class="mt-4 mb-3 text-secondary"><i class="fas fa-calendar-day me-2"></i> Today's Lectures (<?php echo date('l, M j'); ?>)</h2>
                <div class="card shadow mb-4">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php 
                            $now = time();
                            $currentDate = date('Y-m-d');
                            foreach ($todays_lectures as $lecture): 
                                $startTime = date('h:i A', strtotime($lecture['scheduleTime']));
                                $endTimeStr = $lecture['endTime'] ?? date('H:i:s', strtotime($lecture['scheduleTime'] . ' +1 hour'));
                                $endTime = date('h:i A', strtotime($endTimeStr));
                                
                                $lectureStart = strtotime($currentDate . ' ' . $lecture['scheduleTime']);
                                $lectureEnd = strtotime($currentDate . ' ' . $endTimeStr);
                                
                                // Determine lecture time status
                                if ($now < $lectureStart) {
                                    $timeStatus = 'upcoming';
                                    $timeBadge = '<span class="badge bg-secondary">Upcoming</span>';
                                } elseif ($now >= $lectureStart && $now <= $lectureEnd) {
                                    $timeStatus = 'active';
                                    $timeBadge = '<span class="badge bg-primary">In Progress</span>';
                                } else {
                                    $timeStatus = 'completed';
                                    $timeBadge = '<span class="badge bg-dark">Completed</span>';
                                }
                                
                                // Determine attendance status
                                $attendanceStatus = $lecture['attendance_status'];
                                $teacherStarted = !empty($lecture['teacher_started']); // Did teacher start attendance?
                                
                                if ($attendanceStatus === 'Present') {
                                    $attendanceBadge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span>';
                                    $rowClass = 'list-group-item-success';
                                } elseif ($attendanceStatus === 'Late') {
                                    $attendanceBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Late</span>';
                                    $rowClass = 'list-group-item-warning';
                                } elseif ($attendanceStatus === 'Absent') {
                                    $attendanceBadge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>';
                                    $rowClass = 'list-group-item-danger';
                                } elseif ($attendanceStatus === 'Excused') {
                                    $attendanceBadge = '<span class="badge bg-info"><i class="fas fa-info me-1"></i>Excused</span>';
                                    $rowClass = 'list-group-item-info';
                                } else {
                                    // No attendance record for this student
                                    if ($timeStatus === 'completed') {
                                        // Lecture ended
                                        if ($teacherStarted) {
                                            // Teacher started attendance but student didn't sign in = ABSENT
                                            $attendanceBadge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>';
                                            $rowClass = 'list-group-item-danger';
                                        } else {
                                            // Teacher never started attendance = MISSED (not student's fault)
                                            $attendanceBadge = '<span class="badge bg-secondary"><i class="fas fa-minus-circle me-1"></i>Missed</span>';
                                            $rowClass = '';
                                        }
                                    } elseif ($timeStatus === 'active') {
                                        // Lecture in progress
                                        if ($teacherStarted) {
                                            $attendanceBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation me-1"></i>Sign Now!</span>';
                                        } else {
                                            $attendanceBadge = '<span class="badge bg-secondary"><i class="fas fa-hourglass me-1"></i>Waiting</span>';
                                        }
                                        $rowClass = '';
                                    } else {
                                        // Upcoming
                                        $attendanceBadge = '<span class="badge bg-light text-dark border"><i class="fas fa-clock me-1"></i>Pending</span>';
                                        $rowClass = '';
                                    }
                                }
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center <?php echo $rowClass; ?>">
                                <div>
                                    <strong><?php echo $startTime; ?> - <?php echo $endTime; ?></strong>
                                    <span class="ms-2 text-muted"><?php echo htmlspecialchars($lecture['subjectCode']); ?></span>
                                    <span class="d-none d-md-inline ms-1">- <?php echo htmlspecialchars($lecture['subjectName']); ?></span>
                                    <br class="d-md-none">
                                    <small class="text-muted"><?php echo htmlspecialchars($lecture['teacher_fn'] . ' ' . $lecture['teacher_ln']); ?></small>
                                </div>
                                <div class="text-end">
                                    <?php echo $timeBadge; ?>
                                    <br class="d-md-none">
                                    <?php echo $attendanceBadge; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="sign-in.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-fingerprint me-1"></i> Go to Sign-in Page
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
</body>
</html>