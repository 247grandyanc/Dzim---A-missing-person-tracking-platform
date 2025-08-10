<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Authenticate user
$user_id = authenticate();

// Fetch user profile
$stmt = $gh->prepare("
    SELECT 
        email, 
        phone, 
        country,
        created_at,
        last_login,
        (SELECT COUNT(*) FROM search_history WHERE user_id = ?) as search_count,
        (SELECT COUNT(*) FROM subscription WHERE user_id = ? AND status = 'ACTIVE') as active_subscriptions
    FROM users 
    WHERE user_id = ?
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch recent searches
$stmt = $gh->prepare("
    SELECT search_id, query_type, created_at, result_count 
    FROM search_history 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_searches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle profile updates
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = clean_input($_POST['phone'] ?? '');
    $country = clean_input($_POST['country'] ?? 'GH');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Validate phone number
    if ($phone && !preg_match('/^\+233\d{9}$/', $phone)) {
        $errors[] = "Phone must be in format +233XXXXXXXXX";
    }
    
    // If changing password
    if ($current_password || $new_password) {
        $stmt = $gh->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $db_password = $stmt->get_result()->fetch_assoc()['password_hash'];
        
        if (!password_verify($current_password, $db_password)) {
            $errors[] = "Current password is incorrect";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $enc_phone = $phone ? encrypt_data($phone) : null;
        
        if ($new_password) {
            $hashed_pw = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $gh->prepare("UPDATE users SET phone = ?, country = ?, password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $enc_phone, $country, $hashed_pw, $user_id);
        } else {
            $stmt = $gh->prepare("UPDATE users SET phone = ?, country = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $enc_phone, $country, $user_id);
        }
        
        if ($stmt->execute()) {
            $success = true;
            // Refresh user data
            $user['phone'] = $phone;
            $user['country'] = $country;
        } else {
            $errors[] = "Update failed. Please try again.";
        }
    }
}
?>


    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>My Profile | SEACH-GH</title>
    <style>
        .profile-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #1e293b;
        }
        .stat-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid #1e293b;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .tab-button {
            transition: all 0.2s ease;
        }
        .tab-button.active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Profile Header -->
            <div class="profile-card rounded-xl p-6 mb-8 shadow-lg">
                <div class="flex flex-col md:flex-row items-start md:items-center">
                    <div class="flex-shrink-0 mb-4 md:mb-0 md:mr-6">
                        <div class="h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center text-3xl font-bold text-white">
                            <?= strtoupper(substr($user['email'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold">My Profile</h1>
                        <p class="text-gray-400">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-700 text-sm font-medium">
                            <?= $user['active_subscriptions'] ? 'Premium Member' : 'Free Account' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Left Column -->
                <div class="lg:w-1/3">
                    <!-- Account Details -->
                    <div class="bg-gray-800 rounded-xl p-6 mb-6 border border-gray-700">
                        <h2 class="text-xl font-bold mb-4">Account Details</h2>
                        
                        <?php if ($success): ?>
                            <div class="mb-4 p-4 bg-green-900 text-green-300 rounded-lg flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Profile updated successfully!
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="mb-4 p-4 bg-red-900 text-red-300 rounded-lg">
                                <?php foreach ($errors as $error): ?>
                                    <p class="flex items-center">
                                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        <?= htmlspecialchars($error) ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2" disabled>
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-400 mb-1">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           placeholder="+233XXXXXXXXX" pattern="\+233\d{9}"
                                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-400 mb-1">Country</label>
                                    <select id="country" name="country" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="GH" <?= $user['country'] === 'GH' ? 'selected' : '' ?>>Ghana</option>
                                        <option value="US" <?= $user['country'] === 'US' ? 'selected' : '' ?>>United States</option>
                                        <option value="UK" <?= $user['country'] === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                                        <option value="NG" <?= $user['country'] === 'NG' ? 'selected' : '' ?>>Nigeria</option>
                                    </select>
                                </div>
                                
                                <div class="pt-4 border-t border-gray-700">
                                    <h3 class="text-sm font-medium text-gray-400 mb-3">Change Password</h3>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label for="current_password" class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" 
                                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        </div>
                                        
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-400 mb-1">New Password</label>
                                            <input type="password" id="new_password" name="new_password" 
                                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pt-2">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                                        Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Account Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="stat-card rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-400">Total Searches</p>
                            <p class="text-2xl font-bold"><?= $user['search_count'] ?></p>
                        </div>
                        <div class="stat-card rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-400">Active Subs</p>
                            <p class="text-2xl font-bold"><?= $user['active_subscriptions'] ?></p>
                        </div>
                        <div class="stat-card rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-400">Last Login</p>
                            <p class="text-lg font-medium"><?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never' ?></p>
                        </div>
                        <div class="stat-card rounded-lg p-4 text-center">
                            <p class="text-sm text-gray-400">Account Age</p>
                            <p class="text-lg font-medium"><?= date_diff(new DateTime($user['created_at']), new DateTime())->format('%a') ?> days</p>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="lg:w-2/3">
                    <!-- Tabs -->
                    <div class="flex border-b border-gray-700 mb-6">
                        <button class="tab-button px-4 py-2 font-medium text-gray-400 hover:text-white active" data-tab="recent-searches">
                            Recent Searches
                        </button>
                        <button class="tab-button px-4 py-2 font-medium text-gray-400 hover:text-white" data-tab="subscriptions">
                            Subscriptions
                        </button>
                        <button class="tab-button px-4 py-2 font-medium text-gray-400 hover:text-white" data-tab="security">
                            Security
                        </button>
                    </div>
                    
                    <!-- Recent Searches Tab -->
                    <div id="recent-searches" class="tab-content">
                        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-700">
                                <thead class="bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Results</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-gray-800 divide-y divide-gray-700">
                                    <?php foreach ($recent_searches as $search): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                                <?= date('M j, g:i a', strtotime($search['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize <?= $search['query_type'] === 'IMAGE' ? 'bg-purple-900 text-purple-300' : 'bg-blue-900 text-blue-300' ?>">
                                                    <?= strtolower($search['query_type']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                                <?= $search['result_count'] ?> matches
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                                <a href="/results.php?id=<?= $search['search_id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3">View</a>
                                                <a href="#" class="text-gray-400 hover:text-gray-300">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($recent_searches)): ?>
                                <div class="p-6 text-center text-gray-400">
                                    No recent searches found
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 text-right">
                            <a href="../templates/history.php" class="inline-flex items-center text-blue-400 hover:text-blue-300">
                                View full history
                                <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Subscriptions Tab (hidden by default) -->
                    <div id="subscriptions" class="tab-content hidden">
                        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                            <h3 class="text-lg font-bold mb-4">Your Subscriptions</h3>
                            <p class="text-gray-400 mb-6">Manage your active subscriptions and view billing history.</p>
                            
                            <div class="bg-gray-700 rounded-lg p-4 mb-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">Current Plan</h4>
                                        <p class="text-sm text-gray-400">
                                            <?= $user['active_subscriptions'] ? 'Premium Membership' : 'Free Account' ?>
                                        </p>
                                    </div>
                                    <a href="subscribe.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium">
                                        <?= $user['active_subscriptions'] ? 'Manage Plan' : 'Upgrade Now' ?>
                                    </a>
                                </div>
                            </div>
                            
                            <h4 class="font-medium mb-3">Billing History</h4>
                            <div class="bg-gray-700 rounded-lg p-4 text-center text-gray-400">
                                No billing history available
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Tab (hidden by default) -->
                    <div id="security" class="tab-content hidden">
                        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                            <h3 class="text-lg font-bold mb-4">Security Settings</h3>
                            
                            <div class="space-y-6">
                                <div class="flex items-start justify-between p-4 bg-gray-700 rounded-lg">
                                    <div class="flex-1 mr-4">
                                        <h4 class="font-medium">Two-Factor Authentication</h4>
                                        <p class="text-sm text-gray-400">Add an extra layer of security to your account</p>
                                    </div>
                                    <button class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm font-medium">
                                        Enable
                                    </button>
                                </div>
                                
                                <div class="flex items-start justify-between p-4 bg-gray-700 rounded-lg">
                                    <div class="flex-1 mr-4">
                                        <h4 class="font-medium">Active Sessions</h4>
                                        <p class="text-sm text-gray-400">Manage devices that are logged into your account</p>
                                    </div>
                                    <button class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-lg text-sm font-medium">
                                        View Sessions
                                    </button>
                                </div>
                                
                                <div class="flex items-start justify-between p-4 bg-gray-700 rounded-lg">
                                    <div class="flex-1 mr-4">
                                        <h4 class="font-medium">Delete Account</h4>
                                        <p class="text-sm text-gray-400">Permanently remove your account and all data</p>
                                    </div>
                                    <button class="px-4 py-2 bg-red-600 hover:bg-red-500 rounded-lg text-sm font-medium">
                                        Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // Update active tab button
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active', 'text-white');
                    btn.classList.add('text-gray-400');
                });
                button.classList.add('active', 'text-white');
                button.classList.remove('text-gray-400');
                
                // Show selected tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(button.dataset.tab).classList.remove('hidden');
            });
        });
        
        // Phone number formatting
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                const value = e.target.value.replace(/\D/g, '');
                if (value.startsWith('233') && !e.target.value.startsWith('+')) {
                    e.target.value = '+' + value;
                } else if (!value.startsWith('233') && value.length > 0) {
                    e.target.value = '+233' + value;
                }
            });
        }
    </script>
</body>
</html>