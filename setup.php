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
    die("Database error while verifying workflow: " . $e->getMessage());
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
    
    // --- Handle WooCommerce Store Save ---
    if (isset($_POST['save_woocommerce_store'])) {
        $store_name = trim($_POST['store_name'] ?? '');
        $store_url = rtrim(trim($_POST['store_url'] ?? ''), '/');
        $consumer_key = trim($_POST['consumer_key'] ?? '');
        $consumer_secret = trim($_POST['consumer_secret'] ?? '');

        if (empty($store_name) || empty($store_url) || empty($consumer_key) || empty($consumer_secret)) {
             $error = "All WooCommerce fields are required.";
        } else {
            try {
                // --- THIS IS THE FIX ---
                // Removed the non-existent 'status' column from the query
                $stmt = $pdo->prepare(
                    "INSERT INTO stores (user_id, workflow_id, name, platform, api_url, api_key, api_secret) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $workflow_id, $store_name, 'woocommerce', $store_url, $consumer_key, $consumer_secret]);
                // --- END OF FIX ---
                $message = "WooCommerce store connected successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    // --- Handle Facebook Ad Account Save ---
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
                $stmt = $pdo->prepare(
                    "INSERT INTO ad_accounts (user_id, workflow_id, platform, account_name, account_id, access_token, selected_campaign_ids) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $workflow_id, 'facebook', $account_name, $account_id, $access_token, $campaigns_json]);
                $message = "Facebook Ad Account connected successfully!";
            } catch (PDOException $e) {
                 $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    // --- Handle Shipping Carrier Save ---
    if (isset($_POST['save_shipping_carrier'])) {
        $carrier_name = trim($_POST['shipping_carrier_name'] ?? '');
        if (empty($carrier_name)) {
            $error = "Shipping carrier name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO shipping_carriers (user_id, workflow_id, carrier_name) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $workflow_id, $carrier_name]);
                $message = "Shipping carrier added successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
    
    // --- Handle Fixed Cost Save ---
    if (isset($_POST['save_fixed_cost'])) {
        $cost_name = trim($_POST['cost_name'] ?? '');
        $cost_amount = $_POST['cost_amount'] ?? 0;
        $cost_period = $_POST['cost_period'] ?? 'monthly';

        if (empty($cost_name) || !is_numeric($cost_amount) || $cost_amount <= 0) {
            $error = "Valid cost name and amount are required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO costs (user_id, workflow_id, cost_name, amount, recurrence) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $workflow_id, $cost_name, $cost_amount, $cost_period]);
                 $message = "Fixed cost added successfully!";
            } catch (PDOException $e) {
                 $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// --- Step 3: Fetch ALL data for this workflow to display ---
$logo_map = ['woocommerce' => 'woocommerce.png', 'facebook' => 'facebook.png', 'shipping' => 'shipping.png', 'cost' => 'cost.png'];
$base_logo_path = BASE_URL . '/assets/images/logos/';
$nodes = [];

$stores_stmt = $pdo->prepare("SELECT id, name, platform FROM stores WHERE user_id = ? AND workflow_id = ?");
$stores_stmt->execute([$user_id, $workflow_id]);
if ($stores_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['woocommerce'];

$ad_accounts_stmt = $pdo->prepare("SELECT id, platform, account_name FROM ad_accounts WHERE user_id = ? AND workflow_id = ?");
$ad_accounts_stmt->execute([$user_id, $workflow_id]);
if ($ad_accounts_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['facebook'];

$shipping_stmt = $pdo->prepare("SELECT id, carrier_name FROM shipping_carriers WHERE user_id = ? AND workflow_id = ?");
$shipping_stmt->execute([$user_id, $workflow_id]);
if ($shipping_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['shipping'];

$costs_stmt = $pdo->prepare("SELECT id, cost_name, amount, recurrence FROM costs WHERE user_id = ? AND workflow_id = ?");
$costs_stmt->execute([$user_id, $workflow_id]);
if ($costs_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['cost'];

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
        <button class="add-node-btn">+</button>
    </div>
</div>

<h2 style="margin-top: 40px; font-size: 1.5rem;">Connection Details</h2>
<p style="margin-bottom: 20px; color: var(--text-light);">Manage the specific services connected to this workflow below.</p>

<div class="connection-grid">
    <?php $store_list = $stores_stmt->fetchAll(PDO::FETCH_ASSOC); if (!empty($store_list)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>woocommerce.png" class="header-icon" alt="WooCommerce"><h3>Stores</h3></div>
        <ul class="connection-list">
            <?php foreach($store_list as $item): ?>
                <li>
                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                    <form method="POST" onsubmit="return confirm('Delete this connection?');"><input type="hidden" name="connection_id" value="<?php echo $item['id']; ?>"><input type="hidden" name="connection_type" value="store"><button type="submit" name="delete_connection" class="btn btn-delete">Delete</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php $ad_account_list = $ad_accounts_stmt->fetchAll(PDO::FETCH_ASSOC); if (!empty($ad_account_list)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>facebook.png" class="header-icon" alt="Facebook"><h3>Ad Platforms</h3></div>
        <ul class="connection-list">
            <?php foreach($ad_account_list as $item): ?>
            <li>
                <span><?php echo htmlspecialchars($item['account_name']); ?></span>
                 <form method="POST" onsubmit="return confirm('Delete this connection?');"><input type="hidden" name="connection_id" value="<?php echo $item['id']; ?>"><input type="hidden" name="connection_type" value="ad_account"><button type="submit" name="delete_connection" class="btn btn-delete">Delete</button></form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php $shipping_list = $shipping_stmt->fetchAll(PDO::FETCH_ASSOC); if (!empty($shipping_list)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>shipping.png" class="header-icon"><h3>Shipping Carriers</h3></div>
        <ul class="connection-list">
            <?php foreach($shipping_list as $item): ?>
            <li>
                <span><?php echo htmlspecialchars($item['carrier_name']); ?></span>
                 <form method="POST" onsubmit="return confirm('Delete this connection?');"><input type="hidden" name="connection_id" value="<?php echo $item['id']; ?>"><input type="hidden" name="connection_type" value="shipping"><button type="submit" name="delete_connection" class="btn btn-delete">Delete</button></form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php $costs_list = $costs_stmt->fetchAll(PDO::FETCH_ASSOC); if (!empty($costs_list)): ?>
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>cost.png" class="header-icon"><h3>Fixed Costs</h3></div>
        <ul class="connection-list">
            <?php foreach($costs_list as $item): ?>
            <li>
                <span><?php echo htmlspecialchars($item['cost_name']); ?> (<?php echo number_format($item['amount'], 2); ?>/<?php echo substr($item['recurrence'], 0, 2); ?>)</span>
                 <form method="POST" onsubmit="return confirm('Delete this connection?');"><input type="hidden" name="connection_id" value="<?php echo $item['id']; ?>"><input type="hidden" name="connection_type" value="cost"><button type="submit" name="delete_connection" class="btn btn-delete">Delete</button></form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modal-overlay"><div class="modal" id="main-modal"></div></div>

<template id="template-select-type">
    <div class="modal-header"><h2>What would you like to add?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-select-store"><img src="<?php echo $base_logo_path; ?>woocommerce.png" alt="Store"><h3>Store</h3></div>
        <div class="choice-card" data-next-template="template-select-ad-platform"><img src="<?php echo $base_logo_path; ?>facebook.png" alt="Ad Platform"><h3>Ad Platform</h3></div>
        <div class="choice-card" data-next-template="template-select-shipping"><img src="<?php echo $base_logo_path; ?>shipping.png" alt="Shipping"><h3>Shipping Company</h3></div>
        <div class="choice-card" data-next-template="template-form-fixed-cost"><img src="<?php echo $base_logo_path; ?>cost.png" alt="Cost"><h3>Fixed Cost</h3></div>
    </div></div>
</template>

<template id="template-select-store">
     <div class="modal-header"><h2>Which store platform?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-form-woocommerce"><img src="<?php echo $base_logo_path; ?>woocommerce.png" alt="WooCommerce"><h3>WooCommerce</h3></div>
        <div class="choice-card" onclick="alert('Shopify integration is coming soon!')"><img src="https://cdn.shopify.com/shopify-marketing_assets/static/shopify-favicon.png" alt="Shopify" style="border-radius:5px;"><h3>Shopify</h3></div>
    </div></div>
</template>

<template id="template-select-ad-platform">
     <div class="modal-header"><h2>Which ad platform?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-form-facebook"><img src="<?php echo $base_logo_path; ?>facebook.png" alt="Facebook"><h3>Facebook Ads</h3></div>
    </div></div>
</template>

<template id="template-select-shipping">
     <div class="modal-header"><h2>Which shipping company?</h2><button class="close-button">&times;</button></div>
    <div class="modal-body"><div class="choice-grid">
        <div class="choice-card" data-next-template="template-form-shipping-sendit"><img src="<?php echo $base_logo_path; ?>shipping.png" alt="Sendit"><h3>Sendit.ma</h3></div>
        <div class="choice-card" data-next-template="template-form-shipping-other"><img src="<?php echo $base_logo_path; ?>shipping.png" alt="Other shipping"><h3>Other</h3></div>
    </div></div>
</template>

<template id="template-form-woocommerce">
    <div class="modal-header"><h2>Connect WooCommerce</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <div class="form-group">
                <label for="store_name">Store Nickname</label>
                <input type="text" id="store_name" name="store_name" placeholder="e.g., My Fashion Store" required>
            </div>
            <div class="form-group">
                <label for="store_url">Store URL</label>
                <input type="url" id="store_url" name="store_url" placeholder="https://yourstore.com" required>
            </div>
            <div class="form-group">
                <label for="consumer_key">Consumer Key</label>
                <input type="text" id="consumer_key" name="consumer_key" required>
            </div>
            <div class="form-group">
                <label for="consumer_secret">Consumer Secret</label>
                <input type="password" id="consumer_secret" name="consumer_secret" required>
            </div>
            <button type="submit" name="save_woocommerce_store" class="btn btn-primary btn-full-width">Save Connection</button>
        </form>
    </div>
</template>

<template id="template-form-facebook">
    <div class="modal-header"><h2>Connect Facebook Ads</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <div id="fb-step-1">
                <div class="form-group"><label for="fb_account_name">Account Nickname</label><input type="text" id="fb_account_name" name="fb_account_name" required placeholder="e.g., My Primary Ad Account"></div>
                <div class="form-group"><label for="fb_account_id">Ad Account ID</label><input type="text" id="fb_account_id" name="fb_account_id" required placeholder="e.g., act_123456789"></div>
                <div class="form-group"><label for="fb_access_token">Access Token</label><input type="password" id="fb_access_token" name="fb_access_token" required></div>
                <div id="connection-test-results" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 5px;"></div>
                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="button" id="test-connection-btn" class="btn btn-secondary" style="flex: 1;">Test</button>
                    <button type="button" id="fetch-campaigns-btn" class="btn btn-primary" style="flex: 2;">Fetch Campaigns</button>
                </div>
            </div>
            <div id="fb-step-2" style="display: none;">
                <h4>Select Campaigns to Track</h4><p>Select the campaigns you want to track.</p>
                <div id="campaign-list-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 5px; margin-bottom: 20px;"></div>
                <button type="submit" name="save_ad_account" class="btn btn-primary btn-full-width">Save Connection</button>
            </div>
        </form>
    </div>
</template>

<template id="template-form-shipping-sendit">
    <div class="modal-header"><h2>Connect Sendit.ma</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <p style="text-align: center; margin-bottom: 20px;">Direct API integration with Sendit is coming soon. For now, please add it manually.</p>
            <input type="hidden" name="shipping_carrier_name" value="Sendit.ma">
            <button type="submit" name="save_shipping_carrier" class="btn btn-primary btn-full-width">Add Sendit.ma</button>
        </form>
    </div>
</template>

<template id="template-form-shipping-other">
    <div class="modal-header"><h2>Add Other Shipping Company</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <div class="form-group">
                <label for="shipping_carrier_name">Company Name</label>
                <input type="text" id="shipping_carrier_name" name="shipping_carrier_name" required placeholder="e.g., Cathedis, Tawsilix">
            </div>
            <button type="submit" name="save_shipping_carrier" class="btn btn-primary btn-full-width">Save Carrier</button>
        </form>
    </div>
</template>

<template id="template-form-fixed-cost">
     <div class="modal-header"><h2>Add a Fixed Cost</h2><button class="close-button">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <div class="form-group"><label for="cost_name">Cost Name</label><input type="text" id="cost_name" name="cost_name" required placeholder="e.g., Office Rent, Employee Salary"></div>
            <div class="form-group"><label for="cost_amount">Amount (MAD)</label><input type="number" step="0.01" id="cost_amount" name="cost_amount" required></div>
            <div class="form-group"><label for="cost_period">Period</label><select id="cost_period" name="cost_period"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
            <button type="submit" name="save_fixed_cost" class="btn btn-primary btn-full-width">Save Cost</button>
        </form>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>