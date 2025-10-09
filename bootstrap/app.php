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
$dotenv = Dotenv::createImmutable($app->basePath(), ['.env']);
$dotenv->safeLoad();

// When running the Swoole server, we must prevent certain libraries from registering
// shutdown functions that conflict with the Swoole event loop. We detect this by
// checking the command line arguments.
$isSwooleServer = php_sapi_name() === 'cli' && isset($_SERVER['argv'][1]) && in_array($_SERVER['argv'][1], ['serve:start', 'serve:watch'], true);

/*
|--------------------------------------------------------------------------
| Initialize Sentry
|--------------------------------------------------------------------------
|
| Initialize the Sentry SDK early in the bootstrap process to catch
| as many errors as possible.
|
*/
$sentryConfig = $app->make('config')->get('sentry');
if (!empty($sentryConfig['dsn'])) {
    \Sentry\init($sentryConfig);
}

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Now we will register all of the application's service providers. This
| is done here to ensure the container is fully configured before either
| the HTTP or Console kernels are instantiated.
|
*/
(new ProviderRepository($app))->load($app->getCachedProvidersPath());

Facade::setFacadeApplication($app);

return $app;
