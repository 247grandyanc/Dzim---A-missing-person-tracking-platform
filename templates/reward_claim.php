<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_rewards.php';

$user_id = authenticate();
$missing_person_id = $_GET['id'] ?? 0;
$error = null;
$success = false;

// Get missing person details
$stmt = $gh->prepare("
    SELECT mp.*, mr.reward_id, mr.amount
    FROM missing_persons mp
    LEFT JOIN missing_person_rewards mr ON mp.id = mr.missing_person_id AND mr.status = 'active'
    WHERE mp.id = ? AND mp.type = 'missing' AND mp.status = 'active'
");
$stmt->bind_param("i", $missing_person_id);
$stmt->execute();
$missing_person = $stmt->get_result()->fetch_assoc();

if (!$missing_person) {
    header("Location: missing_list.php");
    exit();
}

// Check if user has already claimed this reward
$stmt = $gh->prepare("
    SELECT claim_id 
    FROM reward_claims 
    WHERE reward_id = ? AND claimer_id = ? AND status IN ('pending', 'approved')
");
$stmt->bind_param("ii", $missing_person['reward_id'], $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    $error = "You have already submitted a claim for this reward";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $message = clean_input($_POST['message'] ?? '');
    
    if (empty($message)) {
        $error = "Please provide information about how you found this person";
    } else {
        try {
            $success = claim_reward($missing_person['reward_id'], $user_id, $message);
            
            if ($success) {
                $_SESSION['claim_success'] = true;
                header("Location: missing_view.php?id=$missing_person_id");
                exit();
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>Claim Reward | DZIM-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../templates/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-3xl">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h1 class="text-2xl font-bold mb-6">Claim Reward for <?= htmlspecialchars($missing_person['full_name']) ?></h1>
            
            <?php if ($error): ?>
                <div class="bg-red-900 text-red-300 rounded-lg p-4 mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-8 p-4 bg-yellow-900 text-yellow-100 rounded-lg">
                <h3 class="font-bold mb-2">Reward: GHC <?= number_format($missing_person['amount'], 2) ?></h3>
                <p class="text-sm">This reward will be paid out after verification by our team and the person who offered the reward.</p>
            </div>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="message" class="block text-sm font-medium mb-2">
                        How did you find this person? *
                    </label>
                    <textarea id="message" name="message" rows="6" required
                              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2"
                              placeholder="Provide detailed information about where and how you found this person..."></textarea>
                    <p class="text-xs text-gray-500 mt-2">
                        Be as specific as possible. This information will help verify your claim.
                    </p>
                </div>
                
                <div class="pt-4 border-t border-gray-700">
                    <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium">
                        Submit Claim
                    </button>
                    <a href="missing_view.php?id=<?= $missing_person_id ?>" class="ml-4 px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-medium inline-block">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>