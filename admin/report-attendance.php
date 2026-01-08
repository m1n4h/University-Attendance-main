<?php
// admin/report-attendance.php
// Admin Report Generation Page with PDF Export

require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

check_access('admin');

$startDate = filter_input(INPUT_GET, 'startDate', FILTER_SANITIZE_STRING) ?? date('Y-m-d', strtotime('-30 days'));
$endDate = filter_input(INPUT_GET, 'endDate', FILTER_SANITIZE_STRING) ?? date('Y-m-d');
$classId = filter_input(INPUT_GET, 'classId', FILTER_VALIDATE_INT);

$attendance_data = [];
$classes = [];

// Fetch Classes for Dropdown
try {
    $stmt_classes = $pdo->query("SELECT classId, className, yearLevel FROM tblclass ORDER BY yearLevel, className");
    $classes = $stmt_classes->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

// Fetch Report Data if Filter Applied
if ($classId) {
    try {
        $sql = "
            SELECT 
                s.admissionNo, s.firstName, s.lastName,
                c.className, c.yearLevel,
                sub.subjectCode, sub.subjectName,
                COUNT(ar.recordId) as total_classes,
                SUM(CASE WHEN ar.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN ar.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN ar.status = 'Late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN ar.status = 'Excused' THEN 1 ELSE 0 END) as excused
            FROM tblattendance_record ar
            JOIN tblattendance a ON ar.attendanceId = a.attendanceId
            JOIN tblteacher_subject_class tsc ON a.assignmentId = tsc.id
            JOIN tblstudent s ON ar.studentId = s.studentId
            JOIN tblclass c ON tsc.classId = c.classId
            JOIN tblsubject sub ON tsc.subjectId = sub.subjectId
            WHERE tsc.classId = :cid AND a.dateTaken BETWEEN :start AND :end
            GROUP BY s.studentId, sub.subjectId
            ORDER BY s.lastName, s.firstName, sub.subjectCode";
            
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $classId, ':start' => $startDate, ':end' => $endDate]);
        $attendance_data = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Database error generating report.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Attendance Reports | <?php echo SITE_NAME; ?></title>
    <!-- jsPDF and AutoTable for PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
                
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">Filter Options</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Class</label>
                                <select name="classId" class="form-select" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['classId']; ?>" <?php echo ($classId == $c['classId']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['className'] . ' - Year ' . $c['yearLevel']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">From</label>
                                <input type="date" name="startDate" class="form-control" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To</label>
                                <input type="date" name="endDate" class="form-control" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-filter"></i> Generate</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($classId && empty($attendance_data)): ?>
                    <div class="alert alert-warning">No attendance records found for this selection.</div>
                <?php elseif (!empty($attendance_data)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Report Results</h6>
                            <button onclick="generatePDF()" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Export to PDF</button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="reportTable" width="100%" cellspacing="0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Admission No</th>
                                            <th>Subject</th>
                                            <th>Total Classes</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                            <th>% Attended</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_data as $row): 
                                            $total = $row['total_classes'];
                                            $attended = $row['present'] + $row['late'] + $row['excused']; // Counting excused/late as attended for basic calc? Or just Present? Let's stick to Present+Late+Excused as "Not Absent"
                                            // Actually, strictly speaking, Present is Present. Let's show % Present.
                                            $percent = $total > 0 ? round(($row['present'] / $total) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                            <td><?php echo htmlspecialchars($row['admissionNo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['subjectCode']); ?></td>
                                            <td><?php echo $total; ?></td>
                                            <td class="text-success"><?php echo $row['present']; ?></td>
                                            <td class="text-danger"><?php echo $row['absent']; ?></td>
                                            <td class="text-warning"><?php echo $row['late']; ?></td>
                                            <td><?php echo $percent; ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>

    <script>
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.text("Attendance Report", 14, 15);
            doc.setFontSize(10);
            doc.text("Generated on: <?php echo date('Y-m-d H:i:s'); ?>", 14, 22);
            doc.text("Period: <?php echo $startDate . ' to ' . $endDate; ?>", 14, 27);

            doc.autoTable({ 
                html: '#reportTable',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [22, 160, 133] }
            });

            doc.save('attendance_report.pdf');
        }
    </script>
</body>
</html>
