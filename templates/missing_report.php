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
            'type' => 'missing',
            'full_name' => $_POST['full_name'] ?? '',
            'home_name' => $_POST['home_name'] ?? '',
            'age' => $_POST['age'] ?? null,
            'gender' => $_POST['gender'] ?? '',
            'height' => $_POST['height'] ?? '',
            'last_seen_location' => $_POST['last_seen_location'] ?? '',
            'description' => $_POST['description'] ?? '',
            'reporter_name' => $_POST['reporter_name'] ?? '',
            'reporter_contact' => $_POST['reporter_contact'] ?? ''
        ];
        
        $report_id = report_missing_person($data, $photo);
        $success = true;
        
        // Redirect to view page after successful submission
        header("Location: missing_view.php?id=$report_id");
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
    <title>Report Missing Person | DZIM-GH</title>
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
        .webcam-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .webcam-container {
            width: 80%;
            max-width: 500px;
            background: #1a202c;
            border-radius: 8px;
            padding: 1rem;
        }
        #webcam {
            width: 100%;
            border-radius: 4px;
        }
        .webcam-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h1 class="text-2xl font-bold mb-6">Report Missing Person</h1>

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
                                <label for="full_name" class="block text-sm font-medium mb-1">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2">
                            </div>
                            
                            <div>
                                <label for="home_name" class="block text-sm font-medium mb-1">Home Name *</label>
                                <input type="text" id="home_name" name="home_name" required
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="e.g. Kwaku, Sena, Esi">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="age" class="block text-sm font-medium mb-1">Age</label>
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
                                <label for="height" class="block text-sm font-medium mb-1">Height</label>
                                <input type="text" id="height" name="height"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="e.g. 5ft 8in or 173cm">
                            </div>
                            
                            <div>
                                <label for="last_seen_location" class="block text-sm font-medium mb-1">Last Seen Location</label>
                                <input type="text" id="last_seen_location" name="last_seen_location"
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                       placeholder="e.g. Accra Mall, Osu">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium mb-1">Description</label>
                                <textarea id="description" name="description" rows="3"
                                          class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                          placeholder="Physical description, clothing, special marks, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Photo Upload -->
                    <div>
                        <h2 class="text-lg font-medium mb-4 text-blue-400">Photo</h2>
                        <div class="flex flex-col items-start space-y-4">
                            <input type="file" id="photo" name="photo" accept="image/*" class="hidden">
                            
                            <div class="flex items-center space-x-4">
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
                                
                                <button type="button" id="open-webcam" class="text-sm text-blue-400 hover:text-blue-300">
                                    or Take Photo
                                </button>
                            </div>
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
                                * Your contact information will only be shown to verified users when there's a potential match.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Reward Section -->
                    <div class="md:col-span-2">
                        <h2 class="text-lg font-medium mb-4 text-blue-400">Reward Offer (Optional)</h2>
                        <div class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" id="offer_reward" name="offer_reward" value="1" 
                                       class="h-4 w-4 text-blue-600 rounded border-gray-600 bg-gray-700 focus:ring-blue-500">
                                <label for="offer_reward" class="ml-2 text-sm font-medium">
                                    I want to offer a reward for finding this person
                                </label>
                            </div>
                            
                            <div id="reward_fields" class="hidden space-y-4">
                                <div>
                                    <label for="reward_amount" class="block text-sm font-medium mb-1">Reward Amount (GHC) *</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-400">GHC</span>
                                        </div>
                                        <input type="number" id="reward_amount" name="reward_amount" min="50" step="10"
                                               class="block w-full pl-12 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                                               placeholder="100.00">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Minimum reward is GHC 50. Platform fee: 10%.
                                    </p>
                                </div>
                                
                                <div class="p-3 bg-gray-700 rounded-lg">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-400">Reward amount:</span>
                                        <span id="reward-amount-display">GHC 0.00</span>
                                    </div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-400">Platform fee (10%):</span>
                                        <span id="platform-fee-display">GHC 0.00</span>
                                    </div>
                                    <div class="flex justify-between text-sm font-medium">
                                        <span class="text-gray-300">Total to pay:</span>
                                        <span id="total-amount-display" class="text-blue-400">GHC 0.00</span>
                                    </div>
                                </div>
                                
                                <p class="text-xs text-gray-500">
                                    The reward will be paid to whoever provides verifiable information leading to finding this person.
                                    You'll be notified when someone submits a claim, and payment will only be processed after verification.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-gray-700">
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">
                        Submit Report
                    </button>
                </div>
            </form>

            <script>
                // Show/hide reward fields
                const offerRewardCheckbox = document.getElementById('offer_reward');
                const rewardFields = document.getElementById('reward_fields');
                
                offerRewardCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        rewardFields.classList.remove('hidden');
                        document.getElementById('reward_amount').required = true;
                    } else {
                        rewardFields.classList.add('hidden');
                        document.getElementById('reward_amount').required = false;
                    }
                });
                
                // Calculate and display reward amounts
                const rewardAmountInput = document.getElementById('reward_amount');
                const rewardAmountDisplay = document.getElementById('reward-amount-display');
                const platformFeeDisplay = document.getElementById('platform-fee-display');
                const totalAmountDisplay = document.getElementById('total-amount-display');
                
                rewardAmountInput.addEventListener('input', function() {
                    const amount = parseFloat(this.value) || 0;
                    const fee = amount * 0.1;
                    const total = amount + fee;
                    
                    rewardAmountDisplay.textContent = `GHC ${amount.toFixed(2)}`;
                    platformFeeDisplay.textContent = `GHC ${fee.toFixed(2)}`;
                    totalAmountDisplay.textContent = `GHC ${total.toFixed(2)}`;
                });
            </script>
        </div>
    </main>

    <!-- Webcam Modal -->
    <div id="webcam-modal" class="webcam-modal">
        <div class="webcam-container">
            <video id="webcam" autoplay playsinline></video>
            <div class="webcam-actions">
                <button id="capture-btn" class="px-4 py-2 bg-blue-600 rounded-lg">
                    Capture
                </button>
                <button id="cancel-webcam" class="px-4 py-2 bg-gray-600 rounded-lg">
                    Cancel
                </button>
            </div>
        </div>
    </div>

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
        
        // Webcam functionality
        const webcamModal = document.getElementById('webcam-modal');
        const webcam = document.getElementById('webcam');
        const openWebcamBtn = document.getElementById('open-webcam');
        const cancelWebcamBtn = document.getElementById('cancel-webcam');
        const captureBtn = document.getElementById('capture-btn');
        let stream = null;
        
        openWebcamBtn.addEventListener('click', async () => {
            try {
                webcamModal.style.display = 'flex';
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                webcam.srcObject = stream;
            } catch (err) {
                alert('Could not access webcam: ' + err.message);
                webcamModal.style.display = 'none';
            }
        });
        
        cancelWebcamBtn.addEventListener('click', () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            webcamModal.style.display = 'none';
        });
        
        captureBtn.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = webcam.videoWidth;
            canvas.height = webcam.videoHeight;
            canvas.getContext('2d').drawImage(webcam, 0, 0, canvas.width, canvas.height);
            
            canvas.toBlob((blob) => {
                const file = new File([blob], 'webcam-capture.jpg', { type: 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                
                const fileInput = document.getElementById('photo');
                fileInput.files = dataTransfer.files;
                
                // Trigger change event to show preview
                const event = new Event('change');
                fileInput.dispatchEvent(event);
                
                // Close webcam
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                webcamModal.style.display = 'none';
            }, 'image/jpeg', 0.9);
        });
    </script>
</body>
</html>