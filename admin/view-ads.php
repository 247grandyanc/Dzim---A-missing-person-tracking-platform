<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_ad'])) {
        $campaign_id = (int)$_POST['campaign_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $gh->prepare("UPDATE ad_campaigns SET is_active = ? WHERE campaign_id = ?");
        $stmt->bind_param("ii", $is_active, $campaign_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], $is_active ? "ACTIVATE_AD" : "DEACTIVATE_AD", $campaign_id);
        header("Location: view-ads.php?success=Ad+updated");
        exit();
    }
    
    if (isset($_POST['delete_ad'])) {
        $campaign_id = (int)$_POST['campaign_id'];
        
        $stmt = $gh->prepare("DELETE FROM ad_campaigns WHERE campaign_id = ?");
        $stmt->bind_param("i", $campaign_id);
        $stmt->execute();
        
        log_admin_action($admin['admin_id'], "DELETE_AD", $campaign_id);
        header("Location: view-ads.php?success=Ad+deleted");
        exit();
    }
}

// Get all ad campaigns with stats
$campaigns = $gh->query("
    SELECT c.*, 
           COUNT(i.impression_id) as impressions,
           SUM(CASE WHEN i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_impressions
    FROM ad_campaigns c
    LEFT JOIN ad_impressions i ON c.campaign_id = i.campaign_id
    GROUP BY c.campaign_id
    ORDER BY c.is_active DESC, c.end_date DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>Ad Management | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-3xl font-bold">Ad Management</h1>
                    <a href="add-ads.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                        Add New Ad
                    </a>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Ad Campaigns -->
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($campaigns as $campaign): ?>
                            <div class="bg-gray-700 rounded-lg border border-gray-600 overflow-hidden">
                                <div class="relative">
                                    <img src="<?= htmlspecialchars($campaign['image_url']) ?>" alt="Ad" class="w-full h-40 object-cover">
                                    <div class="absolute top-2 right-2">
                                        <span class="px-2 py-1 text-xs font-bold rounded-full 
                                            <?= $campaign['is_active'] ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' ?>">
                                            <?= $campaign['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars($campaign['advertiser_name']) ?></h3>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-400">Impressions</p>
                                            <p class="font-medium"><?= number_format($campaign['impressions']) ?></p>
                                            <p class="text-xs text-gray-500"><?= number_format($campaign['recent_impressions']) ?> this week</p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-400">Clicks Left</p>
                                            <p class="font-medium"><?= number_format($campaign['clicks_remaining']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="text-sm text-gray-400 mb-4">
                                        <p>Target: <?= $campaign['target_country'] ? htmlspecialchars($campaign['target_country']) : 'Global' ?></p>
                                        <p>Runs: <?= date('M j', strtotime($campaign['start_date'])) ?> - <?= date('M j, Y', strtotime($campaign['end_date'])) ?></p>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <form method="post" class="inline">
                                            <input type="hidden" name="campaign_id" value="<?= $campaign['campaign_id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $campaign['is_active'] ? 0 : 1 ?>">
                                            <button type="submit" name="toggle_ad" class="text-blue-400 hover:text-blue-300 text-sm">
                                                <?= $campaign['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this ad campaign?');">
                                            <input type="hidden" name="campaign_id" value="<?= $campaign['campaign_id'] ?>">
                                            <button type="submit" name="delete_ad" class="text-red-400 hover:text-red-300 text-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
</body>
</html>