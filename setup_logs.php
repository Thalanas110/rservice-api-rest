<?php

require __DIR__ . '/src/autoload.php';

use Core\Database;

try {
    $db = Database::getInstance()->getConnection();
    
    $sql = file_get_contents(__DIR__ . '/schema/logs.sql');
    
    if (!$sql) {
        die("Error reading schema/logs.sql");
    }
    
    $db->exec($sql);
    
    echo "Logs table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
