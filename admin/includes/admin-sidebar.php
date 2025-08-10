<?php
require_once __DIR__ . '/admin-auth.php';
$admin = admin_authenticate();
?>

<div class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
    <div class="p-4 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-lg font-bold text-white">
                <?= strtoupper(substr($admin['username'], 0, 1)) ?>
            </div>
            <div>
                <div class="font-medium"><?= htmlspecialchars($admin['username']) ?></div>
                <div class="text-xs text-gray-400"><?= ucfirst(strtolower($admin['role'])) ?></div>
            </div>
        </div>
    </div>
    
    <nav class="flex-1 overflow-y-auto">
        <div class="space-y-1 p-2">
            <a href="dashboard.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                Dashboard
            </a>
            
            <a href="users.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'users.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                </svg>
                User Management
            </a>
            
            <a href="subscriptions.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'subscriptions.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                </svg>
                Subscriptions
            </a>
            
            <a href="view-ads.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= in_array(basename($_SERVER['SCRIPT_NAME']), ['view-ads.php', 'add-ad.php']) ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd" />
                </svg>
                Ad Management
            </a>
            
            <a href="transactions.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'transactions.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                </svg>
                Transactions
            </a>
            
            <a href="blockip.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'blockip.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
                IP Blocking
            </a>
            
            <a href="audit-log.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'audit-log.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                Audit Log
            </a>
            
            <a href="error-report.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                <?= basename($_SERVER['SCRIPT_NAME']) === 'error-report.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                Error Reports
            </a>
            
            <?php if ($admin['role'] === 'SUPER_ADMIN'): ?>
                <a href="settings.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg 
                    <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white' ?>">
                    <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    System Settings
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="p-4 border-t border-gray-700">
        <a href="logout.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">
            <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
            </svg>
            Logout
        </a>
    </div>
</div>