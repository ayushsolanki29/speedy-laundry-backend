<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

// Authentication Check (Simplified for now - should check token in production)
$token = getBearerToken();
if (!$token) {
    // For now, we'll allow but in a real app, verify this token against the database/JWT
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch all enquiries ordered by newest first
    $stmt = $db->query("SELECT * FROM enquiries ORDER BY created_at DESC");
    $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse('success', 'Enquiries fetched successfully', $enquiries);
    
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
