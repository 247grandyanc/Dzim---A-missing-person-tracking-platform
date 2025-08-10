<?php
session_start ();
require_once __DIR__ . '/includes/net.php';
require_once __DIR__ . '/includes/functions_missing.php';
require_once __DIR__ . '/includes/functions_rewards.php';


// Redirect logged-in users to dashboard
// if (isset($_COOKIE['jwt'])) {
//     $user = validate_jwt($_COOKIE['jwt']);
//     if ($user) header("Location: search.php");
// }

// Get missing persons for homepage
$missing_persons = get_homepage_missing_persons();
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<title>DZIM-GH | People Search Platform</title>
<?php include __DIR__ . '/includes/navbar.php'; ?>
<style>
    .person-card {
        transition: transform 0.2s, box-shadow 0.2s;
        background: #2d3748;
        border-radius: 8px;
        overflow: hidden;
    }
    .person-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .person-photo {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    .reward-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #f6ad55;
        color: #1a202c;
        font-weight: bold;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
    }
</style>
</head>
<body class="bg-gray-900 text-white">
    <main class="container mx-auto px-4 py-10">
        <!-- Hero Section -->
        <section class="text-center py-20">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                Search <span class="text-blue-400">Anyone</span> in Ghana
            </h1>
            <p class="text-xl mb-10 max-w-2xl mx-auto">
                Find people across social media platforms with advanced biometric matching
            </p>
            <div class="flex justify-center gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Show search button for logged in users -->
                    <a href="templates/search.php" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg font-medium">
                        Search with Dzim
                    </a>
                    <a href="templates/profile.php" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-medium">
                        My Profile
                    </a>
                <?php else: ?>
                    <!-- Show register/login buttons for guests -->
                    <a href="register.php" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg font-medium">
                        Get Started
                    </a>
                    <a href="login.php" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-medium">
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Missing Persons Section -->
        <section class="py-16">
            <h2 class="text-3xl font-bold mb-8">Help Find Missing Persons</h2>
            
            <!-- Basic Missing Person -->
            <?php if ($missing_persons['basic']): ?>
                <div class="mb-12">
                    <h3 class="text-xl font-medium mb-6 text-blue-400">Recently Reported</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="person-card">
                            <div class="relative">
                                <?php if ($missing_persons['basic']['photo_path']): ?>
                                    <img src="<?= htmlspecialchars($missing_persons['basic']['photo_path']) ?>" 
                                         alt="<?= htmlspecialchars($missing_persons['basic']['full_name']) ?>" 
                                         class="person-photo">
                                <?php else: ?>
                                    <div class="person-photo bg-gray-700 flex items-center justify-center">
                                        <svg class="h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-6">
                                <h4 class="text-xl font-bold mb-2"><?= htmlspecialchars($missing_persons['basic']['full_name']) ?></h4>
                                <p class="text-blue-400 mb-3"><?= htmlspecialchars($missing_persons['basic']['home_name']) ?></p>
                                <div class="flex justify-between text-sm text-gray-400 mb-4">
                                    <span><?= $missing_persons['basic']['age'] ? htmlspecialchars($missing_persons['basic']['age']) . ' years' : 'Age unknown' ?></span>
                                    <span><?= htmlspecialchars(ucfirst($missing_persons['basic']['gender'])) ?></span>
                                </div>
                                <a href="missing_view.php?id=<?= $missing_persons['basic']['id'] ?>" class="text-blue-400 hover:text-blue-300 text-sm font-medium">
                                    View details &rarr;
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div>
                                <h4 class="text-lg font-medium mb-4">How you can help</h4>
                                <p class="mb-6">Share this person's information to help bring them home. The more people who see this, the better the chances of finding them.</p>
                                <a href="missing_view.php?id=<?= $missing_persons['basic']['id'] ?>" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">
                                    Share This Case
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Reward Missing Persons -->
            <?php if (!empty($missing_persons['rewards'])): ?>
                <div>
                    <h3 class="text-xl font-medium mb-6 text-blue-400">Reward Offers</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($missing_persons['rewards'] as $person): ?>
                            <div class="person-card">
                                <div class="relative">
                                    <?php if ($person['photo_path']): ?>
                                        <img src="<?= htmlspecialchars($person['photo_path']) ?>" 
                                             alt="<?= htmlspecialchars($person['full_name']) ?>" 
                                             class="person-photo">
                                    <?php else: ?>
                                        <div class="person-photo bg-gray-700 flex items-center justify-center">
                                            <svg class="h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    <div class="reward-badge">
                                        GHC <?= number_format($person['reward_amount'], 2) ?>
                                    </div>
                                </div>
                                <div class="p-6">
                                    <h4 class="text-xl font-bold mb-2"><?= htmlspecialchars($person['full_name']) ?></h4>
                                    <p class="text-blue-400 mb-3"><?= htmlspecialchars($person['home_name']) ?></p>
                                    <div class="flex justify-between text-sm text-gray-400 mb-4">
                                        <span><?= $person['age'] ? htmlspecialchars($person['age']) . ' years' : 'Age unknown' ?></span>
                                        <span><?= htmlspecialchars(ucfirst($person['gender'])) ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <a href="missing_view.php?id=<?= $person['id'] ?>" class="text-blue-400 hover:text-blue-300 text-sm font-medium">
                                            View details &rarr;
                                        </a>
                                        <?php if ($person['claim_count'] > 0): ?>
                                            <span class="text-xs bg-gray-700 text-gray-300 px-2 py-1 rounded">
                                                <?= $person['claim_count'] ?> claim<?= $person['claim_count'] > 1 ? 's' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-8 text-center">
                        <a href="missing_list.php?filter=reward" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-medium inline-block">
                            View All Reward Cases
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Features Grid -->
        <section class="py-16">
            <h2 class="text-3xl font-bold mb-12 text-center">How It Works</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="text-blue-400 text-2xl mb-4">1</div>
                    <h3 class="text-xl font-bold mb-3">Basic Search</h3>
                    <p>Free name/phone lookup across major platforms</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="text-blue-400 text-2xl mb-4">2</div>
                    <h3 class="text-xl font-bold mb-3">Deep Search</h3>
                    <p>Paid facial recognition with confidence scoring</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="text-blue-400 text-2xl mb-4">3</div>
                    <h3 class="text-xl font-bold mb-3">Reward System</h3>
                    <p>Offer rewards to incentivize finding your missing loved ones</p>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>