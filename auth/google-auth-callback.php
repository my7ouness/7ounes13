<?php
require_once '../vendor/autoload.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

require_login();

$user_id = $_SESSION['user']['id'];
$workflow_id = $_SESSION['google_auth_workflow_id'] ?? null;

if (!$workflow_id) {
    die("Error: Workflow context was lost. Please try connecting again from the setup page.");
}

try {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            throw new Exception('Error fetching access token: ' . $token['error_description']);
        }
        
        if (empty($token['refresh_token'])) {
             // This can happen if the user has already granted permission and `prompt` wasn't set.
            throw new Exception("Could not retrieve a refresh token. Please ensure you are prompted for consent.");
        }
        
        $client->setAccessToken($token);
        $refresh_token = $token['refresh_token'];
        
        // Save the refresh token to the database for this workflow
        // Use an UPSERT to either create or update the connection
        $sql = "
            INSERT INTO google_sheets_connections (user_id, workflow_id, name, refresh_token)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (user_id, workflow_id) DO UPDATE SET
                refresh_token = EXCLUDED.refresh_token,
                updated_at = NOW()
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $workflow_id, 'Google Sheets Connection', $refresh_token]);

        // Redirect back to the setup page
        unset($_SESSION['google_auth_workflow_id']);
        header('Location: ' . BASE_URL . '/setup.php?workflow_id=' . $workflow_id . '&status=google_success');
        exit();
    }

} catch (Exception $e) {
    die("An error occurred during Google authentication: " . $e->getMessage());
}