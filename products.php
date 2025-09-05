<?php
require_once 'includes/header.php';
require_login();
// require_setup is no longer needed here if we allow workflow selection

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// --- Handle Form Submission to Update a Product's Cost ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cost'])) {
    $product_id = $_POST['product_id'] ?? null;
    $cost = $_POST['cost'] ?? 0;

    if ($product_id && is_numeric($cost) && $cost >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET cost = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$cost, $product_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Product cost updated successfully!";
            } else {
                $error = "Could not update product. It may not belong to you or the value was unchanged.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid input. Please provide a valid product and cost.";
    }
}

// Fetch all workflows for the selector
$workflows_stmt = $pdo->prepare("SELECT id, name FROM workflows WHERE user_id = ? ORDER BY name");
$workflows_stmt->execute([$user_id]);
$workflows = $workflows_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine the current workflow
$current_workflow_id = $_GET['workflow_id'] ?? ($workflows[0]['id'] ?? null);

// --- Fetch All Products for the Current Workflow ---
$products = [];
if ($current_workflow_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, sku, cost, platform_product_id FROM products WHERE user_id = ? AND workflow_id = ? ORDER BY name ASC");
        $stmt->execute([$user_id, $current_workflow_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Could not fetch products: " . $e->getMessage();
    }
}

?>

<div class="page-header">
    <h1>Product Costs</h1>
    <div class="page-controls">
        <div class="control-group">
            <label for="workflow-selector">Workflow</label>
            <select id="workflow-selector" name="workflow_selector">
                <?php if (empty($workflows)): ?>
                    <option>No workflows found</option>
                <?php else: ?>
                    <?php foreach ($workflows as $workflow): ?>
                        <option value="<?php echo $workflow['id']; ?>" <?php echo ($workflow['id'] === $current_workflow_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($workflow['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <button id="sync-products-btn" class="btn btn-secondary" <?php echo empty($current_workflow_id) ? 'disabled' : ''; ?>>Sync Products from Store</button>
    </div>
</div>

<p>Set the Cost of Goods Sold (COGS) for each product. This is crucial for accurate profit calculation.</p>

<div id="sync-status" style="display:none; margin: 15px 0;"></div>

<?php if ($message): ?>
    <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="table-header">
    <input type="text" id="product-filter" placeholder="Filter products by name or SKU...">
</div>

<div class="table-container">
    <table class="products-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Cost of Goods (COGS)</th>
                <th style="width: 120px;">Action</th>
            </tr>
        </thead>
        <tbody id="products-tbody">
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="4" class="empty-cell">
                        No products found. Use the "Sync Products" button to import them from your store.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr class="product-row" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>" data-sku="<?php echo strtolower(htmlspecialchars($product['sku'] ?? '')); ?>">
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                        <td>
                            <form action="products.php?workflow_id=<?php echo $current_workflow_id; ?>" method="POST" class="cogs-form">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="cost" step="0.01" class="cogs-input" value="<?php echo htmlspecialchars(number_format($product['cost'], 2, '.', '')); ?>" required>
                        </td>
                        <td>
                                <button type="submit" name="save_cost" class="btn btn-save-cost">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>