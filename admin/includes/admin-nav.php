<?php
// admin-nav.php - Admin navigation and header system
require_once __DIR__ . '/admin-auth.php';

// Check if user is authenticated
$admin = admin_authenticate();

$js_vars = [
    'base_url' => '/admin/api/',
    'csrf_token' => $_SESSION['admin_csrf'] ?? '',
    'settings' => [
        'require_gh_ip' => function_exists('get_setting') && get_setting('REQUIRE_GH_IP'),
        'biometrics_enabled' => function_exists('get_setting') && get_setting('BIOMETRICS_ENABLED')
    ]
];

// Get unread error reports count (for badge)
$unread_errors = 0;
if ($admin['role'] === 'SUPER_ADMIN' || $admin['role'] === 'MODERATOR') {
    $stmt = $gh->prepare("SELECT COUNT(*) FROM error_reports WHERE is_resolved = 0");
    $stmt->execute();
    $unread_errors = $stmt->get_result()->fetch_row()[0];
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) . ' | ' : '' ?>SEACH-GH Admin</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1f2937;
        }
        ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
        
        /* Animation for notifications */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Smooth transitions */
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
    </style>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.tailwindcss.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom JS -->

</head>
<body class="h-full flex flex-col">
    <!-- Admin Top Navigation Bar -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button type="button" id="mobile-menu-button" class="text-gray-400 hover:text-white focus:outline-none">
                        <span class="sr-only">Open main menu</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
                
                <!-- Logo and Desktop Navigation -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <a href="dashboard.php" class="flex items-center">
                            <img class="h-8 w-auto" src="/assets/images/logo-admin.png" alt="SEACH-GH Admin">
                            <span class="ml-2 text-white font-bold">SEACH-GH</span>
                        </a>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="dashboard.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                            </a>
                            <a href="users.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'users.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-users mr-1"></i> Users
                            </a>
                            <a href="subscriptions.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'subscriptions.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-credit-card mr-1"></i> Subscriptions
                            </a>
                            <a href="transactions.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'transactions.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-exchange-alt mr-1"></i> Transactions
                            </a>
                            <?php if ($admin['role'] === 'SUPER_ADMIN'): ?>
                            <a href="settings.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-cog mr-1"></i> Settings
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right side icons -->
                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        <!-- Search button -->
                        <button type="button" class="p-1 rounded-full text-gray-400 hover:text-white focus:outline-none">
                            <span class="sr-only">Search</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                        
                        <!-- Notifications dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" id="notifications-button" class="max-w-xs rounded-full flex items-center text-sm focus:outline-none">
                                    <span class="sr-only">View notifications</span>
                                    <div class="relative">
                                        <i class="fas fa-bell text-gray-400 hover:text-white text-xl"></i>
                                        <?php if ($unread_errors > 0): ?>
                                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                                            <?= $unread_errors ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </button>
                            </div>
                            
                            <!-- Notifications dropdown panel -->
                            <div id="notifications-dropdown" class="hidden origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg py-1 bg-gray-700 ring-1 ring-black ring-opacity-5 z-50">
                                <div class="px-4 py-2 border-b border-gray-600">
                                    <p class="text-sm font-medium text-white">Notifications</p>
                                </div>
                                <?php if ($unread_errors > 0): ?>
                                <a href="error-report.php" class="block px-4 py-3 hover:bg-gray-600">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-0.5">
                                            <i class="fas fa-exclamation-triangle text-red-400"></i>
                                        </div>
                                        <div class="ml-3 w-0 flex-1">
                                            <p class="text-sm font-medium text-white">
                                                <?= $unread_errors ?> Unread Error <?= $unread_errors === 1 ? 'Report' : 'Reports' ?>
                                            </p>
                                            <p class="mt-1 text-sm text-gray-400">
                                                Click to view system errors
                                            </p>
                                        </div>
                                    </div>
                                </a>
                                <?php else: ?>
                                <div class="px-4 py-3 text-center">
                                    <p class="text-sm text-gray-400">No new notifications</p>
                                </div>
                                <?php endif; ?>
                                <div class="border-t border-gray-600 px-4 py-2">
                                    <a href="audit-log.php" class="text-xs text-blue-400 hover:text-blue-300">View all activity</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Profile dropdown -->
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" id="user-menu-button" class="max-w-xs rounded-full flex items-center text-sm focus:outline-none">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold">
                                        <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                                    </div>
                                    <span class="ml-2 text-gray-300 text-sm hidden lg:inline"><?= htmlspecialchars($admin['username']) ?></span>
                                </button>
                            </div>
                            
                            <!-- Profile dropdown panel -->
                            <div id="user-dropdown" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-gray-700 ring-1 ring-black ring-opacity-5 z-50">
                                <div class="px-4 py-2 border-b border-gray-600">
                                    <p class="text-sm text-white">Signed in as</p>
                                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($admin['username']) ?></p>
                                </div>
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-400 hover:bg-gray-600 hover:text-white">
                                    <i class="fas fa-user-circle mr-2"></i> Your Profile
                                </a>
                                <a href="activity.php" class="block px-4 py-2 text-sm text-gray-400 hover:bg-gray-600 hover:text-white">
                                    <i class="fas fa-history mr-2"></i> Your Activity
                                </a>
                                <div class="border-t border-gray-600"></div>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-400 hover:bg-gray-600 hover:text-white">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-gray-700">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="dashboard.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="users.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'users.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-users mr-2"></i> Users
                </a>
                <a href="subscriptions.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'subscriptions.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-credit-card mr-2"></i> Subscriptions
                </a>
                <a href="transactions.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'transactions.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-exchange-alt mr-2"></i> Transactions
                </a>
                <?php if ($admin['role'] === 'SUPER_ADMIN'): ?>
                <a href="settings.php" class="<?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-cog mr-2"></i> Settings
                </a>
                <?php endif; ?>
                <div class="border-t border-gray-700 pt-2">
                    <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-400 hover:text-white">
                        <i class="fas fa-user-circle mr-2"></i> Your Profile
                    </a>
                    <a href="logout.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-400 hover:text-white">
                        <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                    </a>
                </div>
            </div>
            <div class="pt-4 pb-3 border-t border-gray-700">
                <div class="flex items-center px-5">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="ml-3">
                        <div class="text-base font-medium text-white"><?= htmlspecialchars($admin['username']) ?></div>
                        <div class="text-sm font-medium text-gray-400"><?= ucfirst(strtolower($admin['role'])) ?></div>
                    </div>
                </div>
                <div class="mt-3 px-2 space-y-1">
                    <a href="activity.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-400 hover:text-white hover:bg-gray-700">
                        <i class="fas fa-history mr-2"></i> Your Activity
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main class="flex-1 overflow-y-auto">
        <!-- Page Header -->
        <div class="bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold leading-7 text-white sm:text-3xl sm:truncate">
                            <?= $page_title ?? 'Dashboard' ?>
                        </h1>
                    </div>
                    <div class="mt-4 flex-shrink-0 flex md:mt-0 md:ml-4">
                        <?php if (isset($page_actions)): ?>
                            <?php foreach ($page_actions as $action): ?>
                                <button type="button" onclick="<?= htmlspecialchars($action['onclick']) ?>" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-<?= $action['color'] ?? 'blue' ?>-600 hover:bg-<?= $action['color'] ?? 'blue' ?>-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-<?= $action['color'] ?? 'blue' ?>-500">
                                    <i class="fas fa-<?= $action['icon'] ?> mr-2"></i>
                                    <?= htmlspecialchars($action['text']) ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (isset($breadcrumbs)): ?>
                <div class="mt-1">
                    <nav class="flex" aria-label="Breadcrumb">
                        <ol class="flex items-center space-x-2 text-sm">
                            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                                <?php if ($i < count($breadcrumbs) - 1): ?>
                                    <li>
                                        <div class="flex items-center">
                                            <a href="<?= htmlspecialchars($crumb['url']) ?>" class="text-gray-400 hover:text-white">
                                                <?= htmlspecialchars($crumb['text']) ?>
                                            </a>
                                            <svg class="flex-shrink-0 h-4 w-4 text-gray-500 mx-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <span class="text-gray-300" aria-current="page">
                                            <?= htmlspecialchars($crumb['text']) ?>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main content will be inserted here by child pages -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="rounded-md bg-green-800/30 border border-green-700 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-400">
                                <?= htmlspecialchars($_SESSION['success_message']) ?>
                            </p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 text-green-400 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-green-50 focus:ring-green-600">
                                    <span class="sr-only">Dismiss</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="rounded-md bg-red-800/30 border border-red-700 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-400">
                                <?= htmlspecialchars($_SESSION['error_message']) ?>
                            </p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 text-red-400 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-red-50 focus:ring-red-600">
                                    <span class="sr-only">Dismiss</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Page-specific content will be inserted here -->
            <div class="px-4 py-6 sm:px-0">