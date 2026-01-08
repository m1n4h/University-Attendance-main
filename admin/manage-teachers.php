<?php
// admin/manage-teachers.php
// Handles COMPLETE CRUD operations for Teachers (Admin Panel).

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
        // Fall through to redisplay teacher list
    } else {
        
        switch ($_POST['action']) {
            
            // --- CREATE Teacher ---
            case 'add_teacher':
                // Collect and sanitize input
                $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
                $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $phoneNo = filter_input(INPUT_POST, 'phoneNo', FILTER_SANITIZE_STRING);
                $rawPassword = $_POST['password'];

                // Server-side validation
                if (empty($firstName) || empty($lastName) || empty($email) || empty($rawPassword)) {
                    $error = "All fields (except Phone No) are required.";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Invalid email format.";
                } else {
                    // Hash the password securely
                    $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
                    
                    // Database Insertion (Using Prepared Statements)
                    $sql = "INSERT INTO tblteacher (firstName, lastName, email, password, phoneNo) 
                            VALUES (:fn, :ln, :email, :pwd, :phone)";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':fn' => $firstName, ':ln' => $lastName, ':email' => $email, ':pwd' => $hashedPassword, ':phone' => $phoneNo]);
                        $success = "Teacher **" . htmlspecialchars($firstName) . " " . htmlspecialchars($lastName) . "** registered successfully.";
                    } catch (PDOException $e) {
                        $error = ($e->getCode() == 23000) ? "Registration failed: Email already exists." : "An unexpected database error occurred.";
                        error_log("Teacher registration error: " . $e->getMessage());
                    }
                }
                break;
            
            // --- UPDATE Teacher ---
            case 'edit_teacher':
                $teacherId = filter_input(INPUT_POST, 'teacherId', FILTER_VALIDATE_INT);
                $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
                $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $phoneNo = filter_input(INPUT_POST, 'phoneNo', FILTER_SANITIZE_STRING);
                $rawPassword = trim($_POST['new_password'] ?? ''); // Optional password update

                if (!$teacherId || empty($firstName) || empty($lastName) || empty($email)) {
                    $error = "Missing required fields for update.";
                    break;
                }

                $sql = "UPDATE tblteacher SET firstName = :fn, lastName = :ln, email = :email, phoneNo = :phone";
                $params = [
                    ':fn' => $firstName, 
                    ':ln' => $lastName, 
                    ':email' => $email, 
                    ':phone' => $phoneNo, 
                    ':tid' => $teacherId
                ];

                if (!empty($rawPassword)) {
                    // Update password if a new one is provided
                    $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
                    $sql .= ", password = :pwd";
                    $params[':pwd'] = $hashedPassword;
                }
                
                $sql .= " WHERE teacherId = :tid";

                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = "Teacher **" . htmlspecialchars($firstName) . " " . htmlspecialchars($lastName) . "** updated successfully.";
                } catch (PDOException $e) {
                    $error = ($e->getCode() == 23000) ? "Update failed: Email already exists." : "An unexpected database error occurred during update.";
                    error_log("Teacher update error: " . $e->getMessage());
                }
                break;
            
            // --- DELETE Teacher ---
            case 'delete_teacher':
                $teacherId = filter_input(INPUT_POST, 'teacherId', FILTER_VALIDATE_INT);
                
                if (!$teacherId) {
                    $error = "Invalid teacher ID provided for deletion.";
                    break;
                }

                // Note: Teacher deletion should SET NULL for tblattendance (if set in schema) and CASCADE for tblteacher_subject_class.
                $sql = "DELETE FROM tblteacher WHERE teacherId = :tid";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':tid' => $teacherId]);
                    
                    if ($stmt->rowCount()) {
                        $success = "Teacher record deleted successfully.";
                    } else {
                        $error = "Deletion failed. Teacher ID not found.";
                    }
                } catch (PDOException $e) {
                    error_log("Teacher deletion error: " . $e->getMessage());
                    $error = "An unexpected database error occurred during deletion.";
                }
                break;

            default:
                $error = "Invalid form action detected.";
        }
    }
}

// -----------------------------------------------------------
// 3. READ Data (Fetch All Teachers for Table)
// -----------------------------------------------------------

$teachers = [];
try {
    $sql_teachers = "SELECT teacherId, firstName, lastName, email, phoneNo, dateCreated FROM tblteacher ORDER BY lastName, firstName";
    $stmt_teachers = $pdo->query($sql_teachers);
    $teachers = $stmt_teachers->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Manage Teachers | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h1>
                <p class="lead">Register and manage academic staff authorized to take attendance.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <button type="button" class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-user-plus me-2"></i> Add New Teacher
                </button>

                <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="addTeacherModalLabel"><i class="fas fa-user-plus me-2"></i> Register New Teacher</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="add_teacher">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phoneNo" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phoneNo" name="phoneNo">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Default Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Close</button>
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i> Save Teacher</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white"><i class="fas fa-list me-2"></i> Registered Teachers List</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle" id="teachersTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Date Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['phoneNo'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($teacher['dateCreated'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1" title="Edit Teacher" data-bs-toggle="modal" data-bs-target="#editTeacherModal_<?php echo $teacher['teacherId']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" title="Delete Teacher" data-bs-toggle="modal" data-bs-target="#deleteTeacherModal_<?php echo $teacher['teacherId']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (empty($teachers)): ?>
                            <div class="alert alert-info text-center">No teachers registered yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Teacher Modals - Outside card for proper rendering -->
                <?php foreach ($teachers as $teacher): ?>
                <!-- Edit Teacher Modal -->
                <div class="modal fade" id="editTeacherModal_<?php echo $teacher['teacherId']; ?>" tabindex="-1" aria-labelledby="editTeacherModalLabel_<?php echo $teacher['teacherId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title" id="editTeacherModalLabel_<?php echo $teacher['teacherId']; ?>"><i class="fas fa-edit me-2"></i> Edit Teacher: <?php echo htmlspecialchars($teacher['lastName']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="edit_teacher">
                                    <input type="hidden" name="teacherId" value="<?php echo htmlspecialchars($teacher['teacherId']); ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">First Name</label>
                                            <input type="text" class="form-control" name="firstName" value="<?php echo htmlspecialchars($teacher['firstName']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" class="form-control" name="lastName" value="<?php echo htmlspecialchars($teacher['lastName']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="phoneNo" value="<?php echo htmlspecialchars($teacher['phoneNo']); ?>">
                                    </div>
                                    
                                    <div class="alert alert-info py-2">
                                        Leave the password field blank unless you need to change the teacher's password.
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password (Optional)</label>
                                        <input type="password" class="form-control" name="new_password">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times-circle me-2"></i> Cancel</button>
                                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i> Update Details</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Teacher Modal -->
                <div class="modal fade" id="deleteTeacherModal_<?php echo $teacher['teacherId']; ?>" tabindex="-1" aria-labelledby="deleteTeacherModalLabel_<?php echo $teacher['teacherId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteTeacherModalLabel_<?php echo $teacher['teacherId']; ?>"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to permanently delete teacher <strong><?php echo htmlspecialchars($teacher['firstName'] . ' ' . $teacher['lastName']); ?></strong> (Email: <strong><?php echo htmlspecialchars($teacher['email']); ?></strong>)?</p>
                                    <p class="text-danger"><strong>This action cannot be undone and will affect associated assignments and attendance records.</strong></p>
                                    
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete_teacher">
                                    <input type="hidden" name="teacherId" value="<?php echo htmlspecialchars($teacher['teacherId']); ?>">
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
            <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'add_teacher'): ?>
                var addModal = new bootstrap.Modal(document.getElementById('addTeacherModal'));
                addModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>