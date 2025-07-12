<?php

namespace Core\Events;

use Core\Application;
use Core\Contracts\Events\EventDispatcherInterface;

class Dispatcher implements EventDispatcherInterface
{
    protected array $listeners = [];

    public function __construct(protected Application $app) {}

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

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listenerClass) {
            $listenerInstance = $this->app->make($listenerClass);
            $listenerInstance->handle($event);
        }
    }
}