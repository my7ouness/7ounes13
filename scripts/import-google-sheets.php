<?php
set_time_limit(3600);
ini_set('memory_limit', '512M');

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';

echo "GOOGLE SHEETS IMPORT STARTED: " . date('Y-m-d H:i:s') . "\n";

/**
 * Creates a Google API client authenticated for a specific user using their refresh token.
 */
function get_google_client_for_user($refresh_token) {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    
    if (empty($refresh_token)) {
        throw new Exception("User does not have a refresh token.");
    }
    
    // This will automatically handle refreshing the access token if it's expired
    $client->refreshToken($refresh_token);
    return $client;
}

function sync_sheet_for_workflow($sheet_connection, $pdo) {
    echo "  - Processing sheet for workflow ID {$sheet_connection['workflow_id']}\n";

    if (empty($sheet_connection['refresh_token']) || empty($sheet_connection['selected_sheet_id']) || empty($sheet_connection['selected_sheet_tab_name'])) {
        echo "    - SKIPPING: Connection is not fully configured (missing token, selected sheet, or tab name).\n";
        return;
    }

    try {
        $client = get_google_client_for_user($sheet_connection['refresh_token']);
        $service = new Google\Service\Sheets($client);

        $sheet_id = $sheet_connection['selected_sheet_id'];
        $tab_name = $sheet_connection['selected_sheet_tab_name'];
        $range = "'{$tab_name}'!A:Z";

        echo "    - Reading from sheet '{$sheet_id}' and range '{$range}'\n";

        $response = $service->spreadsheets_values->get($sheet_id, $range);
        $values = $response->getValues();

        if (empty($values)) {
            echo "    - No data found in the sheet/tab. Skipping.\n";
            return;
        }

        $header = array_map('trim', array_shift($values));
        
        // Define standard column names we're looking for
        $order_id_col_name = 'Order ID';
        $status_col_name = 'Shipping Status';
        $cogs_col_name = 'COGS';
        $sku_col_name = 'SKU';

        // Find the index for each column
        $order_id_idx = array_search($order_id_col_name, $header);
        $status_idx = array_search($status_col_name, $header);
        $cogs_idx = array_search($cogs_col_name, $header);
        $sku_idx = array_search($sku_col_name, $header);

        $pdo->beginTransaction();

        $update_order_stmt = $pdo->prepare("UPDATE orders SET shipping_status = ? WHERE platform_order_id = ? AND user_id = ?");
        $update_product_stmt = $pdo->prepare("UPDATE products SET cost = ? WHERE sku = ? AND user_id = ? AND workflow_id = ?");

        $orders_updated = 0;
        $products_updated = 0;

        foreach ($values as $row) {
            // Update Order Status via Order ID
            if ($order_id_idx !== false && $status_idx !== false && isset($row[$order_id_idx]) && isset($row[$status_idx])) {
                $order_id = trim($row[$order_id_idx]);
                $status = strtolower(trim($row[$status_idx]));
                if (!empty($order_id) && !empty($status)) {
                    $update_order_stmt->execute([$status, $order_id, $sheet_connection['user_id']]);
                    if ($update_order_stmt->rowCount() > 0) $orders_updated++;
                }
            }

            // Update Product COGS via SKU
            if ($sku_idx !== false && $cogs_idx !== false && isset($row[$sku_idx]) && isset($row[$cogs_idx])) {
                $sku = trim($row[$sku_idx]);
                $cogs = $row[$cogs_idx];
                if (!empty($sku) && is_numeric($cogs)) {
                    $update_product_stmt->execute([floatval($cogs), $sku, $sheet_connection['user_id'], $sheet_connection['workflow_id']]);
                    if ($update_product_stmt->rowCount() > 0) $products_updated++;
                }
            }
        }

        $pdo->commit();
        echo "    - DONE. Checked " . count($values) . " rows. Updated {$orders_updated} orders and {$products_updated} products.\n";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "    - ERROR: " . $e->getMessage() . "\n";
    }
}

// --- Main Execution ---
try {
    $sheets_stmt = $pdo->query("SELECT * FROM google_sheets_connections ORDER BY user_id");
    $all_sheets = $sheets_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_sheets)) {
        echo "No Google Sheets connections found in the database.\n";
    } else {
        echo "Found " . count($all_sheets) . " Google Sheet connection(s) to process.\n\n";
        foreach ($all_sheets as $sheet) {
            sync_sheet_for_workflow($sheet, $pdo);
        }
    }
} catch(Exception $e) {
    echo "A critical error occurred: " . $e->getMessage() . "\n";
}

echo "\nGOOGLE SHEETS IMPORT FINISHED: " . date('Y-m-d H:i:s') . "\n";