<?php

/*
 * Front controller para InfinityFree.
 *
 * Estructura en el servidor (todo dentro de htdocs/ por open_basedir):
 *   htdocs/index.php, .htaccess, build/, favicon.ico ...  <- contenido de public/
 *   htdocs/laravel/                                       <- resto de la app (bloqueado por .htaccess)
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$core = __DIR__.'/laravel';

if (file_exists($maintenance = $core.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $core.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $core.'/bootstrap/app.php';

// public_path() apunta a htdocs/ (necesario para @vite y asset()).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
