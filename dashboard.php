<?php
require_once 'includes/header.php';
require_login();
require_setup();
?>

<div class="dashboard-header">
    <h1>Dashboard</h1>
    <div class="dashboard-controls">
        <div class="control-group">
            <label for="workflow-selector">Workflow</label>
            <select id="workflow-selector" name="workflow_selector">
                <option>Loading...</option>
            </select>
        </div>
        <div class="control-group">
            <label for="date-range-picker">Date Range</label>
            <input type="text" id="date-range-picker" />
        </div>
    </div>
</div>

<!-- Full-Width Net Profit Banner -->
<div id="net-profit-banner" class="profit-banner">
    <div class="profit-banner-content">
        <h3>Net Profit</h3>
        <div class="value" id="net-profit-value">...</div>
    </div>
</div>

<!-- Comprehensive KPI Sections -->
<div class="dashboard-columns kpi-section">
    <div class="column">
        <h3 class="section-title">Profitability & Analysis</h3>
        <div class="kpi-card"><h3>Delivered Revenue</h3><div class="value" id="delivered-revenue-value">...</div></div>
        <div class="kpi-card"><h3>Total Costs</h3><div class="value" id="total-costs-value">...</div></div>
        <div class="kpi-card"><h3>ROAS</h3><div class="value" id="roas-value">...</div></div>
        <div class="kpi-card"><h3>Pending Profit</h3><div class="value" id="pending-profit-value">...</div></div>
        <div class="kpi-card"><h3>Lost Profit (RTO)</h3><div class="value" id="lost-profit-value">...</div></div>
        <div class="kpi-card"><h3>Cost Per Delivered</h3><div class="value" id="cost-per-delivered-value">...</div></div>
        <div class="kpi-card"><h3>Orders 'til Breakeven</h3><div class="value" id="breakeven-point-value">...</div></div>
    </div>
    <div class="column">
        <h3 class="section-title">Operational Health & Sales Funnel</h3>
        <div class="kpi-card"><h3>Gross Sales</h3><div class="value" id="gross-sales-value">...</div></div>
        <div class="kpi-card"><h3>Total Orders</h3><div class="value" id="total-orders-value">...</div></div>
        <div class="kpi-card"><h3>Shipped Orders</h3><div class="value" id="shipped-orders-value">...</div></div>
        <div class="kpi-card"><h3>Delivered Orders</h3><div class="value" id="delivered-orders-value">...</div></div>
        <div class="kpi-card"><h3>Delivery Rate</h3><div class="value" id="delivery-rate-value">...</div></div>
        <div class="kpi-card"><h3>Return Rate (RTO)</h3><div class="value" id="return-rate-value">...</div></div>
        <div class="kpi-card"><h3>Confirmation Rate</h3><div class="value" id="confirmation-rate-value">...</div></div>
    </div>
</div>

<!-- Ad Platform Breakdown -->
<h3 class="section-title">Ad Platform Breakdown</h3>
<div class="kpi-grid" id="platform-performance-grid">
    <!-- JS will populate this -->
</div>

<!-- Detailed Data Tables -->
<h3 class="section-title">Product-Level Profitability</h3>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Units Sold</th>
                <th>Revenue</th>
                <th>COGS</th>
                <th>Ad Spend</th>
                <th>Net Profit</th>
            </tr>
        </thead>
        <tbody id="product-profitability-tbody"></tbody>
    </table>
</div>

<div class="dashboard-columns">
    <div class="column">
        <h3 class="section-title">Campaign Breakdown</h3>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Campaign Name</th><th>Spend</th><th>CPM</th></tr></thead>
                <tbody id="campaign-breakdown-tbody"></tbody>
            </table>
        </div>
    </div>
    <div class="column">
        <h3 class="section-title">Recent Orders</h3>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Order ID</th><th>Date</th><th>Revenue</th><th>Platform</th><th>Status</th></tr></thead>
                <tbody id="recent-orders-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
