<?php
require_once 'includes/header.php';
require_login();
// We don't need require_setup() here, as users should always be able to access their account.

$user_email = $_SESSION['user']['email'];
$access_token = $_SESSION['user']['access_token'];
$message = '';
$error = '';

// --- Handle Password Change Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Prepare the data for the Supabase API
        $data = json_encode(['password' => $new_password]);
        
        // cURL request to Supabase user update endpoint
        $ch = curl_init(SUPABASE_URL . '/auth/v1/user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // Use PUT for updates
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code === 200) {
            $message = 'Password updated successfully!';
        } else {
            $error = $result['msg'] ?? 'An error occurred while updating the password.';
        }
    }
}
?>

<h1>My Account</h1>

<div class="form-container" style="margin: 20px 0;">
    <h3>Account Information</h3>
    <div class="form-group">
        <label>Email Address</label>
        <input type="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
    </div>
</div>

<div class="form-container" style="margin: 20px 0;">
    <h3>Change Password</h3>

    <?php if ($message): ?>
        <p style="color: green; margin-bottom: 15px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error-message" style="text-align: left; margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="account.php" method="POST">
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>