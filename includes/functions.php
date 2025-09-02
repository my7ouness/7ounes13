<?php
/**
 * This file contains core functions used throughout the application.
 */

/**
 * Checks if a user is logged in by verifying the session.
 * If the user is not logged in, they are redirected to the login page.
 * This should be called at the top of any page that requires authentication.
 */
function require_login() {
    // The BASE_URL constant is defined in db.php, which is included by header.php
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

/**
 * Checks if the logged-in user has completed the basic setup.
 * The basic setup is defined as having at least one store connected.
 * If they haven't, they are redirected to the setup page to complete onboarding.
 * This should be called on pages like the dashboard and products page.
 */
function require_setup() {
    // Access the global PDO object defined in db.php
    global $pdo; 
    
    // Don't run this check if the user isn't logged in or if the PDO object isn't available
    if (!isset($_SESSION['user']['id']) || !$pdo) {
        return;
    }

    // Check if the current page is the setup page itself to avoid a redirect loop
    if (basename($_SERVER['SCRIPT_NAME']) === 'setup.php') {
        return;
    }
    
    try {
        // Check if the user has at least one store connected in the database.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $store_count = $stmt->fetchColumn();

        // If no stores are found, redirect to the setup page.
        if ($store_count == 0) {
            header('Location: ' . BASE_URL . '/setup.php');
            exit();
        }
    } catch (PDOException $e) {
        // In case of a database error, show a simple message.
        // In a production environment, you would log this error.
        die("Error checking user setup status. Please try again later.");
    }
}

?>