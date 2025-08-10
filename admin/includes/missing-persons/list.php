<?php
// Get filter parameters
$type = $_GET['type'] ?? 'missing';
$status = $_GET['status'] ?? 'active';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT * FROM missing_persons WHERE type = ?";
$params = [$type];
$types = 's';

if ($status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

// Get total count for pagination
$count_stmt = $gh->prepare(str_replace('*', 'COUNT(*)', $query));
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total / $per_page);

// Get paginated results
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $gh->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Missing Persons Reports</h2>
        <div class="flex space-x-2">
            <a href="?action=rewards" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                Manage Rewards
            </a>
            <a href="?action=claims" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                Reward Claims
            </a>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="?type=missing" 
           class="<?= $type === 'missing' ? 'bg-blue-900 text-blue-300' : 'bg-gray-700 hover:bg-gray-600' ?> px-3 py-1 rounded-lg">
            Missing Persons
        </a>
        <a href="?type=found" 
           class="<?= $type === 'found' ? 'bg-blue-900 text-blue-300' : 'bg-gray-700 hover:bg-gray-600' ?> px-3 py-1 rounded-lg">
            Found Persons
        </a>
        <div class="ml-auto">
            <select onchange="window.location.href = '?type=<?= $type ?>&status='+this.value" 
                    class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-1">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>
    </div>

    <!-- Cases Table -->
    <div class="overflow-x-auto mb-4">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Photo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Age/Gender</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Seen</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reward</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php while ($person = $result->fetch_assoc()): ?>
                <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($person['photo_path']): ?>
                            <img src="<?= htmlspecialchars($person['photo_path']) ?>" 
                                 alt="Photo" 
                                 class="h-10 w-10 rounded-full object-cover">
                        <?php else: ?>
                            <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium"><?= htmlspecialchars($person['full_name']) ?></div>
                        <div class="text-sm text-gray-400"><?= htmlspecialchars($person['home_name']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div><?= $person['age'] ? htmlspecialchars($person['age']) . ' years' : 'Unknown' ?></div>
                        <div class="text-sm text-gray-400"><?= ucfirst($person['gender']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div><?= htmlspecialchars($person['last_seen_location'] ?? 'Unknown') ?></div>
                        <div class="text-sm text-gray-400"><?= date('M j, Y', strtotime($person['created_at'])) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 rounded-full text-xs 
                            <?= $person['status'] === 'active' ? 'bg-yellow-900 text-yellow-300' : 
                               ($person['status'] === 'resolved' ? 'bg-green-900 text-green-300' : 'bg-gray-700 text-gray-300') ?>">
                            <?= ucfirst($person['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($person['has_reward']): ?>
                            <span class="px-2 py-1 bg-purple-900 text-purple-300 rounded-full text-xs">
                                GHS <?= number_format($person['reward_amount'], 2) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-500 text-xs">No reward</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="?action=view&id=<?= $person['id'] ?>" 
                           class="text-blue-400 hover:text-blue-300 mr-3" 
                           title="View details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="?action=edit&id=<?= $person['id'] ?>" 
                           class="text-yellow-400 hover:text-yellow-300 mr-3" 
                           title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($person['status'] === 'active'): ?>
                            <a href="?action=resolve&id=<?= $person['id'] ?>" 
                               class="text-green-400 hover:text-green-300" 
                               title="Mark as resolved">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-between items-center">
        <div class="text-sm text-gray-400">
            Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?>
        </div>
        <div class="flex space-x-1">
            <?php if ($page > 1): ?>
                <a href="?type=<?= $type ?>&status=<?= $status ?>&page=<?= $page - 1 ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    &laquo; Prev
                </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++): 
            ?>
                <a href="?type=<?= $type ?>&status=<?= $status ?>&page=<?= $i ?>" 
                   class="<?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?> px-3 py-1 rounded-lg">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?type=<?= $type ?>&status=<?= $status ?>&page=<?= $page + 1 ?>" 
                   class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded-lg">
                    Next &raquo;
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>