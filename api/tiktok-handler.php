<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
require_login();

$mode = $_POST['mode'] ?? 'fetch';
$advertiser_id = $_POST['advertiser_id'] ?? null;
$access_token = $_POST['access_token'] ?? null;

if (!$advertiser_id || !$access_token) {
    echo json_encode(['success' => false, 'message' => 'Advertiser ID and Access Token are required.']);
    exit();
}

function call_tiktok_api($url, $access_token, $params = []) {
    $ch = curl_init();
    $full_url = "https://business-api.tiktok.com/open_api/v1.3" . $url . "?" . http_build_query($params);
    
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Access-Token: ' . $access_token]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    curl_close($ch);
    
    return json_decode($response, true);
}

try {
    if ($mode === 'test') {
        // A simple call to get advertiser info to test the token
        $result = call_tiktok_api('/advertiser/info/', $access_token, ['advertiser_ids' => json_encode([$advertiser_id])]);
        
        if (isset($result['code']) && $result['code'] === 0) {
            echo json_encode(['success' => true, 'message' => 'Connection successful!']);
        } else {
            throw new Exception($result['message'] ?? 'Failed to connect to TikTok.');
        }

    } elseif ($mode === 'fetch') {
        // Fetch campaigns for the given advertiser
        $params = [
            'advertiser_id' => $advertiser_id,
            'fields' => json_encode(['campaign_id', 'campaign_name', 'status'])
        ];
        $result = call_tiktok_api('/campaign/get/', $access_token, $params);

        if (isset($result['code']) && $result['code'] === 0 && isset($result['data']['list'])) {
            $campaigns = [];
            foreach($result['data']['list'] as $campaign) {
                $campaigns[] = [
                    'id' => $campaign['campaign_id'],
                    'name' => $campaign['campaign_name'],
                    'effective_status' => $campaign['status']
                ];
            }
            echo json_encode(['success' => true, 'campaigns' => $campaigns]);
        } else {
            throw new Exception($result['message'] ?? 'Failed to fetch campaigns from TikTok.');
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>