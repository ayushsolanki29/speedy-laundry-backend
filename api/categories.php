<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch distinct categories from the blogs table
    $stmt = $db->query("SELECT DISTINCT category FROM blogs WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    sendResponse('success', 'Categories fetched successfully', $categories);
} catch (Exception $e) {
    sendResponse('error', 'Server error: ' . $e->getMessage(), null, 500);
}
?>
