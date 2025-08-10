<?php
// Verify we have an ID
if (empty($id)) {
    header("Location: missing-persons.php");
    exit();
}

// Get case details
$stmt = $gh->prepare("
    SELECT mp.*, u.email as reporter_email
    FROM missing_persons mp
    LEFT JOIN users u ON mp.reporter_id = u.user_id
    WHERE mp.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$case = $stmt->get_result()->fetch_assoc();

if (!$case) {
    $_SESSION['error_message'] = "Case not found";
    header("Location: missing-persons.php");
    exit();
}

// Get resolution details if case is resolved
$resolution = null;
if ($case['status'] === 'resolved') {
    $stmt = $gh->prepare("
        SELECT cr.*, a.email as resolved_by_email
        FROM case_resolutions cr
        LEFT JOIN admin_users a ON cr.resolved_by = a.admin_id
        WHERE cr.case_id = ?
        ORDER BY cr.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resolution = $stmt->get_result()->fetch_assoc();
}

// Get reward details if any
$reward = null;
if ($case['has_reward']) {
    $stmt = $gh->prepare("
        SELECT r.*, u.email as offered_by
        FROM missing_person_rewards r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.missing_person_id = ?
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $reward = $stmt->get_result()->fetch_assoc();
}
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Case Details: <?= htmlspecialchars($case['full_name']) ?></h2>
        <div class="flex space-x-2">
            <a href="missing-persons.php?action=edit&id=<?= $id ?>" 
               class="bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded-lg">
                Edit
            </a>
            <?php if ($case['status'] === 'active'): ?>
                <a href="missing-persons.php?action=resolve&id=<?= $id ?>" 
                   class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                    Mark Resolved
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Main Case Info -->
        <div class="md:col-span-2 bg-gray-700 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-lg font-medium mb-3">Personal Information</h3>
                    <div class="space-y-2">
                        <div class="flex">
                            <span class="text-gray-400 w-32">Full Name:</span>
                            <span><?= htmlspecialchars($case['full_name']) ?></span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Home Name:</span>
                            <span><?= htmlspecialchars($case['home_name']) ?></span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Age/Gender:</span>
                            <span>
                                <?= $case['age'] ? htmlspecialchars($case['age']) . ' years' : 'Unknown' ?>
                                / <?= ucfirst($case['gender']) ?>
                            </span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Height:</span>
                            <span><?= $case['height'] ? htmlspecialchars($case['height']) : 'Unknown' ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium mb-3">Case Details</h3>
                    <div class="space-y-2">
                        <div class="flex">
                            <span class="text-gray-400 w-32">Status:</span>
                            <span class="<?= $case['status'] === 'active' ? 'text-yellow-400' : 'text-green-400' ?>">
                                <?= ucfirst($case['status']) ?>
                            </span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Last Seen:</span>
                            <span><?= htmlspecialchars($case['last_seen_location'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Reported:</span>
                            <span><?= date('M j, Y H:i', strtotime($case['created_at'])) ?></span>
                        </div>
                        <div class="flex">
                            <span class="text-gray-400 w-32">Reporter:</span>
                            <span>
                                <?= $case['reporter_email'] ? htmlspecialchars($case['reporter_email']) : 
                                   ($case['reporter_name'] ? htmlspecialchars($case['reporter_name']) : 'Anonymous') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <h3 class="text-lg font-medium mb-2">Description</h3>
                <div class="bg-gray-600 p-3 rounded-lg">
                    <?= $case['description'] ? nl2br(htmlspecialchars($case['description'])) : 'No description provided' ?>
                </div>
            </div>
            
            <?php if ($resolution): ?>
                <div class="mt-4">
                    <h3 class="text-lg font-medium mb-2">Resolution Details</h3>
                    <div class="bg-gray-600 p-3 rounded-lg">
                        <div class="mb-2"><?= nl2br(htmlspecialchars($resolution['resolution'])) ?></div>
                        <div class="text-sm text-gray-400">
                            Resolved by <?= htmlspecialchars($resolution['resolved_by_email']) ?> 
                            on <?= date('M j, Y H:i', strtotime($resolution['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Photo and Reward -->
        <div class="space-y-6">
            <div class="bg-gray-700 p-4 rounded-lg">
                <h3 class="text-lg font-medium mb-3">Photo</h3>
                <?php if ($case['photo_path']): ?>
                    <img src="<?= htmlspecialchars($case['photo_path']) ?>" 
                         alt="Case photo" 
                         class="w-full rounded-lg">
                <?php else: ?>
                    <div class="bg-gray-600 h-48 flex items-center justify-center rounded-lg text-gray-500">
                        <i class="fas fa-user-slash text-5xl"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($reward): ?>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-lg font-medium mb-3">Reward Offer</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Amount:</span>
                            <span class="font-bold">GHS <?= number_format($reward['amount'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Status:</span>
                            <span class="<?= $reward['status'] === 'active' ? 'text-green-400' : 'text-gray-400' ?>">
                                <?= ucfirst($reward['status']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Payment:</span>
                            <span class="<?= $reward['payment_status'] === 'paid' ? 'text-green-400' : 
                                         ($reward['payment_status'] === 'failed' ? 'text-red-400' : 'text-yellow-400') ?>">
                                <?= ucfirst($reward['payment_status']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Offered by:</span>
                            <span><?= htmlspecialchars($reward['offered_by']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Date:</span>
                            <span><?= date('M j, Y', strtotime($reward['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="missing-persons.php?action=rewards&highlight=<?= $reward['reward_id'] ?>" 
                           class="text-blue-400 hover:text-blue-300 text-sm">
                            View reward details
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Back button -->
    <div class="mt-6">
        <a href="missing-persons.php" 
           class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg inline-block">
            Back to Cases
        </a>
    </div>
</div>