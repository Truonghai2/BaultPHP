<?php

namespace Core\Events;

use Core\Events\EventDispatcherInterface as BaultDispatcherInterface;
use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcherContract;

/**
 * Class IlluminateViewEventAdapter
 *
 * This class acts as an adapter to make the BaultPHP event dispatcher compatible
 * with the interface required by the illuminate/view package. It allows us to use
 * the powerful Blade templating engine without being tightly coupled to the
 * full Illuminate event system.
 *
 * The methods are stubbed because the core functionality of rendering views
 * does not depend on them. If view composers are needed in the future, the
 * `dispatch` method can be implemented to translate Illuminate's string-based
 * events into BaultPHP's object-based events.
 */
class IlluminateViewEventAdapter implements IlluminateDispatcherContract
{
    public function __construct(protected BaultDispatcherInterface $baultDispatcher)
    {
    }

    /**
     * Register an event listener.
     *
     * @param string $event The event name or class.
     * @param string|callable $listener The listener class or callable.
     * @return void
     */
    public function listen($events, $listener = null): void
    {
        // Not used by the view factory for basic rendering.
    }

    public function hasListeners($eventName): bool
    {
        // Not used by the view factory for basic rendering.
        return false;
    }

    public function subscribe($subscriber): void
    {
        // Not used by the view factory for basic rendering.
    }

    public function until($event, $payload = []): ?array
    {
        // Not used by the view factory for basic rendering.
        return null;
    }

    public function dispatch($event, $payload = [], $halt = false)
    {
        // Not used by the view factory for basic rendering.
        return null;
    }

    public function push($event, $payload = []): void
    {
    }

    public function flush($event): void
    {
    }

    public function forget($event): void
    {
    }

    public function forgetPushed(): void
    {
    }
}
