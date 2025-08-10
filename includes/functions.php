<?php
// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// AES Encryption (for phone numbers, etc.)
function encrypt_data($data, $key) {
    $iv_length = openssl_cipher_iv_length('AES-128-CBC');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_data($data, $key) {
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('AES-128-CBC');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Authenticate user
 */
function login_user($email, $password) {
    global $gh;
    
    // Prepare SQL statement
    $stmt = $gh->prepare("SELECT user_id, password_hash FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        error_log("Login query failed: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password against hashed version
        if (password_verify($password, $user['password_hash'])) {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $email;
            
            // Generate JWT token
            // $jwt = generate_jwt($user['user_id'], $email);
            // setcookie('jwt', $jwt, time() + (86400 * 30), "/", "", false, true); // 30 days
            
            return true;
        }
    }
    
    return false;
}

// Fetch active ads for the current user's country and page type
function fetch_ads($placement_type, $user_country = 'GH') {
    global $gh;
    
    $ads = [];
    $current_time = date('Y-m-d H:i:s');
    
    $stmt = $gh->prepare("
        SELECT 
            c.campaign_id, 
            c.advertiser_name as title,
            c.image_url as image,
            c.destination_url as url,
            c.advertiser_name,
            p.placement_type,
            p.weight
        FROM 
            ad_campaigns c
        JOIN 
            ad_placements p ON c.campaign_id = p.campaign_id
        WHERE 
            p.placement_type = ?
            AND c.is_active = 1
            AND c.start_date <= ?
            AND c.end_date >= ?
            AND (c.target_country IS NULL OR c.target_country = ?)
            AND c.clicks_remaining > 0
        ORDER BY 
            p.weight DESC, 
            RAND()
        LIMIT 3
    ");
    
    $stmt->bind_param("ssss", $placement_type, $current_time, $current_time, $user_country);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Add sponsored flag for premium campaigns
        $row['sponsored'] = ($row['weight'] > 100);
        $ads[] = $row;
        
        // Log impression
        log_impression($row['campaign_id'], $user_country);
    }
    
    return $ads;
}

/**
 * Validate registration input
 */
function validate_registration($email, $password, $confirm_password, $phone = null) {
    $errors = [];
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Password validation
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    // Password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords don't match";
    }
    
    // Phone validation (if provided)
    if (!empty($phone) && !preg_match('/^\+233\d{9}$/', $phone)) {
        $errors[] = "Phone must be in format +233XXXXXXXXX";
    }
    
    return $errors;
}

/**
 * Check if email exists in database
 */
function email_exists($email) {
    global $gh;
    
    $stmt = $gh->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}

/**
 * Register a new user
 */
function register_user($email, $password, $phone = null, $ip_address) {
    global $gh;
    
    try {
        $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
        $enc_phone = $phone ? encrypt_data($phone) : null;
        
        $stmt = $gh->prepare("INSERT INTO users (email, password_hash, phone, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $hashed_pw, $enc_phone, $ip_address);
        
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return false;
    }
}

// Log ad impression
function log_impression($campaign_id, $user_id = null) {
    global $gh;
    
    $stmt = $gh->prepare("
        INSERT INTO ad_impressions 
        (campaign_id, user_id, ip_address, page_url) 
        VALUES (?, ?, ?, ?)
    ");
    
    $page_url = $_SERVER['REQUEST_URI'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt->bind_param("iiss", $campaign_id, $user_id, $ip_address, $page_url);
    $stmt->execute();
    
    // Update remaining clicks (decrement budget)
    $gh->query("
        UPDATE ad_campaigns 
        SET clicks_remaining = clicks_remaining - 1 
        WHERE campaign_id = $campaign_id
    ");
}

// Get user's country (from their profile or IP)
function get_user_country($user_id) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT country FROM users WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['country'];
    }
    
    // Fallback to IP geolocation if country not set
    return ip_to_country($_SERVER['REMOTE_ADDR']);
}

// Simple IP to country lookup (implement properly in production)
function ip_to_country($ip) {
    // In production, use a proper GeoIP service
    return 'GH'; // Default to Ghana
}

/**
 * Perform deep search with facial recognition and comprehensive data aggregation
 */
function deep_search($query = null, $image_path = null) {
    global $gh;
    
    $results = [];

     // Check rate limit
    if (!check_rate_limit($_SESSION['user_id'])) {
        throw new Exception("Search rate limit exceeded. Please try again later.");
    }
    
    // Validate we have either text or image input
    if (empty($query) && empty($image_path)) {
        throw new Exception("Either text query or image must be provided for deep search");
    }
    
    // Track search credits
    if (!deduct_search_credit($_SESSION['user_id'])) {
        throw new Exception("Insufficient search credits");
    }
    
    // Image-based deep search
    if ($image_path) {
        $results = process_image_deep_search($image_path);
    } 
    // Text-based deep search
    else {
        $results = process_text_deep_search($query);
    }
    
    // Enhance results with additional data sources
    $enhanced_results = enhance_results_with_external_data($results);
    
    // Log the deep search
    log_deep_search($_SESSION['user_id'], $query, $image_path, count($enhanced_results));
    
    return $enhanced_results;
}

/**
 * Process image for deep search using facial recognition
 */
function process_image_deep_search($image_path) {
    global $gh;
    
    $results = [];
    
    // Step 1: Call facial recognition microservice
    $face_matches = call_facial_recognition_service($image_path);
    
    if (empty($face_matches)) {
        return [];
    }
    
    // Step 2: Get matches from database
    foreach ($face_matches as $match) {
        $stmt = $gh->prepare("
            SELECT 
                p.*,
                b.vector_data,
                b.last_updated
            FROM 
                social_profiles p
            JOIN 
                biometric_vectors b ON p.vector_id = b.vector_id
            WHERE 
                p.vector_id = ?
                AND p.is_active = 1
            ORDER BY
                p.verification_score DESC
        ");
        $stmt->bind_param("i", $match['vector_id']);
        $stmt->execute();
        
        while ($row = $stmt->get_result()->fetch_assoc()) {
            $results[] = [
                'match_type' => 'biometric',
                'confidence' => $match['confidence'],
                'profile' => $row,
                'sources' => ['biometric_database']
            ];
        }
    }
    
    // Step 3: Reverse image search if no good matches found
    if (count($results) < 3) {
        $reverse_results = reverse_image_search($image_path);
        $results = array_merge($results, $reverse_results);
    }
    
    return $results;
}

/**
 * Rate limiting for searches
 */
function check_rate_limit($user_id, $limit = 10, $period = 60) {
    global $gh;
    
    $cache_key = "search_limit_$user_id";
    $current_time = time();
    $expires_at = date('Y-m-d H:i:s', $current_time + $period);
    
    // Clean up expired records
    $gh->query("DELETE FROM rate_limits WHERE expires_at < NOW()");
    
    // Check or create rate limit record
    $stmt = $gh->prepare("
        INSERT INTO rate_limits (cache_key, requests, expires_at)
        VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE 
        requests = IF(expires_at < NOW(), 1, requests + 1),
        expires_at = IF(expires_at < NOW(), ?, expires_at)
    ");
    $stmt->bind_param("sss", $cache_key, $expires_at, $expires_at);
    $stmt->execute();
    
    // Get current count
    $result = $gh->query("
        SELECT requests FROM rate_limits 
        WHERE cache_key = '$cache_key' AND expires_at > NOW()
    ");
    
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['requests'] > $limit) {
            return false;
        }
    }
    
    return true;
}

/**
 * Call facial recognition service (Python microservice)
 */
function call_facial_recognition_service($image_path) {
    $service_url = 'http://facial-recognition-service:5000/recognize';
    
    // Prepare the image for API call
    $image_data = base64_encode(file_get_contents($image_path));
    $image_type = pathinfo($image_path, PATHINFO_EXTENSION);
    
    $payload = json_encode([
        'image' => $image_data,
        'image_type' => $image_type,
        'min_confidence' => 0.7,
        'max_results' => 5
    ]);
    
    $ch = curl_init($service_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . FACIAL_RECOGNITION_API_KEY
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        throw new Exception("Facial recognition service unavailable");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid response from facial recognition service");
    }
    
    return $data['matches'] ?? [];
}

/**
 * Perform reverse image search
 */
function reverse_image_search($image_path) {
    global $gh;
    
    $results = [];
    $image_hash = sha1_file($image_path);
    
    // Search our database
    $stmt = $gh->prepare("
        SELECT 
            p.*,
            'image_hash_match' as match_type,
            0.8 as confidence
        FROM 
            social_profiles p
        WHERE 
            p.image_hash = ?
            AND p.is_active = 1
        LIMIT 10
    ");
    $stmt->bind_param("s", $image_hash);
    $stmt->execute();
    
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $results[] = [
            'match_type' => 'image_hash',
            'confidence' => 0.8,
            'profile' => $row,
            'sources' => ['image_database']
        ];
    }
    
    // Call external reverse image search APIs
    $external_results = call_external_image_search_apis($image_path);
    $results = array_merge($results, $external_results);
    
    return $results;
}

/**
 * Process text-based deep search
 */
function process_text_deep_search($query) {
    global $gh;
    
    $results = [];
    $query_param = "%$query%";
    
    // 1. Direct matches
    $stmt = $gh->prepare("
        SELECT 
            p.*,
            'direct_match' as match_type,
            1.0 as confidence
        FROM 
            social_profiles p
        WHERE 
            (p.name LIKE ? OR p.phone LIKE ?)
            AND p.verification_score > 0.8
            AND p.is_active = 1
        ORDER BY
            p.verification_score DESC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $query_param, $query_param);
    $stmt->execute();
    
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $results[] = [
            'match_type' => 'direct',
            'confidence' => 1.0,
            'profile' => $row,
            'sources' => ['primary_database']
        ];
    }
    
    // 2. Partial matches with lower confidence
    if (count($results) < 10) {
        $stmt = $gh->prepare("
            SELECT 
                p.*,
                'partial_match' as match_type,
                0.6 as confidence
            FROM 
                social_profiles p
            WHERE 
                (p.name LIKE ? OR p.phone LIKE ?)
                AND p.verification_score > 0.5
                AND p.is_active = 1
            ORDER BY
                p.verification_score DESC
            LIMIT 20
        ");
        $stmt->bind_param("ss", $query_param, $query_param);
        $stmt->execute();
        
        while ($row = $stmt->get_result()->fetch_assoc()) {
            $results[] = [
                'match_type' => 'partial',
                'confidence' => 0.6,
                'profile' => $row,
                'sources' => ['primary_database']
            ];
        }
    }
    
    // 3. Search external data sources
    $external_results = search_external_data_sources($query);
    $results = array_merge($results, $external_results);
    
    return $results;
}

/**
 * Enhance results with external data sources
 */
function enhance_results_with_external_data($results) {
    $enhanced_results = [];
    
    foreach ($results as $result) {
        $profile_id = $result['profile']['id'] ?? null;
        
        if ($profile_id) {
            // Get social media profiles
            $social_profiles = get_social_media_profiles($profile_id);
            
            // Get associated phone numbers
            $phone_numbers = get_associated_phone_numbers($profile_id);
            
            // Get possible locations
            $locations = get_associated_locations($profile_id);
            
            // Get professional information
            $professional_info = get_professional_info($profile_id);
            
            $enhanced_results[] = array_merge($result, [
                'social_profiles' => $social_profiles,
                'phone_numbers' => $phone_numbers,
                'locations' => $locations,
                'professional_info' => $professional_info,
                'sources' => array_merge($result['sources'] ?? [], ['enhancement_service'])
            ]);
        }
    }
    
    return $enhanced_results;
}

/**
 * Deduct search credit from user's account
 */
function deduct_search_credit($user_id) {
    global $gh;
    
    // Start transaction
    $gh->begin_transaction();
    
    try {
        // Check available credits
        $stmt = $gh->prepare("
            SELECT searches_remaining 
            FROM subscriptions 
            WHERE user_id = ? 
            AND expires_at > NOW()
            FOR UPDATE
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();
        
        if (!$subscription || $subscription['searches_remaining'] <= 0) {
            $gh->rollback();
            return false;
        }
        
        // Deduct credit
        $stmt = $gh->prepare("
            UPDATE subscriptions 
            SET searches_remaining = searches_remaining - 1 
            WHERE user_id = ?
            AND expires_at > NOW()
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $gh->commit();
        return true;
    } catch (Exception $e) {
        $gh->rollback();
        return false;
    }
}

/**
 * Log deep search activity
 */
function log_deep_search($user_id, $query, $image_path, $result_count) {
    global $gh;
    
    $query_type = $image_path ? 'image' : 'text';
    $query_value = $image_path ? basename($image_path) : $query;
    
    $stmt = $gh->prepare("
        INSERT INTO deep_search_logs (
            user_id,
            query_type,
            query_value,
            result_count,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param(
        "ississ", 
        $user_id, 
        $query_type, 
        $query_value, 
        $result_count, 
        $ip_address, 
        $user_agent
    );
    
    $stmt->execute();
    
    return $gh->insert_id;
}

function initializePaystackPayment($user_id, $plan_id, $amount, $reference) {
    global $gh;
    
    // Create pending subscription record
    $stmt = $gh->prepare("
        INSERT INTO subscriptions (
            user_id, 
            plan_id, 
            status, 
            payment_reference,
            starts_at,
            expires_at
        ) VALUES (?, ?, 'pending', ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))
    ");
    $stmt->bind_param("iis", $user_id, $plan_id, $reference);
    $stmt->execute();
    
    // Prepare Paystack payment data
    $callback_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?paystack_callback=1";
    
    $data = [
        'email' => $_SESSION['user_email'],
        'amount' => $amount,
        'reference' => $reference,
        'callback_url' => $callback_url,
        'metadata' => [
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'custom_fields' => [
                [
                    'display_name' => "Subscription Plan",
                    'variable_name' => "plan_id",
                    'value' => $plan_id
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
        error_log("Paystack CURL Error: " . $err);
        return false;
    }
    
    $result = json_decode($response, true);
    return $result['data']['authorization_url'] ?? false;
}

function handlePaystackCallback() {
    global $gh;
    
    $reference = $_GET['reference'] ?? '';
    if (empty($reference)) {
        die("Invalid callback - no reference provided");
    }
    
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
        error_log("Paystack verification error: " . $err);
        die("Payment verification failed. Please contact support.");
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['status']) {
        error_log("Invalid Paystack response: " . $response);
        die("Invalid payment verification response.");
    }
    
    $payment_data = $result['data'];
    
    // Update subscription record
    $stmt = $gh->prepare("
        UPDATE subscriptions 
        SET 
            status = 'active',
            payment_method = ?,
            payment_amount = ?,
            payment_currency = ?,
            payment_date = NOW(),
            paystack_reference = ?,
            paystack_data = ?
        WHERE 
            payment_reference = ?
            AND status = 'pending'
    ");
    
    $paystack_data = json_encode($payment_data);
    $stmt->bind_param(
        "sdssss",
        $payment_data['channel'],
        $payment_data['amount'] / 100, // Convert back from kobo
        $payment_data['currency'],
        $payment_data['reference'],
        $paystack_data,
        $reference
    );
    
    if ($stmt->execute()) {
        $_SESSION['payment_success'] = true;
        header("Location: subscription.php");
        exit();
    } else {
        error_log("Failed to update subscription: " . $gh->error);
        die("Failed to activate your subscription. Please contact support.");
    }
}
?>
