<?php

namespace Core\Queue;

use Core\Contracts\Queue\Dispatcher;

/**
 * Trait Dispatchable
 *
 */
trait Dispatchable
{
    public static function dispatch(...$arguments): void
    {
        app(Dispatcher::class)->dispatch(new static(...$arguments));
    }
}
