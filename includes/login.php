<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/net.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    if (login_user($email, $password)) {
        header("Location: templates/search.php");
        exit();
    } else {
        $errors[] = "Invalid email or password";
        
        // Log failed attempt
        try {
            $stmt = $gh->prepare("INSERT INTO login_attempts (ip, email, attempt_time) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $_SERVER['REMOTE_ADDR'], $email);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }
}
?>