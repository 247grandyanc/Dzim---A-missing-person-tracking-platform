<?php
session_start(); // Ensure session is started
// In public/search.php
require_once __DIR__ . '/../includes/net.php';  // Go up one level from public to includes
require_once __DIR__ . '/../includes/functions.php';

// Restrict access to logged-in users only
// if (!isset($_COOKIE['jwt'])) {
//     header("Location: login.php");
//     exit();
// }

// $user = validate_jwt($_COOKIE['jwt']);
// if (!$user) {
//     header("Location: login.php");
//     exit();
// }

// At the top of search.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Remove the JWT cookie check


// Handle search requests
$results = [];
$search_type = 'name';
$search_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['photo']['name'])) {
        // Handle photo search
        $search_type = 'photo';
        $photo_path = handle_photo_upload($_FILES['photo']);
        
        if ($photo_path) {
            // Process image search (in production, call your facial recognition service)
            $search_value = 'Image search: ' . basename($photo_path);
            
            // Mock results for demo - replace with actual image search logic
            $stmt = $gh->prepare("
                SELECT p.*, 0.85 as match_score 
                FROM profiles p
                JOIN biometric_vectors b ON p.vector_id = b.vector_id
                ORDER BY RAND() 
                LIMIT 6
            ");
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Clean up uploaded file after processing
            unlink($photo_path);
        }
    } else {
        // Handle text search
        $search_type = 'text';
        $search_value = clean_input($_POST['query'] ?? '');
        
        if (!empty($search_value)) {
            $stmt = $gh->prepare("
                SELECT *, 1.0 as match_score 
                FROM profiles 
                WHERE name LIKE ? OR phone LIKE ?
                ORDER BY name
                LIMIT 20
            ");
            $search_param = "%" . $search_value . "%";
            $stmt->bind_param("ss", $search_param, $search_param);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

function handle_photo_upload($file) {
    $upload_dir = __DIR__ . '/../assets/uploads/';
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        return false;
    }
    
    // Generate unique filename
    $filename = 'search_' . uniqid() . '.' . $ext;
    $destination = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return false;
    }
    
    return $destination;
}
?>

<?php include __DIR__ . '/../includes/header.php';
    // include("includes/header.php"); ?>

<?php include __DIR__ . '/../includes/navbar.php';
    // include("includes/navbar.php"); ?>
<main class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="text-center mb-10">
        <h1 class="text-3xl md:text-4xl font-bold mb-4">Advanced People Search</h1>
        <p class="text-gray-400 max-w-2xl mx-auto">
            Search by name, phone number, or upload a photo for facial recognition matching
        </p>
    </div>

    <!-- Search Box -->
    <div class="bg-gray-800 rounded-xl p-6 mb-8 border border-gray-700 shadow-lg">
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Text Search Tab -->
            <div class="flex-1" id="text-search-tab">
                <form method="POST" class="space-y-4" enctype="multipart/form-data">
                    <div>
                        <label for="search-query" class="block text-sm font-medium text-gray-300 mb-2">
                            Search by Name or Phone
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                id="search-query"
                                name="query" 
                                value="<?= htmlspecialchars($search_type === 'text' ? $search_value : '') ?>"
                                placeholder="Enter name, phone number, or email..." 
                                class="pl-10 w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4 pt-2">
                        <button 
                            type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200 transform hover:scale-[1.02]"
                        >
                            Search
                        </button>
                        <button 
                            type="button" 
                            onclick="switchToImageSearch()"
                            class="text-gray-400 hover:text-white flex items-center text-sm"
                        >
                            <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Search by photo
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Image Search Tab (hidden by default) -->
            <div class="flex-1 hidden" id="image-search-tab">
                <form method="POST" class="space-y-4" enctype="multipart/form-data" id="image-search-form">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Search by Photo
                        </label>
                        
                        <div 
                            id="drop-zone" 
                            class="border-2 border-dashed border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition duration-200"
                            ondragover="event.preventDefault(); document.getElementById('drop-zone').classList.add('border-blue-500', 'bg-gray-700');"
                            ondragleave="document.getElementById('drop-zone').classList.remove('border-blue-500', 'bg-gray-700');"
                            ondrop="handleDrop(event)"
                        >
                            <input 
                                type="file" 
                                id="photo-upload"
                                name="photo" 
                                accept="image/*" 
                                class="hidden"
                                onchange="handleFileSelect(this.files)"
                            >
                            <div id="upload-content">
                                <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-400">
                                    <span class="font-medium text-blue-400">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    JPG, PNG, or WEBP (max. 5MB)
                                </p>
                            </div>
                            <div id="preview-container" class="hidden mt-4">
                                <img id="image-preview" class="mx-auto max-h-48 rounded-lg" src="" alt="Preview">
                                <button 
                                    type="button" 
                                    onclick="clearImage()"
                                    class="mt-2 text-sm text-red-400 hover:text-red-300"
                                >
                                    Remove Image
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4 pt-2">
                        <button 
                            type="submit" 
                            id="image-search-btn"
                            disabled
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200 transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Search by Photo
                        </button>
                        <button 
                            type="button" 
                            onclick="switchToTextSearch()"
                            class="text-gray-400 hover:text-white flex items-center text-sm"
                        >
                            <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Search by text
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Search Results -->
    <?php if (!empty($results)): ?>
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-bold">
                <?php if ($search_type === 'photo'): ?>
                    <span class="flex items-center">
                        <svg class="h-5 w-5 mr-2 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Photo Search Results
                    </span>
                <?php else: ?>
                    Search Results for "<?= htmlspecialchars($search_value) ?>"
                <?php endif; ?>
            </h2>
            <span class="text-sm text-gray-400">
                <?= count($results) ?> matches
            </span>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($results as $result): ?>
                <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:shadow-lg transition duration-200 transform hover:-translate-y-1">
                    <!-- Confidence meter for photo searches -->
                    <?php if ($search_type === 'photo'): ?>
                        <div class="h-1 bg-gray-700">
                            <div 
                                class="h-full <?= $result['match_score'] > 0.8 ? 'bg-green-500' : ($result['match_score'] > 0.5 ? 'bg-yellow-500' : 'bg-red-500') ?>" 
                                style="width: <?= $result['match_score'] * 100 ?>%"
                            ></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xl">
                                    <?= strtoupper(substr($result['name'], 0, 1)) ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold truncate"><?= htmlspecialchars($result['name']) ?></h3>
                                <p class="text-sm text-gray-400 truncate">
                                    <?= !empty($result['phone']) ? decrypt_data($result['phone'], ENCRYPTION_KEY) : 'Phone not available' ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($search_type === 'photo'): ?>
                            <div class="mt-4 flex items-center">
                                <span class="text-xs font-medium mr-2">Match Confidence:</span>
                                <span class="text-sm font-bold <?= $result['match_score'] > 0.8 ? 'text-green-400' : ($result['match_score'] > 0.5 ? 'text-yellow-400' : 'text-red-400') ?>">
                                    <?= round($result['match_score'] * 100) ?>%
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-6 flex space-x-2">
                            <a 
                                href="#" 
                                class="flex-1 text-center bg-gray-700 hover:bg-gray-600 text-sm font-medium py-2 px-3 rounded-lg transition duration-200"
                            >
                                View Profile
                            </a>
                            <a 
                                href="#" 
                                class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-sm font-medium py-2 px-3 rounded-lg transition duration-200"
                            >
                                Contact
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="bg-gray-800 rounded-xl p-8 text-center border border-gray-700">
            <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium">No matches found</h3>
            <p class="mt-2 text-gray-400">Try adjusting your search criteria</p>
        </div>
    <?php else: ?>
        <!-- Tips for better searching -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-bold mb-4">Search Tips</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-medium">Name Searches</h4>
                        <p class="text-sm text-gray-400">Use full names when possible. Try variations if you don't get results.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-medium">Phone Numbers</h4>
                        <p class="text-sm text-gray-400">Include country code (e.g., +233) for best results with phone searches.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-medium">Photo Searches</h4>
                        <p class="text-sm text-gray-400">Use clear, front-facing photos for the most accurate facial recognition matches.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-medium">Deep Search</h4>
                        <p class="text-sm text-gray-400">Upgrade for more comprehensive results and advanced matching.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
    // Toggle between text and image search
    function switchToImageSearch() {
        document.getElementById('text-search-tab').classList.add('hidden');
        document.getElementById('image-search-tab').classList.remove('hidden');
    }
    
    function switchToTextSearch() {
        document.getElementById('image-search-tab').classList.add('hidden');
        document.getElementById('text-search-tab').classList.remove('hidden');
    }
    
    // Handle drag and drop for image upload
    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('drop-zone').classList.remove('border-blue-500', 'bg-gray-700');
        
        if (e.dataTransfer.files.length) {
            handleFileSelect(e.dataTransfer.files);
        }
    }
    
    // Handle file selection
    function handleFileSelect(files) {
        const file = files[0];
        if (!file.type.match('image.*')) {
            alert('Please select an image file (JPG, PNG, WEBP)');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('upload-content').classList.add('hidden');
            document.getElementById('preview-container').classList.remove('hidden');
            document.getElementById('image-preview').src = e.target.result;
            document.getElementById('image-search-btn').disabled = false;
        };
        reader.readAsDataURL(file);
    }
    
    // Clear selected image
    function clearImage() {
        document.getElementById('photo-upload').value = '';
        document.getElementById('upload-content').classList.remove('hidden');
        document.getElementById('preview-container').classList.add('hidden');
        document.getElementById('image-search-btn').disabled = true;
    }
    
    // Click on drop zone triggers file input
    document.getElementById('drop-zone').addEventListener('click', function() {
        document.getElementById('photo-upload').click();
    });
</script>