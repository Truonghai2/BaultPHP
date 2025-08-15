<?php

declare(strict_types=1);

namespace Core\Events;

/**
 * EventDispatcherInterface defines the contract for an event dispatcher.
 * It allows for registering event listeners and dispatching events to those listeners.
 */
interface EventDispatcherInterface
{
    /**
     * Register an event listener.
     *
     * @param string $event The event name or class.
     * @param string|callable $listener The listener class or callable.
     * @return void
     */
    public function listen(string $event, string|callable $listener): void;

    /**
     * Dispatch an event to its listeners.
     *
     * @param object $event
     * @return void
     */
    public function dispatch(object $event): void;
}
