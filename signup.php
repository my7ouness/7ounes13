<?php
require_once 'includes/db.php';

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Prepare the data for the Supabase API
        $data = json_encode(['email' => $email, 'password' => $password]);
        
        // cURL request to Supabase signup endpoint
        $ch = curl_init(SUPABASE_URL . '/auth/v1/signup');
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

        if ($http_code >= 200 && $http_code < 300 && isset($result['id'])) {
            $success_message = 'Sign-up successful! Please check your email for a confirmation link. You can now log in.';
            // In a real app with email confirmation enabled, the user must click the link.
            // For this project, we assume it's disabled or they will do it.
        } else {
            $error_message = $result['msg'] ?? 'An error occurred during sign-up.';
        }
    }
}

// If user is already logged in, redirect them
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard.php'); // <-- CORRECTED LINE
    exit();
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <h1 class="text-center">Create Account</h1>
    <p class="text-center">Join the COD Profit Hub</p>
    
    <?php if ($success_message): ?>
        <p class="success-message" style="color: green; text-align:center; margin-bottom: 15px;"><?php echo htmlspecialchars($success_message); ?></p>
        <div class="text-center">
<a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">Go to Login</a>
        </div>
    <?php else: ?>
        <form action="signup.php" method="POST">
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
            <button type="submit" class="btn btn-primary btn-full-width">Sign Up</button>
        </form>
        <p class="text-center" style="margin-top: 20px;">
    Already have an account? <a href="<?php echo BASE_URL; ?>/index.php">Log In</a>
        </p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>