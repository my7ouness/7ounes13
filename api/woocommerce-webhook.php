<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

// --- NEW TOKEN-BASED SECURITY CHECK ---
$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication token is missing.']);
    exit();
}

try {
    // Find the store that matches this secret token
    $stmt = $pdo->prepare("SELECT id, user_id, workflow_id FROM stores WHERE webhook_token = ?");
    $stmt->execute([$token]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no store is found with this token, the request is invalid.
    if (!$store) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Invalid authentication token.']);
        exit();
    }
    
    // --- If token is valid, proceed with processing ---
    $store_id = $store['id'];
    $user_id = $store['user_id'];
    $workflow_id = $store['workflow_id'];

    $payload = file_get_contents('php://input');
    $order_data = json_decode($payload, true);

    if (!isset($order_data['id'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Incomplete order data received.']);
        exit();
    }
    
    $platform_order_id = $order_data['id'];
    $total_revenue = $order_data['total'];
    $status = $order_data['status'];
    $order_date = $order_data['date_created_gmt'] . 'Z';

    $total_cogs = 0;
    if (!empty($order_data['line_items'])) {
        $product_ids = array_map(fn($item) => $item['product_id'], $order_data['line_items']);
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $sql = "SELECT platform_product_id, cost FROM products WHERE user_id = ? AND platform_product_id IN ($placeholders)";
            $params = array_merge([$user_id], $product_ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $product_costs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($order_data['line_items'] as $item) {
                $cost_per_item = $product_costs[(string)$item['product_id']] ?? 0;
                $total_cogs += $cost_per_item * $item['quantity'];
            }
        }
    }
    
    $sql = "
        INSERT INTO orders (user_id, store_id, platform_order_id, status, total_revenue, total_cogs, order_date, raw_data, shipping_status, workflow_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ON CONFLICT (user_id, platform_order_id) DO UPDATE SET
            status = EXCLUDED.status,
            total_revenue = EXCLUDED.total_revenue,
            total_cogs = EXCLUDED.total_cogs,
            order_date = EXCLUDED.order_date,
            raw_data = EXCLUDED.raw_data,
            workflow_id = EXCLUDED.workflow_id,
            updated_at = NOW()
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $store_id, $platform_order_id, $status, $total_revenue, $total_cogs, $order_date, $payload, $workflow_id]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully.']);

} catch (PDOException $e) {
    error_log('Webhook PDO Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error during order processing.']);
}
?>