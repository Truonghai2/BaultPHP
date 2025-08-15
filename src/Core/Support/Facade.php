<?php

namespace Core\Support;

use Core\Application;
use RuntimeException;

/**
 * Cung cấp một lớp cơ sở (base class) để tạo các "facade" tĩnh,
 * cho phép truy cập các service trong container một cách thuận tiện.
 * Lấy cảm hứng từ cơ chế Facade của Laravel.
 */
abstract class Facade
{
    /**
     * The application instance.
     * @var \Core\Application
     */
    protected static $app;

    /**
     * The resolved object instances.
     * @var array<string, mixed>
     */
    protected static array $resolvedInstance = [];

    /**
     * Lấy ra tên đã đăng ký của component trong container.
     *
     * @return string
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor(): string
    {
        throw new RuntimeException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Cung cấp application instance cho Facade.
     *
     * @param \Core\Application $app
     * @return void
     */
    public static function setFacadeApplication(Application $app): void
    {
        static::$app = $app;
    }

    /**
     * Resolve facade root instance từ container.
     *
     * @param string $name
     * @return mixed
     */
    protected static function resolveFacadeInstance(string $name)
    {
        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        if (static::$app) {
            return static::$resolvedInstance[$name] = static::$app->make($name);
        }

        throw new RuntimeException('Application instance not provided to Facade.');
    }

    /**
     * Xử lý các lời gọi tĩnh động đến đối tượng.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::resolveFacadeInstance(static::getFacadeAccessor());
        return $instance->{$method}(...$args);
    }
}
