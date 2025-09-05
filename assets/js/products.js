
document.addEventListener('DOMContentLoaded', function() {
    const workflowSelector = document.getElementById('workflow-selector');
    const syncBtn = document.getElementById('sync-products-btn');
    const syncStatusDiv = document.getElementById('sync-status');
    const productFilter = document.getElementById('product-filter');
    const productsTbody = document.getElementById('products-tbody');

    // 1. Handle Workflow Selection Change
    if (workflowSelector) {
        workflowSelector.addEventListener('change', function() {
            const selectedWorkflowId = this.value;
            window.location.href = `products.php?workflow_id=${selectedWorkflowId}`;
        });
    }

    // 2. Handle Sync Button Click
    if (syncBtn) {
        syncBtn.addEventListener('click', async function() {
            const workflowId = workflowSelector.value;
            if (!workflowId) {
                alert('Please select a workflow first.');
                return;
            }

            syncBtn.disabled = true;
            syncBtn.textContent = 'Syncing...';
            syncStatusDiv.style.display = 'block';
            syncStatusDiv.className = 'alert';
            syncStatusDiv.textContent = 'Please wait, syncing all products from your store. This may take a few minutes...';

            try {
                const response = await fetch(`${BASE_URL}/api/product-sync.php?workflow_id=${workflowId}`);
                const result = await response.json();

                if (result.success) {
                    syncStatusDiv.className = 'alert success';
                    syncStatusDiv.textContent = result.message + ' Page will now refresh.';
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                syncStatusDiv.className = 'alert error';
                syncStatusDiv.textContent = `Error: ${error.message}`;
                syncBtn.disabled = false;
                syncBtn.textContent = 'Sync Products from Store';
            }
        });
    }

    // 3. Handle Product List Filtering
    if (productFilter && productsTbody) {
        productFilter.addEventListener('keyup', function() {
            const filterText = this.value.toLowerCase();
            const rows = productsTbody.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const name = row.dataset.name;
                const sku = row.dataset.sku;
                if (name.includes(filterText) || sku.includes(filterText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});