<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/config/config.php

// -- Database Credentials --
// (Pulled from docker-compose.yml environment variables)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'todo_app');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'devuser');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'devpass');

// -- Application Settings --
define('APP_NAME', 'Advanced To-Do App');
define('BASE_URL', 'http://localhost:8080');

// -- API Settings --
define('API_V1_PATH', '/api/v1');

// -- Error Reporting --
// (Set to 'development' or 'production')
define('ENVIRONMENT', 'development');

if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}


define('GEMINI_API_KEY', 'put-your-gemini-api-key-here');
define('GEMINI_MODEL', 'models/gemini-2.5-flash');

?>