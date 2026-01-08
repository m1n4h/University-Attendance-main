<?php
// admin/assign-class.php
// Handles CRUD operations for Teacher-Subject-Class Assignments (tblteacher_subject_class).

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Admin role access
check_access('admin'); 

// Initialize variables
$error = '';
$success = '';

// -----------------------------------------------------------
// 2. CRUD Operations Handler
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CSRF Protection Check for all POST actions
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        
        switch ($_POST['action']) {
            
            // --- CREATE Assignment ---
            case 'add_assignment':
                // Collect and sanitize input
                $teacherId = filter_input(INPUT_POST, 'teacherId', FILTER_VALIDATE_INT);
                $subjectId = filter_input(INPUT_POST, 'subjectId', FILTER_VALIDATE_INT);
                $classId = filter_input(INPUT_POST, 'classId', FILTER_VALIDATE_INT);
                $dayOfWeek = isset($_POST['dayOfWeek']) ? trim($_POST['dayOfWeek']) : '';
                $scheduleTime = isset($_POST['scheduleTime']) ? trim($_POST['scheduleTime']) : '';
                $endTime = isset($_POST['endTime']) ? trim($_POST['endTime']) : '';
                $topic = isset($_POST['topic']) ? trim($_POST['topic']) : '';

                // Validate day of week
                $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                if (!in_array($dayOfWeek, $validDays)) {
                    $dayOfWeek = null;
                }

                // Server-side validation
                if (!$teacherId || !$subjectId || !$classId || empty($scheduleTime) || empty($dayOfWeek)) {
                    $error = "Teacher, Subject, Class, Day of Week, and Start Time are required.";
                } else {
                    
                    try {
                        // Check if EXACT same schedule exists (same teacher + subject + class + day + time)
                        $sql_check = "SELECT id FROM tblteacher_subject_class 
                                      WHERE teacherId = :tid AND subjectId = :sid AND classId = :cid 
                                      AND dayOfWeek = :day AND scheduleTime = :time";
                        $stmt_check = $pdo->prepare($sql_check);
                        $stmt_check->execute([
                            ':tid' => $teacherId, 
                            ':sid' => $subjectId, 
                            ':cid' => $classId,
                            ':day' => $dayOfWeek,
                            ':time' => $scheduleTime
                        ]);
                        
                        if ($stmt_check->fetch()) {
                            $error = "This exact schedule already exists (same teacher, subject, class, day, and time).";
                        } else {
                            // Insert new timetable entry
                            $sql = "INSERT INTO tblteacher_subject_class (teacherId, subjectId, classId, scheduleTime, endTime, dayOfWeek, topic) 
                                    VALUES (:tid, :sid, :cid, :startTime, :endTime, :day, :topic)";
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                ':tid' => $teacherId, 
                                ':sid' => $subjectId, 
                                ':cid' => $classId, 
                                ':startTime' => $scheduleTime,
                                ':endTime' => !empty($endTime) ? $endTime : null,
                                ':day' => $dayOfWeek,
                                ':topic' => !empty($topic) ? $topic : null
                            ]);
                            $success = "Assignment created successfully! Lecture scheduled for {$dayOfWeek}.";
                        }
                    } catch (PDOException $e) {
                        // Check for duplicate entry error (unique constraint violation)
                        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique_assignment') !== false) {
                            // The database has a unique constraint - we need to work around it
                            // Try to check what constraint is blocking
                            $error = "Cannot add assignment. The database has a unique constraint that prevents this combination. Please contact the administrator to update the database schema, or delete the existing entry first.";
                        } else {
                            $error = "Database error: " . $e->getMessage();
                        }
                        error_log("Assignment creation error: " . $e->getMessage());
                    }
                }
                break;
            
            // --- DELETE Assignment ---
            case 'delete_assignment':
                $assignmentId = filter_input(INPUT_POST, 'assignmentId', FILTER_VALIDATE_INT);
                
                if (!$assignmentId) {
                    $error = "Invalid Assignment ID provided for deletion.";
                    break;
                }

                // Delete the assignment record
                $sql = "DELETE FROM tblteacher_subject_class WHERE id = :aid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':aid' => $assignmentId]);
                    
                    if ($stmt->rowCount()) {
                        $success = "Assignment successfully deleted.";
                    } else {
                        $error = "Deletion failed. Assignment ID not found.";
                    }
                } catch (PDOException $e) {
                    error_log("Assignment deletion error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during deletion.";
                }
                break;

            // --- EDIT Assignment ---
            case 'edit_assignment':
                $assignmentId = filter_input(INPUT_POST, 'assignmentId', FILTER_VALIDATE_INT);
                $teacherId = filter_input(INPUT_POST, 'teacherId', FILTER_VALIDATE_INT);
                $subjectId = filter_input(INPUT_POST, 'subjectId', FILTER_VALIDATE_INT);
                $classId = filter_input(INPUT_POST, 'classId', FILTER_VALIDATE_INT);
                $dayOfWeek = isset($_POST['dayOfWeek']) ? trim($_POST['dayOfWeek']) : '';
                $scheduleTime = isset($_POST['scheduleTime']) ? trim($_POST['scheduleTime']) : '';
                $endTime = isset($_POST['endTime']) ? trim($_POST['endTime']) : '';
                $topic = isset($_POST['topic']) ? trim($_POST['topic']) : '';

                // Validate day of week
                $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                if (!in_array($dayOfWeek, $validDays)) {
                    $error = "Invalid day of week selected.";
                    break;
                }

                // Server-side validation
                if (!$assignmentId || !$teacherId || !$subjectId || !$classId || empty($scheduleTime) || empty($dayOfWeek)) {
                    $error = "All required fields must be filled.";
                } else {
                    try {
                        // Check if another entry with same schedule exists (excluding current one)
                        $sql_check = "SELECT id FROM tblteacher_subject_class 
                                      WHERE teacherId = :tid AND subjectId = :sid AND classId = :cid 
                                      AND dayOfWeek = :day AND scheduleTime = :time AND id != :aid";
                        $stmt_check = $pdo->prepare($sql_check);
                        $stmt_check->execute([
                            ':tid' => $teacherId, 
                            ':sid' => $subjectId, 
                            ':cid' => $classId,
                            ':day' => $dayOfWeek,
                            ':time' => $scheduleTime,
                            ':aid' => $assignmentId
                        ]);
                        
                        if ($stmt_check->fetch()) {
                            $error = "This exact schedule already exists for another entry.";
                        } else {
                            // Update the assignment
                            $sql = "UPDATE tblteacher_subject_class 
                                    SET teacherId = :tid, subjectId = :sid, classId = :cid, 
                                        dayOfWeek = :day, scheduleTime = :startTime, endTime = :endTime, topic = :topic
                                    WHERE id = :aid";
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                ':tid' => $teacherId, 
                                ':sid' => $subjectId, 
                                ':cid' => $classId, 
                                ':day' => $dayOfWeek,
                                ':startTime' => $scheduleTime,
                                ':endTime' => !empty($endTime) ? $endTime : null,
                                ':topic' => !empty($topic) ? $topic : null,
                                ':aid' => $assignmentId
                            ]);
                            
                            if ($stmt->rowCount()) {
                                $success = "Assignment updated successfully!";
                            } else {
                                $success = "No changes were made.";
                            }
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $error = "Cannot update. This schedule conflicts with an existing entry.";
                        } else {
                            $error = "Database error: " . $e->getMessage();
                        }
                        error_log("Assignment update error: " . $e->getMessage());
                    }
                }
                break;

            default:
                $error = "Invalid form action detected.";
        }
    }
}

// -----------------------------------------------------------
// 3. READ Data (Fetch Dropdown Data and Assignments List)
// -----------------------------------------------------------

// Fetch all necessary entities for the Assignment Form
try {
    $teachers = $pdo->query("SELECT teacherId, firstName, lastName, email FROM tblteacher ORDER BY lastName")->fetchAll();
    $classes = $pdo->query("SELECT classId, className, yearLevel, semester FROM tblclass ORDER BY yearLevel, className")->fetchAll();
    $subjects = $pdo->query("SELECT subjectId, subjectCode, subjectName FROM tblsubject ORDER BY subjectCode")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching dropdown data: " . $e->getMessage());
    $error = "Could not load data for assignment form.";
}

// Fetch all existing assignments for the table
$assignments = [];
try {
    $sql_assignments = "
        SELECT 
            tsc.id, tsc.teacherId, tsc.subjectId, tsc.classId,
            t.firstName AS teacher_fn, t.lastName AS teacher_ln,
            s.subjectCode, s.subjectName,
            c.className, c.yearLevel,
            tsc.scheduleTime, tsc.endTime, tsc.dayOfWeek, tsc.topic
        FROM tblteacher_subject_class tsc
        JOIN tblteacher t ON tsc.teacherId = t.teacherId
        JOIN tblsubject s ON tsc.subjectId = s.subjectId
        JOIN tblclass c ON tsc.classId = c.classId
        ORDER BY FIELD(tsc.dayOfWeek, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), tsc.scheduleTime";
    $assignments = $pdo->query($sql_assignments)->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching assignments: " . $e->getMessage());
    $error = "Could not load existing assignments.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Assign Classes to Teachers | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-link"></i> Teacher Assignment</h1>
                <p class="lead">Map teachers to the specific classes and subjects they will teach, defining their lecture schedule.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white"><i class="fas fa-plus me-2"></i> Create New Timetable Entry</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="add_assignment">

                            <div class="row">
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <label for="teacherId" class="form-label">Teacher <span class="text-danger">*</span></label>
                                    <select class="form-select" id="teacherId" name="teacherId" required>
                                        <option value="">-- Select Teacher --</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?php echo htmlspecialchars($t['teacherId']); ?>">
                                                <?php echo htmlspecialchars($t['firstName'] . ' ' . $t['lastName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <label for="classId" class="form-label">Class <span class="text-danger">*</span></label>
                                    <select class="form-select" id="classId" name="classId" required>
                                        <option value="">-- Select Class --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['classId']); ?>">
                                                <?php echo htmlspecialchars($c['className'] . ' - Y' . $c['yearLevel']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-lg-4 col-md-6 mb-3">
                                    <label for="subjectId" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <select class="form-select" id="subjectId" name="subjectId" required>
                                        <option value="">-- Select Subject --</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s['subjectId']); ?>">
                                                <?php echo htmlspecialchars($s['subjectCode'] . ' - ' . $s['subjectName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="dayOfWeek" class="form-label">Day of Week <span class="text-danger">*</span></label>
                                    <select class="form-select" id="dayOfWeek" name="dayOfWeek" required>
                                        <option value="">-- Select Day --</option>
                                        <option value="Monday">Monday</option>
                                        <option value="Tuesday">Tuesday</option>
                                        <option value="Wednesday">Wednesday</option>
                                        <option value="Thursday">Thursday</option>
                                        <option value="Friday">Friday</option>
                                        <option value="Saturday">Saturday</option>
                                        <option value="Sunday">Sunday</option>
                                    </select>
                                </div>
                                
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="scheduleTime" class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="scheduleTime" name="scheduleTime" required>
                                </div>
                                
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="endTime" class="form-label">End Time</label>
                                    <input type="time" class="form-control" id="endTime" name="endTime">
                                    <small class="text-muted">Optional - defaults to 1 hour</small>
                                </div>

                                <div class="col-lg-3 col-md-6 mb-3">
                                    <label for="topic" class="form-label">Topic/Info</label>
                                    <input type="text" class="form-control" id="topic" name="topic" placeholder="e.g., Lab Session">
                                </div>
                            </div>

                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-calendar-plus me-2"></i> Add to Timetable
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white"><i class="fas fa-calendar-alt me-2"></i> Current Timetable</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle" id="assignmentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Teacher</th>
                                        <th>Topic</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <?php 
                                        $startTime = date('h:i A', strtotime($assignment['scheduleTime']));
                                        $endTime = $assignment['endTime'] ? date('h:i A', strtotime($assignment['endTime'])) : date('h:i A', strtotime($assignment['scheduleTime'] . ' +1 hour'));
                                        $dayBadgeClass = [
                                            'Monday' => 'bg-primary',
                                            'Tuesday' => 'bg-success',
                                            'Wednesday' => 'bg-info',
                                            'Thursday' => 'bg-warning text-dark',
                                            'Friday' => 'bg-danger',
                                            'Saturday' => 'bg-secondary',
                                            'Sunday' => 'bg-dark'
                                        ];
                                        $day = $assignment['dayOfWeek'] ?? 'N/A';
                                        $badgeClass = $dayBadgeClass[$day] ?? 'bg-secondary';
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($day); ?></span></td>
                                        <td><small><?php echo $startTime; ?> - <?php echo $endTime; ?></small></td>
                                        <td><?php echo htmlspecialchars($assignment['subjectCode']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['className'] . ' Y' . $assignment['yearLevel']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['teacher_fn'] . ' ' . $assignment['teacher_ln']); ?></td>
                                        <td><small><?php echo htmlspecialchars($assignment['topic'] ?? '-'); ?></small></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editAssignmentModal_<?php echo $assignment['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteAssignmentModal_<?php echo $assignment['id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info text-center">No timetable entries yet. Add lectures above.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Assignment Modals - Outside card for proper rendering -->
                <?php foreach ($assignments as $assignment): ?>
                <!-- Edit Modal -->
                <div class="modal fade" id="editAssignmentModal_<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-warning">
                                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Timetable Entry</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="edit_assignment">
                                    <input type="hidden" name="assignmentId" value="<?php echo $assignment['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Teacher <span class="text-danger">*</span></label>
                                            <select class="form-select" name="teacherId" required>
                                                <?php foreach ($teachers as $t): ?>
                                                    <option value="<?php echo $t['teacherId']; ?>" <?php echo ($t['teacherId'] == $assignment['teacherId']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($t['firstName'] . ' ' . $t['lastName']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Class <span class="text-danger">*</span></label>
                                            <select class="form-select" name="classId" required>
                                                <?php foreach ($classes as $c): ?>
                                                    <option value="<?php echo $c['classId']; ?>" <?php echo ($c['classId'] == $assignment['classId']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['className'] . ' - Y' . $c['yearLevel']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                                            <select class="form-select" name="subjectId" required>
                                                <?php foreach ($subjects as $s): ?>
                                                    <option value="<?php echo $s['subjectId']; ?>" <?php echo ($s['subjectId'] == $assignment['subjectId']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($s['subjectCode'] . ' - ' . $s['subjectName']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Day <span class="text-danger">*</span></label>
                                            <select class="form-select" name="dayOfWeek" required>
                                                <?php 
                                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                                foreach ($days as $d): ?>
                                                    <option value="<?php echo $d; ?>" <?php echo ($d == $assignment['dayOfWeek']) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" name="scheduleTime" value="<?php echo htmlspecialchars($assignment['scheduleTime']); ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">End Time</label>
                                            <input type="time" class="form-control" name="endTime" value="<?php echo htmlspecialchars($assignment['endTime'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Topic</label>
                                            <input type="text" class="form-control" name="topic" value="<?php echo htmlspecialchars($assignment['topic'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Modal -->
                <div class="modal fade" id="deleteAssignmentModal_<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete this assignment?</p>
                                    <p>Assignment: <strong><?php echo htmlspecialchars($assignment['teacher_ln']); ?></strong> teaching <strong><?php echo htmlspecialchars($assignment['subjectCode']); ?></strong> to <strong><?php echo htmlspecialchars($assignment['className']); ?></strong>.</p>
                                    <p class="text-danger"><strong>WARNING:</strong> Deleting this will prevent the teacher from taking attendance for this specific combination.</p>
                                    
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_assignment">
                                    <input type="hidden" name="assignmentId" value="<?php echo $assignment['id']; ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> Confirm Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
</body>
</html>