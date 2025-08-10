<?php
// require_once __DIR__ . '/vendor/autoload.php'; // Add this line
require_once __DIR__ . '/net.php';

// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

// $secret_key = "YOUR_VERY_SECURE_KEY_HERE"; // Change this to a strong random string

// function generate_jwt($user_id, $email) {
//     global $secret_key;
    
//     $payload = [
//         'iss' => 'your-domain.com', // Issuer
//         'aud' => 'your-audience',   // Audience
//         'iat' => time(),            // Issued at
//         'nbf' => time(),            // Not before
//         'exp' => time() + (86400 * 7), // Expire in 7 days
//         'data' => [                 // Data payload
//             'user_id' => $user_id,
//             'email' => $email
//         ]
//     ];
    
//     return JWT::encode($payload, $secret_key, 'HS256');
// }

// function validate_jwt($token) {
//     global $secret_key;
    
//     try {
//         $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
//         return $decoded->data; // Return the data payload
//     } catch (Exception $e) {
//         error_log("JWT Validation Error: " . $e->getMessage());
//         return false;
//     }
// }

function authenticate() {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    return $_SESSION['user_id'];
}

// JWT-based alternative if you're using tokens
/*
function authenticate() {
    if (!isset($_COOKIE['jwt'])) {
        header("Location: ../login.php");
        exit();
    }
    
    require_once __DIR__ . '/functions.php';
    $user = validate_jwt($_COOKIE['jwt']);
    
    if (!$user) {
        header("Location: ../login.php");
        exit();
    }
    
    return $user['user_id'];
}
*/
?>