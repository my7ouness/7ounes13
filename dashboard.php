<?php
require_once 'includes/header.php';
require_login();
require_setup();
?>

<div class="dashboard-header">
    <h1>Dashboard</h1>
    <!-- We will add a workflow selector here later -->
    <div class="date-picker-container">
        <input type="text" id="date-range-picker" />
    </div>
</div>

<h3>Primary KPIs</h3>
<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="kpi-card">
        <h3>Net Profit</h3>
        <div class="value positive" id="net-profit-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Delivered Revenue</h3>
        <div class="value" id="delivered-revenue-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Total Costs</h3>
        <div class="value negative" id="total-costs-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>ROAS</h3>
        <div class="value" id="roas-value">Loading...</div>
    </div>
</div>

<h3 style="margin-top: 30px;">Break-Even Analysis</h3>
<div class="kpi-grid">
    <div class="kpi-card">
        <h3>Break-Even Point (Orders)</h3>
        <div class="value" id="break-even-orders-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Orders to be Profitable</h3>
        <div class="value" id="orders-to-be-profitable-value">Loading...</div>
    </div>
</div>


<div class="dashboard-columns" style="display: flex; gap: 30px; margin-top: 30px;">
    
    <!-- Left Column: Operational KPIs -->
    <div class="column" style="flex: 1;">
        <h3>Operational KPIs</h3>
        <div class="kpi-grid" style="grid-template-columns: 1fr 1fr;">
             <div class="kpi-card">
                <h3>Delivered Orders</h3>
                <div class="value" id="delivered-orders-value">Loading...</div>
            </div>
             <div class="kpi-card">
                <h3>Returned Orders</h3>
                <div class="value" id="returned-orders-value">Loading...</div>
            </div>
             <div class="kpi-card">
                <h3>Delivery Rate %</h3>
                <div class="value" id="delivery-rate-value">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Orders Widget -->
    <div class="column" style="flex: 2;">
        <h3>Recent Orders in Period</h3>
        <div class="table-container">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Revenue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="orders-widget-tbody">
                    <!-- JS will populate this -->
                    <tr><td colspan="4" style="text-align:center; padding: 20px;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>