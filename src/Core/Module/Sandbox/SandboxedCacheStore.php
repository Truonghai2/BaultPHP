<?php

declare(strict_types=1);

namespace Core\Module\Sandbox;

use Core\Contracts\Cache\Store;
use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache store decorator that enforces module permissions (cache:read, cache:write).
 *
 * When a module is in context (e.g. extension point handler), only operations
 * allowed by its manifest "permissions" are permitted.
 */
final class SandboxedCacheStore implements Store
{
    private const PERMISSION_READ  = 'cache:read';
    private const PERMISSION_WRITE = 'cache:write';

    public function __construct(
        private readonly Store $inner,
        private readonly ModulePermissionGate $gate,
    ) {
    }

    /** @inheritdoc */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->gate->authorize(self::PERMISSION_READ);
        return $this->inner->get($key, $default);
    }

    /** @inheritdoc */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->set($key, $value, $ttl);
    }

    /** @inheritdoc */
    public function delete(string $key): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->delete($key);
    }

    /** @inheritdoc */
    public function clear(): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->clear();
    }

    /** @inheritdoc */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->gate->authorize(self::PERMISSION_READ);
        return $this->inner->getMultiple($keys, $default);
    }

    /** @inheritdoc */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->setMultiple($values, $ttl);
    }

    /** @inheritdoc */
    public function deleteMultiple(iterable $keys): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->deleteMultiple($keys);
    }

    /** @inheritdoc */
    public function has(string $key): bool
    {
        $this->gate->authorize(self::PERMISSION_READ);
        return $this->inner->has($key);
    }

    public function forgetPattern(string $pattern): bool
    {
        $this->gate->authorize(self::PERMISSION_WRITE);
        return $this->inner->forgetPattern($pattern);
    }
}
