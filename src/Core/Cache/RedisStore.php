<?php

namespace Core\Cache;

use Core\Contracts\Cache\Store;
use Redis;

class RedisStore implements Store
{
    protected \Redis $redis;
    protected string $prefix;

    /**
     * T\u1ea1o m\u1ed9t instance RedisStore m\u1edbi.
     *
     * @param  \Redis  $redis
     * @param  string  $prefix
     */
    public function __construct(Redis $redis, string $prefix = '')
    {
        $this->redis = $redis;
        $this->setPrefix($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== false && $value !== null ? unserialize($value) : $default;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $key
     * @param mixed $value
     * @param \DateInterval|\DateTimeInterface|int|null $ttl
     */
    public function set($key, $value, $ttl = null): bool
    {
        $seconds = $this->getSeconds($ttl);

        if ($seconds <= 0) {
            return $this->delete($key);
        }

        return $this->redis->setex(
            $this->prefix . $key,
            $seconds,
            serialize($value),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    public function clear(): bool
    {
        $iterator = null;
        do {
            $keys = $this->redis->scan($iterator, $this->prefix . '*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        } while ($iterator > 0);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return [];
        }

        $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
        $values = $this->redis->mget($prefixedKeys);
        $results = [];
        foreach ($keys as $index => $key) {
            $results[$key] = (isset($values[$index]) && $values[$index] !== false)
                ? unserialize($values[$index])
                : $default;
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable $values
     * @param \DateInterval|\DateTimeInterface|int|null $ttl
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $values = is_array($values) ? $values : iterator_to_array($values);
        if (empty($values)) {
            return true;
        }

        $this->redis->multi(\Redis::PIPELINE);
        $seconds = $this->getSeconds($ttl);
        foreach ($values as $key => $value) {
            if ($seconds > 0) {
                $this->redis->setex($this->prefix . $key, $seconds, serialize($value));
            } else {
                $this->redis->del($this->prefix . $key);
            }
        }
        $this->redis->exec();
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return true;
        }

        $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
        $this->redis->del($prefixedKeys);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    /**
     * @deprecated Use delete() instead to conform to PSR-16.
     */
    public function forget($key): bool
    {
        return $this->delete($key);
    }

    public function flush()
    {
        return $this->clear();
    }

    /**
     * \u0110\u1eb7t ti\u1ec1n t\u1ed1 cho c\u00e1c key cache.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = !empty($prefix) ? $prefix . ':' : '';
    }

    protected function getSeconds(\DateInterval|\DateTimeInterface|int|null $ttl): int
    {
        if ($ttl instanceof \DateInterval) {
            return (new \DateTime('now'))->add($ttl)->getTimestamp() - time();
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->getTimestamp() - time();
        }

        if (is_null($ttl)) {
            return 31536000; // 1 n\u0103m
        }

        return (int) $ttl;
    }
}
