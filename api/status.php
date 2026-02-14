<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/functions.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Test a simple query
    $stmt = $db->query("SELECT COUNT(*) as admin_count FROM admins");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendResponse('success', 'Backend is operational', [
        'database' => 'connected',
        'admin_count' => $result['admin_count'],
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    sendResponse('error', 'Backend error: ' . $e->getMessage(), null, 500);
}
?>
