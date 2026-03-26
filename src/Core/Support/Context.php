<?php

namespace Core\Support;

/**
 * Context Helper for Request-Scoped Data.
 *
 * Provides access to request-scoped context data like correlation ID,
 * user ID, and other request metadata.
 * 
 * Now uses AsyncLocalContext for automatic propagation!
 * 
 * Features:
 * - Swoole Coroutine support
 * - PHP Fiber support
 * - Zero overhead (no manual passing)
 * - Thread-safe
 */
class Context
{
    /**
     * Use AsyncLocal for better context propagation.
     */
    protected static bool $useAsyncLocal = true;
    /**
     * Get correlation ID from context.
     */
    public static function getCorrelationId(): ?string
    {
        return self::get('correlation_id');
    }

    /**
     * Set correlation ID in context.
     */
    public static function setCorrelationId(string $correlationId): void
    {
        self::set('correlation_id', $correlationId);
    }

    /**
     * Get current user ID from context.
     */
    public static function getUserId(): string|int|null
    {
        return self::get('user_id');
    }

    /**
     * Set current user ID in context.
     */
    public static function setUserId(string|int $userId): void
    {
        self::set('user_id', $userId);
    }

    /**
     * Get current tenant ID from context (multi-tenant).
     */
    public static function getTenantId(): ?int
    {
        $v = self::get('tenant_id');
        return $v !== null ? (int) $v : null;
    }

    /**
     * Set current tenant ID in context.
     */
    public static function setTenantId(int $tenantId): void
    {
        self::set('tenant_id', $tenantId);
    }

    /**
     * Get a value from context.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$useAsyncLocal) {
            return AsyncLocalContext::get($key, $default);
        }

        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return $context[$key] ?? $default;
        }

        static $storage = [];
        return $storage[$key] ?? $default;
    }

    /**
     * Set a value in context.
     */
    public static function set(string $key, mixed $value): void
    {
        if (self::$useAsyncLocal) {
            AsyncLocalContext::set($key, $value);
            return;
        }

        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            $context[$key] = $value;
            return;
        }

        static $storage = [];
        $storage[$key] = $value;
    }

    /**
     * Check if a key exists in context.
     */
    public static function has(string $key): bool
    {
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return isset($context[$key]);
        }

        static $storage = [];
        return isset($storage[$key]);
    }

    /**
     * Remove a key from context.
     */
    public static function remove(string $key): void
    {
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            unset($context[$key]);
            return;
        }

        static $storage = [];
        unset($storage[$key]);
    }

    /**
     * Clear all context data.
     */
    public static function clear(): void
    {
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            foreach ($context as $key => $value) {
                unset($context[$key]);
            }
            return;
        }

        static $storage = [];
        $storage = [];
    }

    /**
     * Get all context data.
     */
    public static function all(): array
    {
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return iterator_to_array($context);
        }

        static $storage = [];
        return $storage;
    }

    /**
     * Generate a new correlation ID.
     */
    public static function generateCorrelationId(): string
    {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(16);
            $uuid = bin2hex($bytes);
            return substr($uuid, 0, 8) . '-' .
                   substr($uuid, 8, 4) . '-' .
                   substr($uuid, 12, 4) . '-' .
                   substr($uuid, 16, 4) . '-' .
                   substr($uuid, 20, 12);
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}