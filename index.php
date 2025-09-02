<?php
require_once 'includes/db.php'; // This also starts the session

// If the user is already logged in, redirect to the dashboard
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); // <-- CORRECTED LINE
    exit();
}

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        // Prepare the data for the Supabase API
        $data = json_encode([
            'email' => $email, 
            'password' => $password
        ]);
        
        // cURL request to Supabase token endpoint (for login)
        $url = SUPABASE_URL . '/auth/v1/token?grant_type=password';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code === 200 && isset($result['access_token'])) {
            // Login successful! Store user data in the session
            $_SESSION['user'] = [
                'id' => $result['user']['id'],
                'email' => $result['user']['email'],
                'access_token' => $result['access_token']
            ];
            
            // Redirect to the dashboard
    header('Location: ' . BASE_URL . '/dashboard.php'); // <-- CORRECTED LINE
            exit();
        } else {
            $error_message = 'Invalid login credentials. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <h1 class="text-center">Welcome Back!</h1>
    <p class="text-center">Log in to your Profit Hub</p>
    
    <form action="index.php" method="POST">
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full-width">Log In</button>
    </form>
    <p class="text-center" style="margin-top: 20px;">
    Don't have an account? <a href="<?php echo BASE_URL; ?>/signup.php">Sign Up</a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>