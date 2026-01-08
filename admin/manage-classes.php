<?php
// admin/manage-classes.php
// Handles COMPLETE CRUD operations for Classes/Academic Levels (Admin Panel).

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
            
            // --- CREATE Class ---
            case 'add_class':
                // Collect and sanitize input
                $className = filter_input(INPUT_POST, 'className', FILTER_SANITIZE_STRING);
                $yearLevel = filter_input(INPUT_POST, 'yearLevel', FILTER_SANITIZE_STRING); // This is ENUM in DB
                $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);
                
                // Server-side validation
                if (empty($className) || empty($yearLevel) || empty($semester)) {
                    $error = "All fields are required.";
                } elseif (!in_array($yearLevel, ['1', '2', '3'])) {
                    $error = "Invalid Year Level selected.";
                } else {
                    
                    // Database Insertion (Using Prepared Statements)
                    $sql = "INSERT INTO tblclass (className, yearLevel, semester) 
                            VALUES (:cn, :yl, :sem)";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':cn' => $className, ':yl' => $yearLevel, ':sem' => $semester]);
                        $success = "Class **" . htmlspecialchars($className) . " (Year " . htmlspecialchars($yearLevel) . ")** created successfully.";
                    } catch (PDOException $e) {
                        // Class/Year/Semester combinations are not unique in the schema, but check for other errors
                        error_log("Class creation error: " . $e->getMessage());
                        $error = "An unexpected database error occurred while creating the class.";
                    }
                }
                break;
            
            // --- UPDATE Class ---
            case 'edit_class':
                $classId = filter_input(INPUT_POST, 'classId', FILTER_VALIDATE_INT);
                $className = filter_input(INPUT_POST, 'className', FILTER_SANITIZE_STRING);
                $yearLevel = filter_input(INPUT_POST, 'yearLevel', FILTER_SANITIZE_STRING);
                $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_STRING);

                if (!$classId || empty($className) || empty($yearLevel) || empty($semester)) {
                    $error = "Missing required fields for update.";
                    break;
                }
                if (!in_array($yearLevel, ['1', '2', '3'])) {
                    $error = "Invalid Year Level selected for update.";
                    break;
                }

                $sql = "UPDATE tblclass SET className = :cn, yearLevel = :yl, semester = :sem WHERE classId = :cid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':cn' => $className, ':yl' => $yearLevel, ':sem' => $semester, ':cid' => $classId]);
                    $success = "Class details updated successfully.";
                } catch (PDOException $e) {
                    error_log("Class update error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during update.";
                }
                break;
            
            // --- DELETE Class ---
            case 'delete_class':
                $classId = filter_input(INPUT_POST, 'classId', FILTER_VALIDATE_INT);
                
                if (!$classId) {
                    $error = "Invalid Class ID provided for deletion.";
                    break;
                }

                // Note: ON DELETE CASCADE will handle associated students, assignments, and attendance records.
                $sql = "DELETE FROM tblclass WHERE classId = :cid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':cid' => $classId]);
                    
                    if ($stmt->rowCount()) {
                        $success = "Class record deleted successfully. Students, assignments, and attendance linked to it were also removed.";
                    } else {
                        $error = "Deletion failed. Class ID not found.";
                    }
                } catch (PDOException $e) {
                    error_log("Class deletion error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during deletion.";
                }
                break;

            default:
                $error = "Invalid form action detected.";
        }
    }
}

// -----------------------------------------------------------
// 3. READ Data (Fetch All Classes for Table)
// -----------------------------------------------------------

$classes = [];
try {
    $sql_classes = "SELECT classId, className, yearLevel, semester, dateCreated FROM tblclass ORDER BY yearLevel, className, semester";
    $stmt_classes = $pdo->query($sql_classes);
    $classes = $stmt_classes->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Manage Classes/Years | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-building"></i> Manage Classes/Years</h1>
                <p class="lead">Define and manage the academic classes, year levels, and semesters.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <button type="button" class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus-circle me-2"></i> Add New Class/Level
                </button>

                <div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="addClassModalLabel"><i class="fas fa-plus me-2"></i> Add New Class</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="add_class">

                                    <div class="mb-3">
                                        <label for="className" class="form-label">Class Name (e.g., BSc IT, Diploma in Eng.) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="className" name="className" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="yearLevel" class="form-label">Year Level <span class="text-danger">*</span></label>
                                            <select class="form-select" id="yearLevel" name="yearLevel" required>
                                                <option value="">-- Select Year --</option>
                                                <option value="1">Year 1</option>
                                                <option value="2">Year 2</option>
                                                <option value="3">Year 3</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="semester" name="semester" placeholder="e.g., Sem1, Spring" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Close</button>
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i> Save Class</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white"><i class="fas fa-list me-2"></i> Defined Classes and Academic Levels</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle" id="classesTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Class Name</th>
                                        <th>Year Level</th>
                                        <th>Semester</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($class['className']); ?></td>
                                        <td><?php echo htmlspecialchars($class['yearLevel']); ?></td>
                                        <td><?php echo htmlspecialchars($class['semester']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($class['dateCreated'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" title="Edit Class" data-bs-toggle="modal" data-bs-target="#editClassModal_<?php echo $class['classId']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" title="Delete Class" data-bs-toggle="modal" data-bs-target="#deleteClassModal_<?php echo $class['classId']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (empty($classes)): ?>
                            <div class="alert alert-info text-center">No classes or academic levels have been defined yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Class Modals - Outside card for proper rendering -->
                <?php foreach ($classes as $class): ?>
                <!-- Edit Class Modal -->
                <div class="modal fade" id="editClassModal_<?php echo $class['classId']; ?>" tabindex="-1" aria-labelledby="editClassModalLabel_<?php echo $class['classId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title" id="editClassModalLabel_<?php echo $class['classId']; ?>"><i class="fas fa-edit me-2"></i> Edit Class Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="edit_class">
                                    <input type="hidden" name="classId" value="<?php echo htmlspecialchars($class['classId']); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Class Name</label>
                                        <input type="text" class="form-control" name="className" value="<?php echo htmlspecialchars($class['className']); ?>" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Year Level</label>
                                            <select class="form-select" name="yearLevel" required>
                                                <option value="1" <?php echo ($class['yearLevel'] == '1') ? 'selected' : ''; ?>>Year 1</option>
                                                <option value="2" <?php echo ($class['yearLevel'] == '2') ? 'selected' : ''; ?>>Year 2</option>
                                                <option value="3" <?php echo ($class['yearLevel'] == '3') ? 'selected' : ''; ?>>Year 3</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Semester</label>
                                            <input type="text" class="form-control" name="semester" value="<?php echo htmlspecialchars($class['semester']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Cancel</button>
                                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i> Update Class</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Class Modal -->
                <div class="modal fade" id="deleteClassModal_<?php echo $class['classId']; ?>" tabindex="-1" aria-labelledby="deleteClassModalLabel_<?php echo $class['classId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteClassModalLabel_<?php echo $class['classId']; ?>"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to permanently delete the class: <strong><?php echo htmlspecialchars($class['className'] . ' - Year ' . $class['yearLevel'] . ' (' . $class['semester'] . ')'); ?></strong>?</p>
                                    <p class="text-danger"><strong>WARNING:</strong> This action will also delete all students, teacher assignments, and attendance records linked to this class due to database cascade constraints.</p>
                                    
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_class">
                                    <input type="hidden" name="classId" value="<?php echo htmlspecialchars($class['classId']); ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Cancel</button>
                                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-2"></i> Confirm Delete</button>
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
    
    <script>
        $(document).ready(function() {
            // Re-show the Add modal if there was an error after form submission
            <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'add_class'): ?>
                var addModal = new bootstrap.Modal(document.getElementById('addClassModal'));
                addModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>