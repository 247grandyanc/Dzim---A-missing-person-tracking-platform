<?php
/**
 * Handle missing/found person reports and matching
 */

// Report a missing or found person
function report_missing_person($data, $photo) {
    global $gh;
    
    // Validate required fields
    $required = ['type', 'full_name', 'home_name', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Required field missing: $field");
        }
    }
    
    // Handle photo upload
    $photo_path = null;
    if ($photo && $photo['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/missing_persons/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $filename = 'mp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $photo_path = 'uploads/missing_persons/' . $filename;
        
        if (!move_uploaded_file($photo['tmp_name'], __DIR__ . '/../' . $photo_path)) {
            throw new Exception("Failed to upload photo");
        }
    }
    
    // Insert record
    $stmt = $gh->prepare("
        INSERT INTO missing_persons (
            type, full_name, home_name, age, gender, height, 
            last_seen_location, description, photo_path,
            reporter_id, reporter_name, reporter_contact, ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_id = $_SESSION['user_id'] ?? null;
    
    $stmt->bind_param(
        "sssisssssisss",
        $data['type'],
        clean_input($data['full_name']),
        clean_input($data['home_name']),
        $data['age'] ?? null,
        $data['gender'],
        clean_input($data['height'] ?? ''),
        clean_input($data['last_seen_location'] ?? ''),
        clean_input($data['description'] ?? ''),
        $photo_path,
        $user_id,
        clean_input($data['reporter_name'] ?? ''),
        clean_input($data['reporter_contact'] ?? ''),
        $ip
    );
    
    if (!$stmt->execute()) {
        if ($photo_path) {
            @unlink(__DIR__ . '/../' . $photo_path);
        }
        throw new Exception("Failed to save report: " . $gh->error);
    }
    
    $report_id = $gh->insert_id;
    
    // Attempt to find matches
    if ($data['type'] === 'found') {
        find_missing_matches($report_id);
    } else {
        find_found_matches($report_id);
    }
    
    return $report_id;
}

// Find matches for a new found person report
function find_missing_matches($found_id) {
    global $gh;
    
    // Get the found person details
    $stmt = $gh->prepare("
        SELECT full_name, home_name, age, gender, height, photo_path 
        FROM missing_persons 
        WHERE id = ? AND type = 'found'
    ");
    $stmt->bind_param("i", $found_id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    
    if (!$found) return;
    
    // Search for potential matches in missing persons
    $stmt = $gh->prepare("
        SELECT id, full_name, home_name, age, gender, height, photo_path
        FROM missing_persons
        WHERE type = 'missing' 
          AND status = 'active'
          AND (
              home_name LIKE CONCAT('%', ?, '%')
              OR full_name LIKE CONCAT('%', ?, '%')
          )
          AND gender = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $home_name = $found['home_name'];
    $full_name = $found['full_name'];
    $gender = $found['gender'];
    
    $stmt->bind_param("sss", $home_name, $full_name, $gender);
    $stmt->execute();
    $potential_matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($potential_matches as $missing) {
        $confidence = calculate_match_confidence($found, $missing);
        
        if ($confidence > 0.5) { // Only consider matches above 50% confidence
            record_potential_match($missing['id'], $found_id, $confidence, 'system');
        }
    }
}

// Find matches for a new missing person report
function find_found_matches($missing_id) {
    global $gh;
    
    // Get the missing person details
    $stmt = $gh->prepare("
        SELECT full_name, home_name, age, gender, height, photo_path 
        FROM missing_persons 
        WHERE id = ? AND type = 'missing'
    ");
    $stmt->bind_param("i", $missing_id);
    $stmt->execute();
    $missing = $stmt->get_result()->fetch_assoc();
    
    if (!$missing) return;
    
    // Search for potential matches in found persons
    $stmt = $gh->prepare("
        SELECT id, full_name, home_name, age, gender, height, photo_path
        FROM missing_persons
        WHERE type = 'found' 
          AND status = 'active'
          AND (
              home_name LIKE CONCAT('%', ?, '%')
              OR full_name LIKE CONCAT('%', ?, '%')
          )
          AND gender = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $home_name = $missing['home_name'];
    $full_name = $missing['full_name'];
    $gender = $missing['gender'];
    
    $stmt->bind_param("sss", $home_name, $full_name, $gender);
    $stmt->execute();
    $potential_matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($potential_matches as $found) {
        $confidence = calculate_match_confidence($missing, $found);
        
        if ($confidence > 0.5) { // Only consider matches above 50% confidence
            record_potential_match($missing_id, $found['id'], $confidence, 'system');
        }
    }
}

// Calculate match confidence between two reports
function calculate_match_confidence($report1, $report2) {
    $confidence = 0;
    
    // Name matching (30% weight)
    similar_text(strtolower($report1['full_name']), strtolower($report2['full_name']), $name_similarity);
    $confidence += $name_similarity * 0.3;
    
    // Home name matching (20% weight)
    if (strtolower($report1['home_name']) == strtolower($report2['home_name'])) {
        $confidence += 0.2;
    }
    
    // Age matching (15% weight)
    if ($report1['age'] && $report2['age'] && abs($report1['age'] - $report2['age']) <= 5) {
        $confidence += 0.15;
    }
    
    // Height matching (15% weight)
    if ($report1['height'] && $report2['height'] && $report1['height'] == $report2['height']) {
        $confidence += 0.15;
    }
    
    // TODO: Add image comparison if both have photos (20% weight)
    // This would require facial recognition integration
    
    return min($confidence, 1.0); // Cap at 100%
}

// Record a potential match between reports
function record_potential_match($missing_id, $found_id, $confidence, $matched_by, $user_id = null) {
    global $gh;
    
    // Check if this match already exists
    $stmt = $gh->prepare("
        SELECT id FROM missing_person_matches 
        WHERE missing_id = ? AND found_id = ?
    ");
    $stmt->bind_param("ii", $missing_id, $found_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return false; // Match already exists
    }
    
    // Insert new match
    $stmt = $gh->prepare("
        INSERT INTO missing_person_matches (
            missing_id, found_id, confidence_score, matched_by, matched_by_user_id
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iidsi", $missing_id, $found_id, $confidence, $matched_by, $user_id);
    return $stmt->execute();
}

// Get missing persons listings
function get_missing_persons($type = 'missing', $limit = 20, $offset = 0, $search = null) {
    global $gh;
    
    $where = "type = ? AND status = 'active'";
    $params = [$type];
    $param_types = "s";
    
    if ($search) {
        $where .= " AND MATCH(full_name, home_name, description) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $search;
        $param_types .= "s";
    }
    
    $stmt = $gh->prepare("
        SELECT id, full_name, home_name, age, gender, height, 
               last_seen_location, description, photo_path, created_at
        FROM missing_persons
        WHERE $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get details for a specific missing/found person
function get_missing_person_details($id) {
    global $gh;
    
    $stmt = $gh->prepare("
        SELECT 
            m.*, 
            u.email as reporter_email,
            CONCAT(u.first_name, ' ', u.last_name) as reporter_full_name
        FROM missing_persons m
        LEFT JOIN users u ON m.reporter_id = u.user_id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        return null;
    }
    
    // Get any matches for this person
    if ($result['type'] === 'missing') {
        $matches = get_person_matches($id, 'missing');
    } else {
        $matches = get_person_matches($id, 'found');
    }
    
    $result['matches'] = $matches;
    return $result;
}

// Get matches for a person
function get_person_matches($id, $type) {
    global $gh;
    
    if ($type === 'missing') {
        $stmt = $gh->prepare("
            SELECT m.*, mm.confidence_score, mm.created_at as matched_at
            FROM missing_person_matches mm
            JOIN missing_persons m ON mm.found_id = m.id
            WHERE mm.missing_id = ?
            ORDER BY mm.confidence_score DESC
        ");
    } else {
        $stmt = $gh->prepare("
            SELECT m.*, mm.confidence_score, mm.created_at as matched_at
            FROM missing_person_matches mm
            JOIN missing_persons m ON mm.missing_id = m.id
            WHERE mm.found_id = ?
            ORDER BY mm.confidence_score DESC
        ");
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Log a search for missing/found persons
function log_missing_person_search($type, $search_terms = null) {
    global $gh;
    
    $stmt = $gh->prepare("
        INSERT INTO missing_person_searches (
            user_id, search_type, search_terms, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param(
        "issss",
        $user_id,
        $type,
        $search_terms,
        $ip,
        $agent
    );
    
    $stmt->execute();
    return $gh->insert_id;
}

// Get count of missing/found persons
function get_missing_persons_count($type = 'missing', $search = null) {
    global $gh;
    
    $where = "type = ? AND status = 'active'";
    $params = [$type];
    $param_types = "s";
    
    if ($search) {
        $where .= " AND MATCH(full_name, home_name, description) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $search;
        $param_types .= "s";
    }
    
    $stmt = $gh->prepare("
        SELECT COUNT(*) as count
        FROM missing_persons
        WHERE $where
    ");
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] ?? 0;
}

/**
 * Get missing persons with custom filters
 */
function get_missing_persons_filtered($where, $params, $param_types, $limit, $offset) {
    global $gh;
    
    $query = "
        SELECT 
            id, full_name, home_name, age, gender, height, 
            last_seen_location, description, photo_path, 
            has_reward, reward_amount, created_at
        FROM missing_persons
        WHERE $where
        ORDER BY 
            has_reward DESC, 
            reward_amount DESC, 
            created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $gh->prepare($query);
    
    // Bind parameters
    $param_types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get count of missing persons with custom filters
 */
function get_missing_persons_count_filtered($where, $params, $param_types) {
    global $gh;
    
    $query = "SELECT COUNT(*) as count FROM missing_persons WHERE $where";
    
    $stmt = $gh->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] ?? 0;
}