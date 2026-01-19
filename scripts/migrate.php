<?php

require_once __DIR__ . '/../src/autoload.php';

use Core\Database;

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');

    $pdo->exec($sql);
    echo "Database schema imported successfully.\n";
} catch (\Exception $e) {
    echo "Error importing schema: " . $e->getMessage() . "\n";
}

