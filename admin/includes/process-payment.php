<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$admin = admin_authenticate();
$data = json_decode(file_get_contents('php://input'), true);

if (!in_array($admin['role'], ['superadmin', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$claim_id = $data['claim_id'] ?? null;
if (!$claim_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid claim ID']);
    exit();
}

global $gh;

// Get claim details
$stmt = $gh->prepare("
    SELECT rc.*, u.email, u.phone, mpr.amount
    FROM reward_claims rc
    JOIN users u ON rc.claimer_id = u.user_id
    JOIN missing_person_rewards mpr ON rc.reward_id = mpr.reward_id
    WHERE rc.claim_id = ? AND rc.status = 'approved'
");
$stmt->bind_param("i", $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();

if (!$claim) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Claim not found or not approved']);
    exit();
}

// Here you would integrate with Paystack or your payment processor
// This is a simplified example
$reference = 'PAY-' . time() . '-' . $claim_id;

// Update claim as paid
$stmt = $gh->prepare("
    UPDATE reward_claims 
    SET status = 'paid',
        paystack_transfer_reference = ?,
        admin_id = ?,
        updated_at = NOW()
    WHERE claim_id = ?
");
$stmt->bind_param("sii", $reference, $admin['admin_id'], $claim_id);
$success = $stmt->execute();

header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'message' => $success ? 'Payment processed successfully' : 'Failed to process payment'
]);
?>