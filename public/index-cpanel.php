<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Ruta a la aplicación Laravel en el directorio privado
define('LARAVEL_PATH', '/home/suguad/documentos_app');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = LARAVEL_PATH.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require LARAVEL_PATH.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once LARAVEL_PATH.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
