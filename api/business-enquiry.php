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

// Sanitize inputs
$business_name = sanitizeInput($input['business_name'] ?? '');
$full_name = sanitizeInput($input['full_name'] ?? '');
$phone = sanitizeInput($input['phone'] ?? '');
$email = sanitizeInput($input['email'] ?? '');
$industry = sanitizeInput($input['industry'] ?? '');
$message = sanitizeInput($input['message'] ?? '');

// Validation
if (empty($business_name) || empty($full_name) || empty($phone) || empty($email)) {
    sendResponse('error', 'Please fill in all required fields.', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse('error', 'Invalid email address.', null, 400);
}

if (strlen($phone) > 30) {
    sendResponse('error', 'Phone number is too long (max 30 characters).', null, 400);
}

// Prepare details for database
$combined_message = "BUSINESS: " . $business_name . "\n";
$combined_message .= "INDUSTRY: " . ($industry ?: 'Not specified') . "\n\n";
$combined_message .= $message;

try {
    $db = Database::getInstance()->getConnection();
    
    // We reuse the enquiries table but mark it with a business service type
    $stmt = $db->prepare("INSERT INTO enquiries (full_name, phone, email, postcode, service, message) VALUES (?, ?, ?, ?, ?, ?)");
    // Postcode is required in schema but not in business form, using 'BUSINESS' as placeholder
    $result = $stmt->execute([$full_name, $phone, $email, 'BUSINESS', 'business-quote', $combined_message]);

    if ($result) {
        $enquiryId = $db->lastInsertId();
        EmailQueue::queueBusinessEmails([
            'business_name' => $business_name,
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'industry' => $industry,
            'message' => $message
        ]);
        sendResponse('success', 'Thank you! Your business quote request has been received. Our team will contact you shortly.', [
            'id' => $enquiryId
        ]);
    } else {
        sendResponse('error', 'Failed to save request. Please try again later.', null, 500);
    }
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
