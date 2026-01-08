<?php
// admin/manage-subjects.php
// Handles COMPLETE CRUD operations for Subjects/Courses (Admin Panel).

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
            
            // --- CREATE Subject ---
            case 'add_subject':
                // Collect and sanitize input
                $subjectCode = filter_input(INPUT_POST, 'subjectCode', FILTER_SANITIZE_STRING);
                $subjectName = filter_input(INPUT_POST, 'subjectName', FILTER_SANITIZE_STRING);
                $creditHours = filter_input(INPUT_POST, 'creditHours', FILTER_VALIDATE_INT);
                
                // Server-side validation
                if (empty($subjectCode) || empty($subjectName) || !$creditHours) {
                    $error = "Subject Code, Name, and Credit Hours are required.";
                } elseif ($creditHours < 1 || $creditHours > 10) {
                    $error = "Credit hours must be a reasonable number (1-10).";
                } else {
                    
                    // Database Insertion (Using Prepared Statements)
                    $sql = "INSERT INTO tblsubject (subjectCode, subjectName, creditHours) 
                            VALUES (:sc, :sn, :ch)";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':sc' => $subjectCode, ':sn' => $subjectName, ':ch' => $creditHours]);
                        $success = "Subject **" . htmlspecialchars($subjectCode) . " - " . htmlspecialchars($subjectName) . "** created successfully.";
                    } catch (PDOException $e) {
                        $error = ($e->getCode() == 23000) ? "Creation failed: Subject Code already exists and must be unique." : "An unexpected database error occurred.";
                        error_log("Subject creation error: " . $e->getMessage());
                    }
                }
                break;
            
            // --- UPDATE Subject ---
            case 'edit_subject':
                $subjectId = filter_input(INPUT_POST, 'subjectId', FILTER_VALIDATE_INT);
                $subjectCode = filter_input(INPUT_POST, 'subjectCode', FILTER_SANITIZE_STRING);
                $subjectName = filter_input(INPUT_POST, 'subjectName', FILTER_SANITIZE_STRING);
                $creditHours = filter_input(INPUT_POST, 'creditHours', FILTER_VALIDATE_INT);

                if (!$subjectId || empty($subjectCode) || empty($subjectName) || !$creditHours) {
                    $error = "Missing required fields for update.";
                    break;
                }
                if ($creditHours < 1 || $creditHours > 10) {
                    $error = "Credit hours must be a reasonable number (1-10).";
                    break;
                }

                $sql = "UPDATE tblsubject SET subjectCode = :sc, subjectName = :sn, creditHours = :ch WHERE subjectId = :sid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':sc' => $subjectCode, ':sn' => $subjectName, ':ch' => $creditHours, ':sid' => $subjectId]);
                    $success = "Subject **" . htmlspecialchars($subjectCode) . "** updated successfully.";
                } catch (PDOException $e) {
                    $error = ($e->getCode() == 23000) ? "Update failed: Subject Code already exists." : "An unexpected database error occurred during update.";
                    error_log("Subject update error: " . $e->getMessage());
                }
                break;
            
            // --- DELETE Subject ---
            case 'delete_subject':
                $subjectId = filter_input(INPUT_POST, 'subjectId', FILTER_VALIDATE_INT);
                
                if (!$subjectId) {
                    $error = "Invalid Subject ID provided for deletion.";
                    break;
                }

                // Note: ON DELETE CASCADE will handle associated teacher assignments, student assignments, and attendance records.
                $sql = "DELETE FROM tblsubject WHERE subjectId = :sid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':sid' => $subjectId]);
                    
                    if ($stmt->rowCount()) {
                        $success = "Subject record deleted successfully. Assignments and attendance linked to it were also removed.";
                    } else {
                        $error = "Deletion failed. Subject ID not found.";
                    }
                } catch (PDOException $e) {
                    error_log("Subject deletion error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during deletion.";
                }
                break;

            default:
                $error = "Invalid form action detected.";
        }
    }
}

// -----------------------------------------------------------
// 3. READ Data (Fetch All Subjects for Table)
// -----------------------------------------------------------

$subjects = [];
try {
    $sql_subjects = "SELECT subjectId, subjectCode, subjectName, creditHours, dateCreated FROM tblsubject ORDER BY subjectCode";
    $stmt_subjects = $pdo->query($sql_subjects);
    $subjects = $stmt_subjects->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Manage Subjects/Courses | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-book-open"></i> Manage Subjects/Courses</h1>
                <p class="lead">Define and manage all academic subjects and their credit loads.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <button type="button" class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus-circle me-2"></i> Add New Subject
                </button>

                <div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="addSubjectModalLabel"><i class="fas fa-book me-2"></i> Add New Subject</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="add_subject">

                                    <div class="mb-3">
                                        <label for="subjectCode" class="form-label">Subject Code (e.g., IT101) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subjectCode" name="subjectCode" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="subjectName" class="form-label">Subject Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subjectName" name="subjectName" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="creditHours" class="form-label">Credit Hours <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="creditHours" name="creditHours" min="1" max="10" value="3" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Close</button>
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i> Save Subject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white"><i class="fas fa-list me-2"></i> Defined Subjects List</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle" id="subjectsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Credits</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($subject['subjectCode']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['subjectName']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['creditHours']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($subject['dateCreated'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" title="Edit Subject" data-bs-toggle="modal" data-bs-target="#editSubjectModal_<?php echo $subject['subjectId']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" title="Delete Subject" data-bs-toggle="modal" data-bs-target="#deleteSubjectModal_<?php echo $subject['subjectId']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (empty($subjects)): ?>
                            <div class="alert alert-info text-center">No subjects have been defined yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Subject Modals - Outside card for proper rendering -->
                <?php foreach ($subjects as $subject): ?>
                <!-- Edit Subject Modal -->
                <div class="modal fade" id="editSubjectModal_<?php echo $subject['subjectId']; ?>" tabindex="-1" aria-labelledby="editSubjectModalLabel_<?php echo $subject['subjectId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title" id="editSubjectModalLabel_<?php echo $subject['subjectId']; ?>"><i class="fas fa-edit me-2"></i> Edit Subject: <?php echo htmlspecialchars($subject['subjectCode']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="edit_subject">
                                    <input type="hidden" name="subjectId" value="<?php echo htmlspecialchars($subject['subjectId']); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Subject Code</label>
                                        <input type="text" class="form-control" name="subjectCode" value="<?php echo htmlspecialchars($subject['subjectCode']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Subject Name</label>
                                        <input type="text" class="form-control" name="subjectName" value="<?php echo htmlspecialchars($subject['subjectName']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Credit Hours</label>
                                        <input type="number" class="form-control" name="creditHours" min="1" max="10" value="<?php echo htmlspecialchars($subject['creditHours']); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Cancel</button>
                                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i> Update Subject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Subject Modal -->
                <div class="modal fade" id="deleteSubjectModal_<?php echo $subject['subjectId']; ?>" tabindex="-1" aria-labelledby="deleteSubjectModalLabel_<?php echo $subject['subjectId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteSubjectModalLabel_<?php echo $subject['subjectId']; ?>"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to permanently delete the subject: <strong><?php echo htmlspecialchars($subject['subjectCode'] . ' - ' . $subject['subjectName']); ?></strong>?</p>
                                    <p class="text-danger"><strong>WARNING:</strong> This action will also delete all teacher assignments, student assignments, and attendance records linked to this subject due to database cascade constraints.</p>
                                    
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_subject">
                                    <input type="hidden" name="subjectId" value="<?php echo htmlspecialchars($subject['subjectId']); ?>">
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
            <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'add_subject'): ?>
                var addModal = new bootstrap.Modal(document.getElementById('addSubjectModal'));
                addModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>