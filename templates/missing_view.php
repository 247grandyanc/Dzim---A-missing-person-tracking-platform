<?php
require_once __DIR__ . '/../includes/net.php';
// require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_missing.php';

$id = $_GET['id'] ?? 0;
$person = get_missing_person_details($id);

if (!$person || $person['type'] !== 'missing' || $person['status'] !== 'active') {
    header("Location: missing_list.php");
    exit();
}

// Log view
log_missing_person_search('missing', "view:$id");

// Get ads
$ads = fetch_ads('MISSING_DETAIL', get_user_country($_SESSION['user_id'] ?? null));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title><?= htmlspecialchars($person['full_name']) ?> | DZIM-GH</title>
    <style>
        .match-card {
            transition: transform 0.2s;
            background: #2d3748;
            border-radius: 8px;
            overflow: hidden;
        }
        .match-card:hover {
            transform: translateY(-4px);
        }
        .match-photo {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .confidence-meter {
            height: 6px;
            background: #2d3748;
            border-radius: 3px;
            overflow: hidden;
        }
        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #f56565, #f6ad55);
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../templates/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="flex items-center mb-6">
            <a href="missing_list.php" class="text-blue-400 hover:text-blue-300 flex items-center">
                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to list
            </a>
        </div>
        
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="md:col-span-1">
                    <?php if ($person['photo_path']): ?>
                        <img src="<?= htmlspecialchars($person['photo_path']) ?>" alt="<?= htmlspecialchars($person['full_name']) ?>" class="w-full rounded-lg">
                    <?php else: ?>
                        <div class="bg-gray-700 rounded-lg flex items-center justify-center" style="height: 300px;">
                            <svg class="h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="md:col-span-2">
                    <h1 class="text-2xl md:text-3xl font-bold mb-2"><?= htmlspecialchars($person['full_name']) ?></h1>
                    <h2 class="text-xl text-blue-400 mb-4"><?= htmlspecialchars($person['home_name']) ?></h2>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-400">Age</p>
                            <p class="font-medium"><?= $person['age'] ? htmlspecialchars($person['age']) . ' years' : 'Unknown' ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Gender</p>
                            <p class="font-medium"><?= htmlspecialchars(ucfirst($person['gender'])) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Height</p>
                            <p class="font-medium"><?= $person['height'] ? htmlspecialchars($person['height']) : 'Unknown' ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Missing Since</p>
                            <p class="font-medium"><?= date('M j, Y', strtotime($person['created_at'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-2">Last Seen Location</h3>
                        <p><?= $person['last_seen_location'] ? htmlspecialchars($person['last_seen_location']) : 'Location unknown' ?></p>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-2">Description</h3>
                        <p class="whitespace-pre-line"><?= htmlspecialchars($person['description']) ?></p>
                    </div>
                    
                    <?php if (!empty($person['matches'])): ?>
                        <div class="pt-4 border-t border-gray-700">
                            <h3 class="text-lg font-medium mb-4">Potential Matches</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($person['matches'] as $match): ?>
                                    <a href="found_view.php?id=<?= $match['id'] ?>" class="match-card p-4">
                                        <div class="flex items-start space-x-4">
                                            <?php if ($match['photo_path']): ?>
                                                <img src="<?= htmlspecialchars($match['photo_path']) ?>" alt="<?= htmlspecialchars($match['full_name']) ?>" class="match-photo rounded-lg w-20 flex-shrink-0">
                                            <?php else: ?>
                                                <div class="bg-gray-700 rounded-lg w-20 h-20 flex-shrink-0 flex items-center justify-center">
                                                    <svg class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h4 class="font-medium"><?= htmlspecialchars($match['full_name']) ?></h4>
                                                <p class="text-sm text-gray-400">Found <?= date('M j, Y', strtotime($match['created_at'])) ?></p>
                                                <div class="mt-2">
                                                    <div class="text-xs text-gray-400 mb-1">
                                                        Match confidence: <?= round($match['confidence_score'] * 100) ?>%
                                                    </div>
                                                    <div class="confidence-meter">
                                                        <div class="confidence-fill" style="width: <?= $match['confidence_score'] * 100 ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($ads)): ?>
            <div class="bg-gray-800 rounded-xl p-4 mb-8">
                <div class="text-xs text-gray-400 mb-2 flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                    </svg>
                    Sponsored
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($ads as $ad): ?>
                        <a href="<?= htmlspecialchars($ad['url']) ?>" target="_blank" rel="noopener" class="block">
                            <img src="<?= htmlspecialchars($ad['image']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="w-full h-32 object-cover rounded-lg">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Reporter contact info (only shown to logged in users) -->
        <?php if ($_SESSION['user_id'] && $person['reporter_contact']): ?>
            <div class="bg-gray-800 rounded-xl p-6 border border-blue-700">
                <h3 class="text-lg font-medium mb-4 text-blue-400">Contact Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-400">Reported by</p>
                        <p class="font-medium"><?= htmlspecialchars($person['reporter_name']) ?></p>
                        <?php if ($person['reporter_email']): ?>
                            <p class="text-sm text-gray-400 mt-1">Email</p>
                            <p class="font-medium"><?= htmlspecialchars($person['reporter_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Contact info</p>
                        <p class="font-medium"><?= htmlspecialchars($person['reporter_contact']) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>