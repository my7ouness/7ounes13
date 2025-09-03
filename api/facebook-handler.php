<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
require_login();

$mode = $_POST['mode'] ?? 'fetch'; // Can be 'test' or 'fetch'
$ad_account_id = $_POST['ad_account_id'] ?? null;
$access_token = $_POST['access_token'] ?? null;

if (!$ad_account_id || !$access_token) {
    echo json_encode(['success' => false, 'message' => 'Error: Ad Account ID and Access Token are required.']);
    exit();
}

// Add the 'act_' prefix if it's missing, as the API requires it.
if (strpos($ad_account_id, 'act_') !== 0) {
    $ad_account_id = 'act_' . $ad_account_id;
}

try {
    if ($mode === 'test') {
        // This is a simple, lightweight call to verify the token has permission to read the account.
        $url = "https://graph.facebook.com/v18.0/{$ad_account_id}?fields=account_status";
    } else { // 'fetch' mode
        // Fetch campaigns with their effective status, which is more comprehensive.
        $url = "https://graph.facebook.com/v18.0/{$ad_account_id}/campaigns?fields=name,effective_status&limit=500";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if ($http_code !== 200 || isset($data['error'])) {
        throw new Exception($data['error']['message'] ?? 'An unknown error occurred while connecting to Facebook.');
    }

    if ($mode === 'test') {
        echo json_encode(['success' => true, 'message' => 'Connection successful! You can now fetch your campaigns.']);
    } else {
        // The API call was successful, return the campaigns found.
        $campaigns = $data['data'] ?? [];
        echo json_encode(['success' => true, 'campaigns' => array_values($campaigns)]);
    }

} catch (Exception $e) {
    // Ensure any failure results in a clear error message.
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

