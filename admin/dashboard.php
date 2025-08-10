<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Add this after authentication
$required_tables = ['users', 'subscription', 'search_history', 'missing_persons', 'reward_claims', 'audit_log'];
foreach ($required_tables as $table) {
    $result = $gh->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        die("Missing required table: $table");
    }
}

// Get stats for dashboard
global $gh;

// Total users
// Replace your query executions with this pattern:
$result = $gh->query("SELECT COUNT(*) as count FROM users");
if (!$result) {
    die("Users query failed: " . $gh->error);
}
$total_users = $result->fetch_assoc()['count'];

// Active subscriptions
$active_subs = $gh->query("
    SELECT COUNT(*) as count 
    FROM subscription 
    WHERE status = 'active' AND expires_at > NOW()
")->fetch_assoc()['count'];

// Recent searches
$recent_searches = $gh->query("
    SELECT sh.search_id, sh.query_type, sh.created_at, u.email 
    FROM search_history sh
    LEFT JOIN users u ON sh.user_id = u.user_id
    ORDER BY sh.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Missing persons reports
$missing_persons = $gh->query("
    SELECT COUNT(*) as count 
    FROM missing_persons 
    WHERE status = 'missing'
")->fetch_assoc()['count'];

// Claimed rewards
$claimed_rewards = $gh->query("
    SELECT COUNT(*) as count 
    FROM reward_claims 
    WHERE status = 'claimed'
")->fetch_assoc()['count'];


// System alerts
$alerts = $gh->query("
    SELECT * FROM audit_log 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
    <title>Admin Dashboard | SEACH-GH</title>
    <style>
        .stat-card {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <h1 class="text-3xl font-bold mb-8">Admin Dashboard</h1>
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400">Total Users</p>
                                <p class="text-3xl font-bold"><?= number_format($total_users) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-900/30 text-blue-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400">Active Subs</p>
                                <p class="text-3xl font-bold"><?= number_format($active_subs) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-green-900/30 text-green-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Missing Persons Card -->
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400">Missing Persons</p>
                                <p class="text-3xl font-bold"><?= number_format($missing_persons) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-900/30 text-yellow-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <a href="missing-persons.php" class="block mt-4 text-sm text-yellow-400 hover:text-yellow-300 text-right">
                            View Reports →
                        </a>
                    </div>
                    
                    <!-- Claimed Rewards Card -->
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400">Claimed Rewards</p>
                                <p class="text-3xl font-bold"><?= number_format($claimed_rewards) ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-pink-900/30 text-pink-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <a href="rewards.php" class="block mt-4 text-sm text-pink-400 hover:text-pink-300 text-right">
                            Manage Rewards →
                        </a>
                    </div>
                    
                    <div class="stat-card rounded-xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400">Today's Searches</p>
                                <p class="text-3xl font-bold">
                                    <?= number_format($gh->query("
                                        SELECT COUNT(*) as count 
                                        FROM search_history 
                                        WHERE created_at >= CURDATE()
                                    ")->fetch_assoc()['count']) ?>
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-purple-900/30 text-purple-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Recent Searches -->
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold">Recent Searches</h2>
                            <a href="logs.php" class="text-sm text-blue-400 hover:text-blue-300">View All</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php foreach ($recent_searches as $search): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-700 rounded-lg">
                                    <div>
                                        <p class="font-medium">
                                            <?= $search['email'] ? htmlspecialchars($search['email']) : 'Guest' ?>
                                        </p>
                                        <p class="text-sm text-gray-400">
                                            <?= strtolower($search['query_type']) ?> search
                                        </p>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?= date('H:i', strtotime($search['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Reward Claims Section -->
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">Recent Reward Claims</h2>
                        <a href="rewards.php" class="text-sm text-blue-400 hover:text-blue-300">View All</a>
                    </div>
                    
                    <?php
                    $recent_rewards = $gh->query("
                        SELECT r.*, u.email, mp.full_name as person_name
                        FROM reward_claims r
                        JOIN users u ON r.message = u.user_id
                        JOIN missing_persons mp ON r.claimer_id = mp.id
                        ORDER BY r.created_at DESC 
                        LIMIT 5
                    ")->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left border-b border-gray-700">
                                    <th class="pb-2">Person</th>
                                    <th class="pb-2">Claimed By</th>
                                    <th class="pb-2">Amount</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2">Claimed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_rewards as $reward): ?>
                                <tr class="border-b border-gray-700 hover:bg-gray-700/50">
                                    <td class="py-3">
                                        <a href="missing-person.php?id=<?= $reward['person_id'] ?>" class="text-blue-400 hover:text-blue-300">
                                            <?= htmlspecialchars($reward['person_name']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3"><?= htmlspecialchars($reward['email']) ?></td>
                                    <td class="py-3">₵<?= number_format($reward['amount'], 2) ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 rounded-full text-xs <?= 
                                            $reward['status'] === 'paid' ? 'bg-green-900/30 text-green-400' : 'bg-yellow-900/30 text-yellow-400'
                                        ?>">
                                            <?= ucfirst($reward['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-400"><?= date('M j, Y', strtotime($reward['claimed_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- System Alerts -->
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold">System Alerts</h2>
                            <a href="audit-log.php" class="text-sm text-blue-400 hover:text-blue-300">View All</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="p-3 bg-gray-700 rounded-lg">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-medium">
                                                <?= htmlspecialchars($alert['action']) ?>
                                            </p>
                                            <p class="text-sm text-gray-400">
                                                <?= htmlspecialchars($alert['ip_address']) ?>
                                            </p>
                                        </div>
                                        <div class="text-sm text-gray-400">
                                            <?= date('H:i', strtotime($alert['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($alerts)): ?>
                                <p class="text-gray-400 text-center py-4">No recent alerts</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="users.php" class="bg-gray-700 hover:bg-gray-600 rounded-lg p-4 text-center transition">
                            <div class="mx-auto w-10 h-10 mb-2 flex items-center justify-center bg-blue-900/30 text-blue-400 rounded-full">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                </svg>
                            </div>
                            <p class="text-sm">Manage Users</p>
                        </a>
                        
                        <a href="subscriptions.php" class="bg-gray-700 hover:bg-gray-600 rounded-lg p-4 text-center transition">
                            <div class="mx-auto w-10 h-10 mb-2 flex items-center justify-center bg-green-900/30 text-green-400 rounded-full">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm">Subscriptions</p>
                        </a>
                        
                        <a href="blockip.php" class="bg-gray-700 hover:bg-gray-600 rounded-lg p-4 text-center transition">
                            <div class="mx-auto w-10 h-10 mb-2 flex items-center justify-center bg-red-900/30 text-red-400 rounded-full">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm">Block IP</p>
                        </a>
                        
                        <a href="settings.php" class="bg-gray-700 hover:bg-gray-600 rounded-lg p-4 text-center transition">
                            <div class="mx-auto w-10 h-10 mb-2 flex items-center justify-center bg-purple-900/30 text-purple-400 rounded-full">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm">Settings</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
</body>
</html>