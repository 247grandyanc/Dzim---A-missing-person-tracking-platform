<?php
require_once __DIR__ . '/includes/auth.php'; // This should load all required files

// Check if user is already logged in
// Ensure session is started at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for existing session instead of cookie
if (isset($_SESSION['user_id'])) {
    header("Location: templates/search.php");
    exit();
}

// Include the login processing
require_once __DIR__ . '/includes/login.php';

// Check for registration success
$registration_success = isset($_GET['registered']) && $_GET['registered'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /*include __DIR__ . '/includes/header.php';*/
    include("includes/header.php"); ?>
    <title>Login | DZIM-GH</title>
    <style>
        .alert-enter {
            opacity: 0;
            transform: translateY(-10px);
        }
        .alert-enter-active {
            opacity: 1;
            transform: translateY(0);
            transition: all 300ms ease-out;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <?php /*include __DIR__ . '/includes/navbar.php';*/ 
     include("includes/navbar.php"); ?>

    <main class="container mx-auto px-4 py-10 max-w-md">
        <h1 class="text-3xl font-bold mb-8">Login</h1>
        
        <?php if ($registration_success): ?>
            <div class="alert-enter bg-green-800 text-white p-4 rounded-lg mb-6 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Registration successful! Please login.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="space-y-3 mb-6">
                <?php foreach ($errors as $error): ?>
                    <div class="alert-enter bg-red-800 text-white p-4 rounded-lg flex items-start">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="email" class="block mb-2">Email</label>
                <input 
                    type="email" 
                    name="email" 
                    required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            
            <div>
                <label for="password" class="block mb-2">Password</label>
                <input 
                    type="password" 
                    name="password" 
                    required
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 px-4 rounded font-medium transition duration-200">
                Login
            </button>
            
            <div class="text-center pt-4">
                <a href="register.php" class="text-blue-400 hover:underline">
                    Don't have an account? Register
                </a>
            </div>
        </form>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        // Simple animation for alerts
        document.addEventListener('DOMContentLoaded', () => {
            const alerts = document.querySelectorAll('.alert-enter');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('alert-enter-active');
                }, 10);
            });
        });
    </script>
</body>
</html>