<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/net.php';
require_once __DIR__ . '/../includes/auth.php';

// Initialize response
$response = [
    'success' => false,
    'results' => [],
    'error' => null
];

try {
    // Validate JWT
    $user_id = authenticate();
    
    // Rate limiting (10 requests/minute)
    $stmt = $gh->prepare("SELECT COUNT(*) FROM search_history 
                           WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 MINUTE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->fetch_row()[0] >= 10) {
        throw new Exception("Too many requests. Please wait 1 minute.");
    }

    // Get search parameters
    $query = clean_input($_POST['query'] ?? '');
    $image_data = $_FILES['image'] ?? null;
    $is_deep_search = isset($_POST['deep_search']) && $_POST['deep_search'] == 'true';
    
    // Validate search type
    if (empty($query) && empty($image_data)) {
        throw new Exception("Either text query or image must be provided");
    }

    // Check subscription for deep search
    if ($is_deep_search) {
        $stmt = $gh->prepare("SELECT searches_remaining FROM subscriptions 
                              WHERE user_id = ? AND status = 'ACTIVE' AND expires_at > NOW()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();
        
        if (!$subscription || $subscription['searches_remaining'] <= 0) {
            throw new Exception("Active subscription required for deep search");
        }
    }

    // Log the search
    $stmt = $gh->prepare("INSERT INTO search_history 
                          (user_id, query_type, query_text, is_deep_search, ip_address) 
                          VALUES (?, ?, ?, ?, ?)");
    $query_type = $image_data ? 'IMAGE' : 'NAME';
    $query_text = $image_data ? null : $query;
    $stmt->bind_param("issis", $user_id, $query_type, $query_text, $is_deep_search, $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    $search_id = $gh->insert_id;

    // Process search based on type
    if ($image_data) {
        // Image search
        $image_path = process_uploaded_image($image_data);
        $response['results'] = image_search($image_path, $is_deep_search);
    } else {
        // Text search
        $response['results'] = text_search($query, $is_deep_search);
    }

    // Update subscription if deep search
    if ($is_deep_search) {
        $stmt = $gh->prepare("UPDATE subscriptions 
                              SET searches_remaining = searches_remaining - 1 
                              WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    // Update search results count
    $stmt = $gh->prepare("UPDATE search_history 
                          SET result_count = ? 
                          WHERE search_id = ?");
    $result_count = count($response['results']);
    $stmt->bind_param("ii", $result_count, $search_id);
    $stmt->execute();

    $response['success'] = true;

} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
} finally {
    echo json_encode($response);
}

