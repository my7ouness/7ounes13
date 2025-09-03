<?php
require_once 'includes/header.php';
require_login();

// --- Step 1: Get and Validate the Workflow ID ---
$workflow_id = $_GET['workflow_id'] ?? null;
if (!$workflow_id) {
    header('Location: workflows.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

try {
    $wf_stmt = $pdo->prepare("SELECT name FROM workflows WHERE id = ? AND user_id = ?");
    $wf_stmt->execute([$workflow_id, $user_id]);
    $workflow_name = $wf_stmt->fetchColumn();
    if (!$workflow_name) {
        header('Location: workflows.php');
        exit();
    }
} catch (PDOException $e) {
    die("Database error while verifying workflow.");
}


// --- Step 2: Handle All Form Submissions for THIS Workflow ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Handle Deletion Requests ---
    if (isset($_POST['delete_connection'])) {
        $connection_id = $_POST['connection_id'];
        $connection_type = $_POST['connection_type'];
        $table_map = ['store' => 'stores', 'ad_account' => 'ad_accounts', 'shipping' => 'shipping_carriers', 'cost' => 'costs'];

        if (isset($table_map[$connection_type])) {
            $table = $table_map[$connection_type];
            try {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ? AND workflow_id = ?");
                $stmt->execute([$connection_id, $user_id, $workflow_id]);
                $message = ucfirst(str_replace('_', ' ', $connection_type)) . " item deleted successfully.";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // --- Handle Facebook Ad Account Save Request from the modal ---
    if (isset($_POST['save_ad_account'])) {
        $account_name = trim($_POST['fb_account_name'] ?? '');
        $account_id = trim($_POST['fb_account_id'] ?? '');
        $access_token = trim($_POST['fb_access_token'] ?? '');
        $selected_campaigns = $_POST['campaigns'] ?? [];

        if (empty($account_name) || empty($account_id) || empty($access_token)) {
            $error = "Account Nickname, Ad Account ID, and Access Token are all required.";
        } else {
            try {
                $campaigns_json = json_encode($selected_campaigns);
                // Use the correct column name from our updated schema
                $stmt = $pdo->prepare(
                    "INSERT INTO ad_accounts (user_id, workflow_id, platform, account_name, account_id, access_token, selected_campaign_ids) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $workflow_id, 'facebook', $account_name, $account_id, $access_token, $campaigns_json]);
                $message = "Facebook Ad Account connected successfully!";

            } catch (PDOException $e) {
                if ($e->getCode() == '23505') { 
                     $error = "Error: An ad account with this ID may already be connected to this or another workflow.";
                } else {
                    $error = "Database Error: Could not save the ad account. " . $e->getMessage();
                }
            }
        }
    }
    // WooCommerce save logic could also be here if needed
}

// --- Step 3: Fetch ALL data for this workflow to display ---
$logo_map = ['woocommerce' => 'woocommerce.png', 'facebook' => 'facebook.png', 'sendit.ma' => 'shipping.png', 'cost' => 'cost.png'];
$base_logo_path = BASE_URL . '/assets/images/logos/';
$nodes = [];

$stores_stmt = $pdo->prepare("SELECT id, name, platform FROM stores WHERE user_id = ? AND workflow_id = ?");
$stores_stmt->execute([$user_id, $workflow_id]);
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
if(!empty($stores)) $nodes[] = $base_logo_path . $logo_map['woocommerce'];

$ad_accounts_stmt = $pdo->prepare("SELECT id, platform, account_name FROM ad_accounts WHERE user_id = ? AND workflow_id = ?");
$ad_accounts_stmt->execute([$user_id, $workflow_id]);
$ad_accounts = $ad_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
if(!empty($ad_accounts)) $nodes[] = $base_logo_path . $logo_map['facebook'];

// --- (Fetch logic for shipping and costs would go here) ---
$nodes = array_unique($nodes);
?>

<div class="setup-header">
    <h1>Manage Workflow: "<?php echo htmlspecialchars($workflow_name); ?>"</h1>
    <a href="workflows.php" class="btn btn-secondary">Back to Workflows</a>
</div>

<?php if ($message): ?> <div class="alert success"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert error"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>

<div class="visual-builder-canvas">
    <div class="workflow-timeline">
         <?php if (empty($nodes)): ?>
            <p style="color: var(--text-light); margin:0;">This workflow is empty. Click '+' to start building.</p>
        <?php else: ?>
            <?php foreach ($nodes as $i => $node_logo_url): ?>
                <div class="timeline-node"><img src="<?php echo $node_logo_url; ?>" alt="Workflow node"></div>
                <?php if ($i < count($nodes) - 1): ?>
                    <svg class="timeline-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <button class="add-node-btn" id="add-connection-btn">+</button>
    </div>
</div>

<h2 style="margin-top: 40px; font-size: 1.5rem;">Connection Details</h2>
<p style="margin-bottom: 20px; color: var(--text-light);">Manage the specific services connected to this workflow below.</p>

<div class="connection-grid">
    <?php if (!empty($stores)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>woocommerce.png" class="header-icon" alt="WooCommerce"><h3>Stores</h3></div>
        <ul class="connection-list">
            <?php foreach($stores as $store): ?>
                <li>
                    <span><?php echo htmlspecialchars($store['name']); ?></span>
                    <form action="setup.php?workflow_id=<?php echo $workflow_id; ?>" method="POST" onsubmit="return confirm('Delete this store?');">
                        <input type="hidden" name="connection_id" value="<?php echo $store['id']; ?>"><input type="hidden" name="connection_type" value="store">
                        <button type="submit" name="delete_connection" class="btn btn-delete">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($ad_accounts)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>facebook.png" class="header-icon" alt="Facebook"><h3>Ad Platforms</h3></div>
        <ul class="connection-list">
            <?php foreach($ad_accounts as $ad_account): ?>
            <li>
                <span><?php echo htmlspecialchars($ad_account['account_name']); ?></span>
                <form action="setup.php?workflow_id=<?php echo $workflow_id; ?>" method="POST" onsubmit="return confirm('Delete this ad account?');">
                    <input type="hidden" name="connection_id" value="<?php echo $ad_account['id']; ?>"><input type="hidden" name="connection_type" value="ad_account">
                    <button type="submit" name="delete_connection" class="btn btn-delete">Delete</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="main-modal"></div>
</div>

<template id="template-select-type">
    <div class="modal-header"><h2>What would you like to add?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-select-store"><img src="<?php echo $base_logo_path; ?>woocommerce.png" alt="Store"><h3>Store</h3></div>
        <div class="choice-card" data-next-template="template-select-ad-platform"><img src="<?php echo $base_logo_path; ?>facebook.png" alt="Ad Platform"><h3>Ad Platform</h3></div>
    </div></div>
</template>

<template id="template-select-store">
    <!-- ... WooCommerce selection ... -->
</template>

<template id="template-select-ad-platform">
     <div class="modal-header"><h2>Which ad platform?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-form-facebook"><img src="<?php echo $base_logo_path; ?>facebook.png" alt="Facebook"><h3>Facebook Ads</h3></div>
    </div></div>
</template>

<template id="template-form-woocommerce">
    <!-- ... WooCommerce form ... -->
</template>

<template id="template-form-facebook">
    <div class="modal-header"><h2>Connect Facebook Ads</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form id="facebook-connection-form" action="setup.php?workflow_id=<?php echo $workflow_id; ?>" method="POST">
            <div class="fb-step" id="fb-step-1">
                <div class="form-group">
                    <label for="fb_account_name">Account Nickname</label>
                    <input type="text" id="fb_account_name" name="fb_account_name" required placeholder="e.g., My Primary Ad Account">
                </div>
                <div class="form-group">
                    <label for="fb_account_id">Ad Account ID</label>
                    <input type="text" id="fb_account_id" name="fb_account_id" required placeholder="e.g., act_123456789">
                </div>
                <div class="form-group">
                    <label for="fb_access_token">Access Token</label>
                    <input type="password" id="fb_access_token" name="fb_access_token" required>
                </div>
                <div id="test-results" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 5px;"></div>
                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="button" id="test-connection-btn" class="btn btn-secondary" style="flex: 1;">Test Connection</button>
                    <button type="button" id="fetch-campaigns-btn" class="btn btn-primary" style="flex: 2;">Fetch Campaigns</button>
                </div>
            </div>
            <div class="fb-step" id="fb-step-2" style="display: none;">
                <h4>Select Campaigns to Track</h4>
                <p>Select the campaigns you want to track in this workflow.</p>
                <div id="campaign-list-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 5px; margin-bottom: 20px;"></div>
                <div id="campaign-error" class="alert error" style="display:none;"></div>
                <button type="submit" name="save_ad_account" class="btn btn-primary btn-full-width">Save Connection</button>
            </div>
        </form>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>

