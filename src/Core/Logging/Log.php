<?php

namespace Core\Support\Facades;

use Core\Support\Facade;

class Log extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'log';
    }
}
