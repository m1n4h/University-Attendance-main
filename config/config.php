<?php
// config/config.php

// Set timezone to East Africa Time (Tanzania)
date_default_timezone_set('Africa/Dar_es_Salaam');

// Start a secure session
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Empty for localhost compatibility
    'secure' => $isSecure, // Only enforce HTTPS when actually on HTTPS
    'httponly' => true, // Prevent JavaScript access to session cookie
    'samesite' => 'Lax'
]);
session_start();

// --- CSRF Token Generation (for all forms) ---
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        // Fallback for older PHP versions (less secure, but a fallback)
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// Function to validate CSRF Token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

// Global settings - Auto-detect URL (supports both localhost and ngrok)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $host . '/University-Attendance-main/');
define('SITE_NAME', 'IFM University Attendance System');

?>