<?php
// Supabase Database & API Configuration
define('SUPABASE_URL', 'https://viloflizjdbjprxkvula.supabase.co'); 
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZpbG9mbGl6amRianByeGt2dWxhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTY4MTgzODIsImV4cCI6MjA3MjM5NDM4Mn0.upfVjdrydfCDc_u6pWixXa0zJq6340IWVO9ERBho5JM');

define('BASE_URL', '/cod-profit-hub');

// NEW: Google OAuth 2.0 Configuration
define('GOOGLE_CLIENT_ID', '780445514097-pigi9h2g10bp6b107qgi7ftmoas982m4.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/cod-profit-hub/auth/google-auth-callback.php');

// --- DATABASE CONNECTION DETAILS ---
$db_host = 'aws-1-eu-north-1.pooler.supabase.com'; 
$db_port = '6543';                                
$db_name = 'postgres';
$db_user = 'postgres.viloflizjdbjprxkvula';        
$db_pass = 'Hanibal123***';                     

$dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;user=$db_user;password=$db_pass;sslmode=prefer";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>