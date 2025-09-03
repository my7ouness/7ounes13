<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
require_login();

$user_id = $_SESSION['user']['id'];
$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workflow_name = trim($_POST['workflow_name'] ?? '');
    $store_platform = $_POST['store_platform'] ?? '';

    if (empty($workflow_name)) {
        $response['message'] = 'Workflow name is required.';
        echo json_encode($response);
        exit();
    }
    
    if (empty($store_platform)) {
        $response['message'] = 'Please select a store platform.';
        echo json_encode($response);
        exit();
    }

    $pdo->beginTransaction();
    try {
        // 1. Create the workflow first
        $stmt = $pdo->prepare("INSERT INTO workflows (user_id, name) VALUES (?, ?) RETURNING id");
        $stmt->execute([$user_id, $workflow_name]);
        $workflow_id = $stmt->fetchColumn();

        if (!$workflow_id) {
            throw new Exception("Failed to create the workflow record.");
        }

        // 2. Add the store connection based on the selected platform
        if ($store_platform === 'woocommerce') {
            $store_name = trim($_POST['store_name'] ?? '');
            $store_url = rtrim(trim($_POST['store_url'] ?? ''), '/');
            $consumer_key = trim($_POST['consumer_key'] ?? '');
            $consumer_secret = trim($_POST['consumer_secret'] ?? '');

            if (empty($store_name) || empty($store_url) || empty($consumer_key) || empty($consumer_secret)) {
                 throw new Exception("All WooCommerce fields are required.");
            }

            // Insert into stores table
            $store_stmt = $pdo->prepare(
                "INSERT INTO stores (user_id, workflow_id, name, platform, api_url, api_key, api_secret) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $store_stmt->execute([$user_id, $workflow_id, $store_name, 'woocommerce', $store_url, $consumer_key, $consumer_secret]);
        }
        
        // After all initial connections are made:
        $pdo->commit();
        $response = [
            'success' => true,
            'message' => 'Workflow created successfully! You can now manage it and add more connections.',
            'redirect' => BASE_URL . '/setup.php?workflow_id=' . $workflow_id
        ];

    } catch (Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>

