<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}
$user_id = $_SESSION['user']['id'];

if (isset($_GET['action']) && $_GET['action'] === 'get_workflows') {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM workflows WHERE user_id = ? ORDER BY name");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error while fetching workflows.', 'details' => $e->getMessage()]);
        exit();
    }
}

try {
    $workflow_id = $_GET['workflow_id'] ?? null;
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d');

    if (!$workflow_id) {
        throw new Exception("Workflow ID is required.");
    }

    $rate_stmt = $pdo->prepare("SELECT usd_to_mad_rate FROM workflows WHERE id = ? AND user_id = ?");
    $rate_stmt->execute([$workflow_id, $user_id]);
    $usd_to_mad_rate = (float)($rate_stmt->fetchColumn() ?: 10.0);

    $params = [$user_id, $workflow_id, $start_date, $end_date];
    $base_params = [$user_id, $workflow_id];

    // --- 1. Order Metrics ---
    $orders_sql = "
        SELECT
            COALESCE(SUM(CASE WHEN shipping_status = 'delivered' THEN total_revenue ELSE 0 END), 0) as delivered_revenue,
            COALESCE(SUM(CASE WHEN shipping_status = 'delivered' THEN total_cogs ELSE 0 END), 0) as delivered_cogs,
            COALESCE(SUM(CASE WHEN shipping_status = 'shipped' THEN total_revenue - total_cogs ELSE 0 END), 0) as pending_profit,
            COALESCE(SUM(total_revenue), 0) as gross_sales,
            COUNT(DISTINCT CASE WHEN shipping_status = 'delivered' THEN platform_order_id END) as delivered_orders,
            COUNT(DISTINCT CASE WHEN shipping_status = 'shipped' THEN platform_order_id END) as shipped_orders_count,
            COUNT(DISTINCT CASE WHEN shipping_status = 'returned' THEN platform_order_id END) as returned_orders_count,
            COUNT(id) as total_orders,
            COALESCE(SUM(CASE WHEN shipping_status = 'returned' THEN total_revenue ELSE 0 END), 0) as lost_profit_rto
        FROM orders
        WHERE user_id = ? AND workflow_id = ? AND DATE(order_date) BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($orders_sql);
    $stmt->execute($params);
    $order_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 2. Ad Spend ---
    $ad_spend_sql = "SELECT SUM(spend) as total_spend_usd FROM daily_ad_spend das JOIN ad_accounts aa ON das.ad_account_id = aa.id WHERE das.user_id = ? AND aa.workflow_id = ? AND das.spend_date BETWEEN ? AND ?";
    $stmt = $pdo->prepare($ad_spend_sql);
    $stmt->execute($params);
    $total_ad_spend_usd = (float)$stmt->fetchColumn();
    $total_ad_spend = $total_ad_spend_usd * $usd_to_mad_rate;

    // --- 3. Fixed Costs ---
    $costs_sql = "SELECT amount, cost_type FROM costs WHERE user_id = ? AND workflow_id = ?";
    $stmt = $pdo->prepare($costs_sql);
    $stmt->execute($base_params);
    $costs_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_fixed_costs = 0;
    $days_in_range = (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1;
    foreach ($costs_rows as $cost) {
        if ($cost['cost_type'] === 'monthly') $total_fixed_costs += ($cost['amount'] / 30) * $days_in_range;
        elseif ($cost['cost_type'] === 'yearly') $total_fixed_costs += ($cost['amount'] / 365) * $days_in_range;
        elseif ($cost['cost_type'] === 'per_order') $total_fixed_costs += $cost['amount'] * $order_metrics['delivered_orders'];
    }

    // --- 4. High-Level KPI Calculations ---
    $total_costs = $order_metrics['delivered_cogs'] + $total_ad_spend + $total_fixed_costs;
    $net_profit = $order_metrics['delivered_revenue'] - $total_costs;
    $roi = $total_costs > 0 ? ($net_profit / $total_costs) * 100 : 0;
    $cost_per_delivered = $order_metrics['delivered_orders'] > 0 ? $total_costs / $order_metrics['delivered_orders'] : 0;
    $breakeven_point = $cost_per_delivered > 0 ? ceil($total_fixed_costs / $cost_per_delivered) : 0;
    $delivery_rate = $order_metrics['shipped_orders_count'] > 0 ? ($order_metrics['delivered_orders'] / $order_metrics['shipped_orders_count']) * 100 : 0;
    $return_rate = $order_metrics['shipped_orders_count'] > 0 ? ($order_metrics['returned_orders_count'] / $order_metrics['shipped_orders_count']) * 100 : 0;
    $confirmation_rate = $order_metrics['total_orders'] > 0 ? (($order_metrics['shipped_orders_count'] + $order_metrics['delivered_orders'] + $order_metrics['returned_orders_count']) / $order_metrics['total_orders']) * 100 : 0;


    // --- 5. Product-Level Profitability ---
    $product_sql = "
        SELECT
            oli.name, SUM(oli.quantity) as units_sold, SUM(oli.total) as revenue, SUM(oli.cogs) as cogs,
            COUNT(DISTINCT CASE WHEN o.shipping_status = 'delivered' THEN o.id END) as delivered_count,
            COUNT(DISTINCT CASE WHEN o.shipping_status = 'shipped' THEN o.id END) as shipped_count
        FROM order_line_items oli
        JOIN orders o ON oli.order_id = o.id
        WHERE oli.user_id = ? AND oli.workflow_id = ? AND DATE(oli.order_date) BETWEEN ? AND ?
        GROUP BY oli.name ORDER BY revenue DESC
    ";
    $stmt = $pdo->prepare($product_sql);
    $stmt->execute($params);
    $product_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $product_profitability = [];
    $gross_sales_total = $order_metrics['gross_sales'];

    foreach($product_rows as $row) {
        $revenue_share = $gross_sales_total > 0 ? $row['revenue'] / $gross_sales_total : 0;
        $p_ad_spend = $total_ad_spend * $revenue_share;
        $p_fixed_costs = $total_fixed_costs * $revenue_share;
        $p_net_profit = $row['revenue'] - $row['cogs'] - $p_ad_spend - $p_fixed_costs;
        $p_delivery_rate = $row['shipped_count'] > 0 ? ($row['delivered_count'] / $row['shipped_count']) * 100 : 0;

        $product_profitability[] = [
            'name' => $row['name'],
            'units_sold' => (int)$row['units_sold'],
            'revenue' => number_format($row['revenue'], 2),
            'ad_spend' => number_format($p_ad_spend, 2),
            'fixed_costs' => number_format($p_fixed_costs, 2),
            'cogs' => number_format($row['cogs'], 2),
            'net_profit' => number_format($p_net_profit, 2),
            'delivery_rate' => number_format($p_delivery_rate, 2) . '%'
        ];
    }
    
    // --- 6. Campaign Performance ---
    $campaign_sql = "
        SELECT c.name as campaign_name, aa.platform, SUM(das.spend) as spend, SUM(das.impressions) as impressions
        FROM daily_ad_spend das
        JOIN ad_accounts aa ON das.ad_account_id = aa.id
        JOIN campaigns c ON das.campaign_id = c.campaign_id AND aa.workflow_id = c.workflow_id
        WHERE das.user_id = ? AND aa.workflow_id = ? AND das.spend_date BETWEEN ? AND ?
        GROUP BY c.name, aa.platform ORDER BY spend DESC
    ";
    $stmt = $pdo->prepare($campaign_sql);
    $stmt->execute($params);
    $campaign_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $campaign_performance = [];
    foreach($campaign_rows as $row){
        $spend_mad = $row['spend'] * $usd_to_mad_rate;
        $campaign_performance[] = [
            'name' => $row['campaign_name'],
            'platform' => ucfirst($row['platform']),
            'spend' => number_format($spend_mad, 2),
            'orders' => 'N/A',
            'cpo' => 'N/A'
        ];
    }

    // --- 7. Recent Orders ---
    $recent_orders_sql = "
        SELECT o.platform_order_id, s.name as store_name, o.shipping_status as status
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.user_id = ? AND o.workflow_id = ?
        ORDER BY o.order_date DESC LIMIT 5
    ";
    $stmt = $pdo->prepare($recent_orders_sql);
    $stmt->execute($base_params);
    $recent_orders_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recent_orders = [];
    foreach($recent_orders_rows as $row) {
        $recent_orders[] = [
            'platform_order_id' => $row['platform_order_id'],
            'store_name' => $row['store_name'],
            'ad_platform' => 'N/A', 
            'status' => $row['status']
        ];
    }


    // --- Assemble Response ---
    $response = [
        'command_center' => [
            'net_profit' => number_format($net_profit, 2),
            'cost_per_delivered' => number_format($cost_per_delivered, 2),
            'ad_spend' => number_format($total_ad_spend, 2),
            'delivered_revenue' => number_format($order_metrics['delivered_revenue'], 2),
            'roi' => number_format($roi, 2) . '%'
        ],
        'kpis' => [
            'lost_profit_rto' => number_format($order_metrics['lost_profit_rto'], 2),
            'fixed_charges' => number_format($total_fixed_costs, 2),
            'breakeven_point' => (int)$breakeven_point . ' orders',
            'gross_sales' => number_format($order_metrics['gross_sales'], 2),
            'total_orders' => (int)$order_metrics['total_orders'],
            'shipped_orders' => (int)$order_metrics['shipped_orders_count'],
            'delivered_orders' => (int)$order_metrics['delivered_orders'],
            'returned_orders' => (int)$order_metrics['returned_orders_count'],
            'pending_profit' => number_format($order_metrics['pending_profit'], 2),
            'delivery_rate' => number_format($delivery_rate, 2) . '%',
            'return_rate' => number_format($return_rate, 2) . '%',
            'confirmation_rate' => number_format($confirmation_rate, 2) . '%'
        ],
        'product_profitability' => $product_profitability,
        'campaign_performance' => $campaign_performance,
        'recent_orders' => $recent_orders
    ];

    echo json_encode($response);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Dashboard API Error: " . $e->getMessage() . " on line " . $e->getLine());
    echo json_encode(['error' => 'An internal server error occurred.', 'details' => $e->getMessage()]);
}
?>