<?php

namespace Core\Support\Facades;

use Core\Application;

/**
 * Provides a static-like interface to services bound in the DI container.
 */
abstract class Facade
{
    protected static ?Application $app = null;

    /**
     * Set the application instance.
     */
    public static function setFacadeApplication(Application $app): void
    {
        static::$app = $app;
    }

    /**
     * Get the root object behind the facade.
     */
    protected static function getFacadeRoot()
    {
        return static::$app->make(static::getFacadeAccessor());
    }

    abstract protected static function getFacadeAccessor(): string;

    public static function __callStatic(string $method, array $arguments)
    {
        $instance = static::getFacadeRoot();

        return $instance->$method(...$arguments);
    }
}
