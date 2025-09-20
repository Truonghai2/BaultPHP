<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Core\Contracts\Exceptions\Handler as ExceptionHandlerContract;
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

        $cachedRoutesFile = $this->app->basePath('bootstrap/cache/routes.php');
        if (file_exists($cachedRoutesFile) && !env('APP_DEBUG', false)) {
            $cachedRoutes = require $cachedRoutesFile;
            $router->loadFromCache($cachedRoutes);
            return;
        }

        // Do not map routes again if running in console, unless it's a caching command.
        if ($this->app->runningInConsole() && !$this->isCachingCommand()) {
            return;
        }

        $this->mapRoutes($router);
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
        $router->post('/bault/upload-file', [\Http\Controllers\ComponentUploadController::class, '__invoke']);
        $router->post('/bault/update', [\Http\Controllers\ComponentController::class, '__invoke']);

        $router->get('/dev/perf-test/db', [\Http\Controllers\PerformanceTestController::class, 'testDb']);

        // Route for real-time server and connection pool status
        $router->get('/_/status', [\Http\Controllers\ServerStatusController::class, '__invoke'])
               ->middleware([\Http\Middleware\ProtectMetricsMiddleware::class]);

        // Route for Prometheus metrics scraping
        $router->get('/metrics', [\Http\Controllers\PrometheusMetricsController::class, '__invoke'])
               ->middleware([\Http\Middleware\ProtectMetricsMiddleware::class]);
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
