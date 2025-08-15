<?php

namespace Core\Events;

use Core\Application;
use Core\Contracts\Queue\ShouldQueue;
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
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event to its listeners.
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listenerClass) {
            $this->dispatchToListener($event, $listenerClass);
        }
    }

    /**
     * Dispatch an event to a specific listener.
     */
    protected function dispatchToListener(object $event, string $listenerClass): void
    {
        $listener = $this->app->make($listenerClass);

        if ($listener instanceof ShouldQueue) {
            $this->app->make('queue')->push($listener, $event);
        } else {
            $listener->handle($event);
        }
    }
}
