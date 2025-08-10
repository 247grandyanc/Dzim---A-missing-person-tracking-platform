<div class="bg-gray-800 rounded-lg shadow px-4 py-6">
    <h2 class="text-xl font-bold mb-6">Active Reward Offers</h2>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Case</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reward Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Offered By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Payment Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php
                $stmt = $gh->prepare("
                    SELECT r.*, p.full_name, p.photo_path, u.email
                    FROM missing_person_rewards r
                    JOIN missing_persons p ON r.missing_person_id = p.id
                    JOIN users u ON r.user_id = u.user_id
                    WHERE r.status = 'active'
                    ORDER BY r.created_at DESC
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($reward = $result->fetch_assoc()):
                ?>
                <tr>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <?php if ($reward['photo_path']): ?>
                                <img src="<?= htmlspecialchars($reward['photo_path']) ?>" alt="Photo" class="h-10 w-10 rounded-full object-cover mr-3">
                            <?php endif; ?>
                            <div>
                                <div class="font-medium"><?= htmlspecialchars($reward['full_name']) ?></div>
                                <div class="text-sm text-gray-400">Case #<?= $reward['missing_person_id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-bold">GHS <?= number_format($reward['amount'], 2) ?></div>
                        <div class="text-sm text-gray-400">Fee: GHS <?= number_format($reward['platform_fee'], 2) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div><?= htmlspecialchars($reward['email']) ?></div>
                        <div class="text-sm text-gray-400"><?= date('M j, Y', strtotime($reward['created_at'])) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 rounded-full text-xs 
                            <?= $reward['payment_status'] === 'paid' ? 'bg-green-900 text-green-300' : 
                               ($reward['payment_status'] === 'failed' ? 'bg-red-900 text-red-300' : 'bg-yellow-900 text-yellow-300') ?>">
                            <?= ucfirst($reward['payment_status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="?action=view&id=<?= $reward['missing_person_id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3">View Case</a>
                        <a href="?action=claims&reward_id=<?= $reward['reward_id'] ?>" class="text-green-400 hover:text-green-300">View Claims</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>