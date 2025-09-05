<?php
// Set a very long execution time limit as this can take a long time
set_time_limit(3600); // 1 hour
ini_set('memory_limit', '512M'); // Increase memory limit

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../includes/db.php';
echo "WOOCOMMERCE HISTORICAL IMPORT STARTED: " . date('Y-m-d H:i:s') . "\n";

function import_all_orders_for_store($store, $pdo) {
    echo "  - Processing store: '{$store['name']}' (ID: {$store['id']})\n";

    $products_stmt = $pdo->prepare("SELECT platform_product_id, cost FROM products WHERE user_id = ?");
    $products_stmt->execute([$store['user_id']]);
    $product_costs = $products_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $page = 1;
    $total_pages = 1; 
    $total_imported_orders = 0;
    $total_failed_orders = 0;

    do {
        if ($page > 500) {
            echo "    - Reached page limit of 500. Stopping.\n";
            break;
        }

        echo "    - Fetching page $page of $total_pages...\n";
        
        $url = rtrim($store['api_url'], '/') . "/wp-json/wc/v3/orders?per_page=100&page={$page}&orderby=id&order=asc";
        $auth = base64_encode($store['api_key'] . ':' . $store['api_secret']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $auth]);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $response_with_headers = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response_with_headers, 0, $header_size);
        $body = substr($response_with_headers, $header_size);
        
        curl_close($ch);

        if ($http_code !== 200) {
            echo "      - ERROR: HTTP status {$http_code}. Stopping.\n";
            break;
        }

        if (preg_match('/X-WP-TotalPages: (\d+)/i', $headers, $matches)) {
            $total_pages = (int)$matches[1];
        }

        $orders = json_decode($body, true);

        if (!is_array($orders) || empty($orders)) {
            echo "    - No more orders found. Finishing.\n";
            break;
        }
        
        foreach ($orders as $order_data) {
            try {
                $order_total_cogs = 0;
                if (!empty($order_data['line_items'])) {
                    foreach($order_data['line_items'] as $item) {
                        $cost_per_item = $product_costs[(string)$item['product_id']] ?? 0;
                        $order_total_cogs += $cost_per_item * $item['quantity'];
                    }
                }

                $order_sql = "
                    INSERT INTO orders (user_id, store_id, workflow_id, platform_order_id, status, total_revenue, total_cogs, order_date, raw_data, shipping_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ON CONFLICT (user_id, platform_order_id) DO UPDATE SET
                        status = EXCLUDED.status,
                        total_revenue = EXCLUDED.total_revenue,
                        total_cogs = EXCLUDED.total_cogs,
                        order_date = EXCLUDED.order_date,
                        raw_data = EXCLUDED.raw_data,
                        updated_at = NOW()
                    RETURNING id
                ";
                $order_stmt = $pdo->prepare($order_sql);
                $order_stmt->execute([
                    $store['user_id'], $store['id'], $store['workflow_id'],
                    $order_data['id'], $order_data['status'], $order_data['total'],
                    $order_total_cogs, $order_data['date_created_gmt'] . 'Z', json_encode($order_data)
                ]);
                $internal_order_id = $order_stmt->fetchColumn();

                if ($internal_order_id) {
                    $delete_stmt = $pdo->prepare("DELETE FROM order_line_items WHERE order_id = ?");
                    $delete_stmt->execute([$internal_order_id]);

                    if (!empty($order_data['line_items'])) {
                        $line_item_sql = "
                            INSERT INTO order_line_items (user_id, workflow_id, order_id, platform_product_id, name, sku, quantity, price, total, cogs, order_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ";
                        $line_item_stmt = $pdo->prepare($line_item_sql);
                        foreach($order_data['line_items'] as $item) {
                            $cost_per_item = $product_costs[(string)$item['product_id']] ?? 0;
                            $line_item_cogs = $cost_per_item * $item['quantity'];
                            $line_item_stmt->execute([
                                $store['user_id'], $store['workflow_id'], $internal_order_id,
                                $item['product_id'], $item['name'], $item['sku'],
                                $item['quantity'], $item['price'], $item['total'],
                                $line_item_cogs, $order_data['date_created_gmt'] . 'Z'
                            ]);
                        }
                    }
                }
                $total_imported_orders++;
            } catch (Exception $e) {
                echo "      - SKIPPED ORDER ID {$order_data['id']}: Error - " . $e->getMessage() . "\n";
                $total_failed_orders++;
            }
        }
        $page++;
    } while ($page <= $total_pages);
    
    echo "  - Finished store '{$store['name']}'.\n";
    echo "  - Successfully Imported/Updated: $total_imported_orders orders.\n";
    echo "  - Failed/Skipped: $total_failed_orders orders.\n";
}

// --- Main Execution ---
$stores_stmt = $pdo->query("SELECT * FROM stores WHERE platform = 'woocommerce' ORDER BY user_id");
$all_stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($all_stores)) {
    echo "No WooCommerce stores found in the database.\n";
    exit();
}
echo "Found " . count($all_stores) . " WooCommerce store(s) to import.\n\n";
foreach ($all_stores as $store) {
    import_all_orders_for_store($store, $pdo);
}

echo "\nWOOCOMMERCE HISTORICAL IMPORT FINISHED: " . date('Y-m-d H:i:s') . "\n";
?>