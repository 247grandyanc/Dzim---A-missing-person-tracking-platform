<?php
session_start();
require_once __DIR__ . '/net.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch available plans
$plans = $gh->query("
    SELECT * FROM subscription_plans 
    WHERE is_active = 1 
    ORDER BY searches_per_month ASC
")->fetch_all(MYSQLI_ASSOC);

if (!$plans) {
    die("No subscription plans available. Please contact support.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['plan_id'])) {
        // First validate additional info if this is the first step
        if (!isset($_SESSION['subscription_info'])) {
            $required_fields = [
                'purpose_of_search', 'full_name', 'relation_with_person',
                'security_question_1', 'security_answer_1',
                'security_question_2', 'security_answer_2'
            ];
            
            $errors = [];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
                }
            }
            
            if (empty($errors)) {
                // Store the info in session
                $_SESSION['subscription_info'] = [
                    'purpose_of_search' => clean_input($_POST['purpose_of_search']),
                    'full_name' => clean_input($_POST['full_name']),
                    'relation_with_person' => clean_input($_POST['relation_with_person']),
                    'organization_name' => clean_input($_POST['organization_name'] ?? ''),
                    'security_question_1' => clean_input($_POST['security_question_1']),
                    'security_answer_1' => encrypt_data(clean_input($_POST['security_answer_1']), ENCRYPTION_KEY),
                    'security_question_2' => clean_input($_POST['security_question_2']),
                    'security_answer_2' => encrypt_data(clean_input($_POST['security_answer_2']), ENCRYPTION_KEY),
                    'plan_id' => (int)$_POST['plan_id']
                ];
            } else {
                $error = implode("<br>", $errors);
            }
        }
        
        // If info is validated or already in session, proceed with payment
        if (isset($_SESSION['subscription_info'])) {
            $plan_id = $_SESSION['subscription_info']['plan_id'];
            $selected_plan = null;
            
            foreach ($plans as $plan) {
                if ($plan['id'] == $plan_id) {
                    $selected_plan = $plan;
                    break;
                }
            }
            
            if ($selected_plan) {
                $reference = 'SUB-' . time() . '-' . $user_id;
                $amount = $selected_plan['price'] * 100;
                
                $paystack_url = initializePaystackPayment($user_id, $plan_id, $amount, $reference);
                
                if ($paystack_url) {
                    // Save the info to database before redirecting
                    $info = $_SESSION['subscription_info'];
                    $stmt = $gh->prepare("
                        INSERT INTO user_subscription_questions (
                            user_id, purpose_of_search, full_name, relation_with_person,
                            organization_name, security_question_1, security_answer_1,
                            security_question_2, security_answer_2, ip_address, user_agent
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    $stmt->bind_param(
                        "issssssssss",
                        $user_id,
                        $info['purpose_of_search'],
                        $info['full_name'],
                        $info['relation_with_person'],
                        $info['organization_name'],
                        $info['security_question_1'],
                        $info['security_answer_1'],
                        $info['security_question_2'],
                        $info['security_answer_2'],
                        $ip_address,
                        $user_agent
                    );
                    
                    if ($stmt->execute()) {
                        unset($_SESSION['subscription_info']);
                        header("Location: $paystack_url");
                        exit();
                    } else {
                        $error = "Failed to save your information. Please try again.";
                    }
                } else {
                    $error = "Failed to initialize payment. Please try again.";
                }
            }
        }
    }
}
?>