<?php
ob_start();

// Suppress PHP 8.4 deprecation warnings (Laravel 8 compatibility)
error_reporting(E_ALL & ~E_DEPRECATED);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/src/storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the srcipt here so we don't need to manually load our classes.
|
*/

require __DIR__.'/src/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Ensure Environment File Exists (Fresh Install Support)
|--------------------------------------------------------------------------
|
| For fresh installations, only .env.example ships in the package.
| Auto-create .env from .env.example so Laravel can boot properly.
|
*/

$envPath = __DIR__.'/src/.env';
$envExample = __DIR__.'/src/.env.example';

if (!file_exists($envPath) && file_exists($envExample)) {
    copy($envExample, $envPath);
}

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/src/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
