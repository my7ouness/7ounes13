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
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css"/>

</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>/dashboard.php">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logos/logos.png" alt="COD Profit Hub Logo" class="logo-img">
                </a>
            </div>
            <nav class="main-nav">
                <?php if (isset($_SESSION['user'])): 
                    $current_page = basename($_SERVER['SCRIPT_NAME']);
                ?>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">Products</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/workflows.php" class="<?php echo in_array($current_page, ['workflows.php', 'create-workflow.php', 'setup.php']) ? 'active' : ''; ?>">Workflows</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/account.php" class="<?php echo $current_page === 'account.php' ? 'active' : ''; ?>">Account</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-secondary">Log Out</a></li>
                    </ul>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">