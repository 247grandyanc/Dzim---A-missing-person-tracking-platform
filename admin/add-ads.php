<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Set page variables
$title = "Create Ad Campaign";
$page_title = "Create New Ad Campaign";
$breadcrumbs = [
    ['text' => 'Dashboard', 'url' => 'dashboard.php'],
    ['text' => 'Ad Management', 'url' => 'view-ads.php'],
    ['text' => 'Create Ad', 'url' => 'add-ad.php']
];

require_once __DIR__ . '/includes/admin-nav.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate inputs
    $advertiser_name = clean_input($_POST['advertiser_name']);
    $target_country = clean_input($_POST['target_country']);
    $destination_url = clean_input($_POST['destination_url']);
    $budget = (float)$_POST['budget'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $placement_types = $_POST['placement_types'] ?? [];
    
    if (empty($advertiser_name)) {
        $errors[] = "Advertiser name is required";
    }
    
    if (empty($destination_url)) {
        $errors[] = "Destination URL is required";
    } elseif (!filter_var($destination_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Invalid destination URL";
    }
    
    if ($budget <= 0) {
        $errors[] = "Budget must be greater than 0";
    }
    
    if (empty($start_date) || empty($end_date)) {
        $errors[] = "Start and end dates are required";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "End date must be after start date";
    }
    
    if (empty($placement_types)) {
        $errors[] = "At least one placement type is required";
    }
    
    // Handle file upload
    $image_url = '';
    if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['ad_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } else {
            $upload_dir = __DIR__ . '/../assets/uploads/ads/';
            $file_name = uniqid() . '_' . basename($_FILES['ad_image']['name']);
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $target_path)) {
                $image_url = '/assets/uploads/ads/' . $file_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    } else {
        $errors[] = "Ad image is required";
    }
    
    if (empty($errors)) {
        // Start transaction
        $gh->begin_transaction();
        
        try {
            // Insert campaign
            $stmt = $gh->prepare("
                INSERT INTO ad_campaigns 
                (advertiser_name, target_country, image_url, destination_url, budget, clicks_remaining, start_date, end_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $clicks_remaining = (int)($budget * 100); // Assuming $1 per 100 clicks
            $stmt->bind_param(
                "ssssdiss",
                $advertiser_name,
                $target_country,
                $image_url,
                $destination_url,
                $budget,
                $clicks_remaining,
                $start_date,
                $end_date
            );
            $stmt->execute();
            $campaign_id = $gh->insert_id;
            
            // Insert placements
            foreach ($placement_types as $placement_type) {
                $stmt = $gh->prepare("
                    INSERT INTO ad_placements 
                    (campaign_id, placement_type, weight)
                    VALUES (?, ?, 100)
                ");
                $stmt->bind_param("is", $campaign_id, $placement_type);
                $stmt->execute();
            }
            
            // Commit transaction
            $gh->commit();
            
            // Log action
            log_admin_action($admin['admin_id'], "CREATE_AD", $campaign_id);
            
            $_SESSION['success_message'] = "Ad campaign created successfully!";
            header("Location: view-ads.php");
            exit();
        } catch (Exception $e) {
            $gh->rollback();
            $errors[] = "Failed to create ad campaign: " . $e->getMessage();
        }
    }
}
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <form method="post" enctype="multipart/form-data">
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-900/30 border border-red-700 text-red-400 px-4 py-3 rounded-lg">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Advertiser Info -->
            <div class="md:col-span-2">
                <h3 class="text-lg font-medium mb-4">Advertiser Information</h3>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Advertiser Name*</label>
                        <input type="text" name="advertiser_name" required 
                               class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                               value="<?= htmlspecialchars($_POST['advertiser_name'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Target Country</label>
                        <select name="target_country" class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2">
                            <option value="">Global</option>
                            <option value="GH" <?= ($_POST['target_country'] ?? '') === 'GH' ? 'selected' : '' ?>>Ghana</option>
                            <option value="US" <?= ($_POST['target_country'] ?? '') === 'US' ? 'selected' : '' ?>>United States</option>
                            <option value="UK" <?= ($_POST['target_country'] ?? '') === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Ad Content -->
            <div>
                <h3 class="text-lg font-medium mb-4">Ad Content</h3>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Ad Image*</label>
                        <div class="mt-1 flex items-center">
                            <label for="ad_image" class="cursor-pointer bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg">
                                <i class="fas fa-upload mr-2"></i> Choose Image
                                <input id="ad_image" name="ad_image" type="file" accept="image/*" class="sr-only" required>
                            </label>
                            <span id="file_name" class="ml-2 text-sm text-gray-400">No file chosen</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">JPEG, PNG or GIF (Max 2MB)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Destination URL*</label>
                        <input type="url" name="destination_url" required 
                               class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                               value="<?= htmlspecialchars($_POST['destination_url'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <!-- Campaign Details -->
            <div>
                <h3 class="text-lg font-medium mb-4">Campaign Details</h3>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Budget (GHS)*</label>
                            <input type="number" name="budget" min="1" step="0.01" required 
                                   class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                                   value="<?= htmlspecialchars($_POST['budget'] ?? '10') ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Clicks</label>
                            <input type="text" readonly 
                                   class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                                   value="<?= isset($_POST['budget']) ? (int)($_POST['budget'] * 100) : '1000' ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Start Date*</label>
                            <input type="date" name="start_date" required 
                                   class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                                   value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">End Date*</label>
                            <input type="date" name="end_date" required 
                                   class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                                   value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 month'))) ?>">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Placement Types*</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="placement_sidebar" name="placement_types[]" value="SIDEBAR" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-500 rounded" 
                                       <?= in_array('SIDEBAR', $_POST['placement_types'] ?? ['SIDEBAR']) ? 'checked' : '' ?>>
                                <label for="placement_sidebar" class="ml-2 text-sm text-gray-300">Sidebar</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="placement_inline" name="placement_types[]" value="INLINE_RESULTS" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-500 rounded"
                                       <?= in_array('INLINE_RESULTS', $_POST['placement_types'] ?? ['INLINE_RESULTS']) ? 'checked' : '' ?>>
                                <label for="placement_inline" class="ml-2 text-sm text-gray-300">Inline Results</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="placement_footer" name="placement_types[]" value="FOOTER" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-500 rounded"
                                       <?= in_array('FOOTER', $_POST['placement_types'] ?? []) ? 'checked' : '' ?>>
                                <label for="placement_footer" class="ml-2 text-sm text-gray-300">Footer</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <a href="view-ads.php" class="mr-3 bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                Create Ad Campaign
            </button>
        </div>
    </form>
</div>

<script>
    // Show selected file name
    document.getElementById('ad_image').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        document.getElementById('file_name').textContent = fileName;
    });
    
    // Calculate clicks based on budget
    document.querySelector('input[name="budget"]').addEventListener('input', function(e) {
        const budget = parseFloat(e.target.value) || 0;
        const clicks = Math.floor(budget * 100);
        document.querySelector('input[name="budget"]').nextElementSibling.nextElementSibling.value = clicks;
    });
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>