<?php
// MySQL connection settings
$host = '127.0.0.1';    // Using IP address instead of 'localhost'
$user = 'root';         // Default XAMPP username
$pass = '';             // Default XAMPP password (empty)
$db   = 'search-gh';    // Your database name

try {
    // Create connection
    $gh = new mysqli($host, $user, $pass, $db);
    
    // Check connection
    if ($gh->connect_error) {
        throw new Exception("Connection failed: " . $gh->connect_error);
    }
    
    // Set charset to utf8mb4
    if (!$gh->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $gh->error);
    }
    
    // Alternative way to disable prepared statements emulation
    if (defined('MYSQLI_ATTR_EMULATE_PREPARES')) {
        $gh->options(MYSQLI_ATTR_EMULATE_PREPARES, false);
    }

    define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here'); // Must be exactly 32 characters
    
    // Enable error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
} catch (Exception $e) {
    // Development error message
    die("Database Error: " . $e->getMessage());
    
    // For production, use:
    // error_log("DB Error: ".$e->getMessage());
    // die("We're experiencing technical difficulties. Please try again later.");
}
?>