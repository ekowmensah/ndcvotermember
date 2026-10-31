<?php
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__ . $path);
$assetRoot = realpath(__DIR__ . '/assets');
if (str_starts_with($path, '/assets/')) {
    $extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    if ($file && $assetRoot && str_starts_with($file, $assetRoot . DIRECTORY_SEPARATOR)
        && is_file($file) && in_array($extension, ['css','js','svg','png','jpg','jpeg','gif','webp','ico','woff','woff2','ttf','txt'], true)) {
        return false;
    }
    http_response_code(404);
    exit('Asset not found.');
}
// PHP's development server does not apply .htaccess. Serve only the front
// controller and allowlisted static assets, never private project files.
if ($path !== '/' && $path !== '/index.php') {
    $private = preg_match('~^/(?:controllers|models|views|config|core|services|database|bin|scripts|storage|tests|vendor|node_modules)(?:/|$)|(?:^|/)\.|\.(?:sql|json|lock|md|ps1|ini|log|ya?ml)$|^/router\.php$~i', $path);
    http_response_code($private ? 403 : 404);
    exit($private ? 'Access denied.' : 'Page not found.');
}
require __DIR__ . '/index.php';

