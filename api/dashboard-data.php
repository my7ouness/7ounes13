<?php
// We need the database connection and login verification
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set a longer execution time for potentially slow API calls
set_time_limit(60); 

// Set content type to JSON
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

$user_id = $_SESSION['user']['id'];

// --- Get Inputs ---
// For now, we get the user's first workflow. A dropdown on the dashboard would change this.
$workflow_stmt = $pdo->prepare("SELECT id FROM workflows WHERE user_id = ? ORDER BY created_at ASC LIMIT 1");
$workflow_stmt->execute([$user_id]);
$workflow_id = $workflow_stmt->fetchColumn();

if (!$workflow_id) {
    // Return empty but valid data if no workflow exists, prevents front-end error
    $empty_kpis = [ 
        'net_profit' => '0.00', 'delivered_revenue' => '0.00', 'total_costs' => '0.00', 
        'roas' => '0.00', 'break_even_orders' => 0, 'orders_to_be_profitable' => 0, 
        'delivered_orders' => 0, 'returned_orders' => 0, 'delivery_rate' => '0.00%',
        'total_ad_spend' => '0.00', 'avg_cpc' => '0.00', 'avg_cpm' => '0.00', 
        'avg_ctr' => '0.00%', 'total_clicks' => 0
    ];
    echo json_encode(['kpis' => $empty_kpis, 'orders_widget_data' => []]);
    exit();
}

$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');


// --- Main Processing Block ---
try {
    // =================================================================
    // 1. FETCH DATA FROM OUR DATABASE
    // =================================================================
    
    $orders_stmt = $pdo->prepare("SELECT * FROM orders WHERE workflow_id = ? AND DATE(order_date AT TIME ZONE 'UTC') BETWEEN ? AND ?");
    $orders_stmt->execute([$workflow_id, $start_date, $end_date]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    $costs_stmt = $pdo->prepare("SELECT amount, cost_type, recurrence FROM costs WHERE workflow_id = ?");
    $costs_stmt->execute([$workflow_id]);
    $all_costs = $costs_stmt->fetchAll(PDO::FETCH_ASSOC);

    $ad_accounts_stmt = $pdo->prepare("SELECT * FROM ad_accounts WHERE workflow_id = ?");
    $ad_accounts_stmt->execute([$workflow_id]);
    $ad_accounts = $ad_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);

    $shipping_stmt = $pdo->prepare("SELECT AVG(average_return_fee) as avg_fee FROM shipping_carriers WHERE workflow_id = ?");
    $shipping_stmt->execute([$workflow_id]);
    $avg_return_fee = $shipping_stmt->fetchColumn() ?: 0;


    // =================================================================
    // 2. FETCH LIVE DATA FROM EXTERNAL APIS (EXPANDED)
    // =================================================================

    $total_ad_spend = 0;
    $total_clicks = 0;
    $total_impressions = 0;
    $weighted_cpc_sum = 0;
    $weighted_cpm_sum = 0;
    $weighted_ctr_sum = 0;

    foreach ($ad_accounts as $ad_account) {
        if ($ad_account['platform'] === 'facebook') {
            $url = "https://graph.facebook.com/v18.0/{$ad_account['account_id']}/insights";
            // EXPANDED aPI call fields
            $params = [
                'access_token' => $ad_account['access_token'],
                'level' => 'account',
                'fields' => 'spend,cpc,cpm,ctr,clicks,impressions', // <-- Added new fields
                'time_range' => json_encode(['since' => $start_date, 'until' => $end_date]),
            ];
            $url .= '?' . http_build_query($params);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $insights = json_decode($response, true);
            
            if (isset($insights['data'][0])) {
                $data = $insights['data'][0];
                $spend = (float)($data['spend'] ?? 0);
                $clicks = (int)($data['clicks'] ?? 0);
                $impressions = (int)($data['impressions'] ?? 0);

                $total_ad_spend += $spend;
                $total_clicks += $clicks;
                $total_impressions += $impressions;
                
                // For calculating a weighted average for CPC, CPM, CTR
                if ($spend > 0) {
                    $weighted_cpc_sum += (float)($data['cpc'] ?? 0) * $spend;
                    $weighted_cpm_sum += (float)($data['cpm'] ?? 0) * $spend;
                    $weighted_ctr_sum += (float)($data['ctr'] ?? 0) * $spend;
                }
            }
        }
    }
    
    // Calculate weighted averages
    $avg_cpc = ($total_ad_spend > 0) ? $weighted_cpc_sum / $total_ad_spend : 0;
    $avg_cpm = ($total_ad_spend > 0) ? $weighted_cpm_sum / $total_ad_spend : 0;
    $avg_ctr = ($total_ad_spend > 0) ? $weighted_ctr_sum / $total_ad_spend : 0;


    // =================================================================
    // 3. PERFORM REAL-TIME CALCULATION
    // =================================================================

    $num_days = (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1;
    $total_recurring_cost = 0;
    foreach ($all_costs as $cost) {
        if ($cost['cost_type'] === 'recurring') {
            $daily_cost = ($cost['recurrence'] === 'monthly') ? $cost['amount'] / 30.42 : $cost['amount'] / 365;
            $total_recurring_cost += $daily_cost * $num_days;
        }
    }
    
    $gross_sales = 0; $delivered_revenue = 0; $total_cogs_for_delivered = 0; $delivered_orders_count = 0; $returned_orders_count = 0;
    
    foreach ($orders as $order) {
        $gross_sales += $order['total_revenue'];
        if ($order['shipping_status'] === 'delivered') {
            $delivered_revenue += $order['total_revenue'];
            $total_cogs_for_delivered += $order['total_cogs'];
            $delivered_orders_count++;
        }
        if ($order['shipping_status'] === 'returned') {
            $returned_orders_count++;
        }
    }
    
    $per_delivered_cost_total = 0;
    foreach ($all_costs as $cost) {
        if ($cost['cost_type'] === 'per_delivered') {
            $per_delivered_cost_total += $cost['amount'] * $delivered_orders_count;
        }
    }

    $return_costs = $returned_orders_count * $avg_return_fee;
    $total_costs = $total_ad_spend + $total_cogs_for_delivered + $per_delivered_cost_total + $return_costs + $total_recurring_cost;
    $net_profit = $delivered_revenue - $total_costs;

    $total_fulfilled_orders = $delivered_orders_count + $returned_orders_count;
    $delivery_rate = ($total_fulfilled_orders > 0) ? ($delivered_orders_count / $total_fulfilled_orders) * 100 : 0;
    $roas = ($total_ad_spend > 0) ? ($delivered_revenue / $total_ad_spend) : 0;
    
    // --- SAFEGUARDED BREAK-EVEN CALCULATION ---
    $total_fixed_costs = $total_ad_spend + $total_recurring_cost;
    $avg_revenue_per_delivered_order = ($delivered_orders_count > 0) ? $delivered_revenue / $delivered_orders_count : 0;
    $avg_cogs_per_delivered_order = ($delivered_orders_count > 0) ? $total_cogs_for_delivered / $delivered_orders_count : 0;
    $avg_variable_cost_per_delivered_order = 0;
    foreach ($all_costs as $cost) {
        if ($cost['cost_type'] === 'per_delivered') $avg_variable_cost_per_delivered_order += $cost['amount'];
    }
    $contribution_margin_per_order = $avg_revenue_per_delivered_order - $avg_cogs_per_delivered_order - $avg_variable_cost_per_delivered_order;
    
    $break_even_orders = 0;
    if ($contribution_margin_per_order > 0) {
        $break_even_orders = $total_fixed_costs / $contribution_margin_per_order;
    }
    // --- END OF SAFEGUARD ---

    // =================================================================
    // 4. PREPARE AND RETURN FINAL JSON RESPONSE
    // =================================================================
    $response = [
        'kpis' => [
            'net_profit' => number_format($net_profit, 2),
            'delivered_revenue' => number_format($delivered_revenue, 2),
            'total_costs' => number_format($total_costs, 2),
            'delivered_orders' => number_format($delivered_orders_count),
            'returned_orders' => number_format($returned_orders_count),
            'delivery_rate' => number_format($delivery_rate, 2) . '%',
            'roas' => number_format($roas, 2),
            'break_even_orders' => ceil($break_even_orders),
            'orders_to_be_profitable' => max(0, ceil($break_even_orders) - $delivered_orders_count),
            // --- ADDED NEW KPIS ---
            'total_ad_spend' => number_format($total_ad_spend, 2),
            'avg_cpc' => number_format($avg_cpc, 2),
            'avg_cpm' => number_format($avg_cpm, 2),
            'avg_ctr' => number_format($avg_ctr, 2) . '%',
            'total_clicks' => number_format($total_clicks),
        ],
        'orders_widget_data' => array_slice($orders, 0, 10)
    ];

    echo json_encode($response);

} catch (Throwable $e) { // Use Throwable to catch both Errors and Exceptions
    http_response_code(500);
    // For debugging, it's helpful to see the exact error
    error_log("API Error in dashboard-data.php: " . $e->getMessage() . " on line " . $e->getLine());
    echo json_encode(['error' => 'An internal server error occurred.', 'details' => $e->getMessage()]);
}
?>