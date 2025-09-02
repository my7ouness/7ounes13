<?php
// Set a longer execution time limit as this script can take a while
set_time_limit(600); 

// This script is meant to be run from the command line (CLI), not a browser.
if (php_sapi_name() !== 'cli') {
    echo "<pre>"; // Use <pre> for readable output in browser
}

echo "SYNC SCRIPT STARTED: " . date('Y-m-d H:i:s') . "\n";

require_once __DIR__ . '/../includes/db.php';

function sync_products_for_store($user, $store, $pdo) {
    echo "    - Syncing products for store: '{$store['name']}'...\n";
    try {
        // --- THIS LINE HAS BEEN FIXED ---
        $url = rtrim($store['api_url'], '/') . '/wp-json/wc/v3/orders?per_page=50&orderby=modified&order=asc&modified_after=' . date('Y-m-d\TH:i:s', strtotime('-2 days'));
        
        $auth = base64_encode($store['api_key'] . ':' . $store['api_secret']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $auth]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            echo "      - NOTICE: Received HTTP status {$http_code} from store '{$store['name']}'. Full URL: {$url}\n";
            return;
        }

        $orders = json_decode($response, true);
        
        if (!is_array($orders) || (isset($orders['code']) && !empty($orders['code']))) {
             echo "      - NOTICE: Could not fetch valid order data from store '{$store['name']}'. Maybe no recent orders.\n";
             return;
        }

        echo "      - Found " . count($orders) . " recent order(s) to check for new products.\n";
        $new_products_found = 0;
        
        $pdo->beginTransaction();
        foreach ($orders as $order_data) {
            if (!isset($order_data['line_items'])) continue;
            foreach ($order_data['line_items'] as $item) {
                $checkStmt = $pdo->prepare("SELECT id FROM products WHERE user_id = ? AND platform_product_id = ?");
                $checkStmt->execute([$user['id'], (string)$item['product_id']]);
                if ($checkStmt->fetchColumn() === false) {
                    $insertStmt = $pdo->prepare("INSERT INTO products (user_id, store_id, platform_product_id, name, sku, workflow_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $insertStmt->execute([$user['id'], $store['id'], (string)$item['product_id'], $item['name'], $item['sku'], $store['workflow_id']]);
                    $new_products_found++;
                }
            }
        }
        $pdo->commit();

        if ($new_products_found > 0) {
            echo "      - Discovered and added $new_products_found new product(s).\n";
        }

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo "    - ERROR syncing WooCommerce store '{$store['name']}': " . $e->getMessage() . "\n";
    }
}

// =============================================================================
// MAIN SCRIPT EXECUTION
// =============================================================================

$users_stmt = $pdo->query("SELECT id, email FROM auth.users ORDER BY email");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($users) . " total users to process.\n";

foreach ($users as $user) {
    echo "\nProcessing user: {$user['email']} (ID: {$user['id']})\n";
    
    $stores_stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ?");
    $stores_stmt->execute([$user['id']]);
    $user_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($user_stores)) {
        echo "  - No WooCommerce stores connected. Skipping product sync.\n";
    } else {
        echo "  - Found " . count($user_stores) . " store(s) to sync.\n";
        foreach ($user_stores as $store) {
            sync_products_for_store($user, $store, $pdo);
        }
    }
    
    echo "  - User processing complete.\n";
}

echo "\nSYNC SCRIPT FINISHED: " . date('Y-m-d H:i:s') . "\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
?>