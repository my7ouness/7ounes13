<?php
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../includes/db.php';
echo "CURRENCY RATE UPDATE STARTED: " . date('Y-m-d H:i:s') . "\n";

// New, key-less API endpoint
$api_url = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json";

echo "Fetching latest rates from public API...\n";
$response = file_get_contents($api_url);

if ($response === false) {
    die("ERROR: Could not fetch data from the API URL.\n");
}

$data = json_decode($response, true);

if (!isset($data['usd']['mad'])) {
    die("ERROR: Could not find 'MAD' in the API response.\n");
}

$usd_to_mad_rate = $data['usd']['mad'];
echo "Successfully fetched USD to MAD rate: {$usd_to_mad_rate}\n";

try {
    // Update the rate for ALL workflows in the database
    $stmt = $pdo->prepare("UPDATE workflows SET usd_to_mad_rate = ?");
    $stmt->execute([$usd_to_mad_rate]);
    
    $count = $stmt->rowCount();
    echo "Successfully updated the exchange rate for {$count} workflow(s) in the database.\n";

} catch (PDOException $e) {
    die("DATABASE ERROR: Could not update rates. " . $e->getMessage() . "\n");
}

echo "CURRENCY RATE UPDATE FINISHED: " . date('Y-m-d H:i:s') . "\n";
?>