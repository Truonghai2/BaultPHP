<?php

namespace Core\Events;

use Core\Application;

class Dispatcher
{
    protected Application $app;
    protected array $listeners = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function listen(string $event, string|callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $eventName = get_class($event);

        foreach ($this->getListenersFor($eventName) as $listener) {
            // Sử dụng container để gọi listener, cho phép inject dependency vào hàm handle()
            $this->app->call([$this->app->make($listener), 'handle'], ['event' => $event]);
        }
    }

    protected function getListenersFor(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }
}