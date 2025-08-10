<?php
// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Log admin actions
function log_admin_action($admin_id, $action, $target_id = null) {
    global $gh;
    
    $stmt = $gh->prepare("
        INSERT INTO audit_log 
        (admin_id, action, target_id, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "isis",
        $admin_id,
        $action,
        $target_id,
        $_SERVER['REMOTE_ADDR']
    );
    $stmt->execute();
}

// Get admin by ID
function get_admin($admin_id) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT admin_id, username, role, last_login 
        FROM admin_users 
        WHERE admin_id = ?
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Check if IP is blocked
function is_ip_blocked($ip) {
    global $gh;
    
    // Check exact matches
    $stmt = $gh->prepare("
        SELECT 1 FROM ip_blocks 
        WHERE ip_range = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return true;
    }
    
    // Check CIDR ranges (simplified - in production use proper IP range checking)
    $blocks = $gh->query("
        SELECT ip_range FROM ip_blocks 
        WHERE ip_range LIKE '%/%' 
        AND (expires_at IS NULL OR expires_at > NOW())
    ")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($blocks as $block) {
        if (ip_in_range($ip, $block['ip_range'])) {
            return true;
        }
    }
    
    return false;
}

// Simplified IP range check (use a proper library in production)
function ip_in_range($ip, $range) {
    list($subnet, $bits) = explode('/', $range);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

// Get system setting
function get_setting($key) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT setting_value FROM system_settings 
        WHERE setting_key = ?
    ");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['setting_value'] : null;
}

// Check if country is allowed
function is_country_allowed($country_code) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT 1 FROM allowed_countries 
        WHERE country_code = ? AND is_active = 1
    ");
    $stmt->bind_param("s", $country_code);
    $stmt->execute();
    
    return $stmt->get_result()->num_rows > 0;
}