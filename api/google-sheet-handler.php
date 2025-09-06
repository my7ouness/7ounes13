<?php
// For robust error handling, always return JSON, even on failure.
header('Content-Type: application/json');

try {
    require_once '../vendor/autoload.php';
    require_once '../includes/db.php';
    require_once '../includes/functions.php';

    /**
     * Creates an authenticated Google API client from a user's refresh token.
     */
    function get_google_client_for_user($pdo, $user_id, $workflow_id) {
        $stmt = $pdo->prepare("SELECT refresh_token FROM google_sheets_connections WHERE user_id = ? AND workflow_id = ?");
        $stmt->execute([$user_id, $workflow_id]);
        $refresh_token = $stmt->fetchColumn();

        if (!$refresh_token) {
            throw new Exception("Google connection not found or refresh token is missing.");
        }

        $client = new Google\Client();
        $client->setClientId(GOOGLE_CLIENT_ID);
        $client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $client->refreshToken($refresh_token);
        return $client;
    }

    require_login();
    $user_id = $_SESSION['user']['id'];

    // Main logic to handle the request
    $action = $_GET['action'] ?? null;
    $workflow_id = $_GET['workflow_id'] ?? null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_sheets') {
        if (!$workflow_id) {
            throw new InvalidArgumentException('Workflow ID is required.');
        }

        $client = get_google_client_for_user($pdo, $user_id, $workflow_id);
        $driveService = new Google\Service\Drive($client);
        
        $optParams = [
            'q' => "mimeType='application/vnd.google-apps.spreadsheet'",
            'fields' => 'files(id, name)',
            'pageSize' => 200 // Increased limit
        ];
        $results = $driveService->files->listFiles($optParams);
        
        $sheets = [];
        foreach ($results->getFiles() as $file) {
            $sheets[] = ['id' => $file->getId(), 'name' => $file->getName()];
        }
        echo json_encode(['success' => true, 'sheets' => $sheets]);
        exit();
    }
    
    throw new BadMethodCallException('Invalid action.');

} catch (Throwable $e) {
    http_response_code(500);
    error_log("API Error in google-sheet-handler.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred: ' . $e->getMessage()
    ]);
    exit();
}