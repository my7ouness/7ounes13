<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// --- THIS IS THE FIX: API-friendly session and login check ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in again.']);
    exit();
}
// --- END OF FIX ---

set_time_limit(300); // 5 minutes

$user_id = $_SESSION['user']['id'];
$workflow_id = $_GET['workflow_id'] ?? null;

if (!$workflow_id) {
    echo json_encode(['success' => false, 'message' => 'Workflow ID is required.']);
    exit();
}

try {
    // Find the primary WooCommerce store for this workflow
    $store_stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? AND workflow_id = ? AND platform = 'woocommerce' LIMIT 1");
    $store_stmt->execute([$user_id, $workflow_id]);
    $store = $store_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        echo json_encode(['success' => false, 'message' => 'No WooCommerce store found for this workflow.']);
        exit();
    }

    $page = 1;
    $newly_added = 0;
    $total_products = 0;

    while (true) {
        $url = rtrim($store['api_url'], '/') . "/wp-json/wc/v3/products?per_page=100&page={$page}";
        $auth = base64_encode($store['api_key'] . ':' . $store['api_secret']);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $auth]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("Failed to connect to WooCommerce. Status code: {$http_code}");
        }

        $products = json_decode($response, true);

        if (empty($products)) {
            break; // No more products, exit the loop
        }

        $total_products += count($products);

        $sql = "
            INSERT INTO products (user_id, store_id, workflow_id, platform_product_id, name, sku)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (user_id, platform_product_id) DO NOTHING
        ";
        $stmt = $pdo->prepare($sql);

        foreach ($products as $product) {
            $stmt->execute([
                $user_id,
                $store['id'],
                $workflow_id,
                $product['id'],
                $product['name'],
                $product['sku']
            ]);
            if ($stmt->rowCount() > 0) {
                $newly_added++;
            }
        }
        $page++;
    }
    
    echo json_encode(['success' => true, 'message' => "Sync complete! Found {$total_products} total products and added {$newly_added} new ones."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>