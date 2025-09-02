<?php
// Ensure db.php is included, which also starts the session.
require_once 'db.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COD Profit Hub</title>
    
    <!-- Use the BASE_URL constant here -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css"/>

</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <!-- Use the BASE_URL constant here -->
                <a href="<?php echo BASE_URL; ?>/dashboard.php">COD Profit Hub</a>
            </div>
            <nav class="main-nav">
                <?php if (isset($_SESSION['user'])): ?>
                    <ul>
                        <!-- Use the BASE_URL constant for all links -->
                        <li><a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/products.php">Products</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/setup.php">Setup</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/account.php">Account</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-secondary">Log Out</a></li>
                    </ul>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">