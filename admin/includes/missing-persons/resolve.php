<?php
// Verify we have an ID
if (empty($id)) {
    header("Location: missing-persons.php");
    exit();
}

// Get case details
$stmt = $gh->prepare("SELECT * FROM missing_persons WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$case = $stmt->get_result()->fetch_assoc();

if (!$case) {
    $_SESSION['error_message'] = "Case not found";
    header("Location: missing-persons.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resolution = clean_input($_POST['resolution']);
    $status = 'resolved';
    
    try {
        $gh->begin_transaction();
        
        // Update case status
        $stmt = $gh->prepare("UPDATE missing_persons SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        
        // Create resolution record
        $stmt = $gh->prepare("
            INSERT INTO case_resolutions 
            (case_id, resolved_by, resolution, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $id, $admin['admin_id'], $resolution);
        $stmt->execute();
        
        $gh->commit();
        
        $_SESSION['success_message'] = "Case marked as resolved successfully";
        header("Location: missing-persons.php?action=view&id=$id");
        exit();
    } catch (Exception $e) {
        $gh->rollback();
        $errors[] = "Failed to resolve case: " . $e->getMessage();
    }
}
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <h2 class="text-xl font-bold mb-6">Resolve Case: <?= htmlspecialchars($case['full_name']) ?></h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Case Summary -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-medium mb-3">Case Summary</h3>
            <div class="space-y-2">
                <div class="flex">
                    <span class="text-gray-400 w-32">Name:</span>
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
                    <span class="text-gray-400 w-32">Last Seen:</span>
                    <span><?= htmlspecialchars($case['last_seen_location'] ?? 'Unknown') ?></span>
                </div>
                <div class="flex">
                    <span class="text-gray-400 w-32">Reported:</span>
                    <span><?= date('M j, Y', strtotime($case['created_at'])) ?></span>
                </div>
            </div>
        </div>
        
        <!-- Photo -->
        <div class="bg-gray-700 p-4 rounded-lg flex items-center justify-center">
            <?php if ($case['photo_path']): ?>
                <img src="<?= htmlspecialchars($case['photo_path']) ?>" 
                     alt="Case photo" 
                     class="max-h-64 rounded-lg">
            <?php else: ?>
                <div class="text-gray-500">
                    <i class="fas fa-user-slash text-5xl"></i>
                    <p>No photo available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Resolution Form -->
    <form method="post" class="bg-gray-700 p-4 rounded-lg">
        <?php if (!empty($errors)): ?>
            <div class="mb-4 bg-red-900/30 border border-red-700 text-red-400 px-4 py-3 rounded-lg">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="mb-4">
            <label class="block text-gray-400 mb-2">Resolution Details*</label>
            <textarea name="resolution" required rows="5"
                      class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                      placeholder="Describe how this case was resolved..."></textarea>
        </div>
        
        <div class="flex justify-end space-x-3">
            <a href="missing-persons.php?action=view&id=<?= $id ?>" 
               class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg">
                Cancel
            </a>
            <button type="submit" 
                    class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                Mark as Resolved
            </button>
        </div>
    </form>
</div>