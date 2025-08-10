<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $phone = clean_input($_POST['phone'] ?? '');

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirm) {
        $errors[] = "Passwords don't match";
    }

    if (empty($errors)) {
        // Check if email exists
        $stmt = $gh->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        } else {
            // Insert new user
            $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
            $enc_phone = $phone ? encrypt_data($phone) : null;
            
            $stmt = $gh->prepare("INSERT INTO users (email, password_hash, phone, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $hashed_pw, $enc_phone, $_SERVER['REMOTE_ADDR']);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Registration failed. Please try later.";
            }
        }
    }
}
?>