<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Application;

/**
 * EventDispatcher is responsible for managing event listeners and dispatching events.
 * It allows for registering listeners and triggering events, which can be handled synchronously or asynchronously.
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<string|callable>>
     * An associative array where the key is the event name or class,
     * and the value is an array of listener classes or callables.
     */
    protected array $listeners = [];

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
     *
     * @param object $event The event instance to dispatch.
     * @return void
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if (is_string($listener)) {
                // Resolve listener từ DI container
                $listenerInstance = $this->app->make($listener);
                $listenerInstance->handle($event);
            } elseif (is_callable($listener)) {
                // Hoặc thực thi một closure
                $listener($event);
            }
        }
    }
}
