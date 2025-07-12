<?php

namespace Core\Support\Facades;

use Core\Logging\Logger;

class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Logger::class;
    }
}
