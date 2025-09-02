<?php
require_once 'includes/header.php';
require_login();
require_setup();

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// --- Handle Form Submission to Update a Product's Cost ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cost'])) {
    $product_id = $_POST['product_id'] ?? null;
    $cost = $_POST['cost'] ?? 0;

    // Basic validation
    if ($product_id && is_numeric($cost) && $cost >= 0) {
        try {
            // Ensure the product belongs to the current user before updating
            $stmt = $pdo->prepare("UPDATE products SET cost = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$cost, $product_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = "Product cost updated successfully!";
            } else {
                $error = "Could not update product. It may not belong to you.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid input. Please provide a valid product and cost.";
    }
}


// --- Fetch All Products for the Current User ---
$products = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, sku, cost, platform_product_id FROM products WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not fetch products: " . $e->getMessage();
}

?>

<h1>Product Costs</h1>
<p>Enter the Cost of Goods Sold (COGS) for each product. This is crucial for accurate profit calculation.</p>

<?php if ($message): ?>
    <div class="alert success" style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error" style="background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table class="products-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>SKU</th>
                <th>Cost of Goods (COGS)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px;">
                        No products found. Your products will appear here automatically after your first order syncs.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                        <td>
                            <form action="products.php" method="POST" style="display: flex; align-items: center; gap: 10px;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="cost" step="0.01" class="cogs-input" value="<?php echo htmlspecialchars(number_format($product['cost'], 2)); ?>" required>
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