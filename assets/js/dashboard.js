document.addEventListener('DOMContentLoaded', function() {
    const pickerElement = document.getElementById('date-range-picker');
    if (!pickerElement) return;

    /**
     * Helper function to update a KPI card's text and color on the UI.
     * @param {string} id - The HTML ID of the element to update.
     * @param {string} value - The new value to display.
     * @param {boolean} isMonetary - If true, prepends the currency symbol.
     */
    function updateKpi(id, value, isMonetary = false) {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`KPI element with ID "${id}" not found.`);
            return;
        }
        
        let displayValue = isMonetary ? 'MAD ' + value : value;
        element.textContent = displayValue;

        // Apply color formatting for Net Profit and "Orders to be Profitable"
        if (id === 'net-profit-value') {
            element.classList.remove('positive', 'negative');
            const numericValue = parseFloat(String(value).replace(/,/g, ''));
            if (numericValue >= 0) {
                element.classList.add('positive');
            } else {
                element.classList.add('negative');
            }
        }
        if (id === 'orders-to-be-profitable-value') {
            element.classList.remove('positive', 'negative');
            const numericValue = parseInt(String(value).replace(/,/g, ''));
             if (numericValue <= 0) {
                element.classList.add('positive'); // It's positive if you've already met the goal
            }
        }
    }

    /**
     * Populates the recent orders widget with data from the API.
     * @param {Array} orders - An array of order objects.
     */
    function updateOrdersWidget(orders) {
        const tbody = document.getElementById('orders-widget-tbody');
        if (!tbody) return;

        if (!orders || orders.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px;">No orders found in this period.</td></tr>`;
            return;
        }

        let tableHtml = '';
        orders.forEach(order => {
            const orderDate = new Date(order.order_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const status = order.shipping_status || 'pending';
            tableHtml += `
                <tr>
                    <td>#${order.platform_order_id}</td>
                    <td>${orderDate}</td>
                    <td>MAD ${parseFloat(order.total_revenue).toFixed(2)}</td>
                    <td><span class="status-${status}">${status}</span></td>
                </tr>
            `;
        });
        tbody.innerHTML = tableHtml;
    }

    /**
     * Main function to fetch all dashboard data from the backend API.
     * @param {Date} startDate - The start of the date range.
     * @param {Date} endDate - The end of the date range.
     */
    async function fetchDashboardData(startDate, endDate) {
        const allKpiElements = document.querySelectorAll('.kpi-card .value, #orders-widget-tbody');
        allKpiElements.forEach(el => {
            if (el.tagName === 'TBODY') {
                 el.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px;">Loading...</td></tr>`;
            } else {
                el.textContent = 'Loading...';
            }
        });

        const start = startDate.toISOString().split('T')[0];
        const end = endDate.toISOString().split('T')[0];
        const apiUrl = `${BASE_URL}/api/dashboard-data.php?start=${start}&end=${end}`;

        try {
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }

            const data = await response.json();

            if (data.kpis) {
                const kpis = data.kpis;
                
                // Update all KPI elements on the page
                updateKpi('net-profit-value', kpis.net_profit, true);
                updateKpi('delivered-revenue-value', kpis.delivered_revenue, true);
                updateKpi('total-costs-value', kpis.total_costs, true);
                updateKpi('roas-value', kpis.roas);
                
                // --- Update New Ad Performance KPIs ---
                updateKpi('ad-spend-value', kpis.total_ad_spend, true);
                updateKpi('total-clicks-value', kpis.total_clicks);
                updateKpi('avg-cpc-value', kpis.avg_cpc, true);
                updateKpi('avg-cpm-value', kpis.avg_cpm, true);
                updateKpi('avg-ctr-value', kpis.avg_ctr);
                
                updateKpi('break-even-orders-value', kpis.break_even_orders);
                updateKpi('orders-to-be-profitable-value', kpis.orders_to_be_profitable);
                
                updateKpi('delivered-orders-value', kpis.delivered_orders);
                updateKpi('returned-orders-value', kpis.returned_orders);
                updateKpi('delivery-rate-value', kpis.delivery_rate);

                // Update the new orders widget
                updateOrdersWidget(data.orders_widget_data);

            } else {
                console.error("API returned no KPI data:", data.error || "Unknown error.");
                allKpiElements.forEach(el => el.textContent = 'Data Error');
            }
        } catch (error) {
            console.error("Error fetching dashboard data:", error);
            allKpiElements.forEach(el => el.textContent = 'Network Error');
        }
    }

    // Initialize the Litepicker date range selector
    const picker = new Litepicker({
        element: pickerElement,
        singleMode: false,
        format: 'MMM D, YYYY',
        plugins: ['ranges'],
        ranges: {
            'Today': [new Date(), new Date()],
            'Yesterday': [
                (() => { const d = new Date(); d.setDate(d.getDate() - 1); return d; })(),
                (() => { const d = new Date(); d.setDate(d.getDate() - 1); return d; })()
            ],
            'Last 7 Days': [
                (() => { const d = new Date(); d.setDate(d.getDate() - 6); return d; })(),
                new Date()
            ],
            'Last 30 Days': [
                (() => { const d = new Date(); d.setDate(d.getDate() - 29); return d; })(),
                new Date()
            ],
            'This Month': [
                (() => { const d = new Date(); d.setDate(1); return d; })(),
                new Date()
            ],
        },
        setup: (picker) => {
            // Bind the data fetching function to the date selection event
            picker.on('selected', (date1, date2) => {
                if (date1 && date2) {
                    fetchDashboardData(date1.dateInstance, date2.dateInstance);
                }
            });
        }
    });

    // Perform the initial data load for "Today"
    const today = new Date();
    fetchDashboardData(today, today);
    // Set the initial visual value in the picker to show "Today"
    picker.setDateRange(today, today);
});