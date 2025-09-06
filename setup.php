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
if (isset($_GET['status']) && $_GET['status'] === 'google_success') {
    $message = "Google Account connected successfully! Please select a sheet and tab below.";
}

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
        $table_map = ['store' => 'stores', 'ad_account' => 'ad_accounts', 'shipping' => 'shipping_carriers', 'cost' => 'costs', 'google_sheet' => 'google_sheets_connections'];

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
    
    // --- Handle Google Sheet SELECTION Save ---
    if (isset($_POST['save_google_sheet_selection'])) {
        $sheet_id = $_POST['sheet_id'] ?? '';
        $tab_name = $_POST['tab_name'] ?? '';
        $conn_id = $_POST['connection_id'] ?? '';

        if (!empty($sheet_id) && !empty($tab_name) && !empty($conn_id)) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE google_sheets_connections SET selected_sheet_id = ?, selected_sheet_tab_name = ? WHERE id = ? AND user_id = ?"
                );
                $stmt->execute([$sheet_id, $tab_name, $conn_id, $user_id]);
                $message = "Google Sheet selection saved successfully!";
            } catch(PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "Please select a sheet and enter a tab name.";
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
                $stmt = $pdo->prepare(
                    "INSERT INTO stores (user_id, workflow_id, name, platform, api_url, api_key, api_secret) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $workflow_id, $store_name, 'woocommerce', $store_url, $consumer_key, $consumer_secret]);
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
$logo_map = ['woocommerce' => 'woocommerce.png', 'facebook' => 'facebook.png', 'google_sheet' => 'google-sheets.png', 'shipping' => 'shipping.png', 'cost' => 'cost.png'];
$base_logo_path = BASE_URL . '/assets/images/logos/';
$nodes = [];

$stores_stmt = $pdo->prepare("SELECT id, name, platform FROM stores WHERE user_id = ? AND workflow_id = ?");
$stores_stmt->execute([$user_id, $workflow_id]);
if ($stores_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['woocommerce'];

$ad_accounts_stmt = $pdo->prepare("SELECT id, platform, account_name FROM ad_accounts WHERE user_id = ? AND workflow_id = ?");
$ad_accounts_stmt->execute([$user_id, $workflow_id]);
if ($ad_accounts_stmt->rowCount() > 0) $nodes[] = $base_logo_path . $logo_map['facebook'];

$gs_stmt = $pdo->prepare("SELECT * FROM google_sheets_connections WHERE user_id = ? AND workflow_id = ?");
$gs_stmt->execute([$user_id, $workflow_id]);
$google_sheet_connection = $gs_stmt->fetch(PDO::FETCH_ASSOC);
if ($google_sheet_connection) $nodes[] = $base_logo_path . $logo_map['google_sheet'];

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

<?php if ($message): ?> <div class="alert success" id="alert-message"><?php echo htmlspecialchars($message); ?></div> <?php endif; ?>
<?php if ($error): ?> <div class="alert error" id="alert-message"><?php echo htmlspecialchars($error); ?></div> <?php endif; ?>


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
    
    <div class="connection-card">
        <div class="connection-card-header"><img src="<?php echo $base_logo_path; ?>google-sheets.png" class="header-icon" alt="Google Sheets"><h3>Google Sheets</h3></div>
        <div class="connection-card-body" style="padding: 15px;">
            <?php if (!$google_sheet_connection): ?>
                <p>Connect your Google Account to select a sheet for order and cost data.</p>
                <a href="<?php echo BASE_URL; ?>/auth/google-auth-redirect.php?workflow_id=<?php echo $workflow_id; ?>" class="btn btn-primary">Connect with Google</a>
            <?php else: ?>
                <p><strong>Status:</strong> Connected! Now select your sheet.</p>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="connection_id" value="<?php echo $google_sheet_connection['id']; ?>">
                    <div class="form-group">
                        <label for="sheet_id">Spreadsheet</label>
                        <select id="sheet_id" name="sheet_id" class="google-sheet-selector">
                            <option value="">-- Loading your sheets --</option>
                             <?php if (!empty($google_sheet_connection['selected_sheet_id'])): ?>
                                <option value="<?php echo htmlspecialchars($google_sheet_connection['selected_sheet_id']); ?>" selected>
                                    (Currently Selected) Loading Name...
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="tab_name">Tab Name</label>
                        <input type="text" id="tab_name" name="tab_name" placeholder="e.g., Orders" value="<?php echo htmlspecialchars($google_sheet_connection['selected_sheet_tab_name'] ?? 'Orders'); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="save_google_sheet_selection" class="btn btn-primary">Save Selection</button>
                    </div>
                </form>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this connection?');" style="margin-top: 15px; text-align: right;">
                    <input type="hidden" name="connection_id" value="<?php echo $google_sheet_connection['id']; ?>">
                    <input type="hidden" name="connection_type" value="google_sheet">
                    <button type="submit" name="delete_connection" class="btn btn-delete">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

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
        
        <?php if (!$google_sheet_connection): ?>
        <a href="<?php echo BASE_URL; ?>/auth/google-auth-redirect.php?workflow_id=<?php echo $workflow_id; ?>" class="choice-card">
            <img src="<?php echo $base_logo_path; ?>google-sheets.png" alt="Google Sheet"><h3>Google Sheets</h3>
        </a>
        <?php endif; ?>

        <div class="choice-card" data-next-template="template-select-shipping"><img src="<?php echo $base_logo_path; ?>shipping.png" alt="Shipping"><h3>Shipping Company</h3></div>
        <div class="choice-card" data-next-template="template-form-fixed-cost"><img src="<?php echo $base_logo_path; ?>cost.png" alt="Cost"><h3>Fixed Cost</h3></div>
    </div></div>
</template>

<?php require_once 'includes/footer.php'; ?>