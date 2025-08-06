<?php

namespace Core\Events;

use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcherContract;
use Core\Events\EventDispatcherInterface as BaultDispatcherInterface;

/**
 * Class IlluminateViewEventAdapter
 *
 * This class acts as an adapter to make the BaultFrame event dispatcher compatible
 * with the interface required by the illuminate/view package. It allows us to use
 * the powerful Blade templating engine without being tightly coupled to the
 * full Illuminate event system.
 *
 * The methods are stubbed because the core functionality of rendering views
 * does not depend on them. If view composers are needed in the future, the
 * `dispatch` method can be implemented to translate Illuminate's string-based
 * events into BaultFrame's object-based events.
 */
class IlluminateViewEventAdapter implements IlluminateDispatcherContract
{
    public function __construct(protected BaultDispatcherInterface $baultDispatcher)
    {
    }

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

    public function push($event, $payload = []): void {}
    public function flush($event): void {}
    public function forget($event): void {}
    public function forgetPushed(): void {}
}
