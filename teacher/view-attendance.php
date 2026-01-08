<?php
// teacher/view-attendance.php
// Allows teachers to view daily attendance records and generate reports.

require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

check_access('teacher'); 

$teacherId = $_SESSION['user_id'];
$error = '';
$assignments = [];
$attendance_records = [];
$summary = ['total_days' => 0, 'avg_attendance' => 0, 'total_present' => 0, 'total_absent' => 0];

// Get form parameters
$selectedAssignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
$startDate = isset($_GET['startDate']) ? htmlspecialchars(strip_tags($_GET['startDate']), ENT_QUOTES, 'UTF-8') : null;
$endDate = isset($_GET['endDate']) ? htmlspecialchars(strip_tags($_GET['endDate']), ENT_QUOTES, 'UTF-8') : null;

// Set default dates
if (empty($endDate)) $endDate = date('Y-m-d');
if (empty($startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));

// Fetch Teacher's Assignments
try {
    $sql_assignments = "
        SELECT tsc.id AS assignmentId, s.subjectCode, s.subjectName,
               c.className, c.yearLevel, tsc.scheduleTime, tsc.dayOfWeek
        FROM tblteacher_subject_class tsc
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        JOIN tblclass c ON tsc.classId = c.classId
        WHERE tsc.teacherId = :tid
        ORDER BY c.yearLevel, s.subjectCode";
    $stmt_assignments = $pdo->prepare($sql_assignments);
    $stmt_assignments->execute([':tid' => $teacherId]);
    $assignments = $stmt_assignments->fetchAll();
} catch (PDOException $e) {
    $error = "Database error loading assignments.";
}

// Fetch Filtered Attendance Records
if ($selectedAssignmentId) {
    try {
        $sql_records = "
            SELECT da.attendanceId, da.dateTaken, da.timeTaken,
                (SELECT COUNT(*) FROM tblattendance_record WHERE attendanceId = da.attendanceId) AS total_students,
                (SELECT COUNT(*) FROM tblattendance_record WHERE attendanceId = da.attendanceId AND status = 'Present') AS present_count,
                (SELECT COUNT(*) FROM tblattendance_record WHERE attendanceId = da.attendanceId AND status = 'Absent') AS absent_count,
                (SELECT COUNT(*) FROM tblattendance_record WHERE attendanceId = da.attendanceId AND status = 'Late') AS late_count
            FROM tblattendance da
            WHERE da.assignmentId = :aid AND da.teacherId = :tid
            AND da.dateTaken BETWEEN :start_date AND :end_date
            ORDER BY da.dateTaken DESC";
        $stmt_records = $pdo->prepare($sql_records);
        $stmt_records->execute([':aid' => $selectedAssignmentId, ':tid' => $teacherId, ':start_date' => $startDate, ':end_date' => $endDate]);
        $attendance_records = $stmt_records->fetchAll();
        
        // Calculate summary
        $summary['total_days'] = count($attendance_records);
        foreach ($attendance_records as $r) {
            $summary['total_present'] += $r['present_count'] + $r['late_count'];
            $summary['total_absent'] += $r['absent_count'];
        }
        $totalRecords = $summary['total_present'] + $summary['total_absent'];
        if ($totalRecords > 0) {
            $summary['avg_attendance'] = round(($summary['total_present'] / $totalRecords) * 100, 1);
        }
    } catch (PDOException $e) {
        $error = "Database error fetching records.";
    }
}

// AJAX: Get daily record details
if (isset($_GET['action']) && $_GET['action'] == 'get_daily_record' && isset($_GET['attendanceId'])) {
    header('Content-Type: application/json');
    $attendanceId = filter_input(INPUT_GET, 'attendanceId', FILTER_VALIDATE_INT);
    if (!$attendanceId) { echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']); exit; }
    try {
        $sql = "SELECT s.admissionNo, s.firstName, s.lastName, dar.status, dar.method
                FROM tblattendance_record dar
                JOIN tblstudent s ON dar.studentId = s.studentId
                WHERE dar.attendanceId = :attid ORDER BY s.lastName";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':attid' => $attendanceId]);
        echo json_encode(['status' => 'success', 'details' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// CSV Export
if (isset($_GET['action']) && $_GET['action'] == 'export_csv' && $selectedAssignmentId) {
    try {
        // Get assignment info
        $sql_info = "SELECT s.subjectCode, s.subjectName, c.className, c.yearLevel 
                     FROM tblteacher_subject_class tsc
                     JOIN tblsubject s ON tsc.subjectId = s.subjectId
                     JOIN tblclass c ON tsc.classId = c.classId
                     WHERE tsc.id = :aid";
        $stmt_info = $pdo->prepare($sql_info);
        $stmt_info->execute([':aid' => $selectedAssignmentId]);
        $info = $stmt_info->fetch();
        
        $filename = "Attendance_{$info['subjectCode']}_{$info['className']}_{$startDate}_to_{$endDate}.csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Header info
        fputcsv($output, ["Attendance Report"]);
        fputcsv($output, ["Subject: {$info['subjectCode']} - {$info['subjectName']}"]);
        fputcsv($output, ["Class: {$info['className']} (Year {$info['yearLevel']})"]);
        fputcsv($output, ["Period: {$startDate} to {$endDate}"]);
        fputcsv($output, []);
        
        // Get all students and their attendance
        $sql_export = "
            SELECT s.admissionNo, s.firstName, s.lastName, a.dateTaken, ar.status
            FROM tblattendance a
            JOIN tblattendance_record ar ON ar.attendanceId = a.attendanceId
            JOIN tblstudent s ON ar.studentId = s.studentId
            WHERE a.assignmentId = :aid AND a.dateTaken BETWEEN :start AND :end
            ORDER BY s.lastName, s.firstName, a.dateTaken";
        $stmt_export = $pdo->prepare($sql_export);
        $stmt_export->execute([':aid' => $selectedAssignmentId, ':start' => $startDate, ':end' => $endDate]);
        $records = $stmt_export->fetchAll();
        
        // Get unique dates
        $dates = [];
        foreach ($records as $r) {
            if (!in_array($r['dateTaken'], $dates)) $dates[] = $r['dateTaken'];
        }
        sort($dates);
        
        // Header row
        $header = ['Adm No', 'Student Name'];
        foreach ($dates as $d) $header[] = date('M j', strtotime($d));
        $header[] = 'Present';
        $header[] = 'Absent';
        $header[] = '%';
        fputcsv($output, $header);
        
        // Group by student
        $students = [];
        foreach ($records as $r) {
            $key = $r['admissionNo'];
            if (!isset($students[$key])) {
                $students[$key] = ['name' => $r['firstName'] . ' ' . $r['lastName'], 'dates' => []];
            }
            $students[$key]['dates'][$r['dateTaken']] = $r['status'];
        }
        
        // Data rows
        foreach ($students as $admNo => $data) {
            $row = [$admNo, $data['name']];
            $present = 0; $absent = 0;
            foreach ($dates as $d) {
                $status = $data['dates'][$d] ?? '-';
                $row[] = $status == 'Present' ? 'P' : ($status == 'Absent' ? 'A' : ($status == 'Late' ? 'L' : '-'));
                if (in_array($status, ['Present', 'Late'])) $present++;
                elseif ($status == 'Absent') $absent++;
            }
            $total = $present + $absent;
            $row[] = $present;
            $row[] = $absent;
            $row[] = $total > 0 ? round(($present / $total) * 100, 1) . '%' : '-';
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Export failed.";
    }
}

// Get current assignment name
$currentAssignmentName = '';
$currentSubjectCode = '';
if ($selectedAssignmentId) {
    foreach ($assignments as $a) {
        if ($a['assignmentId'] == $selectedAssignmentId) {
            $currentAssignmentName = "{$a['subjectCode']} → {$a['className']} (Y{$a['yearLevel']})";
            $currentSubjectCode = $a['subjectCode'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>View Attendance | <?php echo SITE_NAME; ?></title>
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container-fluid { padding: 10px !important; }
            h1 { font-size: 1.5rem !important; }
            .card-header { padding: 10px 12px !important; font-size: 0.9rem; }
            .card-body { padding: 12px !important; }
            .form-label { font-size: 0.85rem; margin-bottom: 4px; }
            .form-select, .form-control { font-size: 0.9rem; padding: 8px 10px; }
            
            /* Summary cards mobile */
            .summary-card .card-body { padding: 12px !important; }
            .summary-card .stat-value { font-size: 1.4rem !important; }
            .summary-card .stat-label { font-size: 0.65rem !important; }
            .summary-card .stat-icon { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            
            /* Table mobile */
            .table { font-size: 0.8rem; }
            .table th, .table td { padding: 6px 4px !important; white-space: nowrap; }
            .badge { font-size: 0.7rem !important; padding: 3px 6px !important; }
            .btn-sm { font-size: 0.75rem !important; padding: 4px 8px !important; }
            
            /* Hide some columns on mobile */
            .hide-mobile { display: none !important; }
            
            /* Export button */
            .card-header .btn { font-size: 0.75rem !important; padding: 4px 8px !important; }
            .card-header span { font-size: 0.85rem; }
        }
        
        @media (max-width: 576px) {
            .summary-card .stat-value { font-size: 1.2rem !important; }
            .summary-card .stat-icon { width: 28px !important; height: 28px !important; }
            .table th, .table td { padding: 5px 3px !important; }
        }
    </style>
</head>
<body>
<div id="wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include_once '../includes/navbar.php'; ?>
        <div class="container-fluid pt-4">
            <h1 class="mb-4 text-primary"><i class="fas fa-history"></i> Attendance History</h1>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="card shadow mb-3">
                <div class="card-header bg-info text-white py-2"><i class="fas fa-filter me-2"></i> Filter</div>
                <div class="card-body py-2">
                    <form method="GET">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-lg-5">
                                <label class="form-label fw-bold mb-1">Lecture</label>
                                <select class="form-select" name="assignmentId" required>
                                    <option value="">-- Choose --</option>
                                    <?php foreach ($assignments as $a): 
                                        $time = date('h:i A', strtotime($a['scheduleTime']));
                                        $day = $a['dayOfWeek'] ?? '';
                                    ?>
                                    <option value="<?php echo $a['assignmentId']; ?>" <?php echo ($a['assignmentId'] == $selectedAssignmentId) ? 'selected' : ''; ?>>
                                        <?php echo "{$a['subjectCode']} → {$a['className']} | {$day} {$time}"; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label fw-bold mb-1">From</label>
                                <input type="date" class="form-control" name="startDate" value="<?php echo $startDate; ?>" required>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label class="form-label fw-bold mb-1">To</label>
                                <input type="date" class="form-control" name="endDate" value="<?php echo $endDate; ?>" required>
                            </div>
                            <div class="col-12 col-lg-1">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i><span class="d-lg-none ms-2">Search</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selectedAssignmentId && !empty($attendance_records)): ?>
            <!-- Summary Cards -->
            <div class="row g-2 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 h-100 summary-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px;">
                        <div class="card-body text-white p-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="stat-icon" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                            </div>
                            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $summary['avg_attendance']; ?>%</div>
                            <div class="stat-label" style="font-size: 0.7rem; opacity: 0.9;">Avg Rate</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 h-100 summary-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 12px;">
                        <div class="card-body text-white p-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="stat-icon" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $summary['total_present']; ?></div>
                            <div class="stat-label" style="font-size: 0.7rem; opacity: 0.9;">Present</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 h-100 summary-card" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); border-radius: 12px;">
                        <div class="card-body text-white p-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="stat-icon" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-xmark"></i>
                                </div>
                            </div>
                            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $summary['total_absent']; ?></div>
                            <div class="stat-label" style="font-size: 0.7rem; opacity: 0.9;">Absent</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 h-100 summary-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px;">
                        <div class="card-body text-white p-3">
                            <div class="d-flex align-items-center mb-2">
                                <div class="stat-icon" style="width: 38px; height: 38px; border-radius: 8px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700;"><?php echo $summary['total_days']; ?></div>
                            <div class="stat-label" style="font-size: 0.7rem; opacity: 0.9;">Lectures</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Records Table -->
            <div class="card shadow mb-3">
                <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="text-truncate" style="max-width: 60%;"><i class="fas fa-list-alt me-1"></i> <span class="d-none d-sm-inline">Records</span> <?php echo $currentSubjectCode ? "- {$currentSubjectCode}" : ''; ?></span>
                    <?php if (!empty($attendance_records)): ?>
                    <a href="?action=export_csv&assignmentId=<?php echo $selectedAssignmentId; ?>&startDate=<?php echo $startDate; ?>&endDate=<?php echo $endDate; ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i><span class="d-none d-sm-inline ms-1">CSV</span>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$selectedAssignmentId): ?>
                    <div class="alert alert-info text-center mb-0">
                        <i class="fas fa-info-circle me-2"></i> Select a lecture and date range to view records.
                    </div>
                    <?php elseif (empty($attendance_records)): ?>
                    <div class="alert alert-warning text-center mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i> No attendance records found for this period.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th class="hide-mobile">Time</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                    <th>%</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i = 1; foreach ($attendance_records as $r): 
                                $rate = $r['total_students'] > 0 ? round((($r['present_count'] + $r['late_count']) / $r['total_students']) * 100) : 0;
                                $rateClass = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><span class="badge bg-primary"><?php echo date('M j', strtotime($r['dateTaken'])); ?></span></td>
                                <td class="hide-mobile"><?php echo date('h:i A', strtotime($r['timeTaken'])); ?></td>
                                <td><span class="badge bg-success"><?php echo $r['present_count']; ?></span></td>
                                <td><span class="badge bg-warning text-dark"><?php echo $r['late_count']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $r['absent_count']; ?></span></td>
                                <td><span class="badge bg-<?php echo $rateClass; ?>"><?php echo $rate; ?>%</span></td>
                                <td>
                                    <button class="btn btn-sm btn-info view-btn" data-id="<?php echo $r['attendanceId']; ?>" data-date="<?php echo date('M j, Y', strtotime($r['dateTaken'])); ?>" data-bs-toggle="modal" data-bs-target="#detailModal">
                                        <i class="fas fa-eye"></i> View
                                    </button>
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

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i> Attendance - <span id="modalDate"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold mb-3">Lecture: <?php echo htmlspecialchars($currentAssignmentName); ?></p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr><th>#</th><th>Adm No</th><th>Student Name</th><th>Status</th><th>Method</th></tr>
                        </thead>
                        <tbody id="detailBody"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    $('.view-btn').on('click', function() {
        const id = $(this).data('id');
        const date = $(this).data('date');
        $('#modalDate').text(date);
        $('#detailBody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
        
        $.get('view-attendance.php?action=get_daily_record&attendanceId=' + id, function(res) {
            if (res.status === 'success' && res.details.length > 0) {
                let html = '';
                res.details.forEach((r, i) => {
                    let badge = r.status === 'Present' ? 'success' : (r.status === 'Absent' ? 'danger' : (r.status === 'Late' ? 'warning' : 'info'));
                    html += `<tr>
                        <td>${i+1}</td>
                        <td>${r.admissionNo}</td>
                        <td>${r.firstName} ${r.lastName}</td>
                        <td><span class="badge bg-${badge}">${r.status}</span></td>
                        <td><small class="text-muted">${r.method || 'Manual'}</small></td>
                    </tr>`;
                });
                $('#detailBody').html(html);
            } else {
                $('#detailBody').html('<tr><td colspan="5" class="text-center text-danger">No records found.</td></tr>');
            }
        }, 'json');
    });
});
</script>
</body>
</html>
