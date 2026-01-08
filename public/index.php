<?php
// public/index.php (Login Page)

require_once '../config/config.php';
require_once '../config/dbcon.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Protection
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF Token validation failed. Try again.";
    } else {
        // 2. Server-side Validation and Sanitization
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password']; // Raw password for verification

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // 3. Authentication (Check Admin, Teacher, then Student)
            $roles = [
                'admin' => 'tbladmin',
                'teacher' => 'tblteacher',
                'student' => 'tblstudent'
            ];

            $authenticated = false;
            foreach ($roles as $role_name => $table_name) {
                $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Success! Setup Session
                    $_SESSION['user_id'] = $user[$role_name . 'Id'];
                    $_SESSION['user_name'] = $user['firstName'] . ' ' . $user['lastName'];
                    $_SESSION['role'] = $role_name;
                    $authenticated = true;

                    // Redirect to the appropriate dashboard
                    header('Location: ' . BASE_URL . $role_name . '/dashboard.php');
                    exit();
                }
            }

            if (!$authenticated) {
                $error = "Invalid email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; // Includes Bootstrap & Font Awesome ?>
    <title><?php echo SITE_NAME; ?> - Login</title>
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card p-4 shadow" style="width: 100%; max-width: 400px;">
            <h2 class="card-title text-center mb-4"><i class="fas fa-university"></i> University AS</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Log In</button>
                </div>
            </form>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>