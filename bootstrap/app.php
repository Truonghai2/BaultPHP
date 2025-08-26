<?php

use Core\Application;
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

// Load environment variables from .env file. Using safeLoad() is a cleaner way
// to load .env files, as it gracefully handles cases where the file doesn't exist.
$dotenv = Dotenv::createImmutable($app->basePath());
$dotenv->safeLoad();

/*
|--------------------------------------------------------------------------
| Register Foundational Service Providers
|--------------------------------------------------------------------------
|
| These providers are essential for the framework's core functionality,
| such as configuration, logging, and exception handling. They have
| minimal dependencies and should be loaded first.
|
*/
$app->register(\App\Providers\FacadeServiceProvider::class);
$app->register(\App\Providers\ConfigServiceProvider::class);
$app->register(\App\Providers\LoggingServiceProvider::class);
$app->register(\App\Providers\ExceptionServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Core Infrastructure Service Providers
|--------------------------------------------------------------------------
|
| These providers register fundamental services like filesystem access,
| event dispatching, and data storage that are used throughout the application.
|
*/
$app->register(\App\Providers\FilesystemServiceProvider::class);
$app->register(\App\Providers\EventServiceProvider::class);
$app->register(\App\Providers\DatabaseServiceProvider::class);
$app->register(\App\Providers\RedisServiceProvider::class);
$app->register(\App\Providers\CacheServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Core Application Service Providers
|--------------------------------------------------------------------------
|
| These providers handle core application concerns like views, sessions,
| authentication, and mail. They often depend on the infrastructure
| providers registered above.
|
*/
$app->register(\App\Providers\ViewServiceProvider::class);
$app->register(\App\Providers\HashServiceProvider::class);
$app->register(\App\Providers\SessionServiceProvider::class);
$app->register(\App\Providers\TranslationServiceProvider::class);
$app->register(\App\Providers\ValidationServiceProvider::class);
$app->register(\App\Providers\MailServiceProvider::class);
$app->register(\App\Providers\AuthServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Domain & Architectural Service Providers
|--------------------------------------------------------------------------
|
| These providers are specific to your application's architecture and
| domain logic, such as CQRS, metrics, and the server itself.
|
*/
$app->register(\App\Providers\CqrsServiceProvider::class);
$app->register(\Core\Queue\QueueServiceProvider::class);
$app->register(\App\Providers\MetricsServiceProvider::class);
$app->register(\App\Providers\ServerServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Application Entry Point Providers
|--------------------------------------------------------------------------
|
| These providers, RouteServiceProvider and ConsoleServiceProvider, define
| the application's entry points (web routes and console commands). They
| should be registered late as they depend on a fully bootstrapped app.
|
*/
$app->register(\App\Providers\RouteServiceProvider::class);
$app->register(\App\Providers\ConsoleServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Register Module Service Providers
|--------------------------------------------------------------------------
|
| This will automatically scan the Modules directory and register the
| main service provider for each module, following the convention:
| Modules/{ModuleName}/Providers/{ModuleName}ServiceProvider.php
| This allows modules to be truly plug-and-play.
|
*/
$modulesPath = $app->basePath('Modules');
if (is_dir($modulesPath)) {
    foreach (new DirectoryIterator($modulesPath) as $moduleInfo) {
        if ($moduleInfo->isDir() && !$moduleInfo->isDot()) {
            $moduleName = $moduleInfo->getFilename();
            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $app->register($providerClass);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Register Application Service Provider
|--------------------------------------------------------------------------
|
| This provider is a general-purpose place for your application's custom
| bootstrapping logic. It is registered last to ensure all other framework
| services are available.
|
*/
$app->register(\App\Providers\AppServiceProvider::class);

return $app;
