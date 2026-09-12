<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($uri === '/favicon.ico') {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, must-revalidate');
    readfile(__DIR__ . '/assets/favicon.svg');
    exit;
}
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
