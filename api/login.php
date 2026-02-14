<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

// Specifically handle preflight for this script if it bypasses config for some reason
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    sendResponse('error', 'Username and password are required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Note: table is 'admins' as created in previous step
    $stmt = $db->prepare("SELECT id, username, password, email, role FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Remove password from response
        unset($admin['password']);

        $token = bin2hex(random_bytes(32));
        // Store token for admin profile / session validation
        try {
            $db->prepare("INSERT INTO admin_sessions (token, admin_id) VALUES (?, ?)")
                ->execute([$token, $admin['id']]);
        } catch (PDOException $e) {
            // admin_sessions table may not exist yet
        }

        sendResponse('success', 'Login successful', [
            'admin' => $admin,
            'token' => $token
        ]);
    } else {
        sendResponse('error', 'Invalid username or password', null, 401);
    }
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
