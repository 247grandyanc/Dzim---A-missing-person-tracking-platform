<?php
require_once __DIR__ . '/../includes/admin-auth.php';
$admin = admin_authenticate();

$action = $_GET['action'] ?? '';
$claim_id = (int)($_GET['id'] ?? 0);

if (!in_array($action, ['approve', 'reject', 'pay']) || $claim_id <= 0) {
    $_SESSION['error_message'] = "Invalid request";
    header("Location: missing-persons.php?action=claims");
    exit();
}

// Get claim details
$stmt = $gh->prepare("
    SELECT c.*, r.amount, r.paystack_reference, u.email as claimant_email
    FROM reward_claims c
    JOIN missing_person_rewards r ON c.reward_id = r.reward_id
    JOIN users u ON c.claimer_id = u.user_id
    WHERE c.claim_id = ?
");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();

if (!$claim) {
    $_SESSION['error_message'] = "Claim not found";
    header("Location: missing-persons.php?action=claims");
    exit();
}

// Process action
try {
    $gh->begin_transaction();
    
    switch ($action) {
        case 'approve':
            $stmt = $gh->prepare("
                UPDATE reward_claims 
                SET status = 'approved', admin_id = ?, updated_at = NOW() 
                WHERE claim_id = ?
            ");
            $stmt->bind_param("ii", $admin['admin_id'], $claim_id);
            $stmt->execute();
            
            // Notify claimant
            send_email(
                $claim['claimant_email'],
                "Your reward claim has been approved",
                "Your claim for GHS " . number_format($claim['amount'], 2) . " has been approved."
            );
            
            $_SESSION['success_message'] = "Claim approved successfully";
            break;
            
        case 'reject':
            $stmt = $gh->prepare("
                UPDATE reward_claims 
                SET status = 'rejected', admin_id = ?, updated_at = NOW() 
                WHERE claim_id = ?
            ");
            $stmt->bind_param("ii", $admin['admin_id'], $claim_id);
            $stmt->execute();
            
            // Notify claimant
            send_email(
                $claim['claimant_email'],
                "Your reward claim has been rejected",
                "Your claim for GHS " . number_format($claim['amount'], 2) . " has been rejected."
            );
            
            $_SESSION['success_message'] = "Claim rejected";
            break;
            
        case 'pay':
            // Initiate Paystack transfer
            $transfer = initiate_paystack_transfer(
                $claim['amount'],
                $claim['claimant_email'],
                "Reward payment for claim #" . $claim_id
            );
            
            if ($transfer && $transfer['status']) {
                $stmt = $gh->prepare("
                    UPDATE reward_claims 
                    SET status = 'paid', 
                        paystack_transfer_reference = ?, 
                        admin_id = ?, 
                        updated_at = NOW() 
                    WHERE claim_id = ?
                ");
                $stmt->bind_param("sii", $transfer['data']['reference'], $admin['admin_id'], $claim_id);
                $stmt->execute();
                
                // Notify claimant
                send_email(
                    $claim['claimant_email'],
                    "Your reward has been paid",
                    "Your claim for GHS " . number_format($claim['amount'], 2) . " has been paid."
                );
                
                $_SESSION['success_message'] = "Payment initiated successfully";
            } else {
                throw new Exception("Failed to initiate payment: " . ($transfer['message'] ?? 'Unknown error'));
            }
            break;
    }
    
    $gh->commit();
} catch (Exception $e) {
    $gh->rollback();
    $_SESSION['error_message'] = "Failed to process claim: " . $e->getMessage();
}

header("Location: missing-persons.php?action=claims");
exit();