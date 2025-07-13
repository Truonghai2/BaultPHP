<?php

namespace Core\Events;

use Core\Application;
use Core\Contracts\Events\EventDispatcherInterface;
use Core\Contracts\Queue\ShouldQueue;
use Core\Queue\QueueManager;

/**
 * Class Dispatcher
 *
 * Handles the registration and dispatching of events to their listeners.
 */
class Dispatcher implements EventDispatcherInterface
{
    protected array $listeners = [];
    protected QueueManager $queue;

    public function __construct(protected Application $app)
    {}

    /**
     * Register an event listener.
     */
    public function listen(string $event, string $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event to its listeners.
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);
        if (!isset($this->listeners[$eventName])) return;

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
            // If the listener should be queued, push it to the queue manager.
            $this->app->make('queue')->push($listener, $event);
        } else {
            // Otherwise, handle it synchronously.
            $listener->handle($event);
        }
    }
}