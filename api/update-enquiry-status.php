<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !$status) {
    sendResponse('error', 'ID and Status are required', null, 400);
}

// Validate status
$allowed_statuses = ['new', 'in_progress', 'completed', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    sendResponse('error', 'Invalid status', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE enquiries SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);

    if ($result) {
        sendResponse('success', 'Status updated successfully');
    } else {
        sendResponse('error', 'Failed to update status', null, 500);
    }
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
