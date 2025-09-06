<?php
// Set a very long execution time limit as this can take a long time
set_time_limit(3600); // 1 hour
ini_set('memory_limit', '512M'); // Increase memory limit

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../includes/db.php';
echo "TIKTOK HISTORICAL IMPORT STARTED: " . date('Y-m-d H:i:s') . "\n";


function get_campaign_details($campaign_id, $advertiser_id, $access_token) {
    // Note: TikTok API for campaign details requires filtering by ID.
    $params = [
        'advertiser_id' => $advertiser_id,
        'filtering' => json_encode(['campaign_ids' => [$campaign_id]]),
        'fields' => json_encode(['campaign_id', 'campaign_name', 'create_time'])
    ];
    
    $response = call_tiktok_api_script('/campaign/get/', $access_token, $params);
    
    if (isset($response['code']) && $response['code'] === 0 && !empty($response['data']['list'])) {
        return $response['data']['list'][0];
    }
    
    echo "        - API Warning (get_campaign_details): " . ($response['message'] ?? 'Could not fetch campaign details.') . "\n";
    return null;
}

function call_tiktok_api_script($url, $access_token, $params = []) {
    $ch = curl_init();
    $full_url = "https://business-api.tiktok.com/open_api/v1.3" . $url . "?" . http_build_query($params);
    
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Access-Token: ' . $access_token]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        return null;
    }
    curl_close($ch);
    
    return json_decode($response, true);
}


function import_spend_for_account($ad_account, $pdo) {
    echo "  - Processing Ad Account: '{$ad_account['account_name']}' (ID: {$ad_account['account_id']})\n";
    
    $campaign_ids = json_decode($ad_account['selected_campaign_ids'], true);
    if (empty($campaign_ids)) {
        echo "    - No campaigns selected for this ad account. Skipping.\n";
        return;
    }
    
    echo "    - Found " . count($campaign_ids) . " selected campaigns.\n";
    
    foreach($campaign_ids as $campaign_id) {
        echo "      - Fetching data for campaign ID: {$campaign_id}\n";
        
        $details = get_campaign_details($campaign_id, $ad_account['account_id'], $ad_account['access_token']);
        
        $campaign_name = $details['campaign_name'] ?? 'Unknown Campaign';

        // Save campaign name to our table
        try {
            $stmt = $pdo->prepare("INSERT INTO campaigns (user_id, workflow_id, campaign_id, name) VALUES (?, ?, ?, ?) ON CONFLICT (workflow_id, campaign_id) DO UPDATE SET name = EXCLUDED.name, updated_at = NOW()");
            $stmt->execute([$ad_account['user_id'], $ad_account['workflow_id'], $campaign_id, $campaign_name]);
        } catch (PDOException $e) {
            echo "        - DB ERROR saving campaign name: " . $e->getMessage() . "\n";
        }

        // Fetch spend data. TikTok reporting API works with date ranges.
        // Let's fetch the last 90 days as an example. For a true historical import, you might loop further back.
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        
        echo "        - Fetching spend for '{$campaign_name}' from {$start_date} to {$end_date}.\n";
        
        $params = [
            'advertiser_id' => $ad_account['account_id'],
            'service_type' => 'AUCTION',
            'report_type' => 'BASIC',
            'data_level' => 'AUCTION_CAMPAIGN',
            'dimensions' => json_encode(['stat_time_day', 'campaign_id']),
            'metrics' => json_encode(['spend', 'impressions']),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'page_size' => 100,
            'filtering' => json_encode([['field_name' => 'campaign_id', 'filter_type' => 'IN', 'filter_value' => [$campaign_id]]])
        ];

        $report_data = call_tiktok_api_script('/report/integrated/get/', $ad_account['access_token'], $params);

        if (isset($report_data['code']) && $report_data['code'] !== 0) {
            echo "        - API ERROR fetching spend: " . ($report_data['message'] ?? 'Unknown API error') . "\n";
            continue;
        }

        if (empty($report_data['data']['list'])) {
            echo "        - No spend data found for this campaign in the date range.\n";
            continue;
        }

        try {
            $pdo->beginTransaction();
            $sql = "
                INSERT INTO daily_ad_spend (user_id, ad_account_id, campaign_id, spend_date, spend, impressions)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT (ad_account_id, campaign_id, spend_date) DO UPDATE SET
                    spend = EXCLUDED.spend,
                    impressions = EXCLUDED.impressions,
                    updated_at = NOW()
            ";
            $stmt = $pdo->prepare($sql);
            
            $imported_days = 0;
            foreach ($report_data['data']['list'] as $daily_insight) {
                $spend = $daily_insight['metrics']['spend'] ?? 0;
                $impressions = $daily_insight['metrics']['impressions'] ?? 0;
                $date = $daily_insight['dimensions']['stat_time_day'];

                $stmt->execute([
                    $ad_account['user_id'], 
                    $ad_account['id'], 
                    $campaign_id, 
                    $date,
                    $spend,
                    $impressions
                ]);
                $imported_days++;
            }
            $pdo->commit();
            echo "        - Successfully imported/updated {$imported_days} days of spend & impressions data.\n";

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "        - DATABASE ERROR: " . $e->getMessage() . "\n";
        }
        sleep(1); // Small delay to avoid hitting API rate limits
    }
}

// --- Main Execution ---
$ad_accounts_stmt = $pdo->query("SELECT * FROM ad_accounts WHERE platform = 'tiktok' ORDER BY user_id");
$all_ad_accounts = $ad_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_ad_accounts)) {
    echo "No TikTok Ad Accounts found in the database.\n";
} else {
    echo "Found " . count($all_ad_accounts) . " TikTok Ad Account(s) to import.\n\n";
    foreach ($all_ad_accounts as $ad_account) {
        import_spend_for_account($ad_account, $pdo);
    }
}

echo "\nTIKTOK HISTORICAL IMPORT FINISHED: " . date('Y-m-d H:i:s') . "\n";
?>