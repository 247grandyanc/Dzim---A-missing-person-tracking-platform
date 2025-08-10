<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_plan'])) {
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $price = (float)$_POST['price'];
        $searches = (int)$_POST['searches'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        
        $stmt = $gh->prepare("
            INSERT INTO subscription_plans 
            (name, description, price, searches_per_month, is_active, is_popular) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdiii", $name, $description, $price, $searches, $is_active, $is_popular);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "ADD_PLAN", $gh->insert_id);
        header("Location: subscriptions.php?success=Plan+added");
        exit();
    }
    
    if (isset($_POST['update_plan'])) {
        $plan_id = (int)$_POST['plan_id'];
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $price = (float)$_POST['price'];
        $searches = (int)$_POST['searches'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        
        $stmt = $gh->prepare("
            UPDATE subscription_plans 
            SET name = ?, description = ?, price = ?, searches_per_month = ?, 
                is_active = ?, is_popular = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("ssdiiii", $name, $description, $price, $searches, $is_active, $is_popular, $plan_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "UPDATE_PLAN", $plan_id);
        header("Location: subscriptions.php?success=Plan+updated");
        exit();
    }
    
    if (isset($_POST['delete_plan'])) {
        $plan_id = (int)$_POST['plan_id'];
        $stmt = $gh->prepare("DELETE FROM subscription_plans WHERE id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "DELETE_PLAN", $plan_id);
        header("Location: subscriptions.php?success=Plan+deleted");
        exit();
    }
    
    if (isset($_POST['extend_sub'])) {
        $sub_id = (int)$_POST['sub_id'];
        $days = (int)$_POST['days'];
        
        $stmt = $gh->prepare("
            UPDATE subscriptions 
            SET expires_at = DATE_ADD(expires_at, INTERVAL ? DAY) 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $days, $sub_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "EXTEND_SUB", $sub_id);
        header("Location: subscriptions.php?success=Subscription+extended");
        exit();
    }
}

// Get all plans
$plans = $gh->query("
    SELECT * FROM subscription_plans 
    ORDER BY is_active DESC, price ASC
")->fetch_all(MYSQLI_ASSOC);

// Get active subscriptions with pagination
$sub_page = isset($_GET['sub_page']) ? (int)$_GET['sub_page'] : 1;
$sub_per_page = 10;
$sub_offset = ($sub_page - 1) * $sub_per_page;

$subs_query = "
    SELECT s.*, u.email, p.name as plan_name 
    FROM subscription s
    JOIN users u ON s.user_id = u.user_id
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.status = 'active' AND s.expires_at > NOW()
    ORDER BY s.expires_at ASC
    LIMIT ?, ?
";
$subs_stmt = $gh->prepare($subs_query);
$subs_stmt->bind_param("ii", $sub_offset, $sub_per_page);
$subs_stmt->execute();
$active_subs = $subs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total active subs count
$total_subs = $gh->query("
    SELECT COUNT(*) as count 
    FROM subscription 
    WHERE status = 'active' AND expires_at > NOW()
")->fetch_assoc()['count'];
$total_sub_pages = ceil($total_subs / $sub_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>Subscription Management | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <h1 class="text-3xl font-bold mb-8">Subscription Management</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Subscription Plans -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold">Subscription Plans</h2>
                        <button onclick="document.getElementById('add-plan-modal').classList.remove('hidden')" 
                                class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                            Add New Plan
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Searches</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-800 divide-y divide-gray-700">
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= $plan['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-300"><?= htmlspecialchars($plan['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($plan['description']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            GHS <?= number_format($plan['price'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= $plan['searches_per_month'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $plan['is_active'] ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' ?>">
                                                <?= $plan['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                            <?php if ($plan['is_popular']): ?>
                                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-900 text-purple-300">
                                                    Popular
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="openEditModal(
                                                <?= $plan['id'] ?>, 
                                                '<?= htmlspecialchars($plan['name'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($plan['description'], ENT_QUOTES) ?>',
                                                <?= $plan['price'] ?>,
                                                <?= $plan['searches_per_month'] ?>,
                                                <?= $plan['is_active'] ? 'true' : 'false' ?>,
                                                <?= $plan['is_popular'] ? 'true' : 'false' ?>
                                            )" class="text-blue-400 hover:text-blue-300 mr-3">
                                                Edit
                                            </button>
                                            <form method="post" class="inline" onsubmit="return confirm('Delete this plan?');">
                                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                                <button type="submit" name="delete_plan" class="text-red-400 hover:text-red-300">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Active Subscriptions -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-xl font-bold mb-6">Active Subscriptions</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Expires</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-800 divide-y divide-gray-700">
                                <?php foreach ($active_subs as $sub): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= $sub['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-300"><?= htmlspecialchars($sub['email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= htmlspecialchars($sub['plan_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= date('M j, Y', strtotime($sub['starts_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= date('M j, Y', strtotime($sub['expires_at'])) ?>
                                            (<?= round((strtotime($sub['expires_at']) - time()) / (60 * 60 * 24)) ?> days left)
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="document.getElementById('extend-sub-id').value = <?= $sub['id'] ?>; 
                                                          document.getElementById('extend-modal').classList.remove('hidden')" 
                                                    class="text-green-400 hover:text-green-300">
                                                Extend
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 bg-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?= $sub_offset + 1 ?> to <?= min($sub_offset + $sub_per_page, $total_subs) ?> of <?= $total_subs ?> subscriptions
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($sub_page > 1): ?>
                                <a href="?sub_page=<?= $sub_page - 1 ?>" class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($sub_page < $total_sub_pages): ?>
                                <a href="?sub_page=<?= $sub_page + 1 ?>" class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded-lg">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Plan Modal -->
    <div id="add-plan-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Add New Plan</h3>
                <button onclick="document.getElementById('add-plan-modal').classList.add('hidden')" 
                        class="text-gray-400 hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Plan Name</label>
                        <input type="text" name="name" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Price (GHS)</label>
                            <input type="number" name="price" step="0.01" min="0" required 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Searches/Month</label>
                            <input type="number" name="searches" min="1" required 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" checked 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-600 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-400">Active</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_popular" id="is_popular" 
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-600 rounded">
                            <label for="is_popular" class="ml-2 block text-sm text-gray-400">Popular</label>
                        </div>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('add-plan-modal').classList.add('hidden')" 
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" name="add_plan" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">
                            Add Plan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Plan Modal -->
    <div id="edit-plan-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Edit Plan</h3>
                <button onclick="document.getElementById('edit-plan-modal').classList.add('hidden')" 
                        class="text-gray-400 hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form method="post">
                <input type="hidden" id="edit-plan-id" name="plan_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Plan Name</label>
                        <input type="text" id="edit-plan-name" name="name" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Description</label>
                        <textarea id="edit-plan-desc" name="description" rows="3" 
                                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Price (GHS)</label>
                            <input type="number" id="edit-plan-price" name="price" step="0.01" min="0" required 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Searches/Month</label>
                            <input type="number" id="edit-plan-searches" name="searches" min="1" required 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="edit-plan-active" name="is_active" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-600 rounded">
                            <label for="edit-plan-active" class="ml-2 block text-sm text-gray-400">Active</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="edit-plan-popular" name="is_popular" 
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-600 rounded">
                            <label for="edit-plan-popular" class="ml-2 block text-sm text-gray-400">Popular</label>
                        </div>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('edit-plan-modal').classList.add('hidden')" 
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" name="update_plan" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">
                            Update Plan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Extend Subscription Modal -->
    <div id="extend-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Extend Subscription</h3>
                <button onclick="document.getElementById('extend-modal').classList.add('hidden')" 
                        class="text-gray-400 hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form method="post">
                <input type="hidden" id="extend-sub-id" name="sub_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Extend by (days)</label>
                        <select name="days" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">365 days</option>
                        </select>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('extend-modal').classList.add('hidden')" 
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" name="extend_sub" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg">
                            Extend
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(id, name, description, price, searches, isActive, isPopular) {
            document.getElementById('edit-plan-modal').classList.remove('hidden');
            document.getElementById('edit-plan-id').value = id;
            document.getElementById('edit-plan-name').value = name;
            document.getElementById('edit-plan-desc').value = description;
            document.getElementById('edit-plan-price').value = price;
            document.getElementById('edit-plan-searches').value = searches;
            document.getElementById('edit-plan-active').checked = isActive;
            document.getElementById('edit-plan-popular').checked = isPopular;
        }
    </script>
    <?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
</body>
</html>