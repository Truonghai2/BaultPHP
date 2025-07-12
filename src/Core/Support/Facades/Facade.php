<?php 

namespace Core\Support\Facades;

use Core\Application;

abstract class Facade
{
    protected static Application $app;

    public static function setFacadeApplication(Application $app): void
    {
        static::$app = $app;
    }

    public static function __callStatic(string $method, array $args)
    {
        return static::resolveFacadeInstance()->$method(...$args);
    }

    protected static function resolveFacadeInstance()
    {
        return static::$app->make(static::getFacadeAccessor());
    }

    abstract protected static function getFacadeAccessor(): string;
}