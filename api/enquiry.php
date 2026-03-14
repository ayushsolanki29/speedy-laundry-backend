<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    sendResponse('error', 'Enquiry ID is required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM enquiries WHERE id = ?");
    $stmt->execute([$id]);
    $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($enquiry) {
        $enquiry['created_at_iso'] = toUtcIso($enquiry['created_at'] ?? null);
        $enquiry['updated_at_iso'] = toUtcIso($enquiry['updated_at'] ?? null);
        sendResponse('success', 'Enquiry fetched successfully', $enquiry);
    } else {
        sendResponse('error', 'Enquiry not found', null, 404);
    }
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
