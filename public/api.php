<?php

// If using Composer:
require_once __DIR__ . '/../vendor/autoload.php';

// If not using Composer (manual requires):
// require_once __DIR__ . '/../src/Http/Request.php';
// require_once __DIR__ . '/../src/Http/Response.php';
// require_once __DIR__ . '/../src/Database/SQLiteManager.php';
// require_once __DIR__ . '/../src/Database/TableManager.php';
// require_once __DIR__ . '/../src/Api/ApiController.php';

use App\Http\Request;
use App\Api\ApiController;

// Basic error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1); // Turn off in production

try {
    $request = new Request();
    $controller = new ApiController($request);
    $controller->handleRequest();
} catch (\InvalidArgumentException $e) {
    // Catch specific exception from Request constructor for invalid JSON
    App\Http\Response::error($e->getMessage(), null, 400);
} catch (Exception $e) {
    // General fallback for uncaught exceptions during setup
    App\Http\Response::error('An unexpected error occurred: ' . $e->getMessage(), null, 500);
}