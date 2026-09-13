<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if ($uri === '/favicon.ico') {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile(__DIR__ . '/assets/favicon.png');
    exit;
}
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
