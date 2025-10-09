<?php

namespace Core\Debug;

use Psr\SimpleCache\CacheInterface;

/**
 * A decorator for a CacheInterface that traces all operations and reports them to a CacheCollector.
 */
class TraceableCacheStore implements CacheInterface
{
    public function __construct(
        private CacheInterface $store,
        private CacheCollector $collector,
        private string $storeName,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key, $default);

        if ($value === $default && !$this->store->has($key)) {
            $this->collector->addMiss($key, $this->storeName);
        } else {
            $this->collector->addHit($key, $this->storeName);
        }

        return $value;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->collector->addWrite($key, $value, $this->parseTtl($ttl), $this->storeName);
        return $this->store->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->collector->addForget($key, $this->storeName);
        return $this->store->delete($key);
    }

    public function clear(): bool
    {
        // Note: 'clear' is a bulk operation, we might just log a generic event.
        return $this->store->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        // For simplicity, we delegate without tracing individual keys.
        // A more thorough implementation could iterate and trace each key.
        return $this->store->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return $this->store->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->store->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    private function parseTtl(\DateInterval|int|null $ttl): ?int
    {
        return is_int($ttl) ? $ttl : null; // Simplified for this example
    }
}
