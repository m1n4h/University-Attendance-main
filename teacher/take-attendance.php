<?php
// teacher/take-attendance.php - Dynamic QR Code (5-second rotation) + Manual Attendance
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

check_access('teacher'); 

$teacherId = $_SESSION['user_id'];
$error = '';
$assignments = [];
$todayDayOfWeek = date('l');

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
    $error = "Database error.";
}

// AJAX: Generate NEW QR Token (called every 5 seconds)
if (isset($_GET['action']) && $_GET['action'] == 'generate_qr' && isset($_GET['assignmentId'])) {
    header('Content-Type: application/json');
    $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
    
    try {
        $token = bin2hex(random_bytes(16)) . '_' . time();
        $expiry = date('Y-m-d H:i:s', strtotime('+5 seconds'));

        $sql = "UPDATE tblteacher_subject_class SET qrToken = :token, qrExpiry = :expiry WHERE id = :aid AND teacherId = :tid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token, ':expiry' => $expiry, ':aid' => $assignmentId, ':tid' => $teacherId]);

        $attendanceDate = date('Y-m-d');
        $sql_check = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
        
        if (!$stmt_check->fetch()) {
            $sql_create = "INSERT INTO tblattendance (assignmentId, teacherId, dateTaken) VALUES (:aid, :tid, :date)";
            $stmt_create = $pdo->prepare($sql_create);
            $stmt_create->execute([':aid' => $assignmentId, ':tid' => $teacherId, ':date' => $attendanceDate]);
        }

        echo json_encode(['status' => 'success', 'token' => $token, 'expiry' => $expiry]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to generate QR.']);
    }
    exit;
}

// AJAX: Get Live Attendance Status
if (isset($_GET['action']) && $_GET['action'] == 'get_live_status' && isset($_GET['assignmentId'])) {
    header('Content-Type: application/json');
    $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
    $attendanceDate = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT classId, subjectId FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
        $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            echo json_encode(['status' => 'error']);
            exit;
        }

        $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
        $stmt_master = $pdo->prepare($sql_master);
        $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
        $attendance = $stmt_master->fetch();

        $signedIn = [];
        if ($attendance) {
            $sql_records = "SELECT ar.studentId, ar.status, ar.method, s.firstName, s.lastName, ar.createdAt
                           FROM tblattendance_record ar
                           JOIN tblstudent s ON ar.studentId = s.studentId
                           WHERE ar.attendanceId = :attid
                           ORDER BY ar.createdAt DESC";
            $stmt_records = $pdo->prepare($sql_records);
            $stmt_records->execute([':attid' => $attendance['attendanceId']]);
            $records = $stmt_records->fetchAll();
            
            // Convert time to East Africa Time
            foreach ($records as $r) {
                $timeDisplay = '-';
                if (!empty($r['createdAt'])) {
                    $dt = new DateTime($r['createdAt'], new DateTimeZone('UTC'));
                    $dt->setTimezone(new DateTimeZone('Africa/Dar_es_Salaam'));
                    $timeDisplay = $dt->format('h:i A');
                }
                $signedIn[] = [
                    'studentId' => $r['studentId'],
                    'status' => $r['status'],
                    'method' => $r['method'],
                    'firstName' => $r['firstName'],
                    'lastName' => $r['lastName'],
                    'time' => $timeDisplay
                ];
            }
        }

        $sql_total = "SELECT COUNT(*) FROM tblstudent s
                      JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subId
                      WHERE s.classId = :cid";
        $stmt_total = $pdo->prepare($sql_total);
        $stmt_total->execute([':cid' => $assignment['classId'], ':subId' => $assignment['subjectId']]);
        $totalStudents = $stmt_total->fetchColumn();

        echo json_encode([
            'status' => 'success', 
            'signedIn' => $signedIn,
            'totalStudents' => $totalStudents,
            'signedCount' => count($signedIn)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// AJAX: Get Roster for Manual Attendance
if (isset($_GET['action']) && $_GET['action'] == 'get_roster' && isset($_GET['assignmentId'])) {
    header('Content-Type: application/json');
    $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
    $attendanceDate = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT classId, subjectId FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
        $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            echo json_encode(['status' => 'error']);
            exit;
        }

        $sql = "SELECT s.studentId, s.firstName, s.lastName, s.admissionNo
                FROM tblstudent s
                JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subId
                WHERE s.classId = :cid
                ORDER BY s.lastName, s.firstName";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $assignment['classId'], ':subId' => $assignment['subjectId']]);
        $students = $stmt->fetchAll();

        $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
        $stmt_master = $pdo->prepare($sql_master);
        $stmt_master->execute([':aid' => $assignmentId, ':date' => $attendanceDate]);
        $attendance = $stmt_master->fetch();

        $statuses = [];
        if ($attendance) {
            $sql_status = "SELECT studentId, status FROM tblattendance_record WHERE attendanceId = :attid";
            $stmt_status = $pdo->prepare($sql_status);
            $stmt_status->execute([':attid' => $attendance['attendanceId']]);
            while ($row = $stmt_status->fetch()) {
                $statuses[$row['studentId']] = $row['status'];
            }
        }

        echo json_encode(['status' => 'success', 'students' => $students, 'statuses' => $statuses, 'attendanceId' => $attendance['attendanceId'] ?? null]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// AJAX: Save Manual Attendance
if (isset($_GET['action']) && $_GET['action'] == 'save_manual') {
    header('Content-Type: application/json');
    $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
    $studentId = filter_input(INPUT_GET, 'studentId', FILTER_VALIDATE_INT);
    $status = isset($_GET['status']) ? htmlspecialchars(strip_tags($_GET['status']), ENT_QUOTES, 'UTF-8') : '';
    $attendanceDate = date('Y-m-d');
    
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
            // Update existing
            $sql_update = "UPDATE tblattendance_record SET status = :status, method = 'Manual' WHERE attendanceId = :attid AND studentId = :sid";
            $pdo->prepare($sql_update)->execute([':status' => $status, ':attid' => $attendanceId, ':sid' => $studentId]);
        } else {
            // Insert new
            $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method) VALUES (:attid, :sid, :status, 'Manual')";
            $pdo->prepare($sql_insert)->execute([':attid' => $attendanceId, ':sid' => $studentId, ':status' => $status]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Saved!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed.']);
    }
    exit;
}

// AJAX: Finalize Attendance
if (isset($_GET['action']) && $_GET['action'] == 'finalize_attendance' && isset($_GET['assignmentId'])) {
    header('Content-Type: application/json');
    $assignmentId = filter_input(INPUT_GET, 'assignmentId', FILTER_VALIDATE_INT);
    $attendanceDate = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT classId, subjectId, scheduleTime, endTime FROM tblteacher_subject_class WHERE id = :aid AND teacherId = :tid");
        $stmt->execute([':aid' => $assignmentId, ':tid' => $teacherId]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            echo json_encode(['status' => 'error', 'message' => 'Assignment not found.']);
            exit;
        }

        $endTimeStr = $assignment['endTime'] ?: date('H:i:s', strtotime($assignment['scheduleTime'] . ' +1 hour'));
        if (time() < strtotime($attendanceDate . ' ' . $endTimeStr)) {
            $remaining = ceil((strtotime($attendanceDate . ' ' . $endTimeStr) - time()) / 60);
            echo json_encode(['status' => 'error', 'message' => "Lecture still in progress. Wait {$remaining} minutes."]);
            exit;
        }

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

        $sql_missing = "SELECT s.studentId FROM tblstudent s
                        JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = :subjectId
                        WHERE s.classId = :classId
                        AND s.studentId NOT IN (SELECT ar.studentId FROM tblattendance_record ar WHERE ar.attendanceId = :attendanceId)";
        $stmt_missing = $pdo->prepare($sql_missing);
        $stmt_missing->execute([':classId' => $assignment['classId'], ':subjectId' => $assignment['subjectId'], ':attendanceId' => $attendanceId]);
        
        $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method) VALUES (:attid, :sid, 'Absent', 'Auto')";
        $stmt_insert = $pdo->prepare($sql_insert);
        
        $count = 0;
        foreach ($stmt_missing->fetchAll() as $student) {
            $stmt_insert->execute([':attid' => $attendanceId, ':sid' => $student['studentId']]);
            $count++;
        }

        echo json_encode(['status' => 'success', 'message' => "Marked {$count} student(s) as Absent."]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Take Attendance | <?php echo SITE_NAME; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .qr-container {
            position: relative;
            display: inline-block;
            padding: 25px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        .qr-container #qrcode, .qr-container #qrcode img, .qr-container #qrcode canvas {
            width: 300px !important;
            height: 300px !important;
        }
        .qr-timer-ring {
            position: absolute;
            top: -15px; right: -15px;
            width: 70px; height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
            font-size: 1.8rem;
            box-shadow: 0 5px 20px rgba(102,126,234,0.5);
            animation: pulse-ring 1s infinite;
        }
        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .qr-refresh-indicator {
            position: absolute;
            bottom: -15px; left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: #fff;
            padding: 8px 25px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .qr-refresh-indicator.refreshing {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            animation: blink 0.3s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        /* Fullscreen QR */
        .qr-container-large {
            position: relative;
            display: inline-block;
            padding: 40px;
            background: #fff;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .qr-container-large #qrcodeLarge, .qr-container-large #qrcodeLarge img, .qr-container-large #qrcodeLarge canvas {
            width: 450px !important;
            height: 450px !important;
        }
        .qr-timer-large {
            position: absolute;
            top: -25px; right: -25px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
            font-size: 3rem;
            box-shadow: 0 10px 30px rgba(255,65,108,0.5);
            animation: pulse-ring 1s infinite;
        }
        .live-pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-clipboard-check"></i> Take Attendance</h1>
                
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white"><i class="fas fa-cog me-2"></i> Select Lecture</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">Lecture</label>
                                <select class="form-select form-select-lg" id="assignmentId">
                                    <option value="">-- Choose Lecture --</option>
                                    <?php 
                                    $currentDay = '';
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
                                        <option value="<?php echo $a['assignmentId']; ?>" <?php echo $isToday ? 'class="fw-bold"' : ''; ?>>
                                            <?php echo "{$time} | {$a['subjectCode']} → {$a['className']}"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($assignments)) echo '</optgroup>'; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end">
                                <button class="btn btn-primary btn-lg w-100" id="startBtn">
                                    <i class="fas fa-play me-2"></i>Start Attendance
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic QR Code Section -->
                <div class="card shadow mb-4 d-none" id="qrSection">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-qrcode me-2"></i>Dynamic QR Code <span class="badge bg-warning text-dark ms-2">Auto-Refresh 5s</span></span>
                        <div>
                            <button class="btn btn-sm btn-light me-2" id="fullscreenBtn"><i class="fas fa-expand me-1"></i>Fullscreen</button>
                            <button class="btn btn-sm btn-outline-light" id="stopBtn"><i class="fas fa-stop me-1"></i>Stop</button>
                        </div>
                    </div>
                    <div class="card-body text-center py-4">
                        <div class="qr-container">
                            <div id="qrcode"></div>
                            <div class="qr-timer-ring" id="qrTimer">5</div>
                            <div class="qr-refresh-indicator" id="qrStatus">ACTIVE</div>
                        </div>
                        <div class="mt-4">
                            <p class="text-muted mb-2">QR Code changes every <strong>5 seconds</strong> for security</p>
                            <div class="alert alert-info py-2 d-inline-block">
                                <i class="fas fa-shield-alt me-1"></i> <strong>Anti-Fraud:</strong> Screenshots won't work!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Fullscreen Modal -->
                <div class="modal fade" id="qrFullscreenModal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content bg-dark">
                            <div class="modal-header border-0 bg-dark">
                                <h4 class="text-white"><i class="fas fa-qrcode me-2"></i>Scan to Mark Attendance</h4>
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-compress me-1"></i>Exit</button>
                            </div>
                            <div class="modal-body d-flex flex-column align-items-center justify-content-center">
                                <div class="qr-container-large">
                                    <div id="qrcodeLarge"></div>
                                    <div class="qr-timer-large" id="qrTimerLarge">5</div>
                                </div>
                                <div class="mt-4 text-center">
                                    <h2 class="text-white mb-3">QR Code refreshes every 5 seconds</h2>
                                    <p class="text-warning fs-4"><i class="fas fa-shield-alt me-2"></i>Anti-Fraud Protection Active</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Status -->
                <div class="card shadow mb-4 d-none" id="liveSection">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap">
                        <span><i class="fas fa-users me-2"></i>Live Attendance</span>
                        <div class="mt-2 mt-md-0">
                            <span class="live-pulse"><i class="fas fa-circle text-success me-1"></i></span>
                            <span id="signedCount">0</span>/<span id="totalCount">0</span>
                            <button class="btn btn-info btn-sm ms-2" id="manualBtn"><i class="fas fa-user-plus me-1"></i>Manual</button>
                            <button class="btn btn-warning btn-sm ms-1" id="finalizeBtn"><i class="fas fa-check-double me-1"></i>Finalize</button>
                        </div>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>#</th><th>Student</th><th>Status</th><th>Method</th><th>Time</th></tr>
                            </thead>
                            <tbody id="liveBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Waiting for students...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Manual Attendance Modal -->
                <div class="modal fade" id="manualModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Manual Attendance</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">Mark attendance manually for students who couldn't scan QR code.</p>
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-sm table-hover">
                                        <thead class="table-dark sticky-top">
                                            <tr><th>Student</th><th>Current</th><th>Change To</th></tr>
                                        </thead>
                                        <tbody id="manualRosterBody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="actionResult"></div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script>
    $(document).ready(function() {
        let qrInterval, statusInterval, countdownInterval;
        let qrInstance = null, qrInstanceLarge = null;
        let isRunning = false;
        let countdown = 5;

        $('#startBtn').on('click', function() {
            const assignmentId = $('#assignmentId').val();
            if (!assignmentId) { alert('Please select a lecture first.'); return; }
            
            isRunning = true;
            $('#qrSection, #liveSection').removeClass('d-none');
            $('#startBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Running...');
            
            generateNewQR();
            qrInterval = setInterval(generateNewQR, 5000);
            
            countdown = 5;
            countdownInterval = setInterval(function() {
                countdown--;
                if (countdown <= 0) countdown = 5;
                $('#qrTimer, #qrTimerLarge').text(countdown);
                
                if (countdown <= 2) {
                    $('#qrStatus').text('REFRESHING...').addClass('refreshing');
                } else {
                    $('#qrStatus').text('ACTIVE').removeClass('refreshing');
                }
            }, 1000);
            
            refreshStatus();
            statusInterval = setInterval(refreshStatus, 3000);
        });

        $('#stopBtn').on('click', stopAttendance);
        
        // Fullscreen button - using data attributes
        $('#fullscreenBtn').on('click', function() {
            $('#qrFullscreenModal').modal('show');
        });

        function stopAttendance() {
            isRunning = false;
            clearInterval(qrInterval);
            clearInterval(statusInterval);
            clearInterval(countdownInterval);
            $('#startBtn').prop('disabled', false).html('<i class="fas fa-play me-2"></i>Start Attendance');
            $('#qrSection').addClass('d-none');
            $('#qrFullscreenModal').modal('hide');
        }

        function generateNewQR() {
            if (!isRunning) return;
            const assignmentId = $('#assignmentId').val();
            
            $.get('take-attendance.php', { action: 'generate_qr', assignmentId: assignmentId }, function(res) {
                if (res.status === 'success') {
                    $('#qrcode, #qrcodeLarge').empty();
                    
                    new QRCode(document.getElementById("qrcode"), {
                        text: res.token, width: 300, height: 300, colorDark: "#000", colorLight: "#fff"
                    });
                    new QRCode(document.getElementById("qrcodeLarge"), {
                        text: res.token, width: 450, height: 450, colorDark: "#000", colorLight: "#fff"
                    });
                    countdown = 5;
                }
            }, 'json');
        }

        function refreshStatus() {
            const assignmentId = $('#assignmentId').val();
            if (!assignmentId) return;

            $.get('take-attendance.php', { action: 'get_live_status', assignmentId: assignmentId }, function(res) {
                if (res.status === 'success') {
                    $('#signedCount').text(res.signedCount);
                    $('#totalCount').text(res.totalStudents);
                    
                    if (res.signedIn.length > 0) {
                        let html = '';
                        res.signedIn.forEach((r, i) => {
                            const sc = r.status === 'Present' ? 'success' : (r.status === 'Late' ? 'warning' : 'danger');
                            html += `<tr><td>${i+1}</td><td><strong>${r.firstName} ${r.lastName}</strong></td>
                                <td><span class="badge bg-${sc}">${r.status}</span></td>
                                <td><small>${r.method || 'QR'}</small></td><td><small><i class="fas fa-check-circle text-success me-1"></i>${r.time || '-'}</small></td></tr>`;
                        });
                        $('#liveBody').html(html);
                    }
                }
            }, 'json');
        }

        // Manual button
        $('#manualBtn').on('click', function() {
            const assignmentId = $('#assignmentId').val();
            if (!assignmentId) {
                alert('Please select a lecture first.');
                return;
            }
            
            $('#manualRosterBody').html('<tr><td colspan="3" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading students...</td></tr>');
            $('#manualModal').modal('show');
            
            $.get('take-attendance.php', { action: 'get_roster', assignmentId: assignmentId }, function(res) {
                if (res.status === 'success') {
                    if (res.students.length === 0) {
                        $('#manualRosterBody').html('<tr><td colspan="3" class="text-center py-4 text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No students enrolled in this subject.</td></tr>');
                        return;
                    }
                    
                    let html = '';
                    res.students.forEach(s => {
                        const current = res.statuses[s.studentId] || 'Not Marked';
                        const badgeClass = current === 'Present' ? 'success' : (current === 'Late' ? 'warning' : (current === 'Absent' ? 'danger' : 'secondary'));
                        html += `<tr>
                            <td>${s.firstName} ${s.lastName}<br><small class="text-muted">${s.admissionNo}</small></td>
                            <td><span class="badge bg-${badgeClass}" id="status-badge-${s.studentId}">${current}</span></td>
                            <td>
                                <select class="form-select form-select-sm manual-status" data-student="${s.studentId}">
                                    <option value="">-- Select --</option>
                                    <option value="Present" ${current === 'Present' ? 'selected' : ''}>Present</option>
                                    <option value="Late" ${current === 'Late' ? 'selected' : ''}>Late</option>
                                    <option value="Absent" ${current === 'Absent' ? 'selected' : ''}>Absent</option>
                                    <option value="Excused" ${current === 'Excused' ? 'selected' : ''}>Excused</option>
                                </select>
                            </td>
                        </tr>`;
                    });
                    $('#manualRosterBody').html(html);
                } else {
                    $('#manualRosterBody').html('<tr><td colspan="3" class="text-center py-4 text-danger"><i class="fas fa-times-circle me-2"></i>Failed to load students.</td></tr>');
                }
            }, 'json').fail(function() {
                $('#manualRosterBody').html('<tr><td colspan="3" class="text-center py-4 text-danger"><i class="fas fa-times-circle me-2"></i>Connection error.</td></tr>');
            });
        });

        // Manual status change - instant save
        $(document).on('change', '.manual-status', function() {
            const studentId = $(this).data('student');
            const status = $(this).val();
            const assignmentId = $('#assignmentId').val();
            const $select = $(this);
            const $badge = $('#status-badge-' + studentId);
            
            if (!status) return;
            
            $select.prop('disabled', true);
            
            $.get('take-attendance.php', { 
                action: 'save_manual', assignmentId: assignmentId, studentId: studentId, status: status 
            }, function(res) {
                $select.prop('disabled', false);
                if (res.status === 'success') {
                    const badgeClass = status === 'Present' ? 'success' : (status === 'Late' ? 'warning' : (status === 'Absent' ? 'danger' : 'info'));
                    $badge.removeClass('bg-success bg-warning bg-danger bg-secondary bg-info').addClass('bg-' + badgeClass).text(status);
                    refreshStatus();
                } else {
                    alert('Failed to save: ' + res.message);
                }
            }, 'json').fail(function() {
                $select.prop('disabled', false);
                alert('Connection error. Please try again.');
            });
        });

        $('#finalizeBtn').on('click', function() {
            if (!confirm('Mark all remaining students as ABSENT?')) return;
            
            const assignmentId = $('#assignmentId').val();
            $(this).prop('disabled', true);
            
            $.get('take-attendance.php', { action: 'finalize_attendance', assignmentId: assignmentId }, function(res) {
                $('#finalizeBtn').prop('disabled', false);
                const alertClass = res.status === 'success' ? 'success' : 'danger';
                $('#actionResult').html(`<div class="alert alert-${alertClass}"><i class="fas fa-${res.status === 'success' ? 'check' : 'times'} me-2"></i>${res.message}</div>`);
                if (res.status === 'success') {
                    refreshStatus();
                    stopAttendance();
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
