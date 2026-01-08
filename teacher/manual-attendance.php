<?php
// teacher/manual-attendance.php - Manual Attendance Page
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

check_access('teacher'); 

$teacherId = $_SESSION['user_id'];

// Handle AJAX requests FIRST before any other processing
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // AJAX: Get Roster
    if ($_GET['action'] == 'get_roster') {
        $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
        $attendanceDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        try {
            $stmt = $pdo->prepare("SELECT classId, subjectId FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
            $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['status' => 'error', 'message' => 'Assignment not found.']);
                exit;
            }

            // Get all students
            $sql = "SELECT s.studentId, s.firstName, s.lastName, s.admissionNo
                    FROM tblstudent s
                    JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subId
                    WHERE s.classId = :cid
                    ORDER BY s.lastName, s.firstName";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':cid' => $assignment['classId'], ':subId' => $assignment['subjectId']]);
            $students = $stmt->fetchAll();

            // Get attendance status for selected date
            $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
            $stmt_master = $pdo->prepare($sql_master);
            $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
            $attendance = $stmt_master->fetch();

            $statuses = [];
            $attendanceId = null;
            if ($attendance) {
                $attendanceId = $attendance['attendanceId'];
                $sql_status = "SELECT studentId, status, method FROM tblattendance_record WHERE attendanceId = :attid";
                $stmt_status = $pdo->prepare($sql_status);
                $stmt_status->execute([':attid' => $attendanceId]);
                while ($row = $stmt_status->fetch()) {
                    $statuses[$row['studentId']] = [
                        'status' => $row['status'],
                        'method' => $row['method']
                    ];
                }
            }

            echo json_encode([
                'status' => 'success', 
                'students' => $students, 
                'statuses' => $statuses, 
                'attendanceId' => $attendanceId,
                'totalStudents' => count($students)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // AJAX: Save Single Student Attendance
    if ($_GET['action'] == 'save_attendance') {
        $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
        $studentId = filter_input(INPUT_GET, 'studentId', FILTER_VALIDATE_INT);
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $attendanceDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        if (!in_array($status, ['Present', 'Late', 'Absent', 'Excused'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
            exit;
        }
        
        try {
            // Get or create attendance master
            $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
            $stmt_master = $pdo->prepare($sql_master);
            $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
            $attendance = $stmt_master->fetch();

            if (!$attendance) {
                $sql_create = "INSERT INTO tblattendance (assignmentId, teacherId, dateTaken) VALUES (:aid, :tid, :date)";
                $pdo->prepare($sql_create)->execute([':aid' => $assignmentId, ':tid' => $teacherId, ':date' => $attendanceDate]);
                $attendanceId = $pdo->lastInsertId();
            } else {
                $attendanceId = $attendance['attendanceId'];
            }

            // Check if record exists
            $sql_check = "SELECT recordId FROM tblattendance_record WHERE attendanceId = :attid AND studentId = :sid";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':attid' => $attendanceId, ':sid' => $studentId]);
            
            if ($stmt_check->fetch()) {
                $sql_update = "UPDATE tblattendance_record SET status = :status, method = 'Manual' WHERE attendanceId = :attid AND studentId = :sid";
                $pdo->prepare($sql_update)->execute([':status' => $status, ':attid' => $attendanceId, ':sid' => $studentId]);
            } else {
                $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method) VALUES (:attid, :sid, :status, 'Manual')";
                $pdo->prepare($sql_insert)->execute([':attid' => $attendanceId, ':sid' => $studentId, ':status' => $status]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Saved!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save.']);
        }
        exit;
    }
    
    // AJAX: Mark All
    if ($_GET['action'] == 'mark_all') {
        $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
        $markAs = isset($_GET['markAs']) ? $_GET['markAs'] : '';
        $attendanceDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        if (!in_array($markAs, ['Present', 'Absent'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT classId, subjectId FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
            $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['status' => 'error', 'message' => 'Assignment not found.']);
                exit;
            }

            // Get or create attendance master
            $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
            $stmt_master = $pdo->prepare($sql_master);
            $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
            $attendance = $stmt_master->fetch();

            if (!$attendance) {
                $sql_create = "INSERT INTO tblattendance (assignmentId, teacherId, dateTaken) VALUES (:aid, :tid, :date)";
                $pdo->prepare($sql_create)->execute([':aid' => $assignmentId, ':tid' => $teacherId, ':date' => $attendanceDate]);
                $attendanceId = $pdo->lastInsertId();
            } else {
                $attendanceId = $attendance['attendanceId'];
            }

            // Get all students not yet marked
            $sql_students = "SELECT s.studentId FROM tblstudent s
                             JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subId
                             WHERE s.classId = :cid
                             AND s.studentId NOT IN (SELECT studentId FROM tblattendance_record WHERE attendanceId = :attid)";
            $stmt_students = $pdo->prepare($sql_students);
            $stmt_students->execute([':cid' => $assignment['classId'], ':subId' => $assignment['subjectId'], ':attid' => $attendanceId]);
            
            $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method) VALUES (:attid, :sid, :status, 'Manual')";
            $stmt_insert = $pdo->prepare($sql_insert);
            
            $count = 0;
            foreach ($stmt_students->fetchAll() as $student) {
                $stmt_insert->execute([':attid' => $attendanceId, ':sid' => $student['studentId'], ':status' => $markAs]);
                $count++;
            }

            echo json_encode(['status' => 'success', 'message' => "Marked {$count} student(s) as {$markAs}.", 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed.']);
        }
        exit;
    }
    
    // AJAX: Get Summary Stats
    if ($_GET['action'] == 'get_summary') {
        $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
        $attendanceDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        try {
            $stmt = $pdo->prepare("SELECT classId, subjectId FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
            $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['status' => 'error']);
                exit;
            }

            // Total students
            $sql_total = "SELECT COUNT(*) FROM tblstudent s
                          JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subId
                          WHERE s.classId = :cid";
            $stmt_total = $pdo->prepare($sql_total);
            $stmt_total->execute([':cid' => $assignment['classId'], ':subId' => $assignment['subjectId']]);
            $total = $stmt_total->fetchColumn();

            // Get counts by status
            $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
            $stmt_master = $pdo->prepare($sql_master);
            $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
            $attendance = $stmt_master->fetch();

            $present = $late = $absent = $excused = $notMarked = 0;
            
            if ($attendance) {
                $sql_counts = "SELECT status, COUNT(*) as cnt FROM tblattendance_record WHERE attendanceId = :attid GROUP BY status";
                $stmt_counts = $pdo->prepare($sql_counts);
                $stmt_counts->execute([':attid' => $attendance['attendanceId']]);
                while ($row = $stmt_counts->fetch()) {
                    switch ($row['status']) {
                        case 'Present': $present = $row['cnt']; break;
                        case 'Late': $late = $row['cnt']; break;
                        case 'Absent': $absent = $row['cnt']; break;
                        case 'Excused': $excused = $row['cnt']; break;
                    }
                }
            }
            
            $marked = $present + $late + $absent + $excused;
            $notMarked = $total - $marked;

            echo json_encode([
                'status' => 'success',
                'total' => $total,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'notMarked' => $notMarked
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
    
    // Unknown action
    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

// Regular page load - fetch assignments
$error = '';
$success = '';
$assignments = [];

// Fetch Teacher's Assignments
try {
    $sql = "SELECT tsc.id AS assignmentId, s.subjectCode, s.subjectName, c.className, c.yearLevel,
                   tsc.scheduleTime, tsc.endTime, tsc.dayOfWeek
            FROM tblteacher_subject_class tsc
            JOIN tblsubject s ON tsc.subjectId = s.subjectId
            JOIN tblclass c ON tsc.classId = c.classId
            WHERE tsc.teacherId = :tid
            ORDER BY FIELD(tsc.dayOfWeek, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), tsc.scheduleTime";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tid' => $teacherId]);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error loading assignments.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Manual Attendance | <?php echo SITE_NAME; ?></title>
    <style>
        .status-btn { min-width: 80px; }
        .status-btn.active { box-shadow: 0 0 0 3px rgba(0,123,255,0.5); }
        .student-row:hover { background: #f8f9fa; }
        .summary-card { border-left: 4px solid; }
        .summary-card.present { border-color: #28a745; }
        .summary-card.late { border-color: #ffc107; }
        .summary-card.absent { border-color: #dc3545; }
        .summary-card.excused { border-color: #17a2b8; }
        .summary-card.not-marked { border-color: #6c757d; }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="text-primary"><i class="fas fa-user-edit"></i> Manual Attendance</h1>
                    <a href="take-attendance.php" class="btn btn-outline-primary">
                        <i class="fas fa-qrcode me-1"></i> QR Attendance
                    </a>
                </div>

                <!-- Selection Card -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-cog me-2"></i> Select Lecture & Date
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Lecture Assignment</label>
                                <select class="form-select" id="assignmentId">
                                    <option value="">-- Choose Lecture --</option>
                                    <?php 
                                    $currentDay = '';
                                    $todayDayOfWeek = date('l');
                                    foreach ($assignments as $a): 
                                        $day = $a['dayOfWeek'] ?? 'Unscheduled';
                                        $time = date('h:i A', strtotime($a['scheduleTime']));
                                        $isToday = ($day === $todayDayOfWeek);
                                        
                                        if ($day !== $currentDay) {
                                            if ($currentDay !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . $day . ($isToday ? ' ★ TODAY' : '') . '">';
                                            $currentDay = $day;
                                        }
                                    ?>
                                        <option value="<?php echo $a['assignmentId']; ?>">
                                            <?php echo "{$time} | {$a['subjectCode']} - {$a['subjectName']} → {$a['className']}"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($assignments)) echo '</optgroup>'; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Attendance Date</label>
                                <input type="date" class="form-control" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button class="btn btn-success w-100" id="loadBtn">
                                    <i class="fas fa-search me-1"></i> Load
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4 d-none" id="summarySection">
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card summary-card present h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="text-success mb-0" id="presentCount">0</h3>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card summary-card late h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="text-warning mb-0" id="lateCount">0</h3>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card summary-card absent h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="text-danger mb-0" id="absentCount">0</h3>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card summary-card excused h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="text-info mb-0" id="excusedCount">0</h3>
                                <small class="text-muted">Excused</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card summary-card not-marked h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="text-secondary mb-0" id="notMarkedCount">0</h3>
                                <small class="text-muted">Not Marked</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <div class="card bg-dark text-white h-100">
                            <div class="card-body text-center py-2">
                                <h3 class="mb-0" id="totalCount">0</h3>
                                <small>Total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow mb-4 d-none" id="actionsSection">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-bolt me-2"></i> Quick Actions</span>
                    </div>
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <button class="btn btn-success" id="markAllPresentBtn">
                                <i class="fas fa-check-double me-1"></i> Mark All Present
                            </button>
                            <button class="btn btn-danger" id="markAllAbsentBtn">
                                <i class="fas fa-times-circle me-1"></i> Mark Remaining Absent
                            </button>
                        </div>
                        <span class="text-muted ms-3"><i class="fas fa-info-circle me-1"></i> Only affects unmarked students</span>
                    </div>
                </div>

                <!-- Student List -->
                <div class="card shadow mb-4 d-none" id="rosterSection">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-users me-2"></i> Student Roster
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">Adm No</th>
                                        <th width="30%">Student Name</th>
                                        <th width="35%">Status</th>
                                        <th width="15%">Method</th>
                                    </tr>
                                </thead>
                                <tbody id="rosterBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="messageArea"></div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script>
    $(document).ready(function() {
        
        $('#loadBtn').on('click', loadRoster);
        $('#assignmentId, #attendanceDate').on('change', function() {
            $('#summarySection, #actionsSection, #rosterSection').addClass('d-none');
        });

        function loadRoster() {
            const assignmentId = $('#assignmentId').val();
            const attendanceDate = $('#attendanceDate').val();
            
            if (!assignmentId) {
                alert('Please select a lecture first.');
                return;
            }
            
            $('#loadBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Loading...');
            
            $.get('manual-attendance.php', { 
                action: 'get_roster', 
                assignmentId: assignmentId,
                date: attendanceDate
            }, function(res) {
                $('#loadBtn').prop('disabled', false).html('<i class="fas fa-search me-1"></i> Load');
                
                if (res.status === 'success') {
                    if (res.students.length === 0) {
                        $('#rosterBody').html('<tr><td colspan="5" class="text-center py-4 text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No students enrolled in this subject.</td></tr>');
                        $('#rosterSection').removeClass('d-none');
                        $('#summarySection, #actionsSection').addClass('d-none');
                        return;
                    }
                    
                    let html = '';
                    res.students.forEach((s, i) => {
                        const info = res.statuses[s.studentId] || null;
                        const currentStatus = info ? info.status : '';
                        const method = info ? info.method : '-';
                        
                        html += `<tr class="student-row" data-student="${s.studentId}">
                            <td>${i + 1}</td>
                            <td><code>${s.admissionNo}</code></td>
                            <td><strong>${s.firstName} ${s.lastName}</strong></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn status-btn ${currentStatus === 'Present' ? 'btn-success active' : 'btn-outline-success'}" data-status="Present">Present</button>
                                    <button class="btn status-btn ${currentStatus === 'Late' ? 'btn-warning active' : 'btn-outline-warning'}" data-status="Late">Late</button>
                                    <button class="btn status-btn ${currentStatus === 'Absent' ? 'btn-danger active' : 'btn-outline-danger'}" data-status="Absent">Absent</button>
                                    <button class="btn status-btn ${currentStatus === 'Excused' ? 'btn-info active' : 'btn-outline-info'}" data-status="Excused">Excused</button>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary method-badge">${method}</span></td>
                        </tr>`;
                    });
                    
                    $('#rosterBody').html(html);
                    $('#summarySection, #actionsSection, #rosterSection').removeClass('d-none');
                    refreshSummary();
                } else {
                    showMessage('danger', res.message || 'Failed to load roster.');
                }
            }, 'json').fail(function() {
                $('#loadBtn').prop('disabled', false).html('<i class="fas fa-search me-1"></i> Load');
                showMessage('danger', 'Connection error. Please try again.');
            });
        }

        // Status button click
        $(document).on('click', '.status-btn', function() {
            const $btn = $(this);
            const $row = $btn.closest('tr');
            const studentId = $row.data('student');
            const status = $btn.data('status');
            const assignmentId = $('#assignmentId').val();
            const attendanceDate = $('#attendanceDate').val();
            
            // Disable all buttons in this row
            $row.find('.status-btn').prop('disabled', true);
            
            $.get('manual-attendance.php', {
                action: 'save_attendance',
                assignmentId: assignmentId,
                studentId: studentId,
                status: status,
                date: attendanceDate
            }, function(res) {
                $row.find('.status-btn').prop('disabled', false);
                
                if (res.status === 'success') {
                    // Update button styles
                    $row.find('.status-btn').removeClass('active btn-success btn-warning btn-danger btn-info')
                        .addClass(function() {
                            const s = $(this).data('status');
                            return 'btn-outline-' + (s === 'Present' ? 'success' : (s === 'Late' ? 'warning' : (s === 'Absent' ? 'danger' : 'info')));
                        });
                    
                    const btnClass = status === 'Present' ? 'success' : (status === 'Late' ? 'warning' : (status === 'Absent' ? 'danger' : 'info'));
                    $btn.removeClass('btn-outline-' + btnClass).addClass('btn-' + btnClass + ' active');
                    
                    // Update method badge
                    $row.find('.method-badge').text('Manual');
                    
                    refreshSummary();
                } else {
                    showMessage('danger', 'Failed to save: ' + res.message);
                }
            }, 'json').fail(function() {
                $row.find('.status-btn').prop('disabled', false);
                showMessage('danger', 'Connection error.');
            });
        });

        // Mark All Present
        $('#markAllPresentBtn').on('click', function() {
            if (!confirm('Mark all UNMARKED students as Present?')) return;
            markAll('Present');
        });

        // Mark All Absent
        $('#markAllAbsentBtn').on('click', function() {
            if (!confirm('Mark all UNMARKED students as Absent?')) return;
            markAll('Absent');
        });

        function markAll(markAs) {
            const assignmentId = $('#assignmentId').val();
            const attendanceDate = $('#attendanceDate').val();
            
            $.get('manual-attendance.php', {
                action: 'mark_all',
                assignmentId: assignmentId,
                markAs: markAs,
                date: attendanceDate
            }, function(res) {
                if (res.status === 'success') {
                    showMessage('success', res.message);
                    loadRoster(); // Reload to show updated statuses
                } else {
                    showMessage('danger', res.message);
                }
            }, 'json');
        }

        function refreshSummary() {
            const assignmentId = $('#assignmentId').val();
            const attendanceDate = $('#attendanceDate').val();
            
            $.get('manual-attendance.php', {
                action: 'get_summary',
                assignmentId: assignmentId,
                date: attendanceDate
            }, function(res) {
                if (res.status === 'success') {
                    $('#presentCount').text(res.present);
                    $('#lateCount').text(res.late);
                    $('#absentCount').text(res.absent);
                    $('#excusedCount').text(res.excused);
                    $('#notMarkedCount').text(res.notMarked);
                    $('#totalCount').text(res.total);
                }
            }, 'json');
        }

        function showMessage(type, message) {
            $('#messageArea').html(`<div class="alert alert-${type} alert-dismissible fade show">
                <i class="fas fa-${type === 'success' ? 'check' : 'times'}-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`);
        }
    });
    </script>
</body>
</html>
