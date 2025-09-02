<?php
require_once 'includes/db.php'; // Ensures session is started

if (isset($_SESSION['user']['access_token'])) {
    $access_token = $_SESSION['user']['access_token'];

    // Call Supabase logout endpoint to invalidate the token
    $ch = curl_init(SUPABASE_URL . '/auth/v1/logout');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    
    // We execute the call but don't need to wait for or check the response.
    // We will log the user out locally regardless.
    curl_exec($ch);
    curl_close($ch);
}

// Unset all of the session variables.
$_SESSION = [];

// Destroy the session.
session_destroy();

// Redirect to login page
header('Location: /index.php');
exit();
?>