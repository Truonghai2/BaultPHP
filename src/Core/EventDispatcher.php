<?php
namespace Core;

use Illuminate\Support\Facades\Event;

class EventDispatcher
{
    public static function map(string $path): void
    {
        if (file_exists($path)) {
            $events = require $path;
            foreach ($events as $event => $listeners) {
                foreach ((array) $listeners as $listener) {
                    Event::listen($event, $listener);
                }
            }
        }
    }

    public static function dispatch(object $event): void
    {
        Event::dispatch($event);
    }
}