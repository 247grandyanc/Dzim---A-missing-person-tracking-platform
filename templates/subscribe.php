<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // Add this line to include authentication functions

// Authenticate user
$user_id = authenticate();

// Fetch user's current subscription status
$stmt = $gh->prepare("
    SELECT s.*, p.name as plan_name, p.searches_per_month
    FROM subscription s
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active' AND s.expires_at > NOW()
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_subscription = $stmt->get_result()->fetch_assoc();

// Fetch available plans
$plans = $gh->query("
    SELECT * FROM subscription_plans 
    WHERE is_active = 1 
    ORDER BY searches_per_month ASC
")->fetch_all(MYSQLI_ASSOC);

// Handle Paystack callback
if (isset($_GET['paystack_callback'])) {
    handlePaystackCallback();
}

// Handle subscription form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    $plan_id = (int)$_POST['plan_id'];
    $selected_plan = null;
    
    foreach ($plans as $plan) {
        if ($plan['id'] == $plan_id) {
            $selected_plan = $plan;
            break;
        }
    }
    
    if ($selected_plan) {
        $reference = 'SUB-' . time() . '-' . $user_id;
        $amount = $selected_plan['price'] * 100; // Paystack uses kobo (multiply by 100)
        
        // Initialize Paystack payment
        $paystack_url = initializePaystackPayment($user_id, $plan_id, $amount, $reference);
        
        if ($paystack_url) {
            header("Location: $paystack_url");
            exit();
        } else {
            $error = "Failed to initialize payment. Please try again.";
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <title>Subscription Plans | DZIM-GH</title>
    <style>
        /* Add to your existing styles */
        .message-box {
            transition: all 0.3s ease;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Error message specific */
        .bg-red-900 {
            background-color: #7f1d1d;
        }
        .text-red-300 {
            color: #fca5a5;
        }

        /* Success message specific */
        .bg-green-900 {
            background-color: #14532d;
        }
        .text-green-300 {
            color: #86efac;
        }
        .plan-card {
            transition: all 0.3s ease;
            border: 1px solid #2d3748;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #4299e1;
        }
        .plan-card.popular {
            border-color: #4299e1;
            position: relative;
        }
        .popular-badge {
            position: absolute;
            top: -12px;
            right: 20px;
            background: #4299e1;
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 3px 12px;
            border-radius: 20px;
        }
        .feature-list li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 24px;
        }
        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #48bb78;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-12 max-w-6xl">
        <?php if (isset($_SESSION['payment_success'])): ?>
            <div class="bg-green-900 text-green-300 rounded-lg p-4 mb-8 flex items-center">
                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>Payment successful! Your subscription is now active.</span>
                <?php unset($_SESSION['payment_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-900 text-red-300 rounded-lg p-4 mb-8 flex items-center">
                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <div class="text-center mb-12">
            <h1 class="text-3xl md:text-4xl font-bold mb-4">Choose Your Plan</h1>
            <p class="text-gray-400 max-w-2xl mx-auto">
                Upgrade to unlock advanced search features, more results, and priority processing.
            </p>
        </div>

        <?php if (!isset($_SESSION['subscription_info'])): ?>
            <!-- Additional Information Form -->
            <div class="bg-gray-800 rounded-xl p-6 mb-10 border border-blue-500">
                <h2 class="text-xl font-bold mb-4">Additional Information Required</h2>
                <p class="text-gray-400 mb-6">Please provide these details to complete your subscription.</p>
                
                <form method="POST" action="../includes/add-question.php">
                    <div class="mb-4">
                        <label for="purpose_of_search" class="block text-gray-300 mb-2">Purpose of Deep Search</label>
                        <select id="purpose_of_search" name="purpose_of_search" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                            <option value="">Select purpose</option>
                            <option value="family_reconnection">Family Reconnection</option>
                            <option value="background_check">Background Check</option>
                            <option value="legal_investigation">Legal Investigation</option>
                            <option value="business_verification">Business Verification</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="full_name" class="block text-gray-300 mb-2">Your Full Legal Name</label>
                        <input type="text" id="full_name" name="full_name" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>

                    <div class="mb-4">
                        <label for="relation_with_person" class="block text-gray-300 mb-2">Your Relationship to Person</label>
                        <input type="text" id="relation_with_person" name="relation_with_person" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>

                    <div class="mb-4">
                        <label for="organization_name" class="block text-gray-300 mb-2">Organization/Institution Name (if applicable)</label>
                        <input type="text" id="organization_name" name="organization_name" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>

                    <div class="mb-4">
                        <label for="security_question_1" class="block text-gray-300 mb-2">Security Question 1</label>
                        <select id="security_question_1" name="security_question_1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                            <option value="">Select a question</option>
                            <option value="What was your first pet's name?">What was your first pet's name?</option>
                            <option value="What city were you born in?">What city were you born in?</option>
                            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                        </select>
                        <input type="text" id="security_answer_1" name="security_answer_1" class="w-full mt-2 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Your answer">
                    </div>

                    <div class="mb-4">
                        <label for="security_question_2" class="block text-gray-300 mb-2">Security Question 2</label>
                        <select id="security_question_2" name="security_question_2" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                            <option value="">Select a question</option>
                            <option value="What was the name of your first school?">What was the name of your first school?</option>
                            <option value="What was your childhood nickname?">What was your childhood nickname?</option>
                            <option value="What street did you grow up on?">What street did you grow up on?</option>
                        </select>
                        <input type="text" id="security_answer_2" name="security_answer_2" class="w-full mt-2 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Your answer">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-300 mb-2">Purpose of Deep Search</label>
                            <select name="purpose_of_search" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                                <option value="">Select Purpose</option>
                                <option value="personal_investigation">Personal Investigation</option>
                                <option value="employment_verification">Employment Verification</option>
                                <option value="legal_investigation">Legal Investigation</option>
                                <option value="academic_research">Academic Research</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-gray-300 mb-2">Full Name</label>
                            <input type="text" name="full_name" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Your full name">
                        </div>
                        
                        <div>
                            <label class="block text-gray-300 mb-2">Relation with Person</label>
                            <select name="relation_with_person" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                                <option value="">Select Relation</option>
                                <option value="self">Self</option>
                                <option value="family_member">Family Member</option>
                                <option value="friend">Friend</option>
                                <option value="business_associate">Business Associate</option>
                                <option value="legal_representative">Legal Representative</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-gray-300 mb-2">Organization (if applicable)</label>
                            <input type="text" name="organization_name" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Company, Church, School, etc.">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h3 class="text-lg font-medium mb-4 text-gray-300">Security Questions</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-300 mb-2">Security Question 1</label>
                                <select name="security_question_1" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                                    <option value="">Select a question</option>
                                    <option value="What was your first pet's name?">What was your first pet's name?</option>
                                    <option value="What city were you born in?">What city were you born in?</option>
                                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                                    <option value="What was the name of your first school?">What was the name of your first school?</option>
                                </select>
                                <input type="text" name="security_answer_1" required class="w-full mt-2 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Your answer">
                            </div>
                            
                            <div>
                                <label class="block text-gray-300 mb-2">Security Question 2</label>
                                <select name="security_question_2" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                                    <option value="">Select a question</option>
                                    <option value="What was your childhood nickname?">What was your childhood nickname?</option>
                                    <option value="What street did you grow up on?">What street did you grow up on?</option>
                                    <option value="What was your favorite teacher's name?">What was your favorite teacher's name?</option>
                                    <option value="What is your favorite movie?">What is your favorite movie?</option>
                                </select>
                                <input type="text" name="security_answer_2" required class="w-full mt-2 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white" placeholder="Your answer">
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="plan_id" value="<?= $_POST['plan_id'] ?? '' ?>">
                    <button type="submit" class="w-full mt-8 py-3 px-6 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium transition duration-200">
                        Continue to Payment
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Current Plan (if any) -->
        <?php if ($current_subscription): ?>
            <div class="bg-gray-800 rounded-xl p-6 mb-10 border border-blue-500">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h2 class="text-xl font-bold mb-1">Your Current Plan</h2>
                        <p class="text-gray-400">
                            <?= htmlspecialchars($current_subscription['plan_name']) ?> • 
                            <?= $current_subscription['searches_per_month'] ?> searches/month •
                            Expires <?= date('M j, Y', strtotime($current_subscription['expires_at'])) ?>
                        </p>
                    </div>
                    <span class="px-4 py-2 bg-blue-600 rounded-lg font-medium">
                        Active
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pricing Plans -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <?php foreach ($plans as $plan): ?>
                <div class="plan-card bg-gray-800 rounded-xl p-8 <?= $plan['is_popular'] ? 'popular' : '' ?>">
                    <?php if ($plan['is_popular']): ?>
                        <div class="popular-badge">POPULAR</div>
                    <?php endif; ?>
                    
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="flex items-center justify-center">
                            <span class="text-4xl font-bold">₵<?= number_format($plan['price'], 2) ?></span>
                            <span class="text-gray-400 ml-1">/month</span>
                        </div>
                    </div>
                    
                    <ul class="feature-list mb-8 text-gray-300">
                        <li><?= number_format($plan['searches_per_month']) ?> deep searches</li>
                        <li>Facial recognition matching</li>
                        <li>Priority result processing</li>
                        <li>Full profile reports</li>
                        <li>Email support</li>
                        <?php if ($plan['is_popular']): ?>
                            <li class="text-blue-400">Advanced filters</li>
                            <li class="text-blue-400">PDF report exports</li>
                        <?php endif; ?>
                    </ul>
                    
                    <form method="POST">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <button 
                            type="submit" 
                            class="w-full py-3 px-6 rounded-lg font-medium transition duration-200 
                                <?= $plan['is_popular'] 
                                    ? 'bg-blue-600 hover:bg-blue-700' 
                                    : 'bg-gray-700 hover:bg-gray-600' ?>"
                        >
                            <?= $current_subscription ? 'Upgrade Plan' : 'Get Started' ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
            // After fetching the plans and before displaying them:
            if (!$plans || count($plans) === 0) {
                echo '
                <div class="message-box bg-red-900 text-red-300 rounded-lg p-6 mb-8 text-center">
                    <svg class="h-8 w-8 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <h2 class="text-xl font-bold mb-2">No Subscription Plans Available</h2>
                    <p>We currently don\'t have any subscription plans available for purchase.</p>
                    <p class="mt-2">Please contact our support team for assistance.</p>
                    <a href="mailto:support@dzim-gh.com" class="inline-block mt-4 px-4 py-2 bg-red-700 hover:bg-red-600 rounded-lg">
                        Contact Support
                    </a>
                </div>';
                
                // Optionally include the footer and exit if you don't want to show anything else
                include __DIR__ . '/../includes/footer.php';
                exit();
            }
            ?>

        <!-- FAQ Section -->
        <div class="bg-gray-800 rounded-xl p-8 border border-gray-700">
            <h2 class="text-2xl font-bold mb-6">Frequently Asked Questions</h2>
            
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-medium mb-2">What payment methods do you accept?</h3>
                    <p class="text-gray-400">
                        We accept all major credit/debit cards (Visa, Mastercard, Verve) and bank transfers through Paystack.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium mb-2">Can I cancel my subscription?</h3>
                    <p class="text-gray-400">
                        Yes, you can cancel anytime. Your subscription will remain active until the end of the current billing period.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium mb-2">How do I get support?</h3>
                    <p class="text-gray-400">
                        Email us at support@seach-gh.com or use the live chat during business hours (9AM-5PM GMT).
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-medium mb-2">Are my searches private?</h3>
                    <p class="text-gray-400">
                        Yes, all searches are confidential and we never share your search history with third parties.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>