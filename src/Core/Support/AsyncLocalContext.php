<?php

namespace Core\Support;

use Fiber;
use WeakMap;

/**
 * AsyncLocal Context using PHP Fibers.
 * 
 * Provides automatic context propagation similar to Node.js AsyncLocalStorage.
 * Zero overhead - no manual parameter passing needed!
 * 
 * Features:
 * - Works with Swoole Coroutines
 * - Works with PHP Fibers
 * - Safe fallback for traditional PHP
 * - Thread-safe
 */
class AsyncLocalContext
{
    /**
     * Fiber-local storage.
     * 
     * @var WeakMap<Fiber, array>
     */
    private static ?WeakMap $fiberStorage = null;

    /**
     * Fallback storage for non-fiber contexts.
     * 
     * @var array
     */
    private static array $fallbackStorage = [];

    /**
     * Initialize storage.
     */
    private static function init(): void
    {
        if (self::$fiberStorage === null) {
            self::$fiberStorage = new WeakMap();
        }
    }

    /**
     * Run code within a new context.
     * 
     * @param callable $callback
     * @param array $initialStore Initial context data
     * @return mixed Result from callback
     */
    public static function run(callable $callback, array $initialStore = []): mixed
    {
        self::init();

        // Check if we're in Swoole coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            return self::runInSwooleCoroutine($callback, $initialStore);
        }

        // Check if we're in a Fiber
        if (class_exists(Fiber::class)) {
            $currentFiber = Fiber::getCurrent();
            if ($currentFiber !== null) {
                return self::runInFiber($callback, $initialStore, $currentFiber);
            }
        }

        // Fallback: Use static storage
        return self::runWithFallback($callback, $initialStore);
    }

    /**
     * Run in Swoole coroutine context.
     */
    protected static function runInSwooleCoroutine(callable $callback, array $initialStore): mixed
    {
        $context = \Swoole\Coroutine::getContext();
        
        // Save current context
        $previousContext = isset($context['async_local']) 
            ? $context['async_local'] 
            : [];

        try {
            // Set new context
            $context['async_local'] = array_merge($previousContext, $initialStore);
            
            return $callback();
        } finally {
            // Restore previous context
            $context['async_local'] = $previousContext;
        }
    }

    /**
     * Run in Fiber context.
     */
    protected static function runInFiber(callable $callback, array $initialStore, Fiber $fiber): mixed
    {
        // Save current context
        $previousContext = self::$fiberStorage[$fiber] ?? [];

        try {
            // Set new context
            self::$fiberStorage[$fiber] = array_merge($previousContext, $initialStore);
            
            return $callback();
        } finally {
            // Restore previous context
            if (empty($previousContext)) {
                unset(self::$fiberStorage[$fiber]);
            } else {
                self::$fiberStorage[$fiber] = $previousContext;
            }
        }
    }

    /**
     * Run with fallback storage.
     */
    protected static function runWithFallback(callable $callback, array $initialStore): mixed
    {
        $previousContext = self::$fallbackStorage;

        try {
            self::$fallbackStorage = array_merge($previousContext, $initialStore);
            
            return $callback();
        } finally {
            self::$fallbackStorage = $previousContext;
        }
    }

    /**
     * Get value from context.
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $store = self::getStore();
        return $store[$key] ?? $default;
    }

    /**
     * Set value in context.
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        self::init();

        // Swoole coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            if (!isset($context['async_local'])) {
                $context['async_local'] = [];
            }
            $context['async_local'][$key] = $value;
            return;
        }

        // Fiber
        if (class_exists(Fiber::class)) {
            $currentFiber = Fiber::getCurrent();
            if ($currentFiber !== null) {
                if (!isset(self::$fiberStorage[$currentFiber])) {
                    self::$fiberStorage[$currentFiber] = [];
                }
                self::$fiberStorage[$currentFiber][$key] = $value;
                return;
            }
        }

        // Fallback
        self::$fallbackStorage[$key] = $value;
    }

    /**
     * Get entire store.
     * 
     * @return array
     */
    public static function getStore(): array
    {
        self::init();

        // Swoole coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return $context['async_local'] ?? [];
        }

        // Fiber
        if (class_exists(Fiber::class)) {
            $currentFiber = Fiber::getCurrent();
            if ($currentFiber !== null) {
                return self::$fiberStorage[$currentFiber] ?? [];
            }
        }

        // Fallback
        return self::$fallbackStorage;
    }

    /**
     * Check if key exists in context.
     * 
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        $store = self::getStore();
        return isset($store[$key]);
    }

    /**
     * Remove key from context.
     * 
     * @param string $key
     */
    public static function remove(string $key): void
    {
        self::init();

        // Swoole coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            if (isset($context['async_local'][$key])) {
                unset($context['async_local'][$key]);
            }
            return;
        }

        // Fiber
        if (class_exists(Fiber::class)) {
            $currentFiber = Fiber::getCurrent();
            if ($currentFiber !== null && isset(self::$fiberStorage[$currentFiber][$key])) {
                unset(self::$fiberStorage[$currentFiber][$key]);
                return;
            }
        }

        // Fallback
        unset(self::$fallbackStorage[$key]);
    }

    /**
     * Clear all context data.
     */
    public static function clear(): void
    {
        self::init();

        // Swoole coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            $context['async_local'] = [];
            return;
        }

        // Fiber
        if (class_exists(Fiber::class)) {
            $currentFiber = Fiber::getCurrent();
            if ($currentFiber !== null) {
                unset(self::$fiberStorage[$currentFiber]);
                return;
            }
        }

        // Fallback
        self::$fallbackStorage = [];
    }
}
