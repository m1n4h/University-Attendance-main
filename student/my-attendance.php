<?php
// student/my-attendance.php
// Allows students to view their personal attendance history.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Student role access
check_access('student'); 

$studentId = $_SESSION['user_id'];
$studentName = $_SESSION['user_name'];
$error = '';
$subjects = [];
$attendance_records = [];
$summary = ['total_lectures' => 0, 'total_attended' => 0, 'percentage' => 0.00];

// Get form parameters (using GET for filtering/searching)
$selectedSubjectId = filter_input(INPUT_GET, 'subjectId', FILTER_VALIDATE_INT);
$startDate = filter_input(INPUT_GET, 'startDate', FILTER_SANITIZE_STRING);
$endDate = filter_input(INPUT_GET, 'endDate', FILTER_SANITIZE_STRING);
$exportFormat = filter_input(INPUT_GET, 'export', FILTER_SANITIZE_STRING);

// Set default dates if not provided
if (empty($endDate)) {
    $endDate = date('Y-m-d');
}
if (empty($startDate)) {
    // Default to the last 90 days for history
    $startDate = date('Y-m-d', strtotime('-90 days'));
}

// -----------------------------------------------------------
// 2. Fetch Student's Subjects (ALL subjects from class + attendance records)
// -----------------------------------------------------------
try {
    // Get student's classId first
    $sql_class = "SELECT classId FROM tblstudent WHERE studentId = :sid";
    $stmt_class = $pdo->prepare($sql_class);
    $stmt_class->execute([':sid' => $studentId]);
    $studentClassId = $stmt_class->fetchColumn();

    // Get ALL subjects - from multiple sources:
    // 1. Subjects assigned to student in tblstudent_subject
    // 2. Subjects where student has attendance records
    // 3. Subjects assigned to student's class (tblteacher_subject_class)
    $sql_subjects = "
        SELECT DISTINCT s.subjectId, s.subjectCode, s.subjectName
        FROM tblsubject s
        WHERE s.subjectId IN (
            -- Subjects assigned to student
            SELECT ss.subjectId FROM tblstudent_subject ss WHERE ss.studentId = :sid1
            UNION
            -- Subjects where student has attendance records
            SELECT tsc.subjectId 
            FROM tblattendance_record ar
            JOIN tblattendance a ON ar.attendanceId = a.attendanceId
            JOIN tblteacher_subject_class tsc ON a.assignmentId = tsc.id
            WHERE ar.studentId = :sid2
            UNION
            -- Subjects assigned to student's class
            SELECT tsc2.subjectId 
            FROM tblteacher_subject_class tsc2
            WHERE tsc2.classId = :classId
        )
        ORDER BY s.subjectCode";
        
    $stmt_subjects = $pdo->prepare($sql_subjects);
    $stmt_subjects->execute([':sid1' => $studentId, ':sid2' => $studentId, ':classId' => $studentClassId]);
    $subjects = $stmt_subjects->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
    $error = "A database error occurred while loading your subjects.";
}

// -----------------------------------------------------------
// 3. Fetch Filtered Attendance Records & Calculate Summary
// -----------------------------------------------------------
try {
    // Get ALL attendance records for this student (not limited to tblstudent_subject)
    $sql_records = "
        SELECT 
            a.dateTaken, a.timeTaken, ar.status, ar.method AS takenBy, 
            s.subjectCode, s.subjectName
        FROM tblattendance_record ar
        JOIN tblattendance a ON ar.attendanceId = a.attendanceId
        JOIN tblteacher_subject_class tsc ON a.assignmentId = tsc.id
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        WHERE ar.studentId = :sid
        AND a.dateTaken BETWEEN :start_date AND :end_date";
        
    $params = [
        ':sid' => $studentId, 
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($selectedSubjectId) {
        $sql_records .= " AND tsc.subjectId = :subid";
        $params[':subid'] = $selectedSubjectId;
    }
    
    $sql_records .= " ORDER BY a.dateTaken DESC, a.timeTaken DESC";

    $stmt_records = $pdo->prepare($sql_records);
    $stmt_records->execute($params);
    $attendance_records = $stmt_records->fetchAll();
    
    // Calculate Summary from fetched records
    $summary['total_lectures'] = count($attendance_records);
    
    foreach ($attendance_records as $record) {
        if (in_array($record['status'], ['Present', 'Late', 'Excused'])) {
            $summary['total_attended']++;
        }
    }

    if ($summary['total_lectures'] > 0) {
        $summary['percentage'] = round(($summary['total_attended'] / $summary['total_lectures']) * 100, 2);
    }

} catch (PDOException $e) {
    error_log("Error fetching student attendance records: " . $e->getMessage());
    $error = "A database error occurred while fetching your attendance history.";
}

// Helper to get the full subject name for display
$currentSubjectName = 'All Subjects';
if ($selectedSubjectId) {
    foreach ($subjects as $s) {
        if ($s['subjectId'] == $selectedSubjectId) {
            $currentSubjectName = $s['subjectCode'] . ' - ' . $s['subjectName'];
            break;
        }
    }
}

// -----------------------------------------------------------
// 4. Export Handler (CSV/Excel)
// -----------------------------------------------------------
if ($exportFormat === 'csv' && !empty($attendance_records)) {
    // Generate filename
    $filename = 'My_Attendance_Report_' . date('Ymd_His') . '.csv';
    
    // Set headers for CSV download - prevent any output before this
    ob_clean(); // Clear any previous output
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write report header info
    fputcsv($output, ['ATTENDANCE REPORT', '', '', '', '', '']);
    fputcsv($output, ['Student Name', $studentName, '', '', '', '']);
    fputcsv($output, ['Subject', $currentSubjectName, '', '', '', '']);
    fputcsv($output, ['Period', $startDate . ' to ' . $endDate, '', '', '', '']);
    fputcsv($output, ['Report Generated', date('d-M-Y H:i:s'), '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '']); // Empty row
    
    // Summary
    fputcsv($output, ['SUMMARY', '', '', '', '', '']);
    fputcsv($output, ['Total Lectures', $summary['total_lectures'], '', '', '', '']);
    fputcsv($output, ['Attended', $summary['total_attended'], '', '', '', '']);
    fputcsv($output, ['Missed', $summary['total_lectures'] - $summary['total_attended'], '', '', '', '']);
    fputcsv($output, ['Attendance Rate', $summary['percentage'] . '%', '', '', '', '']);
    fputcsv($output, ['', '', '', '', '', '']); // Empty row
    
    // Write column headers
    fputcsv($output, ['No', 'Subject Code', 'Subject Name', 'Date', 'Time', 'Status', 'Method']);
    
    // Write data rows
    $counter = 1;
    foreach ($attendance_records as $record) {
        $dateFormatted = date('d-M-Y', strtotime($record['dateTaken']));
        $timeFormatted = date('h:i A', strtotime($record['timeTaken']));
        $methodText = ($record['takenBy'] == 'QR') ? 'QR Scan' : 'Manual';
        
        fputcsv($output, [
            $counter++,
            $record['subjectCode'],
            $record['subjectName'],
            $dateFormatted,
            $timeFormatted,
            $record['status'],
            $methodText
        ]);
    }
    
    fclose($output);
    exit;
}

// Function to determine the badge class for status
function get_status_badge($status) {
    switch ($status) {
        case 'Present': return 'bg-success';
        case 'Absent': return 'bg-danger';
        case 'Late': return 'bg-warning text-dark';
        case 'Excused': return 'bg-info';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>My Attendance History | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-calendar-alt"></i> My Attendance History</h1>
                <p class="lead">View detailed records of your attendance for all enrolled subjects.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white"><i class="fas fa-filter me-2"></i> Filter Records</div>
                    <div class="card-body">
                        <form method="GET" action="my-attendance.php">
                            <div class="row align-items-end">
                                <div class="col-lg-5 col-md-12 mb-3">
                                    <label for="subjectId" class="form-label fw-bold">Select Subject</label>
                                    <select class="form-select" id="subjectId" name="subjectId">
                                        <option value="">-- All Subjects --</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s['subjectId']); ?>" <?php echo ($s['subjectId'] == $selectedSubjectId) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars("{$s['subjectCode']} - {$s['subjectName']}"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="startDate" class="form-label fw-bold">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" value="<?php echo htmlspecialchars($startDate); ?>" required>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="endDate" class="form-label fw-bold">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" value="<?php echo htmlspecialchars($endDate); ?>" required>
                                </div>
                                <div class="col-lg-1 col-md-12 mb-3">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Go</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white"><i class="fas fa-chart-line me-2"></i> Attendance Rate (<?php echo htmlspecialchars($currentSubjectName); ?>)</div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6 text-center border-end">
                                <h1 class="display-3 fw-bold text-success"><?php echo htmlspecialchars($summary['percentage']); ?>%</h1>
                                <p class="lead">Overall Attendance Percentage</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Total Lectures Tracked:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($summary['total_lectures']); ?></span></p>
                                <p class="mb-1"><strong>Lectures Attended:</strong> <span class="badge bg-success"><?php echo htmlspecialchars($summary['total_attended']); ?></span></p>
                                <p class="mb-0"><strong>Lectures Missed:</strong> <span class="badge bg-danger"><?php echo htmlspecialchars($summary['total_lectures'] - $summary['total_attended']); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-list me-2"></i> Detailed Records
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_records)): ?>
                            <div class="alert alert-warning text-center">
                                No attendance records found for the selected filters.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="recordsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Time Recorded</th>
                                            <th>Status</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td>**<?php echo htmlspecialchars($record['subjectCode']); ?>** - <?php echo htmlspecialchars($record['subjectName']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo date('Y-m-d', strtotime($record['dateTaken'])); ?></span></td>
                                                <td><?php echo date('h:i A', strtotime($record['timeTaken'])); ?></td>
                                                <td><span class="badge <?php echo get_status_badge($record['status']); ?>"><?php echo htmlspecialchars($record['status']); ?></span></td>
                                                <td><?php echo htmlspecialchars($record['takenBy'] == 'QR' ? 'QR Scan' : 'Manual/Teacher'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-end">
                         <?php if (!empty($attendance_records)): ?>
                            <?php 
                            // Build export URL with current filters
                            $exportParams = http_build_query([
                                'subjectId' => $selectedSubjectId,
                                'startDate' => $startDate,
                                'endDate' => $endDate,
                                'export' => 'csv'
                            ]);
                            ?>
                            <a href="my-attendance.php?<?php echo $exportParams; ?>" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i> Download Excel/CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>