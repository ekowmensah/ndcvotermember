<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$classMap = require __DIR__ . '/autoload.php';
spl_autoload_register(static function (string $class) use ($classMap): void {
    if (isset($classMap[$class])) { require BASE_PATH . '/' . $classMap[$class]; }
});
require BASE_PATH . '/core/helpers.php';
App\Config::load(BASE_PATH . '/.env');
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(BASE_PATH . '/storage')) { mkdir(BASE_PATH . '/storage', 0700, true); }
ini_set('error_log', BASE_PATH . '/storage/php-error.log');

