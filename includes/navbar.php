<?php
// includes/navbar.php
// Top bar containing the sidebar toggle, system title, and user dropdown.

// Assumes config.php and session is active.
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm fixed-top py-2">
    <div class="container-fluid">
        
        <button type="button" id="sidebarCollapse" class="btn btn-outline-primary me-3">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand me-auto" href="<?php echo BASE_URL . ($_SESSION['role'] ?? 'public'); ?>/dashboard.php">
            <i class="fas fa-graduation-cap text-warning me-2"></i>
            <span class="fw-bold">IFM-AS</span>
        </a>

        <div class="dropdown">
            <a class="nav-link dropdown-toggle text-dark" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle me-1 text-primary"></i> 
                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <span class="badge bg-primary ms-1 text-capitalize"><?php echo $_SESSION['role'] ?? 'guest'; ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>public/profile.php"><i class="fas fa-id-card me-2"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>public/change-password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>public/logout.php"><i class="fas fa-power-off me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>