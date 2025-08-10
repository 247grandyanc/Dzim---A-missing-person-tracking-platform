<?php
session_start();
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {  // Added missing closing parenthesis
    header('Location: dashboard.php');
    exit();
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    // Validate credentials
    $stmt = $gh->prepare("SELECT admin_id, username, password_hash, role FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
       // In your login.php, replace the success block with:
        if (password_verify($password, $admin['password_hash'])) {
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
            
            // Update last login
            $update_stmt = $gh->prepare("UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?");
            $update_stmt->bind_param("i", $admin['admin_id']);
            $update_stmt->execute();
            
            header('Location: dashboard.php');
            exit();
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEACH-GH | Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-blue-500">
                <i class="fas fa-search mr-2"></i>SEACH-GH
            </h1>
            <p class="text-gray-400 mt-2">Admin Portal</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-900 text-red-200 p-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-blue-500 focus:ring-blue-500" 
                       required autofocus>
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-400 mb-1">Password</label>
                <input type="password" id="password" name="password" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-blue-500 focus:ring-blue-500" 
                       required>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" 
                           class="h-4 w-4 rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-400">Remember me</label>
                </div>
                
                <div class="text-sm">
                    <a href="forgot-password.php" class="text-blue-500 hover:text-blue-400">Forgot password?</a>
                </div>
            </div>
            
            <div>
                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition duration-200">
                    Sign in
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>© <?= date('Y') ?> SEACH-GH. 247Grand Yanc Nexus - All rights reserved.</p>
        </div>
    </div>
</body>
</html>