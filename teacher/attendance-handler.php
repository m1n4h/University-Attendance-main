<?php
// teacher/attendance-handler.php (AJAX Endpoint Example)
// ... includes, auth_check, CSRF validation ...

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_access('teacher')) {
    // Basic CSRF Check
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Security token mismatch.']);
        exit();
    }

    $teacherId = $_SESSION['user_id'];
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $attendance_data = $_POST['attendance'] ?? []; // Array of [studentId => status]

    // 1. Validate Teacher is assigned to this Class/Subject (Security Check)
    $stmt_check = $pdo->prepare("SELECT id FROM tblteacher_subject_class WHERE teacherId = :tid AND classId = :cid AND subjectId = :sid");
    $stmt_check->execute([':tid' => $teacherId, ':cid' => $classId, ':sid' => $subjectId]);
    if ($stmt_check->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized assignment.']);
        exit();
    }

    // 2. Insert/Update Attendance (Prepared Statement for Security)
    $pdo->beginTransaction();
    try {
        $dateTaken = date('Y-m-d');
        $status = '';
        $studentId = 0;
        
        $stmt_insert_or_update = $pdo->prepare("
            INSERT INTO tblattendance (studentId, teacherId, subjectId, classId, dateTaken, status, takenBy, takenById)
            VALUES (:sid, :tid, :subid, :cid, :date, :status, 'Teacher', :tid)
            ON DUPLICATE KEY UPDATE status = VALUES(status), timeTaken = CURRENT_TIME()
        ");
        
        foreach ($attendance_data as $studentId_str => $status_val) {
            $studentId = (int)$studentId_str; // Cast to integer
            $status = htmlspecialchars($status_val); // Sanitize status (e.g., 'Present', 'Absent')

            $stmt_insert_or_update->execute([
                ':sid' => $studentId, 
                ':tid' => $teacherId, 
                ':subid' => $subjectId, 
                ':cid' => $classId, 
                ':date' => $dateTaken, 
                ':status' => $status
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Attendance successfully recorded.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        // Log the error
        error_log("Attendance error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: Could not record attendance.']);
    }
}
?>