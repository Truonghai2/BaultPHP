<?php

file_put_contents(base_path('storage/logs/public_index_test.log'), 'public/index.php reached\n', FILE_APPEND);

use Core\AppKernel;
use Laminas\Diactoros\Response\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

define('BAULT_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so we don't have to worry about manual loading
| of any of our classes. It's just great.
|
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the application instance. This instance is the
| "glue" for all the components of BaultPHP and is the IoC container
| for the system binding all of the various parts.
|
*/

/** @var \Core\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

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

try {
    $kernel = new AppKernel($app);

    // Create a PSR-7 request from PHP's globals
    $psr17Factory = new Psr17Factory();
    $creator = new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    $request = $creator->fromGlobals();

    // Handle the request through the kernel to get a response
    $response = $kernel->handle($request);

    // Send the response back to the browser
    (new SapiEmitter())->emit($response);
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>500 - Internal Server Error</h1>';
    if (env('APP_DEBUG', false)) {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
    }
}
