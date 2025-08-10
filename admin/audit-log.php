<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$action = isset($_GET['action']) ? clean_input($_GET['action']) : '';
$admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;

// Build query
$where = [];
$params = [];
$types = '';

if ($action) {
    $where[] = "action LIKE ?";
    $params[] = "%$action%";
    $types .= 's';
}

if ($admin_id) {
    $where[] = "admin_id = ?";
    $params[] = $admin_id;
    $types .= 'i';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get logs
$query = "SELECT l.*, a.username 
          FROM audit_log l
          LEFT JOIN admin_users a ON l.admin_id = a.admin_id
          $where_clause
          ORDER BY created_at DESC 
          LIMIT ?, ?";

// Handle parameter binding
if ($where_clause) {
    // Case 1: With filters - bind filters + LIMIT
    $params[] = $offset;
    $params[] = $per_page;
    $types .= 'ii';
    $stmt = $gh->prepare($query);
    $stmt->bind_param($types, ...$params);
} else {
    // Case 2: No filters - just bind LIMIT
    $stmt = $gh->prepare($query);
    $stmt->bind_param('ii', $offset, $per_page);
}

$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total count for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_log";
if ($where_clause) {
    $count_query .= " " . $where_clause;
}

$count_stmt = $gh->prepare($count_query);

if ($where_clause) {
    // For count query, we don't need the LIMIT params (remove last 2)
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    // Only bind if we have parameter types
    if (!empty($count_types)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}

$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get all admins for filter
$admins = $gh->query("SELECT admin_id, username FROM admin_users ORDER BY username")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>Audit Log | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <h1 class="text-3xl font-bold mb-8">Audit Log</h1>
                
                <!-- Filters -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Action</label>
                            <input type="text" name="action" value="<?= htmlspecialchars($action) ?>" 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Admin</label>
                            <select name="admin_id" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                <option value="0">All Admins</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?= $admin['admin_id'] ?>" <?= $admin_id === $admin['admin_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($admin['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg w-full">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Logs Table -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Admin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $log['log_id'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $log['target_id'] ? '#' . $log['target_id'] : 'N/A' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= date('M j, Y H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 bg-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_logs) ?> of <?= $total_logs ?> logs
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&action=<?= urlencode($action) ?>&admin_id=<?= $admin_id ?>" 
                                   class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&action=<?= urlencode($action) ?>&admin_id=<?= $admin_id ?>" 
                                   class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Export Button -->
                <div class="mt-6">
                    <a href="export-audit.php?action=<?= urlencode($action) ?>&admin_id=<?= $admin_id ?>" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Export to CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>