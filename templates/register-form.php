
    <?php /*include __DIR__ . '/includes/header.php';*/
    include("includes/header.php");
    ?>
    <title>Register | DZIM-GH</title>
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
        .alert-exit {
            opacity: 1;
        }
        .alert-exit-active {
            opacity: 0;
            transition: opacity 300ms ease-in;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <?php /*include __DIR__ . '/includes/navbar.php';*/ 
     include("includes/navbar.php"); ?>

        <main class="container mx-auto px-4 py-10 max-w-md">
            <h1 class="text-3xl font-bold mb-8">Create Account</h1>
            
            <?php if ($success): ?>
                <div class="alert-enter bg-green-800 text-white p-4 rounded-lg mb-6 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Registration successful! Redirecting to login...</span>
                </div>
                <script>
                    setTimeout(() => {
                        window.location.href = 'login.php?registered=1';
                    }, 2000);
                </script>
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
                <div class="space-y-2">
                <label for="email" class="block text-sm font-medium">Email*</label>
                <input 
                    type="email" 
                    name="email" 
                    required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200"
                    placeholder="your@email.com"
                >
            </div>
            
            <div class="space-y-2">
                <label for="password" class="block text-sm font-medium">Password*</label>
                <div class="relative">
                    <input 
                        type="password" 
                        name="password" 
                        required
                        minlength="8"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200"
                        placeholder="At least 8 characters"
                    >
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 text-xs">8+</span>
                    </div>
                </div>
            </div>
            
            <div class="space-y-2">
                <label for="confirm_password" class="block text-sm font-medium">Confirm Password*</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200"
                    placeholder="Re-enter your password"
                >
            </div>
            
            <div class="space-y-2">
                <label for="phone" class="block text-sm font-medium">Phone (Optional)</label>
                <input 
                    type="tel" 
                    name="phone" 
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200"
                    placeholder="+233XXXXXXXXX"
                    pattern="\+233\d{9}"
                    title="Ghanaian phone number format: +233XXXXXXXXX"
                >
                <p class="text-xs text-gray-500">Format: +233XXXXXXXXX</p>
            </div>
            
            <button 
                type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-700 py-3 px-4 rounded-lg font-medium transition duration-200 transform hover:scale-[1.01] focus:scale-[0.99]"
            >
                Register
            </button>
            
            <div class="text-center pt-4 text-sm">
                <p class="text-gray-400">Already have an account? 
                    <a href="login.php" class="text-blue-400 hover:text-blue-300 underline transition duration-200">
                        Login here
                    </a>
                </p>
            </div>
            </form>
        </main>

        <?php /*include __DIR__ . '/includes/footer.php';*/
        include("includes/footer.php");
        ?>

        <script>
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