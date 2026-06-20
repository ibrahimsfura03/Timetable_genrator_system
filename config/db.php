<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'university_timetable');


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please contact administrator.");
}

$conn->set_charset("utf8mb4");

function logAudit($conn, $user_id, $action, $entity_type, $entity_id, $old_values = null, $new_values = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs(user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
                           VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $old_val = json_encode($old_values);
        $new_val = json_encode($new_values);
        $stmt->bind_param("issiisss", $user_id, $action, $entity_type, $entity_id, $old_val, $new_val, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>