<?php

namespace Core\Events;

use Core\Application;
use Core\Contracts\Queue\ShouldQueue;
use Core\Events\Queue\CallQueuedListener;
use Core\Queue\QueueManager;

/**
 * Class Dispatcher
 *
 * Handles the registration and dispatching of events to their listeners.
 */
class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<string|callable>>
     * An associative array where the key is the event name or class,
     * and the value is an array of listener classes or callables.
     */
    protected array $listeners = [];

    /**
     * The wildcard listeners.
     *
     * @var array<string, array<string|callable>>
     */
    protected array $wildcardListeners = [];

    /**
     * @var QueueManager
     * The queue manager instance for handling queued events.
     */
    protected QueueManager $queue;

    /**
     * Dispatcher constructor.
     *
     * @param Application $app The application container to resolve listeners.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register an event listener.
     *
     * @param string $event The event name or class.
     * @param string|callable $listener The listener class or callable.
     * @return void
     */
    public function listen(string $event, string|callable $listener): void
    {
        if (str_contains($event, '*')) {
            $this->wildcardListeners[$event][] = $listener;
        } else {
            $this->listeners[$event][] = $listener;
        }
    }

    /**
     * Dispatch an event to its listeners.
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        $listeners = $this->getListenersFor($eventName);

        if (empty($listeners)) {
            return;
        }

        foreach ($listeners as $listener) {
            if (is_callable($listener) && !is_string($listener)) {
                $listener($event);
            } else {
                $this->dispatchToListener($event, (string) $listener);
            }
        }
    }

    /**
     * Get all the listeners for a given event name, including wildcards.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function getListenersFor(string $eventName): array
    {
        $specificListeners = $this->listeners[$eventName] ?? [];
        $wildcardListeners = $this->getWildcardListeners($eventName);

        return array_merge($specificListeners, $wildcardListeners);
    }

    /**
     * Get the wildcard listeners for the event.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function getWildcardListeners(string $eventName): array
    {
        $wildcards = [];

        foreach ($this->wildcardListeners as $key => $listeners) {
            if (fnmatch($key, $eventName)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }

        return $wildcards;
    }

    /**
     * Dispatch an event to a specific listener.
     */
    protected function dispatchToListener(object $event, string $listenerClass): void
    {
        $listener = $this->app->make($listenerClass);

        if ($listener instanceof ShouldQueue) {
            $job = new CallQueuedListener($listenerClass, $event, $listener->tries ?? null);
            $this->app->make('queue')->push($job);
        } else {
            $listener->handle($event);
        }
    }
}
