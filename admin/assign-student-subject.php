<?php
// admin/assign-student-subject.php
// Handles assignment of Subjects to Students (tblstudent_subject).

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
// 2. CRUD Operations Handler (Focus: CREATE/DELETE)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // CSRF Protection Check
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        
        // --- DELETE Subject Assignment ---
        if ($_POST['action'] === 'delete_assignment') {
            $assignmentId = filter_input(INPUT_POST, 'assignmentId', FILTER_VALIDATE_INT);
            
            if (!$assignmentId) {
                $error = "Invalid Assignment ID provided for deletion.";
            } else {
                $sql = "DELETE FROM tblstudent_subject WHERE id = :aid";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':aid' => $assignmentId]);
                    $success = "Student subject assignment successfully removed.";
                } catch (PDOException $e) {
                    error_log("Student assignment deletion error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during deletion.";
                }
            }
        }
        
        // --- CREATE Multiple Subject Assignments ---
        elseif ($_POST['action'] === 'add_assignments') {
            $studentId = filter_input(INPUT_POST, 'studentId', FILTER_VALIDATE_INT);
            $selectedSubjects = $_POST['subjectIds'] ?? []; // Array of selected subject IDs

            if (!$studentId || empty($selectedSubjects)) {
                $error = "Student and at least one subject must be selected.";
            } else {
                
                $pdo->beginTransaction();
                try {
                    $sql = "INSERT IGNORE INTO tblstudent_subject (studentId, subjectId) VALUES (:sid, :subid)";
                    $stmt = $pdo->prepare($sql);

                    $insertCount = 0;
                    foreach ($selectedSubjects as $subjectId) {
                        $subjectIdInt = filter_var($subjectId, FILTER_VALIDATE_INT);
                        if ($subjectIdInt) {
                            $stmt->execute([':sid' => $studentId, ':subid' => $subjectIdInt]);
                            $insertCount += $stmt->rowCount(); // Count successful, non-ignored inserts
                        }
                    }

                    $pdo->commit();
                    $success = "**{$insertCount}** new subject(s) assigned to the student successfully. Duplicate assignments were ignored.";

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Multi-assignment error: " . $e->getMessage());
                    $error = "A database error occurred during mass assignment.";
                }
            }
        }
    }
}

// -----------------------------------------------------------
// 3. READ Data (Fetch Students, Subjects, and Current Assignments)
// -----------------------------------------------------------

// Fetch all Students (grouped by class for better organization)
$students = [];
try {
    $students = $pdo->query("SELECT s.studentId, s.firstName, s.lastName, s.admissionNo, c.className 
                             FROM tblstudent s JOIN tblclass c ON s.classId = c.classId 
                             ORDER BY c.className, s.lastName")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// Fetch all Subjects for the Checkbox List
$allSubjects = [];
try {
    $allSubjects = $pdo->query("SELECT subjectId, subjectCode, subjectName FROM tblsubject ORDER BY subjectCode")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
}


// Function to fetch current assignments for a specific student (for the AJAX view)
function get_student_assignments($pdo, $studentId) {
    $sql = "
        SELECT 
            ss.id, 
            s.subjectCode, 
            s.subjectName
        FROM tblstudent_subject ss
        JOIN tblsubject s ON ss.subjectId = s.subjectId
        WHERE ss.studentId = :sid
        ORDER BY s.subjectCode";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching specific student assignments: " . $e->getMessage());
        return [];
    }
}

// -----------------------------------------------------------
// 4. AJAX Endpoint Simulation (If requested)
// -----------------------------------------------------------
// NOTE: For a clean separation, this logic would ideally be in a separate file (e.g., /admin/ajax/get_student_assignments.php).
// For simplicity, we are handling potential AJAX request in the same file.
if (isset($_GET['action']) && $_GET['action'] == 'get_assignments' && isset($_GET['studentId'])) {
    header('Content-Type: application/json');
    $studentId = filter_input(INPUT_GET, 'studentId', FILTER_VALIDATE_INT);
    
    if ($studentId) {
        $assignments = get_student_assignments($pdo, $studentId);
        echo json_encode(['status' => 'success', 'assignments' => $assignments]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID.']);
    }
    exit; // Crucial: Stop processing the rest of the HTML
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Assign Subjects to Students | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-book-reader"></i> Student Subject Assignment</h1>
                <p class="lead">Assign multiple subjects to a specific student for attendance tracking.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white"><i class="fas fa-user-plus me-2"></i> Mass Subject Assignment</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="add_assignments">

                            <div class="mb-4">
                                <label for="studentId" class="form-label fw-bold">1. Select Student <span class="text-danger">*</span></label>
                                <select class="form-select" id="studentId" name="studentId" required>
                                    <option value="">-- Select Student for Assignment --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo htmlspecialchars($student['studentId']); ?>">
                                            <?php echo htmlspecialchars("{$student['admissionNo']} - {$student['firstName']} {$student['lastName']} ({$student['className']})"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">2. Select Subjects to Assign <span class="text-danger">*</span></label>
                                <div class="border p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                                    <?php if (empty($allSubjects)): ?>
                                        <div class="alert alert-warning m-0">No subjects defined yet. Please define subjects first.</div>
                                    <?php endif; ?>
                                    <div class="row">
                                        <?php foreach ($allSubjects as $subject): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjectIds[]" value="<?php echo htmlspecialchars($subject['subjectId']); ?>" id="subject_<?php echo htmlspecialchars($subject['subjectId']); ?>">
                                                    <label class="form-check-label" for="subject_<?php echo htmlspecialchars($subject['subjectId']); ?>">
                                                        <?php echo htmlspecialchars($subject['subjectCode'] . ' - ' . $subject['subjectName']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-link me-2"></i> Assign Selected Subjects to Student
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white"><i class="fas fa-list-alt me-2"></i> View/Remove Current Assignments</div>
                    <div class="card-body">
                        <div class="alert alert-primary" id="assignmentStatus">Select a student above to view their current subject assignments.</div>
                        <div id="assignedSubjectsTableContainer" class="table-responsive d-none">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="assignmentsTableBody">
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script>
        $(document).ready(function() {
            const studentIdSelect = $('#studentId');
            const assignmentsTableBody = $('#assignmentsTableBody');
            const assignmentStatus = $('#assignmentStatus');
            const tableContainer = $('#assignedSubjectsTableContainer');

            // --- Function to Load Assignments via AJAX ---
            function loadAssignments(studentId) {
                if (studentId) {
                    assignmentStatus.removeClass('alert-danger alert-success').addClass('alert-primary').text('Loading assignments...');
                    tableContainer.addClass('d-none');
                    
                    $.ajax({
                        url: 'assign-student-subject.php?action=get_assignments&studentId=' + studentId,
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            assignmentsTableBody.empty();
                            if (response.status === 'success' && response.assignments.length > 0) {
                                let html = '';
                                response.assignments.forEach((assignment, index) => {
                                    // Prepare the DELETE form for each assignment (POST request)
                                    html += `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td>${assignment.subjectCode}</td>
                                            <td>${assignment.subjectName}</td>
                                            <td>
                                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this subject?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="action" value="delete_assignment">
                                                    <input type="hidden" name="assignmentId" value="${assignment.id}">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Remove Assignment">
                                                        <i class="fas fa-unlink"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    `;
                                });
                                assignmentsTableBody.append(html);
                                assignmentStatus.addClass('d-none');
                                tableContainer.removeClass('d-none');
                            } else {
                                assignmentStatus.removeClass('d-none alert-success').addClass('alert-warning').text('This student is not currently assigned to any subjects.');
                                tableContainer.addClass('d-none');
                            }
                        },
                        error: function() {
                            assignmentStatus.removeClass('d-none alert-success').addClass('alert-danger').text('Error loading assignments.');
                            tableContainer.addClass('d-none');
                        }
                    });
                } else {
                    assignmentStatus.removeClass('d-none alert-danger alert-success').addClass('alert-primary').text('Select a student above to view their current subject assignments.');
                    tableContainer.addClass('d-none');
                }
            }

            // --- Event Listener for Student Selection ---
            studentIdSelect.on('change', function() {
                const studentId = $(this).val();
                loadAssignments(studentId);
            });
            
            // Re-load assignments if a success/error message indicates a fresh assignment/deletion just occurred
            <?php if ($success || $error): ?>
                const studentIdAfterPost = studentIdSelect.val();
                if(studentIdAfterPost) {
                     loadAssignments(studentIdAfterPost);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>