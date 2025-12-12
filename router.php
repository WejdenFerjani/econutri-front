<?php

// Router script for PHP built-in server
// Handles static files and Symfony routing

$requestUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($requestUri !== '/' && file_exists(__DIR__ . '/public' . $requestUri)) {
    return false;
}

// All other requests go to Symfony front controller
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/public/index.php';
