<?php
require_once 'includes/header.php';
require_login();

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// --- Handle Deletion Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_connection'])) {
    $connection_id = $_POST['connection_id'];
    $connection_type = $_POST['connection_type'];
    
    $table_map = [
        'store' => 'stores',
        'ad_account' => 'ad_accounts',
        'shipping' => 'shipping_carriers'
    ];

    if (isset($table_map[$connection_type])) {
        $table = $table_map[$connection_type];
        try {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
            $stmt->execute([$connection_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                $message = ucfirst(str_replace('_', ' ', $connection_type)) . " connection deleted successfully.";
            } else {
                $error = "Could not delete connection.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// --- Handle New Connection Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- WooCommerce Form (TOKEN-BASED AUTH) ---
    if (isset($_POST['save_store'])) {
        // We need a workflow to attach this store to. For now, we'll find or create one.
        $workflow_id = null;
        $wf_stmt = $pdo->prepare("SELECT id FROM workflows WHERE user_id = ? LIMIT 1");
        $wf_stmt->execute([$user_id]);
        $workflow_id = $wf_stmt->fetchColumn();
        if (!$workflow_id) {
            $pdo->prepare("INSERT INTO workflows (user_id, name) VALUES (?, ?)")->execute([$user_id, 'Default Workflow']);
            $workflow_id = $pdo->lastInsertId(); // Note: This may not work with Supabase UUIDs, fetching is better.
             $wf_stmt->execute([$user_id]);
             $workflow_id = $wf_stmt->fetchColumn();
        }

        $store_name = $_POST['store_name'];
        $store_url = rtrim($_POST['store_url'], '/');
        $consumer_key = $_POST['consumer_key'];
        $consumer_secret = $_POST['consumer_secret'];

        $verify_url = $store_url . '/wp-json/wc/v3/system_status';
        $auth_header = "Authorization: Basic " . base64_encode($consumer_key . ':' . $consumer_secret);
        $ch_verify = curl_init($verify_url);
        curl_setopt($ch_verify, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_verify, CURLOPT_HTTPHEADER, [$auth_header]);
        curl_exec($ch_verify);
        $http_code = curl_getinfo($ch_verify, CURLINFO_HTTP_CODE);
        curl_close($ch_verify);

        if ($http_code !== 200) {
            $error = "Error: Could not connect to your store. Please check the URL and API keys. (Status code: $http_code)";
        } else {
            $pdo->beginTransaction();
            try {
                // The DB auto-generates the webhook_token. We fetch it back.
                $stmt = $pdo->prepare(
                    "INSERT INTO stores (user_id, name, api_url, api_key, api_secret, workflow_id) VALUES (?, ?, ?, ?, ?, ?) RETURNING id, webhook_token"
                );
                $stmt->execute([$user_id, $store_name, $store_url, $consumer_key, $consumer_secret, $workflow_id]);
                $store_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $store_id = $store_info['id'];
                $webhook_token = $store_info['webhook_token'];

                // --- TEMPORARY NGROK URL WITH FULL PATH & SECRET TOKEN ---
                // Replace with your current ngrok URL
                $delivery_url = 'https://9eb93fb6470a.ngrok-free.app/cod-profit-hub/api/woocommerce-webhook.php?token=' . $webhook_token;

                $webhook_data = json_encode([
                    'name' => 'COD Profit Hub - Order Created (Token Auth)',
                    'topic' => 'order.created',
                    'delivery_url' => $delivery_url
                ]);

                $ch_webhook = curl_init($store_url . '/wp-json/wc/v3/webhooks');
                curl_setopt($ch_webhook, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_webhook, CURLOPT_HTTPHEADER, [$auth_header, 'Content-Type: application/json']);
                curl_setopt($ch_webhook, CURLOPT_POST, true);
                curl_setopt($ch_webhook, CURLOPT_POSTFIELDS, $webhook_data);
                $response = curl_exec($ch_webhook);
                $response_code = curl_getinfo($ch_webhook, CURLINFO_HTTP_CODE);
                curl_close($ch_webhook);
                $webhook_response = json_decode($response, true);
                
                if (($response_code === 201 || $response_code === 200) && isset($webhook_response['id'])) {
                    $update_stmt = $pdo->prepare("UPDATE stores SET webhook_id = ? WHERE id = ?");
                    $update_stmt->execute([$webhook_response['id'], $store_id]);
                    $pdo->commit();
                    $message = 'New WooCommerce store connected successfully!';
                } else {
                    $pdo->rollBack();
                    $error = "Store connected, but failed to create webhook. Error ($response_code): " . ($webhook_response['message'] ?? 'Unknown error.');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
}

// --- Fetch Existing Connections to Display ---
$connected_stores = $pdo->prepare("SELECT id, name, api_url FROM stores WHERE user_id = ?");
$connected_stores->execute([$user_id]);
$stores = $connected_stores->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
/* Add some simple styling for the connected items list */
.connected-items-list { list-style-type: none; margin-bottom: 25px; padding-left: 0; }
.connected-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid var(--border-color); border-radius: 5px; margin-bottom: 10px; }
.connected-item form { margin-bottom: 0; }
.btn-delete { background-color: #ffdddd; color: var(--danger-color); padding: 5px 10px; font-size: 0.8rem; }
</style>

<h1>Business Setup</h1>
<p>Connect your services to start calculating your profit automatically.</p>

<?php if ($message): ?>
    <div class="alert success" style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 5px;"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error" style="background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 5px;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="form-container" style="margin-top:20px;">
    <h2>WooCommerce Stores</h2>
    
    <?php if (!empty($stores)): ?>
        <p>Your connected stores:</p>
        <ul class="connected-items-list">
            <?php foreach ($stores as $store): ?>
                <li class="connected-item">
                    <span><strong><?php echo htmlspecialchars($store['name']); ?></strong> (<?php echo htmlspecialchars($store['api_url']); ?>)</span>
                    <form action="setup.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this store?');">
                        <input type="hidden" name="connection_id" value="<?php echo $store['id']; ?>">
                        <input type="hidden" name="connection_type" value="store">
                        <button type="submit" name="delete_connection" class="btn btn-delete">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <hr>
    <?php endif; ?>

    <h3 style="margin-top:20px;">Connect a New Store</h3>
    <form action="setup.php" method="POST">
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
        <button type="submit" name="save_store" class="btn btn-primary">Save & Connect New Store</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>