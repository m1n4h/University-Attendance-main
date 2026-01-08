<?php
// public/profile_security.php
// Allows any authenticated user (Admin, Teacher, Student) to change their password securely.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';

// Check if a user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . 'public/index.php');
    exit();
}

// Get user info from session
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$userDisplayName = $_SESSION['user_name'];
$error = '';
$success = '';

// Determine the table name and ID column based on the role
switch ($userRole) {
    case 'admin':
        $tableName = 'tbladmin';
        $idColumn = 'adminId';
        break;
    case 'teacher':
        $tableName = 'tblteacher';
        $idColumn = 'teacherId';
        break;
    case 'student':
        $tableName = 'tblstudent';
        $idColumn = 'studentId';
        break;
    default:
        // Should be caught by the initial check, but good practice
        header('Location: ' . BASE_URL . 'public/logout.php');
        exit();
}

// -----------------------------------------------------------
// 2. Password Change Handler
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    
    // CSRF Protection Check
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        
        // Collect and validate input
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New password and confirmation password do not match.";
        } elseif (strlen($newPassword) < 8) {
            $error = "New password must be at least 8 characters long.";
        } else {
            // A. Fetch current hashed password from DB
            $sql_fetch_hash = "SELECT password FROM {$tableName} WHERE {$idColumn} = :uid";
            $stmt_fetch_hash = $pdo->prepare($sql_fetch_hash);
            $stmt_fetch_hash->execute([':uid' => $userId]);
            $user = $stmt_fetch_hash->fetch();
            
            if (!$user) {
                $error = "User account not found.";
            } elseif (!password_verify($currentPassword, $user['password'])) {
                // B. Verify current password securely
                $error = "Current password is incorrect.";
            } else {
                // C. Hash the new password and update the database
                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $sql_update = "UPDATE {$tableName} SET password = :new_pwd WHERE {$idColumn} = :uid";
                
                try {
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute([':new_pwd' => $newHashedPassword, ':uid' => $userId]);
                    
                    $success = "Password changed successfully! You will be redirected to the login page to log in with your new password.";
                    
                    // Force logout after a short delay for security
                    echo "<meta http-equiv='refresh' content='5;url=" . BASE_URL . "public/logout.php'>";
                    
                } catch (PDOException $e) {
                    error_log("Password update error ({$userRole}): " . $e->getMessage());
                    $error = "A database error occurred during password change.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Change Password | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-key"></i> Change Password</h1>
                <p class="lead">User: **<?php echo htmlspecialchars($userDisplayName); ?>** (Role: <?php echo ucfirst($userRole); ?>)</p>
                
                <hr>

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-danger text-white"><i class="fas fa-lock me-2"></i> Security Update</div>
                            <div class="card-body">
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger" role="alert"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                <?php if ($success): ?>
                                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>

                                    <hr>
                                    
                                    <div class="alert alert-warning py-2">
                                        Choose a strong new password (minimum 8 characters).
                                    </div>

                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    </div>

                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                    </div>
                                    
                                    <div class="d-grid mt-4">
                                        <button type="submit" class="btn btn-danger btn-lg"><i class="fas fa-lock me-2"></i> Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script>
        // Example client-side password matching check
        $(document).ready(function() {
            $('#confirm_password').on('keyup', function () {
                if ($('#new_password').val() !== $('#confirm_password').val()) {
                    $('#confirm_password').get(0).setCustomValidity('Passwords must match.');
                } else {
                    $('#confirm_password').get(0).setCustomValidity('');
                }
            });
        });
    </script>
</body>
</html>