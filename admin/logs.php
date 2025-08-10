<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Set page variables
$title = "System Logs";
$page_title = "System Logs & Monitoring";
$breadcrumbs = [
    ['text' => 'Dashboard', 'url' => 'dashboard.php'],
    ['text' => 'System Logs', 'url' => 'logs.php']
];

require_once __DIR__ . '/includes/admin-nav.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$type = isset($_GET['type']) ? clean_input($_GET['type']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query
$where = [];
$params = [];
$types = '';

if ($type) {
    $where[] = "log_type = ?";
    $params[] = $type;
    $types .= 's';
}

if ($search) {
    $where[] = "(message LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get logs
$query = "SELECT * FROM error_logs $where_clause ORDER BY created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= 'ii';

$stmt = $gh->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// COUNT QUERY
$count_query = "SELECT COUNT(*) as total FROM error_logs $where_clause";
$count_stmt = $gh->prepare($count_query);

// Only bind parameters if we have filters
if ($where_clause) {
    // For count query, we don't need LIMIT params
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    // Extra safety check in case $types was only 'ii' (LIMIT params)
    if (!empty($count_types)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}

$count_stmt->execute();
$total_errors = $count_stmt->get_result()->fetch_assoc()['total'];

// TOTALS QUERY (if needed)
if (isset($totals_query)) {
    $totals_stmt = $gh->prepare($totals_query);
    
    if ($where_clause && !empty($count_types)) {
        $totals_stmt->bind_param($count_types, ...$count_params);
    }
    
    $totals_stmt->execute();
    $totals = $totals_stmt->get_result()->fetch_assoc();
}

// Get log types for filter
$log_types = $gh->query("SELECT DISTINCT error_code FROM error_logs ORDER BY error_code")->fetch_all(MYSQLI_ASSOC);
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <!-- Filters -->
    <div class="mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Log Type</label>
                <select name="type" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    <option value="">All Types</option>
                    <?php foreach ($log_types as $error_types): ?>
                        <option value="<?= htmlspecialchars($error_types['error_code']) ?>" <?= $type === $error_code['error_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($error_types['error_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search messages or IPs" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg w-full">
                    Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Logs Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Message</th>
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
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $log['error_code'] === 'ERROR' ? 'bg-red-900 text-red-300' : 
                                   ($log['error_code'] === 'WARNING' ? 'bg-yellow-900 text-yellow-300' : 'bg-blue-900 text-blue-300') ?>">
                                <?= $log['error_code'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-300">
                            <?= htmlspecialchars($log['message']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= htmlspecialchars($log['ip_address']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="mt-4 flex items-center justify-between">
        <div class="text-sm text-gray-400">
            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_errors) ?> of <?= $total_errors ?> logs
        </div>
        <div class="flex space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Previous
                </a>
            <?php endif; ?>
            
            <?php if ($page < $total_errors): ?>
                <a href="?page=<?= $page + 1 ?>&type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Export Button -->
    <div class="mt-6">
        <a href="export-logs.php?type=<?= urlencode($type) ?>&search=<?= urlencode($search) ?>" 
           class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg">
            <i class="fas fa-file-export mr-2"></i> Export to CSV
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>