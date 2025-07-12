<?php

namespace App\Providers;

use Core\Contracts\Events\EventDispatcherInterface;
use Core\Events\Dispatcher;
use Core\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [];

    public function register(): void
    {
        $this->app->singleton(EventDispatcherInterface::class, function ($app) {
            return new Dispatcher($app);
        });
    }

    public function boot(): void
    {
        $dispatcher = $this->app->make(EventDispatcherInterface::class);

        // Load global listeners
        $globalListeners = config('events', []);
        foreach ($globalListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        // Load listeners from all modules
        $moduleDirs = glob(base_path('Modules/*'), GLOB_ONLYDIR);
        foreach ($moduleDirs as $dir) {
            $eventsFile = $dir . '/events.php';
            if (!file_exists($eventsFile)) continue;

            $moduleListeners = require $eventsFile;
            foreach ($moduleListeners as $event => $listeners) {
                foreach ($listeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }
        }
    }
}