<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Core\Contracts\Exceptions\Handler as ExceptionHandlerContract;
use Core\Contracts\StatefulService;
use Core\Exceptions\RouteNotFoundException;
use Core\Module\Declarative\DeclarativeConfigLoader;
use Core\Routing\RouteRegistrar;
use Core\Support\ServiceProvider;
use Psr\Http\Message\RequestInterface;

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

        $this->app->singleton(\Core\Routing\Router::class, function ($app) {
            return new \Core\Routing\Router($app);
        });
        $this->app->tag(\Core\Routing\Router::class, StatefulService::class);

        $this->app->singleton(DeclarativeConfigLoader::class);
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
        $this->mapDeclarativeRoutes($router);

        $router->get('/{any}', function (RequestInterface $request) {
            $path = $request->getUri()->getPath();

            if (str_starts_with($path, '/api/')) {
                throw new RouteNotFoundException("Route not found for GET {$path}");
            }

            if (preg_match('/\\.[a-zA-Z0-9]+$/', $path)) {
                throw new RouteNotFoundException("Route not found for GET {$path}");
            }

            return view('layouts.app');
        })->group('web');
    }

    /**
     * Register routes from module declarative manifests (manifest.yaml / manifest.json).
     */
    protected function mapDeclarativeRoutes(\Core\Routing\Router $router): void
    {
        if (!$this->app->bound(DeclarativeConfigLoader::class)) {
            return;
        }
        $loader = $this->app->make(DeclarativeConfigLoader::class);
        foreach ($loader->loadAll() as $config) {
            if (!$config->hasRoutes()) {
                continue;
            }
            foreach ($config->routes as $r) {
                try {
                    $handler = $config->resolveAction($r['action']);
                    if (!class_exists($handler[0]) || !method_exists($handler[0], $handler[1])) {
                        continue;
                    }
                    $route = $router->addRoute($r['method'], $r['uri'], $handler);
                    if (!empty($r['name'])) {
                        $route->name($r['name']);
                    }
                    if (!empty($r['middleware'])) {
                        $route->middleware($r['middleware']);
                    }
                    if (!empty($r['group'])) {
                        $route->group($r['group']);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }
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
