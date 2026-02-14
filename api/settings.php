<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();

    if ($method === 'GET') {
        // Public - no auth required for frontend
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        sendResponse('success', 'Settings fetched', $settings);
    }

    if ($method === 'PUT' || $method === 'POST') {
        // Admin only - verify token in production
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            sendResponse('error', 'Invalid input', null, 400);
        }

        $allowedKeys = [
            'footer_facebook', 'footer_instagram', 'footer_twitter', 'footer_linkedin',
            'contact_email', 'contact_phone', 'contact_address', 'contact_hours'
        ];

        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $input)) {
                $value = is_string($input[$key]) ? trim($input[$key]) : json_encode($input[$key]);
                $stmt->execute([$key, $value]);
            }
        }

        sendResponse('success', 'Settings saved');
    }

    sendResponse('error', 'Method not allowed', null, 405);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
