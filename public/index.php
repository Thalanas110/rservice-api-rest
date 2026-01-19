<?php

require_once __DIR__ . '/../src/autoload.php';

use Core\Router;
use Core\Request;
use Core\Response;

// Error Handling
set_exception_handler(function ($e) {
    Response::error($e->getMessage(), 500);
});

// CORS (if needed, good practice for REST API)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE,PATCH");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$router = new Router();
$request = new Request();

require_once __DIR__ . '/../src/routes.php';

$router->dispatch($request);
