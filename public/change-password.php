<?php
// public/change-password.php
// Change password page - works for all roles

require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . 'public/index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = '';
$success = '';

$tables = [
    'admin' => ['table' => 'tbladmin', 'idCol' => 'adminId'],
    'teacher' => ['table' => 'tblteacher', 'idCol' => 'teacherId'],
    'student' => ['table' => 'tblstudent', 'idCol' => 'studentId']
];

$tableInfo = $tables[$role];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "All fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM {$tableInfo['table']} WHERE {$tableInfo['idCol']} = :uid");
                $stmt->execute([':uid' => $userId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE {$tableInfo['table']} SET password = :pwd WHERE {$tableInfo['idCol']} = :uid");
                    $stmt->execute([':pwd' => $hashedPassword, ':uid' => $userId]);
                    
                    $success = "Password changed successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to change password.";
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
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-lock me-2"></i> Update Your Password
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="new_password" minlength="6" required>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-warning btn-lg">
                                            <i class="fas fa-check me-2"></i> Change Password
                                        </button>
                                        <a href="<?php echo BASE_URL; ?>public/profile.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Back to Profile
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i> <strong>Password Tips:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Use a mix of letters, numbers, and symbols</li>
                                <li>Avoid common words or personal information</li>
                                <li>Change your password regularly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
