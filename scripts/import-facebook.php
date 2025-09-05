<?php
// Set a very long execution time limit as this can take a long time
set_time_limit(3600); // 1 hour
ini_set('memory_limit', '512M'); // Increase memory limit

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Include the database file FIRST, before any output
require_once __DIR__ . '/../includes/db.php';
echo "FACEBOOK HISTORICAL IMPORT STARTED: " . date('Y-m-d H:i:s') . "\n";

function get_campaign_details($campaign_id, $access_token) {
    $url = "https://graph.facebook.com/v18.0/{$campaign_id}?fields=start_time,name";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function import_spend_for_account($ad_account, $pdo) {
    echo "  - Processing Ad Account: '{$ad_account['account_name']}'\n";
    
    $campaign_ids = json_decode($ad_account['selected_campaign_ids'], true);
    if (empty($campaign_ids)) {
        echo "    - No campaigns selected for this ad account. Skipping.\n";
        return;
    }
    
    echo "    - Found " . count($campaign_ids) . " selected campaigns.\n";
    
    foreach($campaign_ids as $campaign_id) {
        echo "      - Fetching data for campaign ID: {$campaign_id}\n";
        
        $details = get_campaign_details($campaign_id, $ad_account['access_token']);
        if (isset($details['error'])) {
            echo "        - API ERROR fetching details: " . $details['error']['message'] . "\n";
            continue;
        }

        $start_time_str = $details['start_time'] ?? null;
        $campaign_name = $details['name'] ?? 'Unknown Campaign';

        // Save campaign name to our new table
        try {
            $stmt = $pdo->prepare("INSERT INTO campaigns (user_id, workflow_id, campaign_id, name) VALUES (?, ?, ?, ?) ON CONFLICT (workflow_id, campaign_id) DO UPDATE SET name = EXCLUDED.name");
            $stmt->execute([$ad_account['user_id'], $ad_account['workflow_id'], $campaign_id, $campaign_name]);
        } catch (PDOException $e) {
            echo "        - DB ERROR saving campaign name: " . $e->getMessage() . "\n";
        }

        if (!$start_time_str) {
            echo "        - ERROR: Could not get start date for campaign. Skipping spend import.\n";
            continue;
        }
        
        $start_date = (new DateTime($start_time_str))->format('Y-m-d');
        $end_date = date('Y-m-d');
        
        echo "        - Campaign '{$campaign_name}' started {$start_date}. Fetching all data until today.\n";
        
        $url = "https://graph.facebook.com/v18.0/{$campaign_id}/insights";
        $params = [
            'fields' => 'spend,impressions', // <-- UPDATED
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $start_date, 'until' => $end_date]),
            'limit' => 500
        ];
        $full_url = $url . '?' . http_build_query($params);

        $ch = curl_init($full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $ad_account['access_token']]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (isset($data['error'])) {
            echo "        - API ERROR fetching spend: " . $data['error']['message'] . "\n";
            continue;
        }

        if (empty($data['data'])) {
            echo "        - No spend data found for this campaign.\n";
            continue;
        }

        try {
            $pdo->beginTransaction();
            $sql = "
                INSERT INTO daily_ad_spend (user_id, ad_account_id, campaign_id, spend_date, spend, impressions)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (ad_account_id, campaign_id, spend_date) DO UPDATE SET
                    spend = EXCLUDED.spend,
                    impressions = EXCLUDED.impressions
            "; // <-- UPDATED
            $stmt = $pdo->prepare($sql);
            
            $imported_days = 0;
            foreach ($data['data'] as $daily_insight) {
                $spend = isset($daily_insight['spend']) ? floatval($daily_insight['spend']) : 0;
                $impressions = isset($daily_insight['impressions']) ? intval($daily_insight['impressions']) : 0; // <-- ADDED
                $stmt->execute([
                    $ad_account['user_id'], 
                    $ad_account['id'], 
                    $campaign_id, 
                    $daily_insight['date_start'], 
                    $spend,
                    $impressions // <-- ADDED
                ]);
                $imported_days++;
            }
            $pdo->commit();
            echo "        - Successfully imported/updated {$imported_days} days of spend & impressions data.\n";

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "        - DATABASE ERROR: " . $e->getMessage() . "\n";
        }
    }
}

// --- Main Execution ---
$ad_accounts_stmt = $pdo->query("SELECT * FROM ad_accounts WHERE platform = 'facebook' ORDER BY user_id");
$all_ad_accounts = $ad_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_ad_accounts)) {
    echo "No Facebook Ad Accounts found in the database.\n";
    exit();
}

echo "Found " . count($all_ad_accounts) . " Facebook Ad Account(s) to import.\n\n";

foreach ($all_ad_accounts as $ad_account) {
    import_spend_for_account($ad_account, $pdo);
}

echo "\nFACEBOOK HISTORICAL IMPORT FINISHED: " . date('Y-m-d H:i:s') . "\n";
?>