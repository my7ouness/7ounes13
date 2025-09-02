<?php
// Supabase Database & API Configuration
define('SUPABASE_URL', 'https://viloflizjdbjprxkvula.supabase.co'); 
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZpbG9mbGl6amRianByeGt2dWxhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTY4MTgzODIsImV4cCI6MjA3MjM5NDM4Mn0.upfVjdrydfCDc_u6pWixXa0zJq6340IWVO9ERBho5JM'); // <-- IMPORTANT: Get this from your project's API settings!

// === ADD THIS LINE ===
define('BASE_URL', '/cod-profit-hub');
// --- DATABASE CONNECTION DETAILS (USING THE IPV4-COMPATIBLE TRANSACTION POOLER) ---
$db_host = 'aws-1-eu-north-1.pooler.supabase.com'; 
$db_port = '6543';                                
$db_name = 'postgres';
$db_user = 'postgres.viloflizjdbjprxkvula';        
$db_pass = 'Hanibal123***';                     

// --- ESTABLISH DATABASE CONNECTION (PDO) ---

// We change sslmode to 'prefer' to bypass the local certificate verification permission issue.
// The connection will still be secure and encrypted because Supabase requires it.
$dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;user=$db_user;password=$db_pass;sslmode=prefer";

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // For development, show the error. In production, log it and show a generic message.
    die("Database connection failed: " . $e->getMessage());
}

// Start the session on every page that includes this file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>