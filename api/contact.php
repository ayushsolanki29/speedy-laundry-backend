<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/EmailQueue.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method not allowed', null, 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse('error', 'Invalid input data', null, 400);
}

$full_name = sanitizeInput($input['full_name'] ?? '');
$phone = sanitizeInput($input['phone'] ?? '');
$email = sanitizeInput($input['email'] ?? '');
$postcode = sanitizeInput($input['postcode'] ?? '');
$service = sanitizeInput($input['service'] ?? '');
$message = sanitizeInput($input['message'] ?? '');

if (empty($full_name) || empty($phone) || empty($email) || empty($postcode)) {
    sendResponse('error', 'Please fill in all required fields.', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email address.', null, 400);
}

if (strlen($phone) > 30) {
    sendResponse('error', 'Phone number is too long (max 30 characters).', null, 400);
}

if (strlen($postcode) > 20) {
    sendResponse('error', 'Postcode is too long (max 20 characters).', null, 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("INSERT INTO enquiries (full_name, phone, email, postcode, service, message) VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$full_name, $phone, $email, $postcode, $service, $message]);

    if ($result) {
        $enquiryId = $db->lastInsertId();
        EmailQueue::queueEnquiryEmails([
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'postcode' => $postcode,
            'service' => $service,
            'message' => $message
        ]);
        sendResponse('success', 'Thank you! Your pickup request has been received. Our team will contact you shortly.', [
            'id' => $enquiryId
        ]);
    } else {
        sendResponse('error', 'Failed to save request. Please try again later.', null, 500);
    }
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
