document.addEventListener('DOMContentLoaded', function() {
    const workflowSelector = document.getElementById('workflow-selector');
    const pickerElement = document.getElementById('date-range-picker');
    if (!workflowSelector || !pickerElement) return;

    let datePicker;
    let currentWorkflowId = null;

    // --- Helper & Render Functions ---
    function updateKpi(id, value, isMonetary = false) {
        const element = document.getElementById(id);
        if (element) {
            const currency = isMonetary ? 'MAD ' : '';
            element.textContent = currency + value;
        }
    }

    function renderPlatformBreakdown(platforms) {
        const container = document.getElementById('platform-performance-grid');
        let html = '';
        for (const [platform, data] of Object.entries(platforms)) {
            html += `
                <div class="kpi-card"><h3>${platform} Spend</h3><div class="value">MAD ${data.spend}</div></div>
                <div class="kpi-card"><h3>${platform} CPM</h3><div class="value">MAD ${data.cpm}</div></div>`;
        }
        container.innerHTML = html || '<div class="kpi-card"><p>No ad platform data found.</p></div>';
    }

    function renderTable(tbodyId, data, columns) {
        const tbody = document.getElementById(tbodyId);
        const colspan = columns.length;
        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center empty-cell">No data for this period.</td></tr>`;
            return;
        }
        tbody.innerHTML = data.map(row => `<tr>${columns.map(col => {
            let value = row[col.key];
            let cellClass = col.class ? col.class(row) : '';
            return `<td class="${cellClass}">${value}</td>`;
        }).join('')}</tr>`).join('');
    }

    // --- Main Data Fetching Function ---
    async function fetchDashboardData() {
        if (!currentWorkflowId || !datePicker.getStartDate() || !datePicker.getEndDate()) return;
        
        // Set loading states
        document.querySelectorAll('.value').forEach(el => el.textContent = '...');
        document.getElementById('net-profit-banner').classList.remove('is-positive', 'is-negative');
        document.querySelectorAll('tbody').forEach(tbody => {
            const colspan = tbody.previousElementSibling.querySelectorAll('th').length;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center loading-cell"><span></span>Loading...</td></tr>`;
        });

        // --- THIS IS THE FIX ---
        // We now access .dateInstance to get the correct JavaScript Date object
        const start = datePicker.getStartDate().dateInstance.toISOString().split('T')[0];
        const end = datePicker.getEndDate().dateInstance.toISOString().split('T')[0];
        // --- END OF FIX ---
        
        const apiUrl = `${BASE_URL}/api/dashboard-data.php?workflow_id=${currentWorkflowId}&start=${start}&end=${end}`;

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error(`Server Error: ${response.status}`);
            const data = await response.json();

            // Populate Banner
            const netProfit = parseFloat(data.net_profit_banner.net_profit);
            updateKpi('net-profit-value', netProfit.toFixed(2), true);
            document.getElementById('net-profit-banner').classList.add(netProfit >= 0 ? 'is-positive' : 'is-negative');
            
            // Populate KPIs
            for (const [key, value] of Object.entries(data.kpis)) {
                const elementId = key.replace(/_/g, '-') + '-value';
                const isMonetary = ['delivered_revenue', 'total_costs', 'pending_profit', 'lost_profit_rto', 'cost_per_delivered', 'gross_sales'].includes(key);
                updateKpi(elementId, value, isMonetary);
            }

            // Populate Breakdowns
            renderPlatformBreakdown(data.platform_breakdown);
            renderTable('product-profitability-tbody', data.product_profitability, [
                { key: 'name' }, { key: 'units_sold' }, { key: 'revenue' }, { key: 'cogs' }, { key: 'ad_spend' },
                { key: 'net_profit', class: r => parseFloat(r.net_profit) >= 0 ? 'positive-text' : 'negative-text' }
            ]);
            renderTable('campaign-breakdown-tbody', data.campaign_breakdown, [{ key: 'name' }, { key: 'spend' }, { key: 'cpm' }]);
            renderTable('recent-orders-tbody', data.recent_orders, [
                { key: 'order_id' }, { key: 'date' }, { key: 'revenue' }, { key: 'platform' },
                { key: 'status', class: r => `status-${r.status}` }
            ]);

        } catch (error) {
            console.error("Error fetching dashboard data:", error);
        }
    }

    // --- Initialization ---
    async function initializeDashboard() {
        // 1. Fetch workflows and populate selector
        try {
            const response = await fetch(`${BASE_URL}/api/dashboard-data.php?action=get_workflows`);
            const workflows = await response.json();
            if (workflows.length > 0) {
                workflowSelector.innerHTML = workflows.map(w => `<option value="${w.id}">${w.name}</option>`).join('');
                currentWorkflowId = workflows[0].id; // Select the first workflow
                workflowSelector.addEventListener('change', (e) => {
                    currentWorkflowId = e.target.value;
                    fetchDashboardData();
                });
            } else {
                workflowSelector.innerHTML = '<option>No workflows found</option>';
            }
        } catch (error) {
            workflowSelector.innerHTML = '<option>Error loading</option>';
        }

        // 2. Initialize Date Picker
        datePicker = new Litepicker({
            element: pickerElement,
            singleMode: false,
            format: 'MMM D, YYYY',
            plugins: ['ranges'],
            setup: (picker) => {
                picker.on('selected', fetchDashboardData);
            }
        });
        datePicker.setDateRange(new Date(), new Date());

        // 3. Initial data load
        if (currentWorkflowId) {
            fetchDashboardData();
        }
    }

    initializeDashboard();
});

