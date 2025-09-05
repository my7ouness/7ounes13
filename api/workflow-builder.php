<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
require_login();

$user_id = $_SESSION['user']['id'];
$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workflow_name = trim($_POST['workflow_name'] ?? '');

    if (empty($workflow_name)) {
        $response['message'] = 'Workflow name is required.';
        echo json_encode($response);
        exit();
    }
    
    $pdo->beginTransaction();
    try {
        // Create the workflow with just the name
        $stmt = $pdo->prepare("INSERT INTO workflows (user_id, name) VALUES (?, ?) RETURNING id");
        $stmt->execute([$user_id, $workflow_name]);
        $workflow_id = $stmt->fetchColumn();

        if (!$workflow_id) {
            throw new Exception("Failed to create the workflow record.");
        }

        $pdo->commit();
        $response = [
            'success' => true,
            'message' => 'Workflow created successfully! You can now add connections.',
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