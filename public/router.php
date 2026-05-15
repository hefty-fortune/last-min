<?php

// Router script for the PHP built-in server.
// Routes all requests to index.php unless they match an existing static file.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // serve the static file
}

require __DIR__ . '/index.php';
