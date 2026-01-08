<?php
/**
 * Auto Mark Absent - Cron Job / Scheduled Task
 * 
 * This script automatically marks students as "Absent" for lectures they didn't attend.
 * Run this script after each lecture ends (e.g., every hour via cron job or Windows Task Scheduler)
 * 
 * Usage:
 * - Cron (Linux): 0 * * * * php /path/to/cron/auto_mark_absent.php
 * - Task Scheduler (Windows): Run every hour
 * - Or call manually: php auto_mark_absent.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/dbcon.php';

// Get current date and time
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');
$todayDayOfWeek = date('l'); // Monday, Tuesday, etc.

$markedCount = 0;
$errors = [];

try {
    // Find all lectures that have ENDED today (endTime has passed)
    // and have at least one attendance record (meaning teacher started attendance)
    $sql_ended_lectures = "
        SELECT 
            tsc.id AS assignmentId,
            tsc.classId,
            tsc.teacherId,
            tsc.scheduleTime,
            tsc.endTime,
            a.attendanceId
        FROM tblteacher_subject_class tsc
        JOIN tblattendance a ON a.assignmentId = tsc.id AND a.dateTaken = :today
        WHERE tsc.dayOfWeek = :dayOfWeek
        AND TIME(:currentTime) > COALESCE(tsc.endTime, ADDTIME(tsc.scheduleTime, '01:00:00'))
    ";
    
    $stmt = $pdo->prepare($sql_ended_lectures);
    $stmt->execute([
        ':today' => $currentDate,
        ':dayOfWeek' => $todayDayOfWeek,
        ':currentTime' => $currentTime
    ]);
    $endedLectures = $stmt->fetchAll();

    foreach ($endedLectures as $lecture) {
        $attendanceId = $lecture['attendanceId'];
        $classId = $lecture['classId'];

        // Get all students in this class who DON'T have an attendance record for this lecture
        $sql_missing = "
            SELECT s.studentId
            FROM tblstudent s
            WHERE s.classId = :classId
            AND s.studentId NOT IN (
                SELECT ar.studentId 
                FROM tblattendance_record ar 
                WHERE ar.attendanceId = :attendanceId
            )
        ";
        
        $stmt_missing = $pdo->prepare($sql_missing);
        $stmt_missing->execute([
            ':classId' => $classId,
            ':attendanceId' => $attendanceId
        ]);
        $missingStudents = $stmt_missing->fetchAll();

        // Mark each missing student as Absent
        $sql_insert = "INSERT INTO tblattendance_record (attendanceId, studentId, status, method) 
                       VALUES (:attendanceId, :studentId, 'Absent', 'Auto')";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($missingStudents as $student) {
            try {
                $stmt_insert->execute([
                    ':attendanceId' => $attendanceId,
                    ':studentId' => $student['studentId']
                ]);
                $markedCount++;
            } catch (PDOException $e) {
                // Skip if already exists (duplicate key)
                if ($e->getCode() != 23000) {
                    $errors[] = "Student {$student['studentId']}: " . $e->getMessage();
                }
            }
        }
    }

    // Output result (useful for logging)
    echo date('Y-m-d H:i:s') . " - Auto Mark Absent Complete\n";
    echo "Lectures processed: " . count($endedLectures) . "\n";
    echo "Students marked absent: {$markedCount}\n";
    
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $err) {
            echo "  - {$err}\n";
        }
    }

} catch (PDOException $e) {
    echo date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    error_log("Auto Mark Absent Error: " . $e->getMessage());
}
