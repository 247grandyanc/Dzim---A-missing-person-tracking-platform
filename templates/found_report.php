<?php
require_once __DIR__ . '/../includes/net.php';
// require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_missing.php';

$user_id = $_SESSION['user_id'] ?? null;
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $photo = $_FILES['photo'] ?? null;
        
        $data = [
            'type' => 'found',
            'full_name' => $_POST['full_name'] ?? '',
            'home_name' => $_POST['home_name'] ?? '',
            'age' => $_POST['age'] ?? null,
            'gender' => $_POST['gender'] ?? '',
            'height' => $_POST['height'] ?? '',
            'last_seen_location' => $_POST['found_location'] ?? '',
            'description' => $_POST['description'] ?? '',
            'reporter_name' => $_POST['reporter_name'] ?? '',
            'reporter_contact' => $_POST['reporter_contact'] ?? ''
        ];
        
        $report_id = report_missing_person($data, $photo);
        $success = true;
        
        // Redirect to view page after successful submission
        header("Location: found_view.php?id=$report_id");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>Report Found Person | DZIM-GH</title>
    <style>
        .photo-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            display: none;
        }
        .camera-btn {
            background: #2d3748;
            border: 2px dashed #4a5568;
            border-radius: 8px;
            width: 150px;
            height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .camera-btn:hover {
            border-color: #4299e1;
            background: #2d3748;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h1 class="text-2xl font-bold mb-6">Report Found Person</h1>
            
            <?php if ($error): ?>
                <div class="bg-red-900 text-red-300 rounded-lg p-4 mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Person Details -->
                    <div class="md:col-span-2">
                        <h2 class="text-lg font-medium mb-4 text-blue-400">Person Details</h2>
                        <div class="space-y-4">
                            <div>
                                <label for="full_name" class="block text-sm font-medium mb-1">Full Name (if known)</label>
                                <input type="text" id="full_name" name="full_name"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                            </div>
                            
                            <div>
                                <label for="home_name" class="block text-sm font-medium mb-1">Home Name (if known)</label>
                                <input type="text" id="home_name" name="home_name"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="e.g. Kwaku, Sena, Esi">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="age" class="block text-sm font-medium mb-1">Approximate Age</label>
                                    <input type="number" id="age" name="age" min="1" max="120"
                                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                </div>
                                
                                <div>
                                    <label for="gender" class="block text-sm font-medium mb-1">Gender *</label>
                                    <select id="gender" name="gender" required
                                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label for="height" class="block text-sm font-medium mb-1">Approximate Height</label>
                                <input type="text" id="height" name="height"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="e.g. 5ft 8in or 173cm">
                            </div>
                            
                            <div>
                                <label for="found_location" class="block text-sm font-medium mb-1">Where Found *</label>
                                <input type="text" id="found_location" name="found_location" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="Location where person was found">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium mb-1">Description *</label>
                                <textarea id="description" name="description" rows="3" required
                                          class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                          placeholder="Physical description, clothing, special marks, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Photo Upload -->
                    <div>
                        <h2 class="text-lg font-medium mb-4 text-blue-400">Photo *</h2>
                        <div class="flex flex-col items-start space-y-4">
                            <input type="file" id="photo" name="photo" accept="image/*" required class="hidden">
                            
                            <div>
                                <img id="photo-preview" class="photo-preview">
                                <label for="photo" class="camera-btn">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-sm mt-2 text-gray-400">Upload Photo</span>
                                </label>
                            </div>
                            
                            <p class="text-xs text-gray-500">
                                * A clear photo helps us match with missing person reports
                            </p>
                        </div>
                    </div>
                    
                    <!-- Reporter Info -->
                    <div>
                        <h2 class="text-lg font-medium mb-4 text-blue-400">Your Information</h2>
                        <div class="space-y-4">
                            <?php if ($user_id): ?>
                                <p class="text-sm text-gray-400">
                                    You're logged in as <?= htmlspecialchars($_SESSION['user_email']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div>
                                <label for="reporter_name" class="block text-sm font-medium mb-1">Your Name *</label>
                                <input type="text" id="reporter_name" name="reporter_name" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       value="<?= $user_id ? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : '' ?>">
                            </div>
                            
                            <div>
                                <label for="reporter_contact" class="block text-sm font-medium mb-1">Contact Info *</label>
                                <input type="text" id="reporter_contact" name="reporter_contact" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="Phone number or email"
                                       value="<?= $user_id ? $_SESSION['user_email'] : '' ?>">
                            </div>
                            
                            <p class="text-xs text-gray-500">
                                * Your contact information will only be shared when there's a potential match.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-gray-700">
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">
                        Submit Report
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script>
        // Handle photo upload preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('photo-preview');
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                    document.querySelector('.camera-btn').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>