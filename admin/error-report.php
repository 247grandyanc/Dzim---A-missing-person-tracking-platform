<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Only super admins and moderators can view error reports
if ($admin['role'] !== 'SUPER_ADMIN' && $admin['role'] !== 'MODERATOR') {
    header('Location: dashboard.php');
    exit();
}

// Set page variables
$title = "Error Reports";
$page_title = "System Error Reports";
$breadcrumbs = [
    ['text' => 'Dashboard', 'url' => 'dashboard.php'],
    ['text' => 'Error Reports', 'url' => 'error-report.php']
];

require_once __DIR__ . '/includes/admin-nav.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resolve'])) {
        $error_id = (int)$_POST['error_id'];
        $stmt = $gh->prepare("UPDATE error_reports SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE error_id = ?");
        $stmt->bind_param("ii", $admin['admin_id'], $error_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "RESOLVE_ERROR", $error_id);
        $_SESSION['success_message'] = "Error marked as resolved";
        header("Location: error-report.php");
        exit();
    }
    
    if (isset($_POST['delete'])) {
        $error_id = (int)$_POST['error_id'];
        $stmt = $gh->prepare("DELETE FROM error_reports WHERE error_id = ?");
        $stmt->bind_param("i", $error_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "DELETE_ERROR", $error_id);
        $_SESSION['success_message'] = "Error report deleted";
        header("Location: error-report.php");
        exit();
    }
}

// Filters
$status = isset($_GET['status']) ? clean_input($_GET['status']) : 'unresolved';
$severity = isset($_GET['severity']) ? clean_input($_GET['severity']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause and parameters
$where = [];
$params = [];
$types = '';

// Add your filters here (example)
if ($status_filter) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// MAIN QUERY
$query = "SELECT e.*, a.username as resolved_by_name 
          FROM error_reports e
          LEFT JOIN admin_users a ON e.resolved_by = a.admin_id
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
$errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// COUNT QUERY
$count_query = "SELECT COUNT(*) as total FROM error_reports $where_clause";
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
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <!-- Filters -->
    <div class="mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Status</label>
                <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    <option value="unresolved" <?= $status === 'unresolved' ? 'selected' : '' ?>>Unresolved</option>
                    <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">Severity</label>
                <select name="severity" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    <option value="">All Severities</option>
                    <option value="critical" <?= $severity === 'critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="error" <?= $severity === 'error' ? 'selected' : '' ?>>Error</option>
                    <option value="warning" <?= $severity === 'warning' ? 'selected' : '' ?>>Warning</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg w-full">
                    Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Error Reports -->
    <div class="space-y-4">
        <?php if (empty($errors)): ?>
            <div class="text-center py-8 text-gray-400">
                No error reports found
            </div>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <div class="bg-gray-700 rounded-lg border <?= $error['severity'] === 'critical' ? 'border-red-700' : 
                                                          ($error['severity'] === 'error' ? 'border-orange-700' : 'border-yellow-700') ?>">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-medium <?= $error['severity'] === 'critical' ? 'text-red-400' : 
                                                      ($error['severity'] === 'error' ? 'text-orange-400' : 'text-yellow-400') ?>">
                                    <?= strtoupper($error['severity']) ?>: <?= htmlspecialchars($error['error_type']) ?>
                                </h3>
                                <p class="text-sm text-gray-400 mt-1">
                                    <?= date('M j, Y H:i:s', strtotime($error['created_at'])) ?>
                                    <?php if ($error['is_resolved']): ?>
                                        <span class="text-green-400 ml-2">
                                            Resolved by <?= htmlspecialchars($error['resolved_by_name']) ?> on <?= date('M j, Y', strtotime($error['resolved_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <?php if (!$error['is_resolved']): ?>
                                    <form method="post">
                                        <input type="hidden" name="error_id" value="<?= $error['error_id'] ?>">
                                        <button type="submit" name="resolve" class="text-green-400 hover:text-green-300">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Delete this error report?');">
                                    <input type="hidden" name="error_id" value="<?= $error['error_id'] ?>">
                                    <button type="submit" name="delete" class="text-red-400 hover:text-red-300">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="mt-3 bg-gray-800 rounded-lg p-3">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 pt-1">
                                    <i class="fas fa-exclamation-circle <?= $error['severity'] === 'critical' ? 'text-red-400' : 
                                                                      ($error['severity'] === 'error' ? 'text-orange-400' : 'text-yellow-400') ?>"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-300"><?= htmlspecialchars($error['error_message']) ?></p>
                                    <p class="mt-2 text-xs text-gray-500">
                                        <span class="font-mono bg-gray-900 px-2 py-1 rounded"><?= htmlspecialchars($error['file_path']) ?>:<?= $error['line_number'] ?></span>
                                    </p>
                                    <?php if ($error['user_id']): ?>
                                        <p class="mt-2 text-xs text-gray-400">
                                            User ID: <?= $error['user_id'] ?>, IP: <?= htmlspecialchars($error['ip_address']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($error['stack_trace']): ?>
                            <div class="mt-3">
                                <button type="button" onclick="toggleStackTrace(<?= $error['error_id'] ?>)" 
                                        class="text-xs text-blue-400 hover:text-blue-300">
                                    <i class="fas fa-chevron-down mr-1"></i> Show Stack Trace
                                </button>
                                <div id="stack-trace-<?= $error['error_id'] ?>" class="hidden mt-2 bg-gray-900 rounded-lg p-3 overflow-x-auto">
                                    <pre class="text-xs text-gray-400"><?= htmlspecialchars($error['stack_trace']) ?></pre>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-400">
            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_errors) ?> of <?= $total_errors ?> errors
        </div>
        <div class="flex space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&severity=<?= urlencode($severity) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Previous
                </a>
            <?php endif; ?>
            
            <?php if ($page < $total_errors): ?>
                <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&severity=<?= urlencode($severity) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function toggleStackTrace(errorId) {
        const element = document.getElementById('stack-trace-' + errorId);
        element.classList.toggle('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>