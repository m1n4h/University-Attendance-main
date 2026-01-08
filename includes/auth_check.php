<?php
// includes/auth_check.php

// Requires config.php to be included before it
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // User is not logged in
    header('Location: ' . BASE_URL . 'public/index.php');
    exit();
}

// Function to check access
function check_access($required_role) {
    if ($_SESSION['role'] !== $required_role) {
        // Log the attempted unauthorized access
        error_log("Unauthorized access attempt by User ID: " . $_SESSION['user_id'] . " (Role: " . $_SESSION['role'] . ") to " . $_SERVER['REQUEST_URI']);
        // Redirect to a secure dashboard or error page
        header('Location: ' . BASE_URL . 'public/access_denied.php'); 
        exit();
    }
}
?>