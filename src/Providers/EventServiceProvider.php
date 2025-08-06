<?php

namespace App\Providers;

use Core\Events\EventDispatcherInterface;
use Core\Events\Dispatcher;
use Core\Events\ModuleChanged;
use Core\Listeners\ClearRelatedCacheOnModuleChange;
use Core\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        ModuleChanged::class => [
            ClearRelatedCacheOnModuleChange::class,
        ],
    ];

    public function register(): void
    {
        // Bind Core\Events\Dispatcher to both the core interface and the illuminate interface
        // so either can be type hinted.
        $this->app->singleton(EventDispatcherInterface::class, function ($app) {
            return new Dispatcher($app);
        });
        // Explicitly bind the dispatcher to the 'events' key.
        $this->app->alias(EventDispatcherInterface::class, 'events');
    }

    public function boot(): void
    {
        $events = $this->app->make('events');

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }

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
            if (!file_exists($eventsFile)) {
                continue;
            }

            $moduleListeners = require $eventsFile;
            foreach ($moduleListeners as $event => $listeners) {
                foreach ($listeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }
        }
    }
}
