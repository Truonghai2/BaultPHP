<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Application;

class EventDispatcher implements EventDispatcherInterface
{
    protected array $listeners = [];

    public function __construct(protected Application $app)
    {
    }

    public function listen(string $event, string|callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

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
