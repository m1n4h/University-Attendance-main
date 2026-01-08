<?php
// public/logout.php
// Handles user session destruction and redirects to the login page.

require_once '../config/config.php'; // Required to ensure session_start() is called

// 1. Unset all session variables
$_SESSION = array();

// 2. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to the login page
header('Location: ' . BASE_URL . 'public/index.php');
exit();
?>