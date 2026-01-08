<?php
// config/dbcon.php

// Database Credentials (REPLACE WITH YOUR ACTUAL CREDENTIALS)
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'IFM_AS');

// DSN (Data Source Name)
$dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";

try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Disabling emulation is CRUCIAL for security (prepared statements)
    ]);
    // Set a flag to confirm connection
    // $pdo_conn_status = "Connected successfully";

} catch (PDOException $e) {
    // Kill the connection and display error message
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}
?>