<?php
require_once __DIR__ . '/includes/net.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Authenticate user
$user_id = authenticate();

// Get search ID from URL
$search_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch search details
$stmt = $gh->prepare("SELECT sh.*, u.email 
                       FROM search_history sh
                       JOIN users u ON sh.user_id = u.user_id
                       WHERE sh.search_id = ? AND sh.user_id = ?");
$stmt->bind_param("ii", $search_id, $user_id);
$stmt->execute();
$search = $stmt->get_result()->fetch_assoc();

if (!$search) {
    header("Location: search.php");
    exit();
}

// Fetch results
$stmt = $gh->prepare("SELECT * FROM search_results WHERE search_id = ?");
$stmt->bind_param("i", $search_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


// Fetch ads for sidebar
$sidebar_ads = fetch_ads('SIDEBAR', get_user_country($user_id));

// Fetch inline ads if needed
$inline_ads = fetch_ads('INLINE_RESULTS', get_user_country($user_id));

?>


    <?php include __DIR__ . '/includes/header.php'; ?>
    <title>Search Results | DZIM-GH</title>
    <style>
        .result-card {
            transition: all 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .platform-icon {
            width: 24px;
            height: 24px;
            filter: grayscale(100%) brightness(0.5);
            transition: all 0.3s ease;
        }
        .result-card:hover .platform-icon {
            filter: grayscale(0%) brightness(1);
        }
        .confidence-meter {
            height: 6px;
            background: linear-gradient(90deg, #ef4444, #f59e0b, #10b981);
        }
        .ad-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-left: 4px solid #3b82f6;
        }
        .sponsored-tag {
            background: linear-gradient(90deg, #f59e0b, #ef4444);
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <!-- Search Summary -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h1 class="text-2xl font-bold mb-2">Search Results</h1>
                    <div class="flex items-center space-x-4 text-sm text-gray-400">
                        <span>Search ID: #<?= $search_id ?></span>
                        <span>•</span>
                        <span><?= date('M j, Y g:i A', strtotime($search['created_at'])) ?></span>
                        <span>•</span>
                        <span><?= count($results) ?> matches</span>
                    </div>
                </div>
                <div class="mt-4 md:mt-0">
                    <?php if ($search['is_deep_search']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-900 text-blue-300 text-sm font-medium">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Deep Search
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-700 text-gray-300 text-sm font-medium">
                            Basic Search
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Main Results Column -->
            <div class="lg:w-2/3">
                <?php if (empty($results)): ?>
                    <!-- No Results -->
                    <div class="bg-gray-800 rounded-lg p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium">No matches found</h3>
                        <p class="mt-2 text-gray-400">Try refining your search criteria</p>
                        <a href="search.php" class="mt-6 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            New Search
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Results Grid -->
                    <div class="space-y-6">
                        <?php foreach ($results as $result): ?>
                            <div class="result-card bg-gray-800 rounded-lg overflow-hidden border border-gray-700">
                                <!-- Confidence Meter (for deep search) -->
                                <?php if ($search['is_deep_search'] && isset($result['match_score'])): ?>
                                    <div class="confidence-meter">
                                        <div class="h-full" style="width: <?= $result['match_score'] * 100 ?>%"></div>
                                    </div>
                                <?php endif; ?>

                                <div class="p-6">
                                    <div class="flex items-start">
                                        <!-- Platform Icon -->
                                        <div class="flex-shrink-0 mr-4">
                                            <?php $platform = strtolower($result['platform']); ?>
                                            <img src="/assets/platforms/<?= $platform ?>.svg" alt="<?= $result['platform'] ?>" class="platform-icon">
                                        </div>

                                        <!-- Result Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start">
                                                <h3 class="text-lg font-bold truncate">
                                                    <a href="<?= htmlspecialchars($result['profile_url']) ?>" target="_blank" class="hover:text-blue-400">
                                                        <?= htmlspecialchars($result['name'] ?? 'Unknown') ?>
                                                    </a>
                                                </h3>
                                                <?php if ($result['is_verified']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                                        Verified
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-1 text-sm text-gray-400">
                                                <?php if ($result['phone']): ?>
                                                    <span class="inline-flex items-center mr-4">
                                                        <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                                        </svg>
                                                        <?= htmlspecialchars($result['phone']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <span class="inline-flex items-center">
                                                    <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707L6 18.414V5.586L3.707 3.293zM17.707 5.293L14 1.586v12.828l2.293 2.293A1 1 0 0018 16V6a1 1 0 00-.293-.707z" clip-rule="evenodd" />
                                                    </svg>
                                                    <?= $result['platform'] ?>
                                                </span>
                                            </div>

                                            <?php if ($search['is_deep_search']): ?>
                                                <div class="mt-3 flex items-center text-sm">
                                                    <?php if (isset($result['match_score'])): ?>
                                                        <span class="text-gray-400 mr-2">Confidence:</span>
                                                        <span class="font-medium <?= 
                                                            $result['match_score'] > 0.8 ? 'text-green-400' : 
                                                            ($result['match_score'] > 0.5 ? 'text-yellow-400' : 'text-red-400') 
                                                        ?>">
                                                            <?= number_format($result['match_score'] * 100, 1) ?>%
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($result['flagged']): ?>
                                                        <span class="ml-4 inline-flex items-center text-red-400">
                                                            <svg class="mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd" />
                                                            </svg>
                                                            Flagged
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="mt-4 flex space-x-3">
                                        <a href="<?= htmlspecialchars($result['profile_url']) ?>" target="_blank" class="inline-flex items-center px-3 py-1.5 border border-gray-600 rounded text-sm font-medium hover:bg-gray-700">
                                            View Profile
                                        </a>
                                        <button type="button" class="inline-flex items-center px-3 py-1.5 border border-gray-600 rounded text-sm font-medium hover:bg-gray-700">
                                            Save Result
                                        </button>
                                        <?php if ($search['is_deep_search']): ?>
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 border border-gray-600 rounded text-sm font-medium hover:bg-gray-700">
                                                View Full Report
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <div class="mt-8 flex justify-between items-center">
                    <button disabled class="inline-flex items-center px-4 py-2 border border-gray-700 rounded-md text-sm font-medium text-gray-400 bg-gray-800 cursor-not-allowed">
                        Previous
                    </button>
                    <span class="text-sm text-gray-400">Showing 1-<?= count($results) ?> of <?= count($results) ?></span>
                    <button disabled class="inline-flex items-center px-4 py-2 border border-gray-700 rounded-md text-sm font-medium text-gray-400 bg-gray-800 cursor-not-allowed">
                        Next
                    </button>
                </div>
            </div>

            <!-- Sidebar with Ads -->
            <div class="lg:w-1/3 space-y-6">
                <!-- Search Tips -->
                <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                    <h3 class="text-lg font-bold mb-3">Search Tips</h3>
                    <ul class="space-y-3 text-sm text-gray-300">
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                            </svg>
                            <span>For phone numbers, include country code (e.g. +233)</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                            </svg>
                            <span>Use high-quality, front-facing photos for best matches</span>
                        </li>
                        <?php if (!$search['is_deep_search']): ?>
                            <li class="flex items-start">
                                <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                                </svg>
                                <span>Upgrade to Deep Search for more comprehensive results</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- In your HTML where you display ads -->
         
                <!-- Display Sidebar Ads -->
                <?php foreach ($sidebar_ads as $ad): ?>
                    <div class="ad-card rounded-lg overflow-hidden border border-gray-700">
                        <?php if ($ad['sponsored']): ?>
                            <div class="sponsored-tag px-3 py-1 text-xs font-bold text-white text-center uppercase tracking-wider">
                                Sponsored
                            </div>
                        <?php endif; ?>
                        <div class="p-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-4">
                                    <img class="h-16 w-16 rounded object-cover" src="<?= htmlspecialchars($ad['image']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>">
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold"><?= htmlspecialchars($ad['title']) ?></h3>
                                    <p class="mt-1 text-sm text-gray-400">By <?= htmlspecialchars($ad['advertiser_name']) ?></p>
                                    <a href="<?= htmlspecialchars($ad['url']) ?>" 
                                       onclick="trackClick(<?= $ad['campaign_id'] ?>)" 
                                       class="mt-3 inline-block px-4 py-2 bg-blue-600 rounded text-sm font-medium hover:bg-blue-700">
                                        Learn More
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

                <!-- Upgrade CTA (for basic searches) -->
                <?php if (!$search['is_deep_search']): ?>
                    <div class="bg-gradient-to-r from-blue-900 to-purple-900 rounded-lg p-6 border border-blue-700">
                        <h3 class="text-xl font-bold mb-2">Get Better Results</h3>
                        <p class="text-sm text-blue-200 mb-4">Upgrade to Deep Search with facial recognition technology</p>
                        <ul class="space-y-2 text-sm text-blue-100 mb-6">
                            <li class="flex items-center">
                                <svg class="h-4 w-4 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Biometric face matching
                            </li>
                            <li class="flex items-center">
                                <svg class="h-4 w-4 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Higher confidence scores
                            </li>
                            <li class="flex items-center">
                                <svg class="h-4 w-4 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                More comprehensive profiles
                            </li>
                        </ul>
                        <a href="/subscribe.php" class="block w-full text-center px-4 py-3 bg-white text-blue-900 rounded-lg font-bold hover:bg-gray-100 transition duration-200">
                            Upgrade Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- JavaScript for click tracking -->
    <script>
    function trackClick(campaignId) {
        // Send async request to track the click
        fetch('/api/track_ad_click.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ campaign_id: campaignId })
        });
    }
    </script>
</body>
</html>