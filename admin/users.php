<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
$admin = admin_authenticate();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$country = isset($_GET['country']) ? clean_input($_GET['country']) : '';

// Build query
global $gh;
$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($country) {
    $where[] = "country = ?";
    $params[] = $country;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get users
// Build base query
$query = "SELECT user_id, email, country, status, created_at, last_login FROM users";
$count_query = "SELECT COUNT(*) as total FROM users";

// Add WHERE clause if filters exist
if ($where) {
    $where_clause = ' WHERE ' . implode(' AND ', $where);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

// Add sorting and pagination
$query .= " ORDER BY created_at DESC LIMIT ?, ?";

// Prepare and execute main query
$stmt = $gh->prepare($query);

if ($where) {
    // Bind both filter params and limit params
    $types .= 'ii';
    $params[] = $offset;
    $params[] = $per_page;
    $stmt->bind_param($types, ...$params);
} else {
    // Just bind limit params
    $stmt->bind_param('ii', $offset, $per_page);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Execute count query
$count_stmt = $gh->prepare($count_query);
if ($where) {
    $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['suspend_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $gh->prepare("UPDATE users SET status = 'SUSPENDED' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Log action
        log_admin_action($admin['admin_id'], "SUSPEND_USER", $user_id);
        
        header("Location: users.php?success=User+suspended");
        exit();
    }
    
    if (isset($_POST['activate_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $gh->prepare("UPDATE users SET status = 'ACTIVE' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Log action
        log_admin_action($admin['admin_id'], "ACTIVATE_USER", $user_id);
        
        header("Location: users.php?success=User+activated");
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $gh->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Log action
        log_admin_action($admin['admin_id'], "DELETE_USER", $user_id);
        
        header("Location: users.php?success=User+deleted");
        exit();
    }
    
    if (isset($_POST['impersonate'])) {
        $user_id = (int)$_POST['user_id'];
        $_SESSION['impersonate_user_id'] = $user_id;
        header("Location: /profile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>User Management | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-3xl font-bold">User Management</h1>
                    <a href="add-user.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                        Add New User
                    </a>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Status</label>
                            <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                <option value="">All</option>
                                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                                <option value="SUSPENDED" <?= $status === 'SUSPENDED' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Country</label>
                            <select name="country" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                <option value="">All</option>
                                <option value="GH" <?= $country === 'GH' ? 'selected' : '' ?>>Ghana</option>
                                <option value="US" <?= $country === 'US' ? 'selected' : '' ?>>USA</option>
                                <option value="UK" <?= $country === 'UK' ? 'selected' : '' ?>>UK</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg w-full">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Country</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Login</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $user['user_id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-300"><?= htmlspecialchars($user['email']) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $user['country'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $user['status'] === 'ACTIVE' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' ?>">
                                            <?= $user['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <form method="post" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <?php if ($user['status'] === 'ACTIVE'): ?>
                                                    <button type="submit" name="suspend_user" class="text-yellow-400 hover:text-yellow-300">Suspend</button>
                                                <?php else: ?>
                                                    <button type="submit" name="activate_user" class="text-green-400 hover:text-green-300">Activate</button>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" name="delete_user" class="text-red-400 hover:text-red-300">Delete</button>
                                            </form>
                                            
                                            <form method="post" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" name="impersonate" class="text-blue-400 hover:text-blue-300">Impersonate</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 bg-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_users) ?> of <?= $total_users ?> users
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&country=<?= urlencode($country) ?>" 
                                   class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&country=<?= urlencode($country) ?>" 
                                   class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>