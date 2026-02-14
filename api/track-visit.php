<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$today = date('Y-m-d');

try {
    $db = Database::getInstance()->getConnection();
    
    // Use INSERT IGNORE to avoid duplicate key errors if already visited today
    $stmt = $db->prepare("INSERT IGNORE INTO visits (ip_address, user_agent, visit_date) VALUES (?, ?, ?)");
    $result = $stmt->execute([$ip_address, $user_agent, $today]);

    if ($stmt->rowCount() > 0) {
        sendResponse('success', 'New visit recorded');
    } else {
        sendResponse('success', 'Visit already recorded for today');
    }
    
} catch (Exception $e) {
    // We don't want to break the frontend if tracking fails
    sendResponse('error', 'Tracking failed', null, 500);
}
?>
