<?php

namespace Core\Debug;

use Core\Application;
use Core\Events\EventDispatcherInterface;
use Core\Routing\Router;
use Core\Support\ServiceProvider;
use Http\Controllers\Debug\DebugController;

class DebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!config('debug.enabled', false)) {
            return;
        }

        $this->app->singleton(DebugManager::class, function () {
            return new DebugManager();
        });

        $this->app->tag(DebugManager::class, \Core\Contracts\StatefulService::class);

        $this->app->extend(EventDispatcherInterface::class, function (EventDispatcherInterface $originalDispatcher, Application $app) {
            /** @var DebugManager $debugManager */
            $debugManager = $app->make(DebugManager::class);

            return new TraceableEventDispatcher($originalDispatcher, $debugManager);
        });
    }

    public function boot(): void
    {
        if (!config('debug.enabled', false)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->addRoute('GET', '/_debug/{id}', [DebugController::class, 'show']);
    }
}
