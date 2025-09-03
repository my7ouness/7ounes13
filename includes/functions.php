<?php
/**
 * This file contains core functions used throughout the application.
 */

/**
 * Checks if a user is logged in by verifying the session.
 * If the user is not logged in, they are redirected to the login page.
 */
function require_login() {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

/**
 * Checks if the logged-in user has completed the basic setup (created at least one workflow).
 * If they haven't, it redirects them to the workflow creation page.
 * IMPORTANT: This function should ONLY be called on pages that require a workflow to exist, like the dashboard.
 */
function require_setup() {
    global $pdo; 
    
    if (!isset($_SESSION['user']['id']) || !$pdo) {
        return;
    }

    // Get the actual script filename being executed. e.g., "dashboard.php"
    $current_page = basename($_SERVER['SCRIPT_FILENAME']);
    
    // Define a "safe list" of pages that are part of the setup/management process.
    $setup_pages = ['workflows.php', 'create-workflow.php', 'setup.php', 'workflow-builder.php'];

    // If the current page is on the safe list, exit the function immediately. This prevents redirect loops.
    if (in_array($current_page, $setup_pages)) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM workflows WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $workflow_count = $stmt->fetchColumn();

        if ($workflow_count == 0) {
            header('Location: ' . BASE_URL . '/create-workflow.php');
            exit();
        }
    } catch (PDOException $e) {
        die("Error checking user setup status: " . $e->getMessage());
    }
}

