<?php
// Set the content type to JSON at the very beginning
header('Content-Type: application/json');

// It's crucial to start the session before any other files are included
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Now include the necessary files
require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- A safer way to handle login for an API ---
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}
$user_id = $_SESSION['user']['id'];

// --- Step 1: Handle Initial Request to Get Workflows ---
if (isset($_GET['action']) && $_GET['action'] === 'get_workflows') {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM workflows WHERE user_id = ? ORDER BY name");
        $stmt->execute([$user_id]);
        $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($workflows);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error while fetching workflows.', 'details' => $e->getMessage()]);
        exit();
    }
}

// --- Step 2: Main Data Fetching Logic ---
try {
    $workflow_id = $_GET['workflow_id'] ?? null;
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d');

    if (!$workflow_id) {
        throw new Exception("Workflow ID is required.");
    }
    
    // Get the USD to MAD conversion rate for this workflow
    $rate_stmt = $pdo->prepare("SELECT usd_to_mad_rate FROM workflows WHERE id = ? AND user_id = ?");
    $rate_stmt->execute([$workflow_id, $user_id]);
    $usd_to_mad_rate = floatval($rate_stmt->fetchColumn());
    if (!$usd_to_mad_rate || $usd_to_mad_rate <= 0) {
        $usd_to_mad_rate = 10.0; // Fallback to a default rate
    }

    $params = [$user_id, $workflow_id, $start_date, $end_date];

    // --- Order Metrics ---
    $orders_sql = "
        SELECT 
            COALESCE(SUM(CASE WHEN shipping_status = 'delivered' THEN total_revenue ELSE 0 END), 0) as delivered_revenue,
            COALESCE(SUM(CASE WHEN shipping_status = 'delivered' THEN total_cogs ELSE 0 END), 0) as delivered_cogs,
            COALESCE(SUM(total_revenue), 0) as gross_sales,
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN shipping_status IN ('shipped', 'delivered', 'returned') THEN 1 ELSE 0 END), 0) as shipped_orders,
            COALESCE(SUM(CASE WHEN shipping_status = 'delivered' THEN 1 ELSE 0 END), 0) as delivered_orders,
            COALESCE(SUM(CASE WHEN shipping_status = 'returned' THEN 1 ELSE 0 END), 0) as returned_orders,
            COALESCE(SUM(CASE WHEN shipping_status = 'shipped' THEN total_revenue - total_cogs ELSE 0 END), 0) as pending_profit,
            COALESCE(SUM(CASE WHEN shipping_status = 'returned' THEN total_revenue ELSE 0 END), 0) as lost_profit_rto
        FROM orders 
        WHERE user_id = ? AND workflow_id = ? AND DATE(order_date) BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($orders_sql);
    $stmt->execute($params);
    $order_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Ad Spend Metrics ---
    $ad_spend_sql = "
        SELECT 
            aa.platform,
            c.name as campaign_name,
            das.campaign_id,
            SUM(das.spend) as total_spend_usd,
            SUM(das.impressions) as total_impressions
        FROM daily_ad_spend das
        JOIN ad_accounts aa ON das.ad_account_id = aa.id
        LEFT JOIN campaigns c ON das.campaign_id = c.campaign_id AND aa.workflow_id = c.workflow_id
        WHERE das.user_id = ? AND aa.workflow_id = ? AND das.spend_date BETWEEN ? AND ?
        GROUP BY aa.platform, c.name, das.campaign_id
    ";
    $stmt = $pdo->prepare($ad_spend_sql);
    $stmt->execute($params);
    $ad_spend_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_ad_spend = 0;
    $platform_breakdown_data = [];
    $campaign_breakdown_data = [];

    foreach($ad_spend_rows as $row) {
        $spend_in_mad = $row['total_spend_usd'] * $usd_to_mad_rate;
        $total_ad_spend += $spend_in_mad;
        $cpm = 0;
        if (!empty($row['total_impressions'])) {
            $cpm = ($spend_in_mad / $row['total_impressions']) * 1000;
        }
        if (!isset($platform_breakdown_data[$row['platform']])) {
            $platform_breakdown_data[$row['platform']] = ['spend' => 0, 'impressions' => 0];
        }
        $platform_breakdown_data[$row['platform']]['spend'] += $spend_in_mad;
        $platform_breakdown_data[$row['platform']]['impressions'] += $row['total_impressions'];
        $display_name = $row['campaign_name'] ?? ('Campaign ID: ' . $row['campaign_id']);
        $campaign_breakdown_data[] = [
            'name' => $display_name,
            'spend' => number_format($spend_in_mad, 2),
            'cpm' => number_format($cpm, 2)
        ];
    }
    foreach($platform_breakdown_data as &$platform) {
        $platform_cpm = 0;
        if (!empty($platform['impressions'])) {
            $platform_cpm = ($platform['spend'] / $platform['impressions']) * 1000;
        }
        $platform['spend'] = number_format($platform['spend'], 2);
        $platform['cpm'] = number_format($platform_cpm, 2);
        unset($platform['impressions']);
    }

    // --- Product Profitability ---
    $product_profit_sql = "
        SELECT
            name,
            SUM(quantity) as units_sold,
            SUM(total) as revenue,
            SUM(cogs) as total_cogs
        FROM order_line_items
        WHERE user_id = ? AND workflow_id = ? AND DATE(order_date) BETWEEN ? AND ?
        GROUP BY name
        ORDER BY revenue DESC
    ";
    $stmt = $pdo->prepare($product_profit_sql);
    $stmt->execute($params);
    $product_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $product_profitability_data = [];
    foreach($product_rows as $row) {
        $revenue_share = ($order_metrics['gross_sales'] > 0) ? ($row['revenue'] / $order_metrics['gross_sales']) : 0;
        $proportional_ad_spend = $total_ad_spend * $revenue_share;
        $net_profit = $row['revenue'] - $row['total_cogs'] - $proportional_ad_spend;

        $product_profitability_data[] = [
            'name' => $row['name'],
            'units_sold' => $row['units_sold'],
            'revenue' => number_format($row['revenue'], 2),
            'cogs' => number_format($row['total_cogs'], 2),
            'ad_spend' => number_format($proportional_ad_spend, 2),
            'net_profit' => number_format($net_profit, 2)
        ];
    }
    
    // --- Fixed Costs ---
    $costs_sql = "SELECT amount, recurrence FROM costs WHERE user_id = ? AND workflow_id = ?";
    $stmt = $pdo->prepare($costs_sql);
    $stmt->execute([$user_id, $workflow_id]);
    $fixed_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_fixed_costs = 0;
    $days_in_range = (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1;
    foreach ($fixed_costs as $cost) {
        if ($cost['recurrence'] === 'monthly') {
            $total_fixed_costs += ($cost['amount'] / 30) * $days_in_range;
        } elseif ($cost['recurrence'] === 'yearly') {
            $total_fixed_costs += ($cost['amount'] / 365) * $days_in_range;
        }
    }

    // --- Final KPI Calculations and Recent Orders ---
    $total_costs = $order_metrics['delivered_cogs'] + $total_ad_spend + $total_fixed_costs;
    $net_profit = $order_metrics['delivered_revenue'] - $total_costs;
    $delivery_rate = ($order_metrics['shipped_orders'] > 0) ? ($order_metrics['delivered_orders'] / $order_metrics['shipped_orders']) * 100 : 0;
    $return_rate = ($order_metrics['shipped_orders'] > 0) ? ($order_metrics['returned_orders'] / $order_metrics['shipped_orders']) * 100 : 0;
    $roas = ($total_ad_spend > 0) ? $order_metrics['delivered_revenue'] / $total_ad_spend : 0;
    $cost_per_delivered = ($order_metrics['delivered_orders'] > 0) ? $total_costs / $order_metrics['delivered_orders'] : 0;

    $recent_orders_sql = "
        SELECT o.platform_order_id, o.order_date, o.total_revenue, o.shipping_status as status, s.platform 
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.user_id = ? AND o.workflow_id = ?
        ORDER BY o.order_date DESC 
        LIMIT 10
    ";
    $stmt = $pdo->prepare($recent_orders_sql);
    $stmt->execute([$user_id, $workflow_id]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($recent_orders as &$order) {
        $order['order_id'] = $order['platform_order_id'];
        $order['date'] = (new DateTime($order['order_date']))->format('Y-m-d');
        $order['revenue'] = number_format($order['total_revenue'], 2);
    }
    
    // --- Assemble Final JSON Response ---
    $response = [
        'net_profit_banner' => ['net_profit' => number_format($net_profit, 2)],
        'kpis' => [
            'delivered_revenue' => number_format($order_metrics['delivered_revenue'], 2),
            'total_costs' => number_format($total_costs, 2),
            'roas' => number_format($roas, 2),
            'pending_profit' => number_format($order_metrics['pending_profit'], 2),
            'lost_profit_rto' => number_format($order_metrics['lost_profit_rto'], 2),
            'cost_per_delivered' => number_format($cost_per_delivered, 2),
            'breakeven_point' => 'N/A',
            'gross_sales' => number_format($order_metrics['gross_sales'], 2),
            'total_orders' => $order_metrics['total_orders'],
            'shipped_orders' => $order_metrics['shipped_orders'],
            'delivered_orders' => $order_metrics['delivered_orders'],
            'delivery_rate' => number_format($delivery_rate, 2) . '%',
            'return_rate_rto' => number_format($return_rate, 2) . '%',
            'confirmation_rate' => 'N/A',
        ],
        'platform_breakdown' => $platform_breakdown_data,
        'product_profitability' => $product_profitability_data,
        'campaign_breakdown' => $campaign_breakdown_data,
        'recent_orders' => $recent_orders
    ];

    echo json_encode($response);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Dashboard API Error: " . $e->getMessage() . " on line " . $e->getLine());
    echo json_encode(['error' => 'An internal server error occurred.', 'details' => $e->getMessage()]);
}
?>