<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

set_time_limit(120);
header('Content-Type: application/json');
require_login();

$user_id = $_SESSION['user']['id'];

// --- Step 1: Handle Initial Request to Get Workflows ---
if (isset($_GET['action']) && $_GET['action'] === 'get_workflows') {
    $stmt = $pdo->prepare("SELECT id, name FROM workflows WHERE user_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($workflows);
    exit();
}

// --- Step 2: Main Data Fetching Logic (Using Mock Data for now) ---
try {
    // These would be used by the real logic later
    $workflow_id = $_GET['workflow_id'] ?? null;
    $start_date = $_GET['start'] ?? date('Y-m-d');
    $end_date = $_GET['end'] ?? date('Y-m-d');

    // ======================= FOR TESTING ONLY =========================
    $MOCK_RESPONSE = [
        'net_profit_banner' => ['net_profit' => '5830.50'],
        'kpis' => [
            // Profitability
            'delivered_revenue' => '15500.00',
            'total_costs' => '9669.50',
            'roas' => '3.88',
            'pending_profit' => '4500.00',
            'lost_profit_rto' => '850.00',
            'cost_per_delivered' => '193.39',
            'breakeven_point' => -25, // Already profitable
            // Ops & Sales
            'gross_sales' => '22500.00',
            'total_orders' => 75,
            'shipped_orders' => 65,
            'delivered_orders' => 50,
            'delivery_rate' => '76.92%',
            'return_rate_rto' => '15.38%',
            'confirmation_rate' => '90.00%',
        ],
        'platform_breakdown' => [
            'facebook' => ['spend' => '4000.00', 'cpm' => '15.50'],
            'tiktok' => ['spend' => '2500.00', 'cpm' => '12.75'],
        ],
        'product_profitability' => [
            ['name' => 'Blue T-Shirt (SKU-001)', 'units_sold' => 30, 'revenue' => 9000.00, 'cogs' => 3000.00, 'ad_spend' => 2500.00, 'net_profit' => 3500.00],
            ['name' => 'Black Hoodie (SKU-002)', 'units_sold' => 20, 'revenue' => 6500.00, 'cogs' => 2500.00, 'ad_spend' => 4000.00, 'net_profit' => 0.00],
        ],
        'campaign_breakdown' => [
            ['name' => 'SKU-001 - Summer Sale', 'spend' => 2500.00, 'cpm' => 15.00],
            ['name' => 'SKU-002 - Winter Promo', 'spend' => 4000.00, 'cpm' => 18.00],
        ],
        'recent_orders' => [
            ['order_id' => '10580', 'date' => '2025-09-03', 'revenue' => '300.00', 'platform' => 'WooCommerce', 'status' => 'delivered'],
            ['order_id' => '10579', 'date' => '2025-09-03', 'revenue' => '325.00', 'platform' => 'Shopify', 'status' => 'shipped'],
            ['order_id' => '10578', 'date' => '2025-09-02', 'revenue' => '300.00', 'platform' => 'WooCommerce', 'status' => 'returned'],
        ]
    ];
    echo json_encode($MOCK_RESPONSE);
    exit();
    // ======================= END TESTING BLOCK ========================

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Dashboard API Error: " . $e->getMessage() . " on line " . $e->getLine());
    echo json_encode(['error' => 'An internal server error occurred.']);
}
?>
