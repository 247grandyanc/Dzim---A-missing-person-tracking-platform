<?php
// /api/paystack-webhook.php
$payload = @file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];

if (hash_hmac('sha512', $payload, 'your-paystack-secret') === $signature) {
    $event = json_decode($payload);
    // Update subscriptions table
}

?>