<?php
require_once 'includes/header.php';
require_login();

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// --- Handle Workflow Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workflow'])) {
    $workflow_id_to_delete = $_POST['workflow_id'];
    // Cascading deletes in the DB now handle the cleanup
    try {
        $stmt_delete = $pdo->prepare("DELETE FROM workflows WHERE id = ? AND user_id = ?");
        $stmt_delete->execute([$workflow_id_to_delete, $user_id]);
        $message = "Workflow deleted successfully.";
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// --- Fetch workflows and their connections for the card display ---
$workflows_data = [];
try {
    $workflows_stmt = $pdo->prepare("SELECT id, name FROM workflows WHERE user_id = ? ORDER BY created_at DESC");
    $workflows_stmt->execute([$user_id]);
    $workflows = $workflows_stmt->fetchAll(PDO::FETCH_ASSOC);

    $logo_map = ['woocommerce' => 'woocommerce.png', 'facebook' => 'facebook.png', 'tiktok' => 'tiktok.png', 'shipping' => 'shipping.png', 'cost' => 'cost.png'];
    $base_logo_path = BASE_URL . '/assets/images/logos/';

    foreach ($workflows as $workflow) {
        $nodes = [];
        
        // Check for Stores
        $stores_stmt = $pdo->prepare("SELECT 1 FROM stores WHERE workflow_id = ? LIMIT 1");
        $stores_stmt->execute([$workflow['id']]);
        if($stores_stmt->fetch()) $nodes[] = $base_logo_path . $logo_map['woocommerce'];
        
        // Check for Ad Platforms
        $ads_stmt = $pdo->prepare("SELECT DISTINCT platform FROM ad_accounts WHERE workflow_id = ?");
        $ads_stmt->execute([$workflow['id']]);
        $platforms = $ads_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($platforms as $platform) {
            if(isset($logo_map[$platform])) {
                $nodes[] = $base_logo_path . $logo_map[$platform];
            }
        }

        // Check for Shipping
        $shipping_stmt = $pdo->prepare("SELECT 1 FROM shipping_carriers WHERE workflow_id = ? LIMIT 1");
        $shipping_stmt->execute([$workflow['id']]);
        if($shipping_stmt->fetch()) $nodes[] = $base_logo_path . $logo_map['shipping'];
        
        // MODIFIED: Check for Fixed Costs
        $costs_stmt = $pdo->prepare("SELECT 1 FROM costs WHERE workflow_id = ? LIMIT 1");
        $costs_stmt->execute([$workflow['id']]);
        if($costs_stmt->fetch()) {
            $nodes[] = $base_logo_path . $logo_map['cost'];
        }

        $workflows_data[] = ['id' => $workflow['id'], 'name' => $workflow['name'], 'nodes' => array_unique($nodes)];
    }
} catch (PDOException $e) {
    $error = "Error fetching workflow data: " . $e->getMessage();
}
?>

<div class="setup-header">
    <h1>My Workflows</h1>
    <a href="create-workflow.php" class="btn btn-primary">+ Create New Workflow</a>
</div>
<p>Create automated timelines to track your business profitability from store to final costs.</p>

<?php if ($message): ?> <div class="alert success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert error" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="workflows-container">
    <?php if (empty($workflows_data)): ?>
        <div style="text-align:center; padding: 50px; background-color: #fff; border-radius: 8px;">
            <h3>You haven't created any workflows yet.</h3>
            <p>Click the button above to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($workflows_data as $workflow): ?>
            <div class="workflow-card">
                <div class="workflow-header">
                    <h2><?php echo htmlspecialchars($workflow['name']); ?></h2>
                    <div class="workflow-actions">
                        <a href="setup.php?workflow_id=<?php echo $workflow['id']; ?>" class="btn-edit">Manage</a>
                        <form action="workflows.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this workflow and all of its data? This cannot be undone.');">
                            <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                            <button type="submit" name="delete_workflow" class="btn btn-delete">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="workflow-timeline">
                    <?php if (empty($workflow['nodes'])): ?>
                        <p style="color: var(--text-light); margin:0;">This workflow is empty. Click 'Manage' to add connections.</p>
                    <?php else: ?>
                        <?php foreach ($workflow['nodes'] as $i => $node_logo_url): ?>
                            <div class="timeline-node"><img src="<?php echo $node_logo_url; ?>"></div>
                            <?php if ($i < count($workflow['nodes']) - 1): ?>
                               <svg class="timeline-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>