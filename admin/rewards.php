<?php
require_once __DIR__ . '/includes/admin-auth.php';

// Authenticate admin
$admin = admin_authenticate();

// Get filter parameters
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Validate status
$valid_statuses = ['pending', 'approved', 'rejected', 'paid'];
if (!in_array($status, $valid_statuses)) {
    $status = 'pending';
}

// Build query
global $gh;
$query = "
    SELECT rc.*, 
           u.email as claimer_email,
           u.phone as claimer_phone,
           mp.full_name as person_name,
           mpr.amount as reward_amount,
           a.username as admin_username
    FROM reward_claims rc
    JOIN users u ON rc.claimer_id = u.user_id
    JOIN missing_person_rewards mpr ON rc.reward_id = mpr.reward_id
    JOIN missing_persons mp ON mpr.user_id = mp.id
    LEFT JOIN admin_users a ON rc.admin_id = a.admin_id
    WHERE rc.status = ?
";

$params = [$status];
$types = "s";

if (!empty($search)) {
    $query .= " AND (mp.full_name LIKE ? OR u.email LIKE ? OR rc.message LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY rc.created_at DESC";

$stmt = $gh->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reward Claims | SEACH-GH</title>
    <style>
        .claim-card {
            transition: all 0.3s ease;
        }
        .claim-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #dcfce7; color: #166534; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-paid { background-color: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold">Reward Claims Management</h1>
            <div class="flex space-x-2">
                <a href="missing-persons.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
                    View Missing Persons
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="get" class="flex flex-col md:flex-row md:items-center md:space-x-4 space-y-4 md:space-y-0">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                           placeholder="Search by name, email or message">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
                
                <div class="self-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Claims List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($claims)): ?>
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No reward claims found</h3>
                    <p class="mt-1 text-sm text-gray-500">There are currently no reward claims with status "<?= ucfirst($status) ?>".</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Person</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Claimer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($claims as $claim): ?>
                            <tr class="claim-card hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($claim['person_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($claim['claimer_email']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($claim['claimer_phone']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ₵<?= number_format($claim['reward_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge status-<?= $claim['status'] ?>">
                                        <?= ucfirst($claim['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($claim['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="reward-details.php?id=<?= $claim['claim_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                    <?php if ($claim['status'] === 'pending' && in_array($admin['role'], ['superadmin', 'admin'])): ?>
                                        <button onclick="processClaim(<?= $claim['claim_id'] ?>, 'approve')" class="text-green-600 hover:text-green-900 mr-3">Approve</button>
                                        <button onclick="processClaim(<?= $claim['claim_id'] ?>, 'reject')" class="text-red-600 hover:text-red-900">Reject</button>
                                    <?php elseif ($claim['status'] === 'approved' && in_array($admin['role'], ['superadmin', 'admin'])): ?>
                                        <button onclick="processPayment(<?= $claim['claim_id'] ?>)" class="text-indigo-600 hover:text-indigo-900">Process Payment</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination would go here -->
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/admin-footer.php'; ?>

    <script>
        function processClaim(claimId, action) {
            if (confirm(`Are you sure you want to ${action} this claim?`)) {
                fetch('process-claim.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        claim_id: claimId,
                        action: action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function processPayment(claimId) {
            if (confirm('Initiate payment for this claim?')) {
                fetch('process-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        claim_id: claimId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>