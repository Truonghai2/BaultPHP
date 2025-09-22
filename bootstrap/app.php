<?php

use Core\Application;
use Core\Foundation\ProviderRepository;
use Core\Support\Facade;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Bault application instance
| which serves as the "glue" for all the components of Bault, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Application(
    realpath(__DIR__ . '/../'),
);

/*
|--------------------------------------------------------------------------
| Load Environment Variables
|--------------------------------------------------------------------------
|
| Load environment variables from the .env file in the project root.
| This is done once at the beginning of the bootstrap process.
|
*/
$dotenv = Dotenv::createImmutable($app->basePath());
$dotenv->safeLoad();

// When running the Swoole server, we must prevent certain libraries from registering
// shutdown functions that conflict with the Swoole event loop. We detect this by
// checking the command line arguments.
$isSwooleServer = php_sapi_name() === 'cli' && isset($_SERVER['argv'][1]) && in_array($_SERVER['argv'][1], ['serve:start', 'serve:watch'], true);

// Disable shutdown handlers for Revolt and Amphp components to prevent deprecation warnings in Swoole.
putenv('REVOLT_DRIVER_DISABLE_SHUTDOWN_HANDLER=1');
putenv('AMPHP_PROCESS_DISABLE_SHUTDOWN_HANDLER=1');
putenv('AMPHP_HTTP_CLIENT_DISABLE_SHUTDOWN_HANDLER=1');

Facade::setFacadeApplication($app);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Now we will register all of the application's service providers.
| The ProviderRepository handles loading from cache in production
| or discovering them on-the-fly in development environments.
|
*/
(new ProviderRepository($app))->load(
    $app->bootstrapPath('cache/services.php'),
);

return $app;
