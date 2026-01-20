<?php

require __DIR__ . '/src/autoload.php';

use Core\Logger;

try {
    $logger = new Logger();
    
    $logger->info("Test info message");
    $logger->error("Test error message");
    $logger->debug("Test debug message");
    
    echo "Logs written successfully.\n";
} catch (Exception $e) {
    echo "Error logging: " . $e->getMessage() . "\n";
}
