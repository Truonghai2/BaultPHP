<?php

namespace Core\Contracts\Events;

/**
 * EventDispatcherInterface is responsible for dispatching events within the application.
 * It allows for the broadcasting of events to any listeners that are registered.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event object to be dispatched.
     * @return void
     */
    public function dispatch(object $event): void;
}
