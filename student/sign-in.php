<?php
// student/sign-in.php
// QR Code attendance sign-in with Device Binding security

require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

check_access('student'); 

$studentId = $_SESSION['user_id'];
$error = '';
$success = '';

$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// -----------------------------------------------------------
// ATTENDANCE SIGN-IN HANDLER (AJAX/POST)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Security token mismatch. Please refresh the page.']);
        exit;
    } 

    $qrToken = filter_input(INPUT_POST, 'qrToken', FILTER_SANITIZE_STRING);
    $deviceFingerprint = filter_input(INPUT_POST, 'deviceFingerprint', FILTER_SANITIZE_STRING);
    $method = 'QR';
    $status = 'Present';

    try {
        $pdo->beginTransaction();

        // A. QR Token is REQUIRED
        if (!$qrToken) {
            echo json_encode(['status' => 'error', 'message' => 'Please scan the QR Code displayed by your lecturer.']);
            exit;
        }
        
        // B. Device fingerprint is REQUIRED
        if (!$deviceFingerprint || strlen($deviceFingerprint) < 32) {
            echo json_encode(['status' => 'error', 'message' => 'Device verification failed. Please enable JavaScript and try again.']);
            exit;
        }
        
        // C. Validate QR Token (5-second expiry!)
        $sql_qr = "SELECT id, teacherId, qrExpiry, scheduleTime, endTime FROM tblteacher_subject_class WHERE qrToken = :token";
        $stmt_qr = $pdo->prepare($sql_qr);
        $stmt_qr->execute([':token' => $qrToken]);
        $assignment = $stmt_qr->fetch();

        if (!$assignment) {
            throw new Exception("Invalid or expired QR Code. The QR code changes every 5 seconds - please scan the current one on screen.");
        }

        if (strtotime($assignment['qrExpiry']) < time()) {
            throw new Exception("QR Code expired! The code changes every 5 seconds. Please scan the NEW code currently displayed on the lecturer's screen.");
        }

        $assignmentId = $assignment['id'];
        $teacherId = $assignment['teacherId'];
        
        // D. Check time window
        $scheduledStart = strtotime($currentDate . ' ' . $assignment['scheduleTime']);
        $endTimeStr = $assignment['endTime'] ?: date('H:i:s', strtotime($assignment['scheduleTime'] . ' +1 hour'));
        $scheduledEnd = strtotime($currentDate . ' ' . $endTimeStr);
        
        if (time() > $scheduledEnd) {
            throw new Exception("Sign-in window is closed. Lecture has ended.");
        }
        
        if (time() > strtotime('+15 minutes', $scheduledStart)) {
            $status = 'Late';
        }

        // E. Check enrollment
        $sql_enroll = "SELECT tsc.id FROM tblteacher_subject_class tsc 
                       JOIN tblstudent s ON s.classId = tsc.classId
                       JOIN tblstudent_subject ss ON ss.studentId = s.studentId AND ss.subjectId = tsc.subjectId
                       WHERE s.studentId = :sid AND tsc.id = :aid";
        $stmt_enroll = $pdo->prepare($sql_enroll);
        $stmt_enroll->execute([':sid' => $studentId, ':aid' => $assignmentId]);
        if (!$stmt_enroll->fetch()) {
            throw new Exception("You are not enrolled in this subject. Please contact the Admin.");
        }

        // F. Get or Create Master Attendance Record
        $sql_master = "SELECT attendanceId FROM tblattendance WHERE assignmentId = :aid AND dateTaken = :date";
        $stmt_master = $pdo->prepare($sql_master);
        $stmt_master->execute([':aid' => $assignmentId, ':date' => $currentDate]);
        $attendance = $stmt_master->fetch();

        if ($attendance) {
            $attendanceId = $attendance['attendanceId'];
        } else {
            $sql_create = "INSERT INTO tblattendance (assignmentId, teacherId, dateTaken) VALUES (:aid, :tid, :date)";
            $stmt_create = $pdo->prepare($sql_create);
            $stmt_create->execute([':aid' => $assignmentId, ':tid' => $teacherId, ':date' => $currentDate]);
            $attendanceId = $pdo->lastInsertId();
        }

        // G. Check if student already signed in
        $sql_check = "SELECT recordId FROM tblattendance_record WHERE attendanceId = :attid AND studentId = :sid";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([':attid' => $attendanceId, ':sid' => $studentId]);
        
        if ($stmt_check->fetch()) {
            throw new Exception("You have already signed in for this lecture.");
        }

        // H. DEVICE BINDING CHECK - One device = One attendance per lecture
        $sql_device = "SELECT da.studentId, s.firstName, s.lastName 
                       FROM tbldevice_attendance da 
                       JOIN tblstudent s ON da.studentId = s.studentId
                       WHERE da.attendanceId = :attid AND da.deviceFingerprint = :fp";
        $stmt_device = $pdo->prepare($sql_device);
        $stmt_device->execute([':attid' => $attendanceId, ':fp' => $deviceFingerprint]);
        $existingDevice = $stmt_device->fetch();
        
        if ($existingDevice) {
            throw new Exception("This device has already been used to mark attendance for this lecture by " . 
                               $existingDevice['firstName'] . " " . $existingDevice['lastName'] . 
                               ". Each device can only mark attendance once per lecture.");
        }

        // I. Record device fingerprint
        $sql_device_insert = "INSERT INTO tbldevice_attendance (attendanceId, deviceFingerprint, studentId) VALUES (:attid, :fp, :sid)";
        $stmt_device_insert = $pdo->prepare($sql_device_insert);
        $stmt_device_insert->execute([':attid' => $attendanceId, ':fp' => $deviceFingerprint, ':sid' => $studentId]);

        // J. Insert attendance record
        $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method, deviceFingerprint) VALUES (:attid, :sid, :status, :method, :fp)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':attid' => $attendanceId,
            ':sid' => $studentId,
            ':status' => $status,
            ':method' => $method,
            ':fp' => $deviceFingerprint
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Successfully signed in as {$status}!"]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------
// FETCH TODAY'S LECTURES
// -----------------------------------------------------------
$active_lectures = [];
$todays_lectures = [];

try {
    $sql_class = "SELECT classId FROM tblstudent WHERE studentId = :sid";
    $stmt_class = $pdo->prepare($sql_class);
    $stmt_class->execute([':sid' => $studentId]);
    $studentClassId = $stmt_class->fetchColumn();

    $todayDayOfWeek = date('l');

    $sql_active = "
        SELECT 
            tsc.id AS assignmentId, 
            tsc.scheduleTime,
            tsc.endTime,
            tsc.dayOfWeek,
            s.subjectCode, s.subjectName,
            t.firstName AS teacher_fn, t.lastName AS teacher_ln
        FROM tblteacher_subject_class tsc
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        JOIN tblteacher t ON tsc.teacherId = t.teacherId
        JOIN tblstudent_subject ss ON ss.subjectId = tsc.subjectId AND ss.studentId = :sid
        WHERE tsc.classId = :classId 
        AND (tsc.dayOfWeek = :today OR tsc.dayOfWeek IS NULL)
        ORDER BY tsc.scheduleTime";
        
    $stmt_active = $pdo->prepare($sql_active);
    $stmt_active->execute([':classId' => $studentClassId, ':today' => $todayDayOfWeek, ':sid' => $studentId]);
    $todays_lectures = $stmt_active->fetchAll();

    $now = time();
    
    foreach ($todays_lectures as $lecture) {
        $startTime = strtotime($currentDate . ' ' . $lecture['scheduleTime']);
        $endTimeStr = $lecture['endTime'] ?: date('H:i:s', strtotime($lecture['scheduleTime'] . ' +1 hour'));
        $endTime = strtotime($currentDate . ' ' . $endTimeStr);
        
        $windowStart = strtotime('-15 minutes', $startTime);
        $windowEnd = $endTime;
        
        if ($now >= $windowStart && $now <= $windowEnd) {
            $sql_check = "SELECT ar.status FROM tblattendance_record ar 
                          JOIN tblattendance a ON ar.attendanceId = a.attendanceId
                          WHERE ar.studentId = :sid AND a.assignmentId = :aid AND a.dateTaken = :date";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':sid' => $studentId, ':aid' => $lecture['assignmentId'], ':date' => $currentDate]);
            $lecture['signed_in_status'] = $stmt_check->fetchColumn();
            $lecture['is_late'] = ($now > strtotime('+15 minutes', $startTime));
            $active_lectures[] = $lecture;
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Attendance Sign-in | <?php echo SITE_NAME; ?></title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        #reader { width: 100%; max-width: 500px; margin: auto; border: 2px solid #007bff; border-radius: 10px; overflow: hidden; }
        .scanner-container { display: none; }
    </style>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="text-primary"><i class="fas fa-fingerprint"></i> Lecture Sign-in</h1>
                    <button class="btn btn-primary btn-lg" id="startScannerBtn">
                        <i class="fas fa-qrcode me-2"></i> Scan QR Code
                    </button>
                </div>

                <div class="scanner-container mb-4" id="scannerSection">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white d-flex justify-content-between">
                            <span><i class="fas fa-camera me-2"></i> QR Scanner</span>
                            <button type="button" class="btn-close btn-close-white" id="stopScannerBtn"></button>
                        </div>
                        <div class="card-body">
                            <div id="reader"></div>
                            <p class="text-center text-muted mt-2"><small><i class="fas fa-shield-alt me-1"></i> Device-bound attendance - one device per lecture</small></p>
                        </div>
                    </div>
                </div>

                <div id="statusMessageContainer"></div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-check me-2"></i> Active Lectures (<?php echo date('l, F j'); ?>)</span>
                        <span><i class="fas fa-clock me-2"></i>Active Time: <span id="liveTime"><?php echo date('h:i:s A'); ?></span></span>
                    </div>
                    <div class="card-body">
                        <?php 
                        $sql_check_subjects = "SELECT COUNT(*) FROM tblstudent_subject WHERE studentId = :sid";
                        $stmt_check = $pdo->prepare($sql_check_subjects);
                        $stmt_check->execute([':sid' => $studentId]);
                        $hasSubjects = $stmt_check->fetchColumn() > 0;
                        ?>
                        <?php if (!$hasSubjects): ?>
                            <div class="alert alert-warning text-center m-0">
                                <i class="fas fa-exclamation-triangle me-2"></i> <strong>You are not enrolled in any subjects!</strong>
                                <br><small>Please contact the Admin to assign your subjects.</small>
                            </div>
                        <?php elseif (empty($active_lectures)): ?>
                            <?php
                            $upcomingCount = 0;
                            $completedCount = 0;
                            $nextLecture = null;
                            $now = time();
                            
                            foreach ($todays_lectures as $lec) {
                                $lecStart = strtotime($currentDate . ' ' . $lec['scheduleTime']);
                                $lecEndStr = $lec['endTime'] ?: date('H:i:s', strtotime($lec['scheduleTime'] . ' +1 hour'));
                                $lecEnd = strtotime($currentDate . ' ' . $lecEndStr);
                                
                                if ($now < $lecStart) {
                                    $upcomingCount++;
                                    if (!$nextLecture) $nextLecture = $lec;
                                } elseif ($now > $lecEnd) {
                                    $completedCount++;
                                }
                            }
                            ?>
                            <div class="alert alert-info text-center m-0">
                                <i class="fas fa-info-circle me-2"></i> <strong>No active lectures at this time.</strong>
                                <?php if ($upcomingCount > 0 && $nextLecture): ?>
                                    <br><small>Next: <strong><?php echo htmlspecialchars($nextLecture['subjectCode']); ?></strong> at <strong><?php echo date('h:i A', strtotime($nextLecture['scheduleTime'])); ?></strong></small>
                                <?php elseif ($completedCount > 0): ?>
                                    <br><small>All <?php echo $completedCount; ?> lecture(s) completed today.</small>
                                <?php else: ?>
                                    <br><small>No lectures scheduled for today.</small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success text-center mb-3">
                                <i class="fas fa-info-circle me-2"></i> <strong><?php echo count($active_lectures); ?> active lecture(s).</strong>
                                Click <strong>"Scan QR"</strong> to mark attendance.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Lecturer</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_lectures as $lecture): ?>
                                            <?php 
                                                $is_signed = !empty($lecture['signed_in_status']); 
                                                $startTime = date('h:i A', strtotime($lecture['scheduleTime']));
                                                $endTimeStr = $lecture['endTime'] ?? date('H:i:s', strtotime($lecture['scheduleTime'] . ' +1 hour'));
                                                $endTime = date('h:i A', strtotime($endTimeStr));
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $startTime; ?></span>
                                                    <small class="text-muted">- <?php echo $endTime; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($lecture['subjectCode'] . ' - ' . $lecture['subjectName']); ?></td>
                                                <td><?php echo htmlspecialchars($lecture['teacher_fn'] . ' ' . $lecture['teacher_ln']); ?></td>
                                                <td>
                                                    <?php if ($is_signed): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i><?php echo $lecture['signed_in_status']; ?></span>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger scan-qr-btn">
                                                            <i class="fas fa-qrcode me-1"></i>Scan QR
                                                        </button>
                                                        <?php if (isset($lecture['is_late']) && $lecture['is_late']): ?>
                                                            <small class="text-warning d-block"><i class="fas fa-clock"></i> Will be Late</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($todays_lectures)): ?>
                <?php
                $lectureIds = array_column($todays_lectures, 'assignmentId');
                $attendanceStatuses = [];
                $teacherStartedMap = [];
                if (!empty($lectureIds)) {
                    $placeholders = implode(',', array_fill(0, count($lectureIds), '?'));
                    
                    $sql_all_status = "SELECT a.assignmentId, ar.status 
                                       FROM tblattendance_record ar 
                                       JOIN tblattendance a ON ar.attendanceId = a.attendanceId
                                       WHERE ar.studentId = ? AND a.dateTaken = ? AND a.assignmentId IN ($placeholders)";
                    $stmt_all = $pdo->prepare($sql_all_status);
                    $params = array_merge([$studentId, $currentDate], $lectureIds);
                    $stmt_all->execute($params);
                    while ($row = $stmt_all->fetch()) {
                        $attendanceStatuses[$row['assignmentId']] = $row['status'];
                    }
                    
                    $sql_teacher_started = "SELECT assignmentId FROM tblattendance WHERE dateTaken = ? AND assignmentId IN ($placeholders)";
                    $stmt_teacher = $pdo->prepare($sql_teacher_started);
                    $params_teacher = array_merge([$currentDate], $lectureIds);
                    $stmt_teacher->execute($params_teacher);
                    while ($row = $stmt_teacher->fetch()) {
                        $teacherStartedMap[$row['assignmentId']] = true;
                    }
                }
                ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-list me-2"></i> Today's Full Schedule
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php 
                            $now = time();
                            foreach ($todays_lectures as $lecture): 
                                $startTime = date('h:i A', strtotime($lecture['scheduleTime']));
                                $endTimeStr = $lecture['endTime'] ?? date('H:i:s', strtotime($lecture['scheduleTime'] . ' +1 hour'));
                                $endTime = date('h:i A', strtotime($endTimeStr));
                                
                                $lectureStart = strtotime($currentDate . ' ' . $lecture['scheduleTime']);
                                $lectureEnd = strtotime($currentDate . ' ' . $endTimeStr);
                                
                                $signedStatus = $attendanceStatuses[$lecture['assignmentId']] ?? null;
                                $teacherStarted = isset($teacherStartedMap[$lecture['assignmentId']]);
                                
                                if ($now < $lectureStart) {
                                    $timeStatus = 'upcoming';
                                    $statusClass = 'text-muted';
                                    $statusIcon = 'fa-clock';
                                    $statusText = 'Upcoming';
                                    $bgClass = '';
                                    $badgeClass = 'bg-secondary';
                                    $attendanceBadge = '<span class="badge bg-light text-dark border ms-1"><i class="fas fa-clock me-1"></i>Pending</span>';
                                } elseif ($now >= $lectureStart && $now <= $lectureEnd) {
                                    $statusClass = 'text-success fw-bold';
                                    $statusIcon = 'fa-play-circle text-success';
                                    $statusText = 'In Progress';
                                    $bgClass = 'list-group-item-success';
                                    $badgeClass = 'bg-success';
                                    if ($signedStatus) {
                                        $attendanceBadge = '<span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>' . $signedStatus . '</span>';
                                    } elseif ($teacherStarted) {
                                        $attendanceBadge = '<span class="badge bg-warning text-dark ms-1"><i class="fas fa-exclamation me-1"></i>Sign Now!</span>';
                                    } else {
                                        $attendanceBadge = '<span class="badge bg-secondary ms-1"><i class="fas fa-hourglass me-1"></i>Waiting</span>';
                                    }
                                } else {
                                    $statusClass = 'text-secondary';
                                    $statusIcon = 'fa-check-circle';
                                    $statusText = 'Completed';
                                    $badgeClass = 'bg-dark';
                                    if ($signedStatus) {
                                        $attendanceBadge = '<span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>' . $signedStatus . '</span>';
                                        $bgClass = '';
                                    } elseif ($teacherStarted) {
                                        $attendanceBadge = '<span class="badge bg-danger ms-1"><i class="fas fa-times me-1"></i>Absent</span>';
                                        $bgClass = 'list-group-item-danger';
                                    } else {
                                        $attendanceBadge = '<span class="badge bg-secondary ms-1"><i class="fas fa-minus-circle me-1"></i>Missed</span>';
                                        $bgClass = '';
                                    }
                                }
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center <?php echo $bgClass; ?>">
                                <div class="<?php echo $statusClass; ?>">
                                    <i class="fas <?php echo $statusIcon; ?> me-2"></i>
                                    <strong><?php echo $startTime; ?> - <?php echo $endTime; ?></strong>
                                    <span class="ms-2"><?php echo htmlspecialchars($lecture['subjectCode'] . ' - ' . $lecture['subjectName']); ?></span>
                                </div>
                                <div>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                                    <?php echo $attendanceBadge; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        // Device Fingerprint Generator - Creates stable unique ID
        function generateDeviceFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('DeviceFingerprint', 2, 2);
            const canvasData = canvas.toDataURL();
            
            const data = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                screen.colorDepth,
                new Date().getTimezoneOffset(),
                navigator.hardwareConcurrency || 'unknown',
                navigator.platform,
                canvasData,
                (navigator.plugins ? Array.from(navigator.plugins).map(p => p.name).join(',') : ''),
                window.devicePixelRatio || 1
            ].join('|||');
            
            // Create hash
            let hash = 0;
            for (let i = 0; i < data.length; i++) {
                const char = data.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            
            // Convert to hex and pad
            const hex = Math.abs(hash).toString(16);
            const fingerprint = hex.padStart(16, '0') + 
                               btoa(navigator.userAgent.substring(0, 50)).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32) +
                               screen.width + screen.height;
            
            return fingerprint.substring(0, 64);
        }
        
        const deviceFingerprint = generateDeviceFingerprint();
        
        // Live clock
        function updateLiveTime() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            document.getElementById('liveTime').textContent = String(hours).padStart(2, '0') + ':' + minutes + ':' + seconds + ' ' + ampm;
        }
        setInterval(updateLiveTime, 1000);
        updateLiveTime();

        $(document).ready(function() {
            const html5QrCode = new Html5Qrcode("reader");
            const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

            function showStatus(type, message) {
                const alertClass = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
                const icon = type === 'success' ? 'check' : (type === 'info' ? 'spinner fa-spin' : 'times');
                $('#statusMessageContainer').html(`<div class="alert alert-${alertClass} alert-dismissible fade show">
                    <i class="fas fa-${icon}-circle me-2"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`);
            }

            function startScanner() {
                $('#scannerSection').slideDown();
                $('#startScannerBtn').hide();
                html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
                    .catch(function(err) {
                        showStatus('error', 'Could not access camera. Please allow camera permission.');
                        $('#scannerSection').slideUp();
                        $('#startScannerBtn').show();
                    });
            }
            
            $('#startScannerBtn').on('click', startScanner);
            $(document).on('click', '.scan-qr-btn', startScanner);

            $('#stopScannerBtn').on('click', function() {
                html5QrCode.stop().then(() => {
                    $('#scannerSection').slideUp();
                    $('#startScannerBtn').show();
                });
            });

            function onScanSuccess(decodedText) {
                html5QrCode.stop().then(() => {
                    $('#scannerSection').slideUp();
                    $('#startScannerBtn').show();
                    showStatus('info', 'Verifying device and processing...');
                    
                    $.post('sign-in.php', { 
                        action: 'sign_in', 
                        qrToken: decodedText, 
                        deviceFingerprint: deviceFingerprint,
                        csrf_token: csrfToken 
                    }, function(res) {
                        if (res.status === 'success') {
                            showStatus('success', res.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showStatus('error', res.message);
                        }
                    }, 'json');
                });
            }
        });
    </script>
</body>
</html>
