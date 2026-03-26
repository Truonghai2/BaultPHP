<?php

namespace Core\Support\Facades;

use Core\Support\AsyncLocalContext;

/**
 * Facade for AsyncLocalContext.
 * 
 * @method static mixed run(callable $callback, array $initialStore = [])
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void remove(string $key)
 * @method static void clear()
 * @method static array getStore()
 */
class AsyncContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AsyncLocalContext::class;
    }
}
