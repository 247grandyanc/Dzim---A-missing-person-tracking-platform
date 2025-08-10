<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['block_ip'])) {
        $ip_range = clean_input($_POST['ip_range']);
        $reason = clean_input($_POST['reason']);
        $expires = !empty($_POST['expires']) ? $_POST['expires'] : null;
        
        // Validate IP range
        if (!filter_var($ip_range, FILTER_VALIDATE_IP) && !preg_match('/^[0-9\.]+\/[0-9]+$/', $ip_range)) {
            $_SESSION['error'] = "Invalid IP address or range";
            header("Location: blockip.php");
            exit();
        }
        
        // Insert into database
        $stmt = $gh->prepare("
            INSERT INTO ip_blocks 
            (ip_range, reason, admin_id, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssis",
            $ip_range,
            $reason,
            $admin['admin_id'],
            $expires
        );
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "BLOCK_IP", $ip_range);
        header("Location: blockip.php?success=IP+blocked");
        exit();
    }
    
    if (isset($_POST['unblock'])) {
        $block_id = (int)$_POST['block_id'];
        $stmt = $gh->prepare("DELETE FROM ip_blocks WHERE block_id = ?");
        $stmt->bind_param("i", $block_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "UNBLOCK_IP", $block_id);
        header("Location: blockip.php?success=IP+unblocked");
        exit();
    }
}

// Get all blocked IPs
$blocks = $gh->query("
    SELECT b.*, a.username as blocked_by 
    FROM ip_blocks b
    LEFT JOIN admin_users a ON b.admin_id = a.admin_id
    ORDER BY b.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>IP Blocking | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <h1 class="text-3xl font-bold mb-8">IP Blocking</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-900/30 border border-red-700 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <!-- Block IP Form -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">Block New IP/Range</h2>
                    
                    <form method="post">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">IP Address/Range</label>
                                <input type="text" name="ip_range" placeholder="e.g., 192.168.1.1 or 192.168.1.0/24" required 
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                            </div>
                            
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Reason</label>
                                <select name="reason" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                    <option value="ABUSE">Abuse</option>
                                    <option value="GEO_BLOCK">Geo Block</option>
                                    <option value="MANUAL_BAN">Manual Ban</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Expires (optional)</label>
                                <input type="datetime-local" name="expires" 
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="block_ip" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg">
                                Block IP
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Blocked IPs Table -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">IP Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Blocked By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Expires</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            <?php foreach ($blocks as $block): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= htmlspecialchars($block['ip_range']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $block['reason'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= htmlspecialchars($block['blocked_by']) ?>
                                        <div class="text-xs text-gray-500">
                                            <?= date('M j, Y', strtotime($block['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        <?= $block['expires_at'] ? date('M j, Y', strtotime($block['expires_at'])) : 'Never' ?>
                                        <?php if ($block['expires_at']): ?>
                                            <div class="text-xs text-gray-500">
                                                <?= round((strtotime($block['expires_at']) - time()) / (60 * 60 * 24)) ?> days left
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="post" class="inline">
                                            <input type="hidden" name="block_id" value="<?= $block['block_id'] ?>">
                                            <button type="submit" name="unblock" class="text-green-400 hover:text-green-300">
                                                Unblock
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
</body>
</html>