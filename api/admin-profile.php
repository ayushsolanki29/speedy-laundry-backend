<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$token = getBearerToken();
if (!$token) {
    sendResponse('error', 'Unauthorized', null, 401);
}

try {
    $db = Database::getInstance()->getConnection();

    // Resolve admin_id from token
    $stmt = $db->prepare("SELECT admin_id FROM admin_sessions WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendResponse('error', 'Invalid or expired session', null, 401);
    }
    $adminId = (int) $row['admin_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT id, username, email, role FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            sendResponse('success', 'Profile fetched', $admin);
        }
        sendResponse('error', 'Admin not found', null, 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            sendResponse('error', 'Invalid input', null, 400);
        }

        $username = isset($input['username']) ? trim($input['username']) : null;
        $email = isset($input['email']) ? trim($input['email']) : null;
        $newPassword = isset($input['password']) ? $input['password'] : null;
        $currentPassword = isset($input['current_password']) ? $input['current_password'] : null;

        $stmt = $db->prepare("SELECT username, email, password FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            sendResponse('error', 'Admin not found', null, 404);
        }

        if ($newPassword) {
            if (!$currentPassword) {
                sendResponse('error', 'Current password required to change password', null, 400);
            }
            if (!password_verify($currentPassword, $current['password'])) {
                sendResponse('error', 'Current password is incorrect', null, 400);
            }
        }

        $updates = [];
        $params = [];

        if ($username !== null && $username !== '') {
            $username = sanitizeInput($username);
            if (strlen($username) < 3) {
                sendResponse('error', 'Username must be at least 3 characters', null, 400);
            }
            $updates[] = "username = ?";
            $params[] = $username;
        }

        if ($email !== null && $email !== '') {
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse('error', 'Invalid email', null, 400);
            }
            $updates[] = "email = ?";
            $params[] = $email;
        }

        if ($newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updates[] = "password = ?";
            $params[] = $hash;
        }

        if (empty($updates)) {
            sendResponse('error', 'Nothing to update', null, 400);
        }

        $params[] = $adminId;
        $sql = "UPDATE admins SET " . implode(', ', $updates) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);

        sendResponse('success', 'Profile updated');
    }

    sendResponse('error', 'Method not allowed', null, 405);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
