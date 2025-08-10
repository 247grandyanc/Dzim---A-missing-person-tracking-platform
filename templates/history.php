<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/auth.php';

// Authenticate user
$user_id = authenticate();

// Get search history for the current user
global $gh;
$search_history = $gh->query("
    SELECT * FROM search_history 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>Search History | DZIM-GH</title>
    <style>
        .history-item {
            transition: all 0.3s ease;
        }
        .history-item:hover {
            background-color: #1e293b;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Your Search History</h1>
            <button onclick="clearHistory()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg">
                Clear All History
            </button>
        </div>

        <?php if (empty($search_history)): ?>
            <div class="bg-gray-800 rounded-lg p-8 text-center">
                <svg class="h-12 w-12 mx-auto text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-xl font-medium mt-4">No search history found</h2>
                <p class="text-gray-400 mt-2">Your search queries will appear here</p>
                <a href="search.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg">
                    Start Searching
                </a>
            </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700 text-left">
                            <th class="p-4">Query</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">Results</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_history as $item): ?>
                        <tr class="border-b border-gray-700 history-item">
                            <td class="p-4">
                                <div class="font-medium"><?= htmlspecialchars($item['query']) ?></div>
                                <?php if (!empty($item['image_path'])): ?>
                                    <div class="text-sm text-gray-400">Image search</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-full text-xs bg-blue-900/30 text-blue-400">
                                    <?= ucfirst($item['query_type']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-400">
                                <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?>
                            </td>
                            <td class="p-4">
                                <?= $item['result_count'] ?> matches
                            </td>
                            <td class="p-4">
                                <div class="flex space-x-2">
                                    <a href="search.php?query=<?= urlencode($item['query']) ?>" 
                                       class="text-blue-400 hover:text-blue-300">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <button onclick="deleteSearch(<?= $item['id'] ?>)" 
                                            class="text-red-400 hover:text-red-300">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function deleteSearch(id) {
            if (confirm('Are you sure you want to delete this search from your history?')) {
                fetch('delete-search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting search: ' + data.message);
                    }
                });
            }
        }

        function clearHistory() {
            if (confirm('Are you sure you want to clear all your search history? This cannot be undone.')) {
                fetch('clear-history.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error clearing history: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>