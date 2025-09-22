<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Core\Contracts\Exceptions\Handler as ExceptionHandlerContract;
use Core\Contracts\StatefulService;
use Core\Routing\RouteRegistrar;
use Core\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register any routes for your application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandlerContract::class, Handler::class);

        $this->app->singleton(static::class, fn () => $this);

        $this->app->singleton(\Core\Routing\Router::class, function ($app) {
            return new \Core\Routing\Router($app);
        });

        $this->app->tag(\Core\Routing\Router::class, StatefulService::class);

        $this->app->singleton(RouteRegistrar::class);

        $this->app->singleton(\Core\Http\Redirector::class, function ($app) {
            $router = $app->make(\Core\Routing\Router::class);
            $session = $app->make(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
            return new \Core\Http\Redirector($app, $router, $session);
        });
    }

    public function boot(): void
    {
        $router = $this->app->make(\Core\Routing\Router::class);
        $cachedRoutesPath = $this->app->basePath('bootstrap/cache/routes.php');

        if (file_exists($cachedRoutesPath)) {
            $routes = require $cachedRoutesPath;
            $router->loadFromCache($routes);
        } else {
            $this->mapRoutes($router);
        }
    }

    /**
     * Load all routes for the purpose of caching.
     * This method is called by the `route:cache` command.
     */
    public function loadRoutesForCaching(\Core\Routing\Router $router): void
    {
        $this->mapRoutes($router);
    }

    /**
     * Define the routes for the application.
     * This is the single source of truth for all route registrations.
     */
    protected function mapRoutes(\Core\Routing\Router $router): void
    {
        $this->mapAttributeRoutes($router);
    }

    /**
     * Scan all relevant directories and register routes defined by attributes.
     * This is the single source for attribute-based routing.
     */
    protected function mapAttributeRoutes(\Core\Routing\Router $router): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);
        $paths = $this->getRouteAttributePaths();

        if (!empty($paths)) {
            $registrar->registerRoutes($router, $paths);
        }
    }

    /**
     * Get all paths where controllers with route attributes might be located.
     *
     * @return string[]
     */
    protected function getRouteAttributePaths(): array
    {
        $normalize = fn (string $path): string => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $pathsToScan = [];

        $coreControllerPath = $normalize(base_path('src/Http/Controllers'));
        if (is_dir($coreControllerPath)) {
            $pathsToScan[] = $coreControllerPath;
        }

        $moduleControllerPaths = glob($normalize(base_path('Modules/*/Http/Controllers')), GLOB_ONLYDIR);
        if ($moduleControllerPaths) {
            $pathsToScan = array_merge($pathsToScan, $moduleControllerPaths);
        }

        return $pathsToScan;
    }

    /**
     * Check if the current command is a route caching command.
     *
     * @return bool
     */
    private function isCachingCommand(): bool
    {
        return isset($_SERVER['argv']) && in_array('route:cache', $_SERVER['argv'], true);
    }
}
