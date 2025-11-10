<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/core/bootstrap.php

// Set global headers for all API responses
header('Content-Type: application/json');

// 1. Load Configuration
require_once __DIR__ . '/../config/config.php';

// 2. Load Core Database Class
require_once __DIR__ . '/Database.php';

// ---------------------------------
// --- REDIS SESSION CONFIG START ---
// ---------------------------------

// Get Redis connection details from environment
$redis_host = getenv('REDIS_HOST') ?: 'cache';
$redis_port = getenv('REDIS_PORT') ?: 6379;

// Tell PHP to use Redis for session storage
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', "tcp://$redis_host:$redis_port");
ini_set('session.gc_maxlifetime', 604800); // 7 days

// Set secure cookie parameters
$is_production = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
session_set_cookie_params([
    'lifetime' => 604800, // 7 days
    'path' => '/',
    'domain' => '', // Empty for current domain
    'secure' => $is_production, // TRUE in production (requires HTTPS)
    'httponly' => true,         // CRITICAL: Prevents JS access to the cookie
    'samesite' => 'Strict'      // CRITICAL: Prevents CSRF attacks
]);

// Start the session
session_start();

// ---------------------------------
// --- REDIS SESSION CONFIG END ---
// ---------------------------------

// 3. Global Exception Handler
// This ensures all errors are caught and returned as JSON
set_exception_handler(function($exception) {
    http_response_code(500);
    $response = [
        'error' => 'An unexpected server error occurred.',
        'message' => $exception->getMessage()
    ];
    
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $response['trace'] = $exception->getTraceAsString();
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
});

?>