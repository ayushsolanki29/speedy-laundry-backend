<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/functions.php';

try {
    $db = Database::getInstance();
    $schemaFile = __DIR__ . '/sql/schema.sql';
    
    if ($db->initializeSchema($schemaFile)) {
        echo "Database and schema initialized successfully!\n";
    } else {
        echo "Failed to initialize schema. Check error logs.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
