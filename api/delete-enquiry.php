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
$id = (int)($input['id'] ?? 0);

if (!$id) {
    sendResponse('error', 'Enquiry ID is required', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("DELETE FROM enquiries WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse('success', 'Enquiry deleted successfully');
    }

    sendResponse('error', 'Enquiry not found', null, 404);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}

