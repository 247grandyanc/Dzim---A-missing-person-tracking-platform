<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/admin-functions.php';
$admin = admin_authenticate();

// Set page variables
$title = "Transaction Management";
$page_title = "Payment Transactions";
$breadcrumbs = [
    ['text' => 'Dashboard', 'url' => 'dashboard.php'],
    ['text' => 'Transactions', 'url' => 'transactions.php']
];

require_once __DIR__ . '/includes/admin-nav.php';

// Filters
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where = [];
$params = [];
$types = '';

if ($status) {
    $where[] = "t.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($search) {
    $where[] = "(t.paystack_reference LIKE ? OR u.email LIKE ? OR t.amount = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = $search;
    $types .= 'sss';
}

if ($date_from) {
    $where[] = "t.created_at >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where[] = "t.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get transactions
$query = "
    SELECT t.*, u.email, p.name as plan_name 
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.user_id
    LEFT JOIN subscription_plans p ON t.subscription_id = p.id
    $where_clause
    ORDER BY t.created_at DESC
    LIMIT ?, ?
";

// Handle parameter binding based on whether we have filters
if ($where_clause) {
    // When filters exist, bind all parameters (filters + limit)
    $params[] = $offset;
    $params[] = $per_page;
    $types .= 'ii';
    $stmt = $gh->prepare($query);
    $stmt->bind_param($types, ...$params);
} else {
    // When no filters, just bind the limit parameters
    $stmt = $gh->prepare($query);
    $stmt->bind_param('ii', $offset, $per_page);
}

$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total count for pagination
$count_query = "SELECT COUNT(*) as total FROM transactions t $where_clause";
$count_stmt = $gh->prepare($count_query);

if ($where_clause) {
    // For count query, we don't need the LIMIT params (remove last 2)
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    $count_stmt->bind_param($count_types, ...$count_params);
}

$count_stmt->execute();
$total_transactions = $count_stmt->get_result()->fetch_assoc()['total'];

// Get totals
$totals_query = "
    SELECT 
        SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) as success_total,
        SUM(CASE WHEN status = 'PENDING' THEN amount ELSE 0 END) as pending_total,
        SUM(CASE WHEN status = 'FAILED' THEN amount ELSE 0 END) as failed_total,
        SUM(amount) as grand_total
    FROM transactions
    $where_clause
";
$totals_stmt = $gh->prepare($totals_query);

if ($where_clause) {
    // Same parameters as count query (no LIMIT params)
    $totals_stmt->bind_param($count_types, ...$count_params);
}

$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <!-- Filters -->
    <div class="mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm text-gray-400 mb-1">Status</label>
                <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    <option value="">All Statuses</option>
                    <option value="SUCCESS" <?= $status === 'SUCCESS' ? 'selected' : '' ?>>Success</option>
                    <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    <option value="FAILED" <?= $status === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                    <option value="REFUNDED" <?= $status === 'REFUNDED' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
            </div>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Reference or email" 
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
            </div>
        </form>
    </div>
    
    <!-- Totals -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-700 rounded-lg p-4 border border-green-700">
            <p class="text-sm text-gray-400">Successful</p>
            <p class="text-xl font-bold text-green-400">GHS <?= number_format($totals['success_total'] ?? 0, 2) ?></p>
        </div>
        <div class="bg-gray-700 rounded-lg p-4 border border-yellow-700">
            <p class="text-sm text-gray-400">Pending</p>
            <p class="text-xl font-bold text-yellow-400">GHS <?= number_format($totals['pending_total'] ?? 0, 2) ?></p>
        </div>
        <div class="bg-gray-700 rounded-lg p-4 border border-red-700">
            <p class="text-sm text-gray-400">Failed</p>
            <p class="text-xl font-bold text-red-400">GHS <?= number_format($totals['failed_total'] ?? 0, 2) ?></p>
        </div>
        <div class="bg-gray-700 rounded-lg p-4 border border-blue-700">
            <p class="text-sm text-gray-400">Total</p>
            <p class="text-xl font-bold text-blue-400">GHS <?= number_format($totals['grand_total'] ?? 0, 2) ?></p>
        </div>
    </div>
    
    <!-- Transactions Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php foreach ($transactions as $txn): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= $txn['transaction_id'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-300"><?= htmlspecialchars($txn['email']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($txn['ip_address']) ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= $txn['plan_name'] ? htmlspecialchars($txn['plan_name']) : 'N/A' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            GHS <?= number_format($txn['amount'], 2) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $txn['status'] === 'SUCCESS' ? 'bg-green-900 text-green-300' : 
                                   ($txn['status'] === 'PENDING' ? 'bg-yellow-900 text-yellow-300' : 
                                   ($txn['status'] === 'REFUNDED' ? 'bg-blue-900 text-blue-300' : 'bg-red-900 text-red-300')) ?>">
                                <?= $txn['status'] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= substr($txn['paystack_reference'], 0, 8) ?>...
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                            <?= date('M j, Y H:i', strtotime($txn['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewTransaction(<?= $txn['transaction_id'] ?>)" class="text-blue-400 hover:text-blue-300 mr-3">
                                View
                            </button>
                            <?php if ($txn['status'] === 'SUCCESS' && $admin['role'] === 'SUPER_ADMIN'): ?>
                                <button onclick="refundTransaction(<?= $txn['transaction_id'] ?>)" class="text-red-400 hover:text-red-300">
                                    Refund
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-400">
            Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_transactions) ?> of <?= $total_transactions ?> transactions
        </div>
        <div class="flex space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Previous
                </a>
            <?php endif; ?>
            
            <?php if ($page < $totals): ?>
                <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div id="transaction-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-2xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Transaction Details</h3>
            <button onclick="document.getElementById('transaction-modal').classList.add('hidden')" 
                    class="text-gray-400 hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <div id="transaction-details" class="space-y-4">
            <!-- Details will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
    // View transaction details
    function viewTransaction(id) {
        fetch(`/admin/api/transaction.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const txn = data.transaction;
                    let html = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-400">Transaction ID</p>
                                <p class="text-gray-300">${txn.transaction_id}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Reference</p>
                                <p class="text-gray-300">${txn.paystack_reference}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">User</p>
                                <p class="text-gray-300">${txn.email || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Plan</p>
                                <p class="text-gray-300">${txn.plan_name || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Amount</p>
                                <p class="text-gray-300">GHS ${txn.amount.toFixed(2)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Status</p>
                                <p class="text-gray-300">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        ${txn.status === 'SUCCESS' ? 'bg-green-900 text-green-300' : 
                                          txn.status === 'PENDING' ? 'bg-yellow-900 text-yellow-300' : 
                                          txn.status === 'REFUNDED' ? 'bg-blue-900 text-blue-300' : 'bg-red-900 text-red-300'}">
                                        ${txn.status}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Payment Method</p>
                                <p class="text-gray-300">${txn.payment_method || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">Date</p>
                                <p class="text-gray-300">${new Date(txn.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm text-gray-400">IP Address</p>
                            <p class="text-gray-300">${txn.ip_address}</p>
                        </div>
                    `;
                    
                    document.getElementById('transaction-details').innerHTML = html;
                    document.getElementById('transaction-modal').classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to load transaction details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load transaction details');
            });
    }
    
    // Refund transaction
    function refundTransaction(id) {
        if (confirm('Are you sure you want to refund this transaction?')) {
            fetch(`/admin/api/transaction.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'refund',
                    transaction_id: id,
                    csrf_token: CSRF_TOKEN
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Transaction refunded successfully');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to refund transaction');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to refund transaction');
            });
        }
    }
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>