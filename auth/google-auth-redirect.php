<?php
require_once '../vendor/autoload.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

require_login();

// Store the workflow_id in the session so we know where to return
$_SESSION['google_auth_workflow_id'] = $_GET['workflow_id'] ?? null;

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->setScopes([
    'https://www.googleapis.com/auth/spreadsheets.readonly', // View spreadsheets
    'https://www.googleapis.com/auth/drive.readonly' // View file metadata to list sheets
]);
$client->setAccessType('offline');
$client->setPrompt('select_account consent'); // Important for getting a refresh token every time

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();