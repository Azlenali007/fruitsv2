<?php
/**
 * PHP Built-in Server Router for N.A Fresh Fruits & Coconuts
 */

$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($rawUri);

// Direct handling for installer endpoints
if ($uri === '/install' || $uri === '/install/' || str_starts_with($uri, '/install/')) {
    $_SERVER['SCRIPT_NAME'] = '/public/index.php';
    require __DIR__ . '/public/index.php';
    return true;
}

// Normalize paths and serve real static assets
$filePath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'json' => 'application/json',
    ];
    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
    }
    readfile($filePath);
    return true;
}

// Forward all non-asset requests to front controller
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
