<?php

namespace Core\Console\Commands;

use App\Providers\RouteServiceProvider;
use Core\Console\Contracts\BaseCommand;
use Core\Routing\Router;

class RouteCacheCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Create a route cache file for faster route registration.';
    }

    public function handle(): int
    {
        $this->comment('Caching application routes...');

        $cachePath = $this->app->getCachedRoutesPath();

        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $router = new Router($this->app);
        $provider = new RouteServiceProvider($this->app);
        $provider->loadRoutesForCaching($router);

        $routes = $router->getRoutesForCaching();

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $uri => $routeData) {
                if ($routeData['handler'] instanceof \Closure || is_object($routeData['handler'])) {
                    $this->error("Cannot cache route [{$method} {$uri}] because it uses a Closure or object handler.");

                    if (file_exists($cachePath)) {
                        unlink($cachePath);
                    }
                    return 1;
                }
            }
        }

        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($routes, true) . ';' . PHP_EOL;
        file_put_contents($cachePath, $content);

        $this->info('✔ Routes cached successfully!');
        return 0;
    }

}
