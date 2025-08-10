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
    $errors = [];
    
    $full_name = clean_input($_POST['full_name']);
    $home_name = clean_input($_POST['home_name']);
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $gender = clean_input($_POST['gender']);
    $last_seen_location = clean_input($_POST['last_seen_location']);
    $description = clean_input($_POST['description']);
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($gender)) {
        $errors[] = "Gender is required";
    }
    
    if (empty($errors)) {
        try {
            $gh->begin_transaction();
            
            $stmt = $gh->prepare("
                UPDATE missing_persons 
                SET full_name = ?, home_name = ?, age = ?, gender = ?, 
                    last_seen_location = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssisssi",
                $full_name,
                $home_name,
                $age,
                $gender,
                $last_seen_location,
                $description,
                $id
            );
            $stmt->execute();
            
            // Handle photo upload if provided
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../../assets/uploads/missing/';
                $file_name = uniqid() . '_' . basename($_FILES['photo']['name']);
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                    // Delete old photo if exists
                    if ($case['photo_path']) {
                        $old_photo = __DIR__ . '/../../../' . ltrim($case['photo_path'], '/');
                        if (file_exists($old_photo)) {
                            unlink($old_photo);
                        }
                    }
                    
                    // Update photo path
                    $photo_path = '/assets/uploads/missing/' . $file_name;
                    $stmt = $gh->prepare("UPDATE missing_persons SET photo_path = ? WHERE id = ?");
                    $stmt->bind_param("si", $photo_path, $id);
                    $stmt->execute();
                }
            }
            
            $gh->commit();
            
            $_SESSION['success_message'] = "Case updated successfully";
            header("Location: missing-persons.php?action=view&id=$id");
            exit();
        } catch (Exception $e) {
            $gh->rollback();
            $errors[] = "Failed to update case: " . $e->getMessage();
        }
    }
}
?>

<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <h2 class="text-xl font-bold mb-6">Edit Case: <?= htmlspecialchars($case['full_name']) ?></h2>
    
    <form method="post" enctype="multipart/form-data">
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-900/30 border border-red-700 text-red-400 px-4 py-3 rounded-lg">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Details -->
            <div class="bg-gray-700 p-4 rounded-lg">
                <h3 class="text-lg font-medium mb-3">Personal Details</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 mb-1">Full Name*</label>
                        <input type="text" name="full_name" required
                               class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                               value="<?= htmlspecialchars($case['full_name']) ?>">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1">Home Name</label>
                        <input type="text" name="home_name"
                               class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                               value="<?= htmlspecialchars($case['home_name']) ?>">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 mb-1">Age</label>
                            <input type="number" name="age" min="1" max="120"
                                   class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                                   value="<?= $case['age'] ? htmlspecialchars($case['age']) : '' ?>">
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-1">Gender*</label>
                            <select name="gender" required
                                    class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2">
                                <option value="male" <?= $case['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $case['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= $case['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Case Details -->
            <div class="bg-gray-700 p-4 rounded-lg">
                <h3 class="text-lg font-medium mb-3">Case Details</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 mb-1">Last Seen Location</label>
                        <input type="text" name="last_seen_location"
                               class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"
                               value="<?= htmlspecialchars($case['last_seen_location']) ?>">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2"><?= htmlspecialchars($case['description']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-1">Photo</label>
                        <div class="mt-1 flex items-center">
                            <label for="photo" class="cursor-pointer bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg">
                                <i class="fas fa-upload mr-2"></i> Choose Image
                                <input id="photo" name="photo" type="file" accept="image/*" class="sr-only">
                            </label>
                            <span id="file_name" class="ml-2 text-sm text-gray-400">
                                <?= $case['photo_path'] ? basename($case['photo_path']) : 'No file chosen' ?>
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">JPEG, PNG (Max 5MB)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-3">
            <a href="missing-persons.php?action=view&id=<?= $id ?>" 
               class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded-lg">
                Cancel
            </a>
            <button type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
    // Show selected file name
    document.getElementById('photo').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        document.getElementById('file_name').textContent = fileName;
    });
</script>