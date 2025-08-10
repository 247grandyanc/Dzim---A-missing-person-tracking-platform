<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();
// var_dump($_SESSION);
// exit();
require_once __DIR__ . '/../../includes/net.php';

// Secure admin authentication
function admin_authenticate() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'use_strict_mode' => true
        ]);
    }

    // Check if admin is logged in
    if (empty($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit();
    }

    // Verify session against database
    global $gh;
    $stmt = $gh->prepare("
        SELECT a.admin_id, a.username, a.role, s.token 
        FROM admin_users a
        JOIN admin_sessions s ON a.admin_id = s.admin_id
        WHERE a.admin_id = ? 
        AND s.token = ?
        AND s.expires_at > NOW()
    ");
    $stmt->bind_param("is", $_SESSION['admin_id'], $_SESSION['admin_token']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin) {
        // Session is invalid
        session_destroy();
        header('Location: /admin/login.php');
        exit();
    }

    return $admin;
}

// Admin login function
function admin_login($username, $password) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT admin_id, username, password_hash, role 
        FROM admin_users 
        WHERE username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // Create session token
    $token = bin2hex(random_bytes(32));
    $hashed_token = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', strtotime('+8 hours'));

    // Store session in database
    $stmt = $gh->prepare("
        INSERT INTO admin_sessions 
        (admin_id, token, ip_address, user_agent, expires_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issss",
        $admin['admin_id'],
        $hashed_token,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $expires_at
    );
    $stmt->execute();

    // Set session variables
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['admin_token'] = $hashed_token;
    $_SESSION['admin_role'] = $admin['role'];

    return true;
}