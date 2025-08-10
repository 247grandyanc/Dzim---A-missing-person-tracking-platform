<?php
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $campaign_id = (int)($data['campaign_id'] ?? 0);
    
    if ($campaign_id > 0) {
        $stmt = $conn->prepare("
            UPDATE ad_campaigns 
            SET clicks_remaining = clicks_remaining - 1 
            WHERE campaign_id = ?
        ");
        $stmt->bind_param("i", $campaign_id);
        $stmt->execute();
        
        // Log the click (you might want to add a separate clicks table)
        $stmt = $conn->prepare("
            INSERT INTO ad_impressions 
            (campaign_id, user_id, ip_address, page_url, clicked) 
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $page_url = $_SERVER['HTTP_REFERER'] ?? 'unknown';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $stmt->bind_param("iiss", $campaign_id, $user_id, $ip_address, $page_url);
        $stmt->execute();
    }
}

echo json_encode(['success' => true]);