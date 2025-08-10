<?php
// require_once __DIR__ . '/includes/net.php';
include("includes/net.php");
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $phone = clean_input($_POST['phone'] ?? '');
    
    // Validate input
    $errors = validate_registration($email, $password, $confirm, $phone);
    
    if (empty($errors)) {
        // Check if email exists
        if (email_exists($email)) {
            $errors[] = "Email already registered";
        } else {
            // Register user
            if (register_user($email, $password, $phone, $_SERVER['REMOTE_ADDR'])) {
                $success = true;
            } else {
                $errors[] = "Registration failed. Please try later.";
            }
        }
    }
}

// Include your HTML template
include __DIR__ . '/templates/register-form.php';