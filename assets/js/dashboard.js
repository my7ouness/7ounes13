document.addEventListener('DOMContentLoaded', function() {
    const workflowSelector = document.getElementById('workflow-selector');
    const pickerElement = document.getElementById('date-range-picker');
    if (!workflowSelector || !pickerElement) return;

    let datePicker;
    let currentWorkflowId = null;

    function updateKpi(id, value, isMonetary = false) {
        const element = document.getElementById(id);
        if (element) {
            const currency = isMonetary ? 'MAD ' : '';
            element.textContent = currency + value;
        }
    }

    function renderTable(tbodyId, data, columns) {
        const tbody = document.getElementById(tbodyId);
        const colspan = columns.length;
        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-cell">No data available.</td></tr>`;
            return;
        }
        tbody.innerHTML = data.map(row => `<tr>${columns.map(col => {
            let value = row[col.key] ?? 'N/A';
            let cellClass = col.class ? col.class(row) : '';
            if (col.key === 'status') {
                 value = `<span class="status-${(value || 'pending').toLowerCase()}">${value}</span>`;
            }
            return `<td class="${cellClass}">${value}</td>`;
        }).join('')}</tr>`).join('');
    }

    async function fetchDashboardData() {
        if (!currentWorkflowId || !datePicker.getStartDate() || !datePicker.getEndDate()) {
            return;
        }
        
        document.querySelectorAll('.value').forEach(el => el.textContent = '...');
        document.getElementById('net-profit-banner').classList.remove('is-positive', 'is-negative');
        document.querySelectorAll('tbody').forEach(tbody => {
            const colspan = tbody.previousElementSibling.querySelectorAll('th').length;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="loading-cell"><span></span>Loading...</td></tr>`;
        });

        const start = datePicker.getStartDate().dateInstance.toISOString().split('T')[0];
        const end = datePicker.getEndDate().dateInstance.toISOString().split('T')[0];
        
        const apiUrl = `${BASE_URL}/api/dashboard-data.php?workflow_id=${currentWorkflowId}&start=${start}&end=${end}`;

        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error(`Server Error: ${response.status}`);
            const data = await response.json();

            if (data.error) throw new Error(data.details || data.error);

            // 1. Populate Command Center
            const cc = data.command_center;
            const netProfit = parseFloat(cc.net_profit.replace(/,/g, ''));
            updateKpi('net-profit-value', cc.net_profit, true);
            document.getElementById('net-profit-banner').classList.add(netProfit >= 0 ? 'is-positive' : 'is-negative');
            updateKpi('cost-per-delivered-value', cc.cost_per_delivered, true);
            updateKpi('ad-spend-value', cc.ad_spend, true);
            updateKpi('delivered-revenue-value', cc.delivered_revenue, true);
            updateKpi('roi-value', cc.roi, false);

            // 2. Populate KPI Grid
            const kpis = data.kpis;
            updateKpi('lost-profit-rto-value', kpis.lost_profit_rto, true);
            updateKpi('fixed-charges-value', kpis.fixed_charges, true);
            updateKpi('breakeven-point-value', kpis.breakeven_point, false);
            updateKpi('pending-profit-value', kpis.pending_profit, true);
            updateKpi('gross-sales-value', kpis.gross_sales, true);
            updateKpi('total-orders-value', kpis.total_orders, false);
            updateKpi('shipped-orders-value', kpis.shipped_orders, false);
            updateKpi('delivered-orders-value', kpis.delivered_orders, false);
            updateKpi('returned-orders-value', kpis.returned_orders, false);
            updateKpi('delivery-rate-value', kpis.delivery_rate, false);
            updateKpi('return-rate-value', kpis.return_rate, false);
            updateKpi('confirmation-rate-value', kpis.confirmation_rate, false);

            // 3. Populate Tables
            renderTable('product-profitability-tbody', data.product_profitability, [
                { key: 'name' }, { key: 'units_sold' }, { key: 'revenue' }, { key: 'ad_spend' },
                { key: 'fixed_costs' }, { key: 'cogs' },
                { key: 'net_profit', class: r => parseFloat(r.net_profit.replace(/,/g, '')) >= 0 ? 'positive-text' : 'negative-text' },
                { key: 'delivery_rate' }
            ]);

            renderTable('campaign-performance-tbody', data.campaign_performance, [
                { key: 'name' }, { key: 'platform' }, { key: 'spend' }, { key: 'orders' }, { key: 'cpo' }
            ]);
            
            renderTable('recent-orders-tbody', data.recent_orders, [
                { key: 'platform_order_id' }, { key: 'store_name' }, { key: 'ad_platform' }, { key: 'status' }
            ]);

        } catch (error) {
            console.error("Error fetching dashboard data:", error);
            alert("Could not load dashboard data: " + error.message);
        }
    }

    async function initializeDashboard() {
        try {
            const response = await fetch(`${BASE_URL}/api/dashboard-data.php?action=get_workflows`);
            const workflows = await response.json();
            if (workflows && workflows.length > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                let selectedWorkflowId = urlParams.get('workflow_id');
                
                workflowSelector.innerHTML = workflows.map(w => `<option value="${w.id}" ${w.id == selectedWorkflowId ? 'selected' : ''}>${w.name}</option>`).join('');
                
                currentWorkflowId = selectedWorkflowId || workflows[0].id;
                
                workflowSelector.addEventListener('change', (e) => {
                    currentWorkflowId = e.target.value;
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.set('workflow_id', currentWorkflowId);
                    window.history.pushState({}, '', newUrl);
                    fetchDashboardData();
                });
            } else {
                workflowSelector.innerHTML = '<option>No workflows found</option>';
            }
        } catch (error) {
            console.error("Error fetching workflows:", error);
            workflowSelector.innerHTML = '<option>Error loading</option>';
        }

        const savedStartDate = localStorage.getItem('dashboardStartDate');
        const savedEndDate = localStorage.getItem('dashboardEndDate');

        datePicker = new Litepicker({
            element: pickerElement,
            singleMode: false,
            format: 'MMM D, YYYY',
            plugins: ['ranges'],
            startDate: savedStartDate ? new Date(savedStartDate) : new Date(),
            endDate: savedEndDate ? new Date(savedEndDate) : new Date(),
            setup: (picker) => {
                picker.on('selected', (date1, date2) => {
                    localStorage.setItem('dashboardStartDate', date1.dateInstance.toISOString());
                    localStorage.setItem('dashboardEndDate', date2.dateInstance.toISOString());
                    fetchDashboardData();
                });
            }
        });

        if (currentWorkflowId) {
            fetchDashboardData();
        } else {
            document.querySelector('.container').innerHTML = '<div class="alert">No workflows found. Please create a workflow to view the dashboard.</div>';
        }
    }

    initializeDashboard();
});