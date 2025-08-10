<?php
/**
 * Reward system functionality for missing persons
 */

const REWARD_PLATFORM_FEE_PERCENT = 10; // 10% platform fee

// Add reward to missing person
function add_missing_person_reward($missing_person_id, $user_id, $amount) {
    global $gh;
    
    // Calculate platform fee
    $platform_fee = $amount * (REWARD_PLATFORM_FEE_PERCENT / 100);
    $total_amount = $amount + $platform_fee;
    
    $stmt = $gh->prepare("
        INSERT INTO missing_person_rewards (
            missing_person_id, user_id, amount, platform_fee, total_amount
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iiddd", $missing_person_id, $user_id, $amount, $platform_fee, $total_amount);
    
    if ($stmt->execute()) {
        $reward_id = $gh->insert_id;
        
        // Update missing person record
        $gh->query("
            UPDATE missing_persons 
            SET has_reward = 1, reward_amount = $amount 
            WHERE id = $missing_person_id
        ");
        
        return $reward_id;
    }
    
    return false;
}

// Initialize Paystack payment for reward
function initialize_reward_payment($reward_id, $callback_url) {
    global $gh;
    
    // Get reward details
    $stmt = $gh->prepare("
        SELECT r.*, u.email, mp.full_name
        FROM missing_person_rewards r
        JOIN users u ON r.user_id = u.user_id
        JOIN missing_persons mp ON r.missing_person_id = mp.id
        WHERE r.reward_id = ?
    ");
    $stmt->bind_param("i", $reward_id);
    $stmt->execute();
    $reward = $stmt->get_result()->fetch_assoc();
    
    if (!$reward) {
        throw new Exception("Reward not found");
    }
    
    // Prepare Paystack payment data
    $reference = 'RW-' . time() . '-' . $reward_id;
    $amount = $reward['total_amount'] * 100; // Convert to kobo
    
    $data = [
        'email' => $reward['email'],
        'amount' => $amount,
        'reference' => $reference,
        'callback_url' => $callback_url,
        'metadata' => [
            'reward_id' => $reward_id,
            'missing_person' => $reward['full_name'],
            'custom_fields' => [
                [
                    'display_name' => "Reward For",
                    'variable_name' => "missing_person",
                    'value' => $reward['full_name']
                ],
                [
                    'display_name' => "Reward Amount",
                    'variable_name' => "amount",
                    'value' => $reward['amount'] . ' GHC'
                ]
            ]
        ]
    ];
    
    // Initialize Paystack transaction
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        throw new Exception("Paystack CURL Error: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['status']) {
        throw new Exception("Paystack error: " . ($result['message'] ?? 'Unknown error'));
    }
    
    // Update reward with payment reference
    $stmt = $gh->prepare("
        UPDATE missing_person_rewards 
        SET paystack_reference = ?
        WHERE reward_id = ?
    ");
    $stmt->bind_param("si", $reference, $reward_id);
    $stmt->execute();
    
    return $result['data']['authorization_url'];
}

// Handle Paystack reward payment callback
function handle_reward_payment_callback($reference) {
    global $gh;
    
    // Verify payment with Paystack
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        throw new Exception("Payment verification failed: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['status']) {
        throw new Exception("Invalid payment verification response");
    }
    
    $payment_data = $result['data'];
    
    // Get reward ID from metadata
    $reward_id = $payment_data['metadata']['reward_id'] ?? null;
    
    if (!$reward_id) {
        throw new Exception("No reward ID in payment metadata");
    }
    
    // Update reward status
    $stmt = $gh->prepare("
        UPDATE missing_person_rewards 
        SET 
            status = 'active',
            payment_status = 'paid',
            paystack_data = ?
        WHERE 
            paystack_reference = ?
            AND payment_status = 'unpaid'
    ");
    
    $paystack_data = json_encode($payment_data);
    $stmt->bind_param("ss", $paystack_data, $reference);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update reward status");
    }
    
    return $reward_id;
}

// Get missing persons with rewards for homepage
function get_homepage_missing_persons() {
    global $gh;
    
    // Get one random basic (non-reward) missing person
    $basic = $gh->query("
        SELECT id, full_name, home_name, age, gender, photo_path, created_at
        FROM missing_persons
        WHERE type = 'missing' 
          AND status = 'active'
          AND has_reward = 0
        ORDER BY RAND()
        LIMIT 1
    ")->fetch_assoc();
    
    // Get top 5 reward missing persons
    $rewards = $gh->query("
        SELECT 
            mp.id, mp.full_name, mp.home_name, mp.age, mp.gender, 
            mp.photo_path, mp.created_at, mp.reward_amount,
            COUNT(rc.claim_id) as claim_count
        FROM missing_persons mp
        LEFT JOIN reward_claims rc ON rc.reward_id IN (
            SELECT reward_id FROM missing_person_rewards 
            WHERE missing_person_id = mp.id AND status = 'active'
        ) AND rc.status = 'pending'
        WHERE mp.type = 'missing' 
          AND mp.status = 'active'
          AND mp.has_reward = 1
        GROUP BY mp.id
        ORDER BY mp.reward_amount DESC, mp.created_at DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    return [
        'basic' => $basic,
        'rewards' => $rewards
    ];
}

// Claim a reward
function claim_reward($reward_id, $claimer_id, $message) {
    global $gh;
    
    // Verify reward exists and is active
    $stmt = $gh->prepare("
        SELECT reward_id 
        FROM missing_person_rewards 
        WHERE reward_id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $reward_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Reward not available for claiming");
    }
    
    // Create claim
    $stmt = $gh->prepare("
        INSERT INTO reward_claims (
            reward_id, claimer_id, message
        ) VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $reward_id, $claimer_id, $message);
    
    return $stmt->execute();
}

// Process reward payment to claimer
function process_reward_payment($claim_id, $admin_id) {
    global $gh;
    
    // Get claim details
    $stmt = $gh->prepare("
        SELECT c.*, r.amount, u.recipient_code
        FROM reward_claims c
        JOIN missing_person_rewards r ON c.reward_id = r.reward_id
        JOIN users u ON c.claimer_id = u.user_id
        WHERE c.claim_id = ?
        AND c.status = 'approved'
        AND r.status = 'active'
    ");
    $stmt->bind_param("i", $claim_id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    
    if (!$claim) {
        throw new Exception("Claim not found or not approved");
    }
    
    if (empty($claim['recipient_code'])) {
        throw new Exception("Claimer has not set up payment recipient");
    }
    
    // Initiate Paystack transfer
    $transfer_data = [
        'source' => 'balance',
        'amount' => $claim['amount'] * 100, // in kobo
        'recipient' => $claim['recipient_code'],
        'reason' => "Reward for finding missing person"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transfer");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transfer_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        throw new Exception("Transfer failed: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['status']) {
        throw new Exception("Paystack transfer error: " . ($result['message'] ?? 'Unknown error'));
    }
    
    // Update claim and reward status
    $gh->begin_transaction();
    
    try {
        // Update claim
        $stmt = $gh->prepare("
            UPDATE reward_claims 
            SET 
                status = 'paid',
                admin_id = ?,
                paystack_transfer_reference = ?,
                updated_at = NOW()
            WHERE claim_id = ?
        ");
        $transfer_ref = $result['data']['transfer_code'];
        $stmt->bind_param("isi", $admin_id, $transfer_ref, $claim_id);
        $stmt->execute();
        
        // Update reward
        $gh->query("
            UPDATE missing_person_rewards 
            SET status = 'claimed'
            WHERE reward_id = {$claim['reward_id']}
        ");
        
        $gh->commit();
        return true;
    } catch (Exception $e) {
        $gh->rollback();
        throw $e;
    }
}