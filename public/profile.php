<?php
// public/profile.php
// User profile page - works for all roles (admin, teacher, student)

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
$user = [];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM {$tableInfo['table']} WHERE {$tableInfo['idCol']} = :uid");
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error loading profile.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phoneNo = filter_input(INPUT_POST, 'phoneNo', FILTER_SANITIZE_STRING);

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error = "First name, last name, and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // CHECK IF EMAIL IS ALREADY USED BY ANOTHER USER
            $emailExists = false;
            $emailOwner = '';
            
            // Only check if email is different from current
            if (strtolower($email) !== strtolower($user['email'])) {
                // Check in all user tables
                $allTables = [
                    'tbladmin' => 'adminId',
                    'tblteacher' => 'teacherId', 
                    'tblstudent' => 'studentId'
                ];
                
                foreach ($allTables as $tbl => $idCol) {
                    $sql_check = "SELECT {$idCol}, firstName, lastName FROM {$tbl} WHERE LOWER(email) = LOWER(:email)";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute([':email' => $email]);
                    $existing = $stmt_check->fetch();
                    
                    if ($existing) {
                        // Make sure it's not the current user
                        if ($tbl !== $tableInfo['table'] || $existing[$idCol] != $userId) {
                            $emailExists = true;
                            $emailOwner = $existing['firstName'] . ' ' . $existing['lastName'];
                            break;
                        }
                    }
                }
            }
            
            if ($emailExists) {
                $error = "This email address is already registered to another user ({$emailOwner}). Please use a different email.";
            } else {
                try {
                    $sql = "UPDATE {$tableInfo['table']} SET firstName = :fn, lastName = :ln, email = :email";
                    $params = [':fn' => $firstName, ':ln' => $lastName, ':email' => $email, ':uid' => $userId];
                    
                    if ($role === 'teacher' || $role === 'student') {
                        $sql .= ", phoneNo = :phone";
                        $params[':phone'] = $phoneNo;
                    }
                    
                    $sql .= " WHERE {$tableInfo['idCol']} = :uid";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $success = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM {$tableInfo['table']} WHERE {$tableInfo['idCol']} = :uid");
                    $stmt->execute([':uid' => $userId]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "This email address is already in use. Please choose a different one.";
                    } else {
                        $error = "Failed to update profile. Please try again.";
                    }
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
    <title>My Profile | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-primary"><i class="fas fa-user-circle"></i> My Profile</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-edit me-2"></i> Edit Profile Information
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="firstName" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="lastName" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" id="emailInput" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Email must be unique. You cannot use an email that belongs to another user.</small>
                                    </div>

                                    <?php if ($role === 'teacher' || $role === 'student'): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="phoneNo" value="<?php echo htmlspecialchars($user['phoneNo'] ?? ''); ?>">
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-info-circle me-2"></i> Account Information
                            </div>
                            <div class="card-body">
                                <p><strong>Role:</strong> <span class="badge bg-primary text-capitalize"><?php echo $role; ?></span></p>
                                <?php if ($role === 'student' && isset($user['admissionNo'])): ?>
                                <p><strong>Admission No:</strong> <?php echo htmlspecialchars($user['admissionNo']); ?></p>
                                <?php endif; ?>
                                <p><strong>Account Created:</strong><br><?php echo isset($user['dateCreated']) ? date('F d, Y', strtotime($user['dateCreated'])) : 'N/A'; ?></p>
                                <hr>
                                <a href="<?php echo BASE_URL; ?>public/change-password.php" class="btn btn-warning w-100">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </a>
                            </div>
                        </div>
                        
                        <div class="card shadow">
                            <div class="card-header bg-secondary text-white">
                                <i class="fas fa-shield-alt me-2"></i> Security Notice
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-0">
                                    <i class="fas fa-lock me-1"></i> Your account is protected with encrypted password storage.
                                    <br><br>
                                    <i class="fas fa-fingerprint me-1"></i> Attendance is device-bound for security.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
