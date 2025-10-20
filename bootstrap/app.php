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
    // For Swoole, we must disable default integrations to prevent Sentry from
    // registering a shutdown function that conflicts with the event loop.
    // We then manually add back the necessary integrations, excluding the one
    // that causes the issue (ErrorListenerIntegration).
    if ($isSwooleServer) {
        $sentryConfig['default_integrations'] = false;
        $sentryConfig['integrations'] = [
            new \Sentry\Integration\RequestIntegration(),
            new \Sentry\Integration\TransactionIntegration(),
            new \Sentry\Integration\FrameContextifierIntegration(),
            new \Sentry\Integration\EnvironmentIntegration(),
        ];
    }

    \Sentry\init(array_merge(['dsn' => $sentryConfig['dsn']], $sentryConfig));
}

/*
|--------------------------------------------------------------------------
| Register Core Bindings
|--------------------------------------------------------------------------
|
| We will register the ProviderRepository here. This class is responsible for
| loading all of the service providers for the application, including from
| a cached file for performance optimization.
|
*/
$app->singleton(ProviderRepository::class);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Now we will resolve the ProviderRepository from the container and tell it
| to load all the application's service providers. In a production environment,
| this will be a very fast operation thanks to the cached manifest file.
|
*/
$app->make(ProviderRepository::class)->load($app->getCachedProvidersPath());

Facade::setFacadeApplication($app);

return $app;
