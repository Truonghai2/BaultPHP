<?php

namespace App\Providers;

use Core\Debug\EventCollector;
use Core\Events\Dispatcher;
use Core\Events\EventDispatcherInterface;
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
        $this->app->singleton(EventDispatcherInterface::class, function ($app) {
            return new Dispatcher($app);
        });

        // Bọc (wrap) dispatcher bằng một phiên bản có thể theo dõi (traceable) nếu debug được bật.
        // Đây là cách tiếp cận sạch sẽ để tích hợp với Debugbar.
        $this->app->extend(EventDispatcherInterface::class, function (EventDispatcherInterface $dispatcher, $app) {
            if ((bool) config('app.debug', false) && $app->bound('debugbar')) {
                /** @var EventCollector $collector */
                $collector = $app->make(EventCollector::class);
                return new \Core\Debug\TraceableEventDispatcher($dispatcher, $collector);
            }
            return $dispatcher;
        });

        $this->app->alias(EventDispatcherInterface::class, 'events');
    }

    public function boot(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->app->make('events');

        $cachedEventsPath = $this->app->basePath('bootstrap/cache/events.php');

        if (file_exists($cachedEventsPath)) {
            $events = require $cachedEventsPath;
        } else {
            $events = $this->getEventsToRegister();
        }

        $this->registerListeners($dispatcher, $events);
    }

    /**
     * Discovers all event-listener mappings from providers, config, and modules.
     * This method is designed to be called by the `event:cache` command.
     *
     * @return array<class-string, array<int, class-string>>
     */
    public function getEventsToRegister(): array
    {
        $providerListeners = $this->listen;
        $globalListeners = config('events', []);

        $moduleListeners = $this->getModuleListeners();

        $allEvents = array_merge_recursive($providerListeners, $globalListeners, $moduleListeners);

        // Ensure listeners are unique for each event
        foreach ($allEvents as $event => &$listeners) {
            if (is_array($listeners)) {
                $listeners = array_values(array_unique($listeners));
            }
        }
        unset($listeners);

        return $allEvents;
    }

    /**
     * Scan all enabled modules and collect their event listeners.
     */
    protected function getModuleListeners(): array
    {
        $moduleListeners = [];
        $enabledModules = $this->getEnabledModules();

        foreach ($enabledModules as $moduleName) {
            $eventsFile = base_path("Modules/{$moduleName}/events.php");

            if (file_exists($eventsFile)) {
                $listeners = require $eventsFile;
                if (is_array($listeners)) {
                    $moduleListeners = array_merge_recursive($moduleListeners, $listeners);
                }
            }
        }
        return $moduleListeners;
    }

    /**
     * Get the list of enabled module names, from cache if available.
     *
     * @return array
     */
    protected function getEnabledModules(): array
    {
        $cachedModulesPath = $this->app->basePath('bootstrap/cache/modules.php');
        if (file_exists($cachedModulesPath)) {
            return require $cachedModulesPath;
        }

        $enabledModuleNames = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                $enabledModuleNames[] = $data['name'];
            }
        }

        return $enabledModuleNames;
    }

    /**
     * Register the given event-listener mappings with the dispatcher.
     */
    protected function registerListeners(EventDispatcherInterface $dispatcher, array $events): void
    {
        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
    }
}
