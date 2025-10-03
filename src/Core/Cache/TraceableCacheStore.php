<?php

namespace Core\Cache;

use DebugBar\DataCollector\CacheCollector;
use Psr\SimpleCache\CacheInterface;

/**
 * Class TraceableCacheStore
 *
 * Bọc (wraps) một CacheInterface (PSR-16) để ghi lại các thao tác.
 */
class TraceableCacheStore implements CacheInterface
{
    protected CacheInterface $store;
    protected CacheCollector $collector;

    public function __construct(CacheInterface $store, CacheCollector $collector)
    {
        $this->store = $store;
        $this->collector = $collector;
    }

    public function get($key, $default = null)
    {
        $value = $this->store->get($key, $default);
        $hit = ($value !== $default);
        $this->collector->addRead($key, $hit);
        return $value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->collector->addWrite($key, $value);
        return $this->store->set($key, $value, $ttl);
    }

    public function delete($key): bool
    {
        $this->collector->addDelete($key);
        return $this->store->delete($key);
    }

    public function clear(): bool
    {
        // Ghi lại như một lần xóa "all"
        $this->collector->addDelete('*');
        return $this->store->clear();
    }

    public function getMultiple($keys, $default = null)
    {
        $values = $this->store->getMultiple($keys, $default);
        foreach ($keys as $key) {
            $hit = array_key_exists($key, $values);
            $this->collector->addRead($key, $hit);
        }
        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->collector->addWrite($key, $value);
        }
        return $this->store->setMultiple($values, $ttl);
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->collector->addDelete($key);
        }
        return $this->store->deleteMultiple($keys);
    }

    public function has($key): bool
    {
        // 'has' có thể được xem như một lần đọc
        $hit = $this->store->has($key);
        $this->collector->addRead($key, $hit);
        return $hit;
    }
}
