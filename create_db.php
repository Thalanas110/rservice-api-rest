<?php

$config = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']}", $config['user'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $dbname = $config['dbname'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "Database '$dbname' created or already exists.\n";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
