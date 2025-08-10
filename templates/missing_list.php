<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_missing.php';
require_once __DIR__ . '/../includes/functions_rewards.php';

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Filters
$search = $_GET['search'] ?? null;
$filter = $_GET['filter'] ?? 'all'; // all, reward, basic

// Log search if query exists
if ($search) {
    log_missing_person_search('missing', $search);
}

// Get missing persons based on filter
$where = "type = 'missing' AND status = 'active'";
$params = [];
$param_types = "";

if ($search) {
    $where .= " AND MATCH(full_name, home_name, description) AGAINST(? IN BOOLEAN MODE)";
    $params[] = $search;
    $param_types .= "s";
}

if ($filter === 'reward') {
    $where .= " AND has_reward = 1";
} elseif ($filter === 'basic') {
    $where .= " AND has_reward = 0";
}

// Get missing persons
$missing_persons = get_missing_persons_filtered($where, $params, $param_types, $per_page, $offset);
$total_count = get_missing_persons_count_filtered($where, $params, $param_types);
$total_pages = ceil($total_count / $per_page);

// Get ads
$ads = fetch_ads('MISSING_LIST', get_user_country($_SESSION['user_id'] ?? null));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>Missing Persons | DZIM-GH</title>
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
        .pagination-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .pagination-btn.active {
            background: #4299e1;
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
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .filter-btn.active {
            background: #4299e1;
            color: white;
        }
        .filter-btn:not(.active) {
            background: #2d3748;
            color: #a0aec0;
        }
        .filter-btn:not(.active):hover {
            background: #4a5568;
        }
        .urgency-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: #f56565;
            color: white;
            font-weight: bold;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <h1 class="text-2xl md:text-3xl font-bold mb-4 md:mb-0">Missing Persons</h1>
            
            <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 w-full md:w-auto">
                <form method="GET" class="flex-1 md:w-64">
                    <div class="relative">
                        <input type="text" name="search" placeholder="Search by name..." 
                               value="<?= htmlspecialchars($search ?? '') ?>"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 pl-10">
                        <svg class="h-5 w-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    </div>
                </form>
                
                <a href="missing_report.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium text-center">
                    Report Missing
                </a>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="flex space-x-2 mb-6">
            <a href="?filter=all<?= $search ? '&search='.urlencode($search) : '' ?>" 
               class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
               All Cases
            </a>
            <a href="?filter=reward<?= $search ? '&search='.urlencode($search) : '' ?>" 
               class="filter-btn <?= $filter === 'reward' ? 'active' : '' ?>">
               Reward Offers
            </a>
            <a href="?filter=basic<?= $search ? '&search='.urlencode($search) : '' ?>" 
               class="filter-btn <?= $filter === 'basic' ? 'active' : '' ?>">
               Basic Cases
            </a>
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
        
        <?php if (empty($missing_persons)): ?>
            <div class="bg-gray-800 rounded-xl p-8 text-center">
                <svg class="h-16 w-16 text-gray-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-xl font-medium mb-2">No missing persons found</h3>
                <p class="text-gray-400">Try adjusting your search or check back later.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($missing_persons as $person): ?>
                    <a href="missing_view.php?id=<?= $person['id'] ?>" class="person-card">
                        <div class="relative">
                            <?php if ($person['photo_path']): ?>
                                <img src="<?= htmlspecialchars($person['photo_path']) ?>" alt="<?= htmlspecialchars($person['full_name']) ?>" class="person-photo">
                            <?php else: ?>
                                <div class="person-photo bg-gray-700 flex items-center justify-center">
                                    <svg class="h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($person['has_reward']): ?>
                                <div class="reward-badge">
                                    GHC <?= number_format($person['reward_amount'], 2) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Show urgency badge for cases older than 1 month
                            $missing_date = new DateTime($person['created_at']);
                            $today = new DateTime();
                            $interval = $today->diff($missing_date);
                            if ($interval->m >= 1): ?>
                                <div class="urgency-badge">
                                    Long-term
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-lg mb-1"><?= htmlspecialchars($person['full_name']) ?></h3>
                            <p class="text-blue-400 text-sm mb-3"><?= htmlspecialchars($person['home_name']) ?></p>
                            
                            <div class="flex justify-between text-sm text-gray-400 mb-2">
                                <span><?= $person['age'] ? htmlspecialchars($person['age']) . ' years' : 'Age unknown' ?></span>
                                <span><?= htmlspecialchars(ucfirst($person['gender'])) ?></span>
                            </div>
                            
                            <p class="text-sm text-gray-300 line-clamp-2 mb-3"><?= htmlspecialchars($person['description']) ?></p>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">
                                    Missing since <?= date('M j, Y', strtotime($person['created_at'])) ?>
                                </span>
                                <?php if ($person['has_reward']): ?>
                                    <span class="text-xs bg-yellow-900 text-yellow-100 px-2 py-1 rounded">
                                        Reward
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&filter=<?= $filter ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="pagination-btn bg-gray-700 hover:bg-gray-600">
                            &lt;
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
                        <a href="?page=1&filter=<?= $filter ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="pagination-btn bg-gray-700 hover:bg-gray-600">
                            1
                        </a>
                        <?php if ($start > 2): ?>
                            <span class="pagination-btn">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?= $i ?>&filter=<?= $filter ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="pagination-btn <?= $i == $page ? 'active' : 'bg-gray-700 hover:bg-gray-600' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?>
                            <span class="pagination-btn">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?>&filter=<?= $filter ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="pagination-btn bg-gray-700 hover:bg-gray-600">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&filter=<?= $filter ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="pagination-btn bg-gray-700 hover:bg-gray-600">
                            &gt;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>