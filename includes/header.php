<?php
// includes/header.php
// Provides the HTML <head> section and links necessary assets.
// Assumes config.php (with SITE_NAME and BASE_URL) is already included 
// in the calling page (e.g., admin/dashboard.php).

// Cache buster to force CSS reload
$cache_version = '20241224v6';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo BASE_URL; ?>public/assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/all.min.css" />
    <!-- Custom Styles - with cache buster -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/custom-style.css?v=<?php echo $cache_version; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>public/assets/images/attnlg.png">
    
    <!-- jQuery (Required for Bootstrap & AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>