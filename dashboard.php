<?php
require_once 'includes/header.php';
require_login();
require_setup();
?>

<div class="dashboard-header">
    <h1>Dashboard</h1>
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

<h3 class="section-title">Ad Performance</h3>
<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="kpi-card">
        <h3>Ad Spend</h3>
        <div class="value" id="ad-spend-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Clicks</h3>
        <div class="value" id="total-clicks-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Avg. CPC</h3>
        <div class="value" id="avg-cpc-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Avg. CPM</h3>
        <div class="value" id="avg-cpm-value">Loading...</div>
    </div>
    <div class="kpi-card">
        <h3>Avg. CTR %</h3>
        <div class="value" id="avg-ctr-value">Loading...</div>
    </div>
</div>
<h3 class="section-title">Break-Even Analysis</h3>
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
    
    <div class="column" style="flex: 1;">
        <h3 class="section-title">Operational KPIs</h3>
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

    <div class="column" style="flex: 2;">
        <h3 class="section-title">Recent Orders in Period</h3>
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
                    <tr><td colspan="4" style="text-align:center; padding: 20px;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?>