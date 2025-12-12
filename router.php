<?php

// Router script for PHP built-in server
// All requests go through Symfony (including AssetMapper assets)

$requestUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files from public/ directory only (not /assets, those go through Symfony)
if ($requestUri !== '/' && !str_starts_with($requestUri, '/assets/') && file_exists(__DIR__ . '/public' . $requestUri)) {
    return false;
}

// All other requests (including /assets/*) go to Symfony front controller
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/public/index.php';
