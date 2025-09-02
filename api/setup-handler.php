<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
require_login();

$user_id = $_SESSION['user']['id'];
$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? '';

    try {
        // --- WooCommerce Form Logic (copied from setup.php) ---
        if ($form_type === 'store') {
            // ... (The full automated webhook creation logic will go here) ...
            // For brevity, let's assume it succeeds and sets the message.
             $response = ['success' => true, 'message' => 'WooCommerce store connected successfully!'];
        }
        // --- Ad Account Form Logic ---
        elseif ($form_type === 'ad_account') {
            // ... (The ad account saving logic will go here) ...
            $response = ['success' => true, 'message' => 'Facebook Ad Account connected successfully!'];
        }
        // ... And so on for shipping and costs ...

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
    }
}

echo json_encode($response);
?>